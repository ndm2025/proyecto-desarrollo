<?php
require_once '../includes/farmacia.php';exigirPermiso('inventario.ver');exigirPermiso('inventario.corregir_lotes');require_once '../includes/lotes_servicio.php';
$db=farmaciaDb();$errores=[];$producto=(int)($_GET['producto']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    validarCsrf();
    try{
        if(!is_string($_POST['operacion']??null)||!isset($_SESSION['correcciones_lotes'][$_POST['operacion']]))throw new InvalidArgumentException('Formulario vencido. Recarga la página.');
        $id=corregirEntreLotes($db,$_POST['origen']??'',$_POST['destino']??'',$_POST['cantidad']??'',$_POST['motivo']??'',$_POST['operacion'],$_SESSION['usuario_id']);
        redirigir('correccion.php?producto='.$producto.'&guardado='.$id);
    }catch(Throwable $error){$errores[]=errorFarmacia($error);}
}
$clave=$_POST['operacion']??bin2hex(random_bytes(32));$_SESSION['correcciones_lotes'][$clave]=time();foreach($_SESSION['correcciones_lotes'] as $k=>$tiempo)if($tiempo<time()-7200)unset($_SESSION['correcciones_lotes'][$k]);
$productos=$db->query('SELECT m.MedicamentoID,m.NombreMedicamento,m.Codigo FROM Medicamentos m WHERE EXISTS(SELECT 1 FROM LotesMedicamentos l WHERE l.MedicamentoID=m.MedicamentoID) ORDER BY m.NombreMedicamento')->fetchAll(PDO::FETCH_ASSOC);
$stmt=$db->prepare('SELECT * FROM LotesMedicamentos WHERE MedicamentoID=? ORDER BY FechaVencimiento,LoteID');$stmt->execute([$producto]);$lotes=$stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt=$db->prepare('SELECT c.*,o.NumeroLote AS Origen,d.NumeroLote AS Destino,u.NombreCompleto FROM CorreccionesLotes c JOIN LotesMedicamentos o ON o.LoteID=c.LoteOrigenID JOIN LotesMedicamentos d ON d.LoteID=c.LoteDestinoID JOIN Usuarios u ON u.UsuarioID=c.UsuarioID WHERE o.MedicamentoID=? ORDER BY c.CorreccionID DESC LIMIT 30');$stmt->execute([$producto]);$historial=$stmt->fetchAll(PDO::FETCH_ASSOC);
$titulo='Corregir cantidades entre lotes';require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-arrow-left-right"></i> Corregir cantidades entre lotes</h1>
<?php erroresVista($errores);if(isset($_GET['guardado'])): ?><div class="alert alert-success">Corrección registrada. La cantidad total del producto se conserva.</div><?php endif; ?>
<div class="alert alert-info">Usa esta operación si el conteo físico demuestra que unas unidades se registraron en el lote equivocado. Verifica los números de los empaques. La salida y la entrada se guardan juntas; los vencimientos y estados de los lotes no cambian.</div>
<div class="card"><div class="card-header">Seleccionar producto</div><div class="card-body"><form method="get" class="row g-3"><div class="col-md-9"><label class="form-label" for="producto">Producto</label><select class="form-select" id="producto" data-buscable="Buscar producto para corregir lotes" name="producto" required><option value="">Selecciona...</option><?php foreach($productos as $m): ?><option value="<?=$m['MedicamentoID']?>" <?=$producto==(int)$m['MedicamentoID']?'selected':''?>><?=h($m['Codigo'].' · '.$m['NombreMedicamento'])?></option><?php endforeach; ?></select></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary">Consultar lotes</button></div></form></div></div>
<?php if($producto): ?>
<?php if(count($lotes)<2): ?><div class="alert alert-warning">Este producto necesita al menos dos lotes para realizar una corrección.</div><?php else: ?>
<div class="card"><div class="card-header">Corrección del conteo</div><div class="card-body"><form method="post"><?=csrfCampo()?><input type="hidden" name="operacion" value="<?=h($clave)?>"><div class="row g-3">
<?php foreach(['origen'=>'Lote al que se le restarán unidades','destino'=>'Lote al que se le sumarán unidades'] as $campo=>$label): ?><div class="col-md-6"><label class="form-label" for="<?=$campo?>"><?=$label?> *</label><select class="form-select" id="<?=$campo?>" name="<?=$campo?>" required><option value="">Selecciona...</option><?php foreach($lotes as $l): ?><option value="<?=$l['LoteID']?>" <?=($_POST[$campo]??'')==$l['LoteID']?'selected':''?>><?=h($l['NumeroLote'].' · '.$l['CantidadActual'].' unidades · vence '.$l['FechaVencimiento'].' · '.($l['Activo']?'Activo':'Inactivo'))?></option><?php endforeach; ?></select></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label" for="cantidad">Cantidad de unidades base *</label><input class="form-control" id="cantidad" name="cantidad" type="number" min="1" max="1000000" step="1" value="<?=h($_POST['cantidad']??'')?>" required></div><div class="col-12"><label class="form-label" for="motivo">Motivo *</label><textarea class="form-control" id="motivo" name="motivo" rows="3" maxlength="500" required><?=h($_POST['motivo']??'')?></textarea></div>
</div><button class="btn btn-primary mt-3">Confirmar corrección</button> <a class="btn btn-secondary mt-3" href="index.php">Volver a lotes</a></form></div></div>
<?php endif; ?>
<div class="card"><div class="card-header">Últimas correcciones del producto</div><div class="card-body table-responsive"><table class="table table-sm"><thead><tr><th>Fecha</th><th>Origen</th><th>Destino</th><th>Unidades</th><th>Usuario</th><th>Motivo</th></tr></thead><tbody><?php foreach($historial as $c): ?><tr><td><?=h($c['Fecha'])?></td><td><?=h($c['Origen'])?></td><td><?=h($c['Destino'])?></td><td><?=h($c['Cantidad'])?></td><td><?=h($c['NombreCompleto'])?></td><td><?=h($c['Motivo'])?></td></tr><?php endforeach; ?><?php if(!$historial): ?><tr><td colspan="6" class="text-muted">No hay correcciones registradas.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endif;require '../includes/footer.php'; ?>
