<?php
require_once '../includes/farmacia.php';exigirPermiso('ventas.ver');require_once '../includes/ventas_servicio.php';
$tipo=$_GET['tipo']??'';if(!in_array($tipo,['Devolucion','Anulacion'],true)){http_response_code(422);exit('Tipo de operación inválido.');}
exigirPermiso($tipo==='Anulacion'?'ventas.anular':'ventas.devolver');$titulo=$tipo==='Anulacion'?'Anular venta':'Devolver productos';$db=farmaciaDb();$errores=[];
try{$id=entero($_GET['id']??0,1,2147483647);$v=datosVenta($db,$id);}catch(Throwable $e){http_response_code(404);require '../includes/header.php';erroresVista([errorFarmacia($e)]);require '../includes/footer.php';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
    validarCsrf();try{if(($_POST['confirmar']??'')!=='1')throw new InvalidArgumentException('Confirma las cantidades y el reintegro de efectivo.');$clave=claveVenta($_POST['operacion']??null);if(($_SESSION['retornos_venta'][$clave]??null)!==$id.':'.$tipo)throw new InvalidArgumentException('El formulario venció. Recarga la página.');$retorno=reintegrarVenta($db,$id,$tipo,$_POST,$_SESSION['usuario_id']);redirigir('ver.php?id='.$id.'&reintegro='.$retorno);}catch(Throwable $e){$errores[]=errorFarmacia($e);http_response_code(422);$v=datosVenta($db,$id);}
}
$clave=isset($clave)&&($_SESSION['retornos_venta'][$clave]??null)===$id.':'.$tipo?$clave:bin2hex(random_bytes(32));$_SESSION['retornos_venta'][$clave]=$id.':'.$tipo;if(count($_SESSION['retornos_venta'])>50)array_shift($_SESSION['retornos_venta']);
$cantidadesVista=is_array($_POST['cantidades']??null)?$_POST['cantidades']:[];
require '../includes/header.php';
?>
<link rel="stylesheet" href="/clinica/css/inventario.css"><link rel="stylesheet" href="/clinica/css/ventas.css">
<div class="inventario-panel ventas-panel"><h1 class="page-title"><?=h($titulo)?> #<?=h($id)?></h1><?php erroresVista($errores); ?>
<div class="alert alert-info">El reintegro se realiza en efectivo sobre el precio original, descontando el descuento de forma proporcional. Las unidades regresarán a sus lotes originales. Los lotes vencidos o inactivos seguirán sin disponibilidad para vender.</div>
<?php if(!in_array($v['Estado'],['Vigente','Parcial'])): ?><div class="alert alert-warning">Esta venta ya no tiene productos pendientes de devolver.</div><a class="btn btn-secondary" href="ver.php?id=<?=h($id)?>">Volver</a><?php else: ?>
<form method="post" id="form-retorno"><?=csrfCampo()?><input name="operacion" type="hidden" value="<?=h($clave)?>">
<div class="card mb-3"><div class="card-header"><?=h($tipo==='Anulacion'?'Se devolverá todo lo pendiente de la venta':'Selecciona las cantidades a devolver')?></div><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Producto</th><th>Presentación</th><th>Vendidas</th><th>Devueltas</th><th>Pendientes</th><th>Devolver</th><th>Reintegro</th></tr></thead><tbody>
<?php foreach($v['lineas'] as $l): $pend=(int)$l['Cantidad']-(int)$l['CantidadDevuelta']; ?><tr data-total="<?=h(centavos($l['Total']))?>" data-cantidad="<?=h($l['Cantidad'])?>" data-devueltas="<?=h($l['CantidadDevuelta'])?>" data-reintegrado="<?=h(centavos($l['ImporteDevuelto']))?>"><td><?=h($l['Producto'])?></td><td><?=h($l['Presentacion'])?> (<?=h($l['UnidadesPorPresentacion'])?> <?=h($l['UnidadBase'])?>)</td><td><?=h($l['Cantidad'])?></td><td><?=h($l['CantidadDevuelta'])?></td><td><?=h($pend)?></td><td><input class="form-control retorno-cantidad" style="min-width:85px" aria-label="Cantidad a devolver de <?=h($l['Producto'])?>" type="number" min="0" max="<?=h($pend)?>" step="1" required name="cantidades[<?=h($l['DetalleID'])?>]" value="<?=h($tipo==='Anulacion'?$pend:(is_scalar($cantidadesVista[$l['DetalleID']]??0)?($cantidadesVista[$l['DetalleID']]??0):0))?>" <?=$tipo==='Anulacion'?'readonly':''?>></td><td class="retorno-importe text-nowrap">Q 0.00</td></tr><?php endforeach; ?></tbody></table></div>
<p class="fs-5">Efectivo a reintegrar: <strong id="retorno-total">Q 0.00</strong></p>
<label class="form-label" for="motivo">Motivo obligatorio *</label><textarea class="form-control mb-3" id="motivo" name="motivo" maxlength="500" rows="3" required><?=h(is_scalar($_POST['motivo']??'')?($_POST['motivo']??''):'')?></textarea>
<label class="form-check mb-3"><input type="checkbox" name="confirmar" value="1" class="form-check-input" required> Confirmo las cantidades y el reintegro de efectivo al cliente.</label>
<button class="btn <?=$tipo==='Anulacion'?'btn-danger':'btn-primary'?>" type="submit" id="confirmar-retorno">Confirmar <?=h($tipo==='Anulacion'?'anulación':'devolución')?></button> <a class="btn btn-secondary" href="ver.php?id=<?=h($id)?>">Cancelar</a>
<p class="small text-muted mt-3 mb-0">Las devoluciones se capturan en las presentaciones vendidas. Una caja se devuelve como una caja completa.</p></div></div></form>
<?php endif; ?></div><script src="/clinica/js/retornos.js?v=4"></script>
<?php require '../includes/footer.php'; ?>
