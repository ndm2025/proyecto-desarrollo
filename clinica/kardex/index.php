<?php
require_once '../includes/farmacia.php';exigirPermiso('kardex.ver');require_once '../includes/kardex_servicio.php';
$db=farmaciaDb();$errores=[];$f=[];$kardex=null;$resultado=null;$lotes=[];
$productos=$db->query('SELECT MedicamentoID,Codigo,NombreMedicamento FROM Medicamentos ORDER BY NombreMedicamento')->fetchAll(PDO::FETCH_ASSOC);
try{
    $f=filtrosKardex($_GET);
    if($f['producto']){
        iniciarConsultaInventario($db);$kardex=prepararKardex($db,$f);
        $resultado=paginarInventario($db,'SELECT * FROM ('.$kardex['sql'].') movimientos',$kardex['params'],$f['pagina'],'FechaMovimiento,MovimientoID',25);
        $stmt=$db->prepare('SELECT LoteID,NumeroLote FROM LotesMedicamentos WHERE MedicamentoID=? ORDER BY NumeroLote');$stmt->execute([$f['producto']]);$lotes=$stmt->fetchAll(PDO::FETCH_ASSOC);$db->commit();
    }
}catch(Throwable $error){if($db->inTransaction())$db->rollBack();http_response_code($error instanceof InvalidArgumentException?422:500);$errores[]=$error instanceof InvalidArgumentException?$error->getMessage():'No se pudo consultar el kardex.';if(!$error instanceof InvalidArgumentException)error_log((string)$error);$kardex=null;}
$titulo='Kardex';$seccionInventario='kardex';require '../includes/header.php';
?>
<div class="inventario-panel"><h1 class="page-title"><i class="bi bi-journal-text" aria-hidden="true"></i> Kardex de productos</h1>
<?php require '../includes/inventario_nav.php';erroresVista($errores); ?>
<div class="card"><div class="card-header">Consultar movimientos</div><div class="card-body">
<form method="get" class="row g-3"><div class="col-md-6"><label class="form-label" for="producto">Producto *</label><select class="form-select" id="producto" data-buscable="Buscar producto del kardex" name="producto" required><option value="">Selecciona...</option><?php foreach($productos as $m): ?><option value="<?=$m['MedicamentoID']?>" <?=($f['producto']??0)==$m['MedicamentoID']?'selected':''?>><?=h($m['Codigo'].' · '.$m['NombreMedicamento'])?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="lote">Lote</label><select class="form-select" id="lote" name="lote"><option value="0">Todos los lotes del producto</option><?php foreach($lotes as $l): ?><option value="<?=$l['LoteID']?>" <?=($f['lote']??0)==$l['LoteID']?'selected':''?>><?=h($l['NumeroLote'])?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="desde">Desde</label><input class="form-control" id="desde" type="date" name="desde" value="<?=h($f['desde']??(is_string($_GET['desde']??null)?$_GET['desde']:date('Y-m-01')))?>"></div><div class="col-md-4"><label class="form-label" for="hasta">Hasta</label><input class="form-control" id="hasta" type="date" name="hasta" value="<?=h($f['hasta']??(is_string($_GET['hasta']??null)?$_GET['hasta']:date('Y-m-d')))?>"></div><div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary">Consultar</button><a class="btn btn-secondary" href="index.php">Limpiar</a></div>
</form><p class="text-muted small mb-0 mt-2">El orden es fecha de registro y número de movimiento. Las fechas incluyen el día completo. Deja Desde vacío para consultar desde el primer movimiento.</p>
</div></div>
<?php if($kardex): $r=$kardex['resumen']; ?>
<?php if((int)$r['Diferencia']!==0): ?><div class="alert alert-warning"><strong>Diferencia pendiente de conciliar:</strong> las existencias físicas actuales (<?=h($r['FisicoActual'])?>) y el total de movimientos registrados (<?=h($r['Historico'])?>) difieren en <?=h($r['Diferencia'])?> unidades. El kardex conserva lo registrado; no se creó ningún ajuste automático.</div><?php endif; ?>
<p class="text-muted">Existencias físicas actuales: <strong><?=h($r['FisicoActual'])?></strong> <?=h($kardex['producto']['UnidadBase'])?>. Los saldos del período corresponden a las fechas seleccionadas.</p>
<div class="row g-2 mb-3"><?php foreach(['Inicial'=>'Saldo inicial del período','Entradas'=>'Entradas del período','Salidas'=>'Salidas del período','Final'=>'Saldo final del período'] as $campo=>$label): ?><div class="col-6 col-lg-3"><div class="card h-100 mb-0"><div class="card-body"><div class="indicador-numero"><?=h($r[$campo])?></div><div class="indicador-label"><?=h($label)?></div></div></div></div><?php endforeach; ?></div>
<div class="card"><div class="card-header"><span><?=h($kardex['producto']['NombreMedicamento'].' · '.$kardex['producto']['UnidadBase'])?></span><?php if(tienePermiso('kardex.exportar')): ?><a class="btn btn-light btn-sm" href="exportar.php?<?=h(http_build_query($f))?>">Exportar CSV</a><?php endif; ?></div><div class="card-body">
<div class="table-responsive"><table class="table table-hover align-middle tabla-inventario"><thead><tr><th>Registro</th><th>Fecha</th><th>Lote</th><th>Origen</th><th>Referencia</th><th>Entrada</th><th>Salida</th><th>Saldo</th><th>Usuario</th><th>Motivo</th></tr></thead><tbody>
<?php foreach($resultado['filas'] as $mov): [$origen,$referencia,$url]=origenMovimiento($mov); ?><tr><td>#<?=$mov['MovimientoID']?></td><td><?=h($mov['FechaMovimiento'])?></td><td><?=h($mov['NumeroLote'])?></td><td><?=h($origen)?></td><td><?php if($url): ?><a href="<?=h($url)?>"><?=h($referencia)?></a><?php else: ?><?=h($referencia)?><?php endif; ?></td><td class="text-success"><?=h($mov['Entrada'])?></td><td class="text-danger"><?=h($mov['Salida'])?></td><td><strong><?=h($mov['Saldo'])?></strong></td><td><?=h($mov['NombreCompleto']??'Sistema')?></td><td class="texto-motivo"><?=h($mov['Observaciones'])?></td></tr><?php endforeach; ?>
<?php if(!$resultado['filas']): ?><tr><td colspan="10" class="text-center text-muted">No hay movimientos en este período. El saldo inicial se conserva como saldo final.</td></tr><?php endif; ?></tbody></table></div>
<?php paginacionInventario($resultado,$f); ?><p class="text-muted small mb-0 mt-3">Los cambios de estado de un lote afectan su disponibilidad, pero no sus unidades físicas; por eso no son entradas ni salidas del kardex.</p>
</div></div><?php elseif(!$errores): ?><div class="alert alert-info">Selecciona un producto para consultar su historial de entradas, salidas y ajustes.</div><?php endif; ?>
</div><script src="/clinica/js/kardex.js"></script>
<?php require '../includes/footer.php'; ?>
