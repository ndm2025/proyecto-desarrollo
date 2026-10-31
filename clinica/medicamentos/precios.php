<?php
require_once '../includes/farmacia.php';
exigirPermiso('medicamentos.precios');$db=farmaciaDb();$id=(int)($_GET['id']??0);$errores=[];
if($id){
    $stmt=$db->prepare('SELECT MedicamentoID,Codigo,NombreMedicamento FROM Medicamentos WHERE MedicamentoID=?');$stmt->execute([$id]);$m=$stmt->fetch(PDO::FETCH_ASSOC);if(!$m){http_response_code(404);exit('Producto no encontrado.');}
    $cargar=function()use($db,$id){$stmt=$db->prepare('SELECT PresentacionID,Nombre,UnidadesBase,PrecioVenta FROM PresentacionesProducto WHERE MedicamentoID=? AND Activo=1 ORDER BY PresentacionID FOR UPDATE');$stmt->execute([$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC);};
    if($_SERVER['REQUEST_METHOD']==='POST'){
        validarCsrf();
        try{
            $db->beginTransaction();$db->prepare('SELECT MedicamentoID FROM Medicamentos WHERE MedicamentoID=? FOR UPDATE')->execute([$id]);$filas=$cargar();
            if(!hash_equals(hash('sha256',json_encode($filas)),(string)($_POST['version']??'')))throw new InvalidArgumentException('Los precios o presentaciones cambiaron. Recarga la página antes de guardar.');
            foreach($filas as $fila){
                $nuevo=centavos($_POST['precios'][$fila['PresentacionID']]??'');if($nuevo<1)throw new InvalidArgumentException('Los precios de venta deben ser mayores a cero.');
                $importe=decimalImporte($nuevo);
                if($importe!==$fila['PrecioVenta']){
                    $db->prepare('UPDATE PresentacionesProducto SET PrecioVenta=? WHERE PresentacionID=?')->execute([$importe,$fila['PresentacionID']]);
                    $db->prepare('INSERT INTO HistorialPrecios(PresentacionID,PrecioAnterior,PrecioNuevo,UsuarioID) VALUES(?,?,?,?)')->execute([$fila['PresentacionID'],$fila['PrecioVenta'],$importe,$_SESSION['usuario_id']]);
                }
            }
            auditar($db,'producto.precios_actualizados',$id);$db->commit();redirigir('precios.php?id='.$id.'&guardado=1');
        }catch(Throwable $error){if($db->inTransaction())$db->rollBack();$errores[]=errorFarmacia($error);}
    }
    $filas=$cargar();$version=hash('sha256',json_encode($filas));
    $stmt=$db->prepare('SELECT h.*,p.Nombre,u.NombreCompleto FROM HistorialPrecios h JOIN PresentacionesProducto p ON p.PresentacionID=h.PresentacionID JOIN Usuarios u ON u.UsuarioID=h.UsuarioID WHERE p.MedicamentoID=? ORDER BY h.CambioID DESC LIMIT 50');$stmt->execute([$id]);$historial=$stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
    $q=trim((string)($_GET['q']??''));$pagina=max(1,(int)($_GET['pagina']??1));$params=['%'.$q.'%','%'.$q.'%'];$where=' WHERE Codigo LIKE ? OR NombreMedicamento LIKE ?';
    $stmt=$db->prepare('SELECT COUNT(*) FROM Medicamentos'.$where);$stmt->execute($params);$total=(int)$stmt->fetchColumn();$paginas=max(1,(int)ceil($total/20));$pagina=min($pagina,$paginas);
    $stmt=$db->prepare('SELECT MedicamentoID,Codigo,NombreMedicamento,Activo FROM Medicamentos'.$where.' ORDER BY NombreMedicamento LIMIT 20 OFFSET '.(($pagina-1)*20));$stmt->execute($params);$productos=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
$titulo='Precios de venta';require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-tag"></i> Precios de venta</h1>
<?php erroresVista($errores);if(isset($_GET['guardado'])): ?><div class="alert alert-success">Precios guardados correctamente.</div><?php endif; ?>
<?php if($id): ?>
<div class="card"><div class="card-header"><?=h($m['Codigo'].' · '.$m['NombreMedicamento'])?></div><div class="card-body"><p class="text-muted">Esta pantalla modifica únicamente precios de venta. Las equivalencias y los datos del producto se conservan.</p>
<form method="post"><?=csrfCampo()?><input type="hidden" name="version" value="<?=h($version)?>"><div class="table-responsive"><table class="table"><thead><tr><th>Presentación</th><th>Unidades base</th><th>Precio de venta (Q)</th></tr></thead><tbody><?php foreach($filas as $fila): ?><tr><td><?=h($fila['Nombre'])?></td><td><?=h($fila['UnidadesBase'])?></td><td><input class="form-control" type="number" min="0.01" max="99999999.99" step="0.01" name="precios[<?=$fila['PresentacionID']?>]" aria-label="Precio de <?=h($fila['Nombre'])?>" value="<?=h($_POST['precios'][$fila['PresentacionID']]??$fila['PrecioVenta'])?>" required></td></tr><?php endforeach; ?></tbody></table></div><button class="btn btn-primary">Guardar precios</button> <a class="btn btn-secondary" href="precios.php">Volver</a></form></div></div>
<div class="card"><div class="card-header">Historial de precios</div><div class="card-body table-responsive"><table class="table table-sm"><thead><tr><th>Fecha</th><th>Presentación</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th></tr></thead><tbody><?php foreach($historial as $h): ?><tr><td><?=h($h['Fecha'])?></td><td><?=h($h['Nombre'])?></td><td><?=dinero($h['PrecioAnterior'])?></td><td><?=dinero($h['PrecioNuevo'])?></td><td><?=h($h['NombreCompleto'])?></td></tr><?php endforeach; ?><?php if(!$historial): ?><tr><td colspan="5" class="text-muted">Sin cambios registrados.</td></tr><?php endif; ?></tbody></table></div></div>
<?php else: ?>
<div class="card"><div class="card-header">Productos</div><div class="card-body"><form class="input-group mb-3" method="get"><input class="form-control" name="q" value="<?=h($q)?>" placeholder="Código o nombre" aria-label="Buscar producto"><button class="btn btn-primary">Buscar</button></form><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Código</th><th>Producto</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach($productos as $m): ?><tr><td><?=h($m['Codigo'])?></td><td><?=h($m['NombreMedicamento'])?></td><td><?=$m['Activo']?'Activo':'Inactivo'?></td><td><a class="btn btn-primary btn-sm" href="precios.php?id=<?=$m['MedicamentoID']?>">Editar precios</a></td></tr><?php endforeach; ?><?php if(!$productos): ?><tr><td colspan="4" class="text-muted">No se encontraron productos.</td></tr><?php endif; ?></tbody></table></div><div class="d-flex justify-content-between flex-wrap gap-2"><span><?=$total?> productos · Página <?=$pagina?> de <?=$paginas?></span><div><?php foreach(['Anterior'=>$pagina-1,'Siguiente'=>$pagina+1] as $label=>$p): if($p>=1&&$p<=$paginas): ?><a class="btn btn-outline-primary btn-sm" href="?<?=h(http_build_query(['q'=>$q,'pagina'=>$p]))?>"><?=$label?></a><?php endif; endforeach; ?></div></div></div></div>
<?php endif;require '../includes/footer.php'; ?>
