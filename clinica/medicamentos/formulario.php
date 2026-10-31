<?php
require_once '../includes/farmacia.php';
$id=isset($editar)?(int)($_GET['id']??0):0;
if (isset($editar) && $id<1) { http_response_code(404); exit('Producto no encontrado.'); }
exigirPermiso($id?'medicamentos.editar':'medicamentos.crear');
$db=farmaciaDb();
$puedePrecios=!$id && tienePermiso('medicamentos.precios');
$errores=[];
$datos=['Codigo'=>'','NombreMedicamento'=>'','PrincipioActivo'=>'','Presentacion'=>'','UnidadBase'=>'unidad','StockMinimo'=>0,'Activo'=>1];
$presentaciones=[];
if ($id) {
    $stmt=$db->prepare('SELECT * FROM Medicamentos WHERE MedicamentoID=?');$stmt->execute([$id]);
    $datos=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$datos) { http_response_code(404); exit('Producto no encontrado.'); }
    $stmt=$db->prepare('SELECT * FROM PresentacionesProducto WHERE MedicamentoID=?');$stmt->execute([$id]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $presentaciones[$row['Nombre']]=$row;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    try {
        foreach(['Codigo'=>40,'NombreMedicamento'=>150,'PrincipioActivo'=>150,'Presentacion'=>100,'UnidadBase'=>30] as $campo=>$limite) {
            $datos[$campo]=trim((string)($_POST[$campo]??''));
            if ($datos[$campo]==='' || mb_strlen($datos[$campo])>$limite) throw new InvalidArgumentException('Completa los campos obligatorios y respeta sus longitudes máximas.');
        }
        $datos['StockMinimo']=entero($_POST['StockMinimo']??0,0);
        $datos['Activo']=isset($_POST['Activo'])?1:0;
        $nuevas=[];
        foreach(['Unidad','Blíster','Caja'] as $nombre) {
            $input=$_POST['presentaciones'][$nombre]??[];
            $activo=isset($input['activo']);
            $unidades=$nombre==='Unidad'?1:($activo?entero($input['unidades']??''):($presentaciones[$nombre]['UnidadesBase']??1));
            $precio=$puedePrecios&&$activo?decimalImporte(centavos($input['precio']??'')):($presentaciones[$nombre]['PrecioVenta']??'0.00');
            $nuevas[$nombre]=['Nombre'=>$nombre,'UnidadesBase'=>$unidades,'PrecioVenta'=>$precio,'Activo'=>(int)$activo];
        }
        $presentaciones=$nuevas;
        $activas=array_filter($nuevas,fn($p)=>$p['Activo']);
        if (!$activas) throw new InvalidArgumentException('Activa al menos una presentación.');
        if ($nuevas['Caja']['Activo'] && $nuevas['Blíster']['Activo'] && ($nuevas['Caja']['UnidadesBase']<$nuevas['Blíster']['UnidadesBase'] || $nuevas['Caja']['UnidadesBase']%$nuevas['Blíster']['UnidadesBase']!==0)) throw new InvalidArgumentException('Las unidades por caja deben ser un múltiplo de las unidades por blíster.');
        $db->beginTransaction();
        if ($id) {
            $stmt=$db->prepare('SELECT UnidadBase FROM Medicamentos WHERE MedicamentoID=? FOR UPDATE');$stmt->execute([$id]);
            $unidadAnterior=$stmt->fetchColumn();
            if ($unidadAnterior!==$datos['UnidadBase']) {
                $stmt=$db->prepare('SELECT COUNT(*) FROM LotesMedicamentos WHERE MedicamentoID=?');$stmt->execute([$id]);
                if ($stmt->fetchColumn()) throw new InvalidArgumentException('No se puede cambiar la unidad base de un producto con lotes registrados.');
            }
            $db->prepare('UPDATE Medicamentos SET Codigo=?,NombreMedicamento=?,PrincipioActivo=?,Presentacion=?,UnidadBase=?,StockMinimo=?,Activo=? WHERE MedicamentoID=?')->execute([$datos['Codigo'],$datos['NombreMedicamento'],$datos['PrincipioActivo'],$datos['Presentacion'],$datos['UnidadBase'],$datos['StockMinimo'],$datos['Activo'],$id]);
        } else {
            $db->prepare('INSERT INTO Medicamentos(Codigo,NombreMedicamento,PrincipioActivo,Presentacion,UnidadBase,StockMinimo,Activo) VALUES(?,?,?,?,?,?,?)')->execute([$datos['Codigo'],$datos['NombreMedicamento'],$datos['PrincipioActivo'],$datos['Presentacion'],$datos['UnidadBase'],$datos['StockMinimo'],$datos['Activo']]);
            $id=(int)$db->lastInsertId();
        }
        $esNuevo=isset($editar)?false:true;
        $sqlPresentacion='INSERT INTO PresentacionesProducto(MedicamentoID,Nombre,UnidadesBase,PrecioVenta,Activo) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE UnidadesBase=VALUES(UnidadesBase),Activo=VALUES(Activo)';
        $stmt=$db->prepare($sqlPresentacion);
        foreach($nuevas as $nombre=>$p) {
            $stmt->execute([$id,$nombre,$p['UnidadesBase'],$p['PrecioVenta'],$p['Activo']]);
            if($esNuevo && (float)$p['PrecioVenta']>0) {
                $buscar=$db->prepare('SELECT PresentacionID FROM PresentacionesProducto WHERE MedicamentoID=? AND Nombre=?');$buscar->execute([$id,$nombre]);
                $db->prepare('INSERT INTO HistorialPrecios(PresentacionID,PrecioAnterior,PrecioNuevo,UsuarioID) VALUES(?,0,?,?)')->execute([$buscar->fetchColumn(),$p['PrecioVenta'],$_SESSION['usuario_id']]);
            }
        }
        auditar($db,'producto.guardar',$id);$db->commit();
        redirigir('ver.php?id='.$id.'&guardado=1');
    } catch(Throwable $error) { if($db->inTransaction())$db->rollBack();if(!isset($editar))$id=0;$errores[]=errorFarmacia($error); }
}
$titulo=$id?'Editar medicamento':'Nuevo medicamento';
require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-capsule"></i> <?=h($titulo)?></h1>
<?php erroresVista($errores); ?>
<form method="post"><?=csrfCampo()?>
<div class="card"><div class="card-header">Información del producto</div><div class="card-body"><div class="row">
<?php foreach(['Codigo'=>'Código','NombreMedicamento'=>'Nombre comercial','PrincipioActivo'=>'Principio activo','Presentacion'=>'Descripción / concentración','UnidadBase'=>'Unidad base (tableta, bolsa, frasco...)'] as $campo=>$label): ?>
<div class="col-md-6 mb-3"><label class="form-label" for="<?=h($campo)?>"><?=h($label)?> *</label><input class="form-control" id="<?=h($campo)?>" name="<?=h($campo)?>" value="<?=h($datos[$campo])?>" maxlength="<?=$campo==='Codigo'?40:($campo==='UnidadBase'?30:($campo==='Presentacion'?100:150))?>" required></div>
<?php endforeach; ?>
<div class="col-md-6 mb-3"><label class="form-label" for="StockMinimo">Stock mínimo en unidades base</label><input type="number" class="form-control" name="StockMinimo" id="StockMinimo" min="0" max="1000000" step="1" value="<?=h($datos['StockMinimo'])?>" required></div>
<div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="Activo" <?=$datos['Activo']?'checked':''?>> Producto activo</label></div>
</div></div></div>
<div class="card"><div class="card-header">Presentaciones de venta</div><div class="card-body">
<p class="text-muted">Activa las presentaciones necesarias. Las existencias siempre se cuentan en unidades base. Para un suero puedes activar Unidad y Caja.</p>
<?php if(!$puedePrecios): ?><div class="alert alert-info">Los precios se conservan. Se administran de forma independiente desde Precios de venta.</div><?php endif; ?>
<?php foreach(['Unidad','Blíster','Caja'] as $nombre): $p=$presentaciones[$nombre]??['Activo'=>$nombre==='Unidad','UnidadesBase'=>1,'PrecioVenta'=>'0.00']; ?>
<div class="border rounded p-3 mb-3 presentacion-fila">
<label class="form-check"><input type="checkbox" class="form-check-input activar-presentacion" name="presentaciones[<?=h($nombre)?>][activo]" <?=$p['Activo']?'checked':''?>> <strong><?=h($nombre)?></strong></label>
<div class="row mt-2 campos-presentacion" <?=$p['Activo']?'':'hidden'?>>
<div class="col-md-6 mb-2"><label class="form-label">Unidades base por <?=h(mb_strtolower($nombre))?></label><input class="form-control" type="number" min="1" max="1000000" step="1" name="presentaciones[<?=h($nombre)?>][unidades]" value="<?=h($p['UnidadesBase'])?>" <?=$nombre==='Unidad'?'readonly':''?>></div>
<?php if($puedePrecios): ?><div class="col-md-6 mb-2"><label class="form-label">Precio de venta de la presentación (Q)</label><input class="form-control" type="number" min="0" max="99999999.99" step="0.01" name="presentaciones[<?=h($nombre)?>][precio]" value="<?=h($p['PrecioVenta'])?>"></div><?php endif; ?>
</div></div>
<?php endforeach; ?>
</div></div>
<button class="btn btn-primary"><i class="bi bi-save"></i> Guardar producto</button> <a class="btn btn-secondary" href="index.php">Cancelar</a>
</form>
<script src="/clinica/js/presentaciones.js"></script>
<?php if(isset($editar) && tienePermiso('medicamentos.precios')): ?><a class="btn btn-outline-primary mt-3" href="precios.php?id=<?=$id?>">Editar precios de venta</a><?php endif; ?>
<?php require '../includes/footer.php'; ?>
