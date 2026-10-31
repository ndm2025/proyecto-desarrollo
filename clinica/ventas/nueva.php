<?php
$titulo='Venta de farmacia';require_once '../includes/farmacia.php';exigirPermiso('ventas.ver');exigirPermiso('ventas.crear');require_once '../includes/ventas_servicio.php';
$db=farmaciaDb();$errores=[];$datos=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    validarCsrf();$datos=$_POST;
    try{
        $clave=claveVenta($datos['operacion']??null);
        if(!isset($_SESSION['operaciones_venta'][$clave]))throw new InvalidArgumentException('El formulario venció. Recarga la página; tu borrador se conserva.');
        $id=registrarVenta($db,$datos,$_SESSION['usuario_id']);redirigir('ver.php?id='.$id.'&guardada=1');
    }catch(Throwable $e){$errores[]=errorFarmacia($e);http_response_code(422);}
}
$clave=isset($clave)&&isset($_SESSION['operaciones_venta'][$clave])?$clave:bin2hex(random_bytes(32));
$_SESSION['operaciones_venta'][$clave]=time();if(count($_SESSION['operaciones_venta'])>50)array_shift($_SESSION['operaciones_venta']);
$claves=array_keys($_SESSION['operaciones_venta']);$s=$db->prepare('SELECT ClaveOperacion,VentaID FROM Ventas WHERE UsuarioID=? AND ClaveOperacion IN ('.implode(',',array_fill(0,count($claves),'?')).')');$s->execute([$_SESSION['usuario_id'],...$claves]);$confirmadas=$s->fetchAll(PDO::FETCH_KEY_PAIR);
$catalogo=catalogoVentas($db);$descuento=tienePermiso('ventas.descuento');$seccionVentas='nueva';require '../includes/header.php';
?>
<link rel="stylesheet" href="/clinica/css/ventas.css?v=4">
<div class="ventas-panel punto-venta">
<div class="venta-titulo"><div><h1 class="page-title"><i class="bi bi-cart-check" aria-hidden="true"></i> Venta de farmacia</h1><p>Busca productos, revisa el detalle y registra el cobro.</p></div><span class="borrador-estado" id="borrador-estado" role="status">Sin productos</span></div>
<?php require '../includes/ventas_nav.php';erroresVista($errores); ?>
<div id="venta-aviso" class="alert alert-info d-none" role="status"></div>
<?php if(!$catalogo): ?><div class="alert alert-warning">No hay productos activos con precios de venta. Configura sus presentaciones y precios antes de vender.</div><?php endif; ?>
<form method="post" id="form-venta" autocomplete="off" novalidate>
<?=csrfCampo()?><input type="hidden" name="operacion" id="operacion-venta" value="<?=h($clave)?>">
<div class="venta-distribucion">
<div class="venta-trabajo">
<section class="card catalogo-panel" aria-labelledby="catalogo-titulo"><div class="card-header"><span id="catalogo-titulo"><i class="bi bi-search" aria-hidden="true"></i> Buscar productos</span><span class="badge bg-light text-dark" id="catalogo-cantidad">0 productos</span></div><div class="card-body">
<div class="buscador-venta"><div class="flex-grow-1"><label class="form-label" for="buscar">Código o nombre</label><input class="form-control" type="search" id="buscar" placeholder="Escribe el código o nombre del medicamento" autofocus></div><button class="btn btn-primary" type="button" id="buscar-productos"><i class="bi bi-search" aria-hidden="true"></i> Buscar</button></div>
<p class="ayuda-catalogo">Selecciona la presentación y la cantidad. Con un solo resultado puedes presionar Enter para agregarlo.</p>
<div class="catalogo-cabecera" aria-hidden="true"><span>Producto</span><span>Presentación</span><span>Stock</span><span>Precio</span><span>Cantidad</span><span></span></div>
<div id="catalogo-resultados" class="catalogo-resultados" role="list" aria-label="Productos de inventario"></div>
<p id="catalogo-vacio" class="text-muted p-3 mb-0" hidden>No encontramos productos. Prueba otro código o nombre.</p>
</div></section>
<section class="card detalle-panel" aria-labelledby="detalle-titulo"><div class="card-header"><span id="detalle-titulo"><i class="bi bi-bag-check" aria-hidden="true"></i> Detalle de venta <span class="badge bg-light text-dark" id="cantidad-renglones">0</span></span><button class="btn btn-light btn-sm" type="button" id="descartar-borrador" disabled><i class="bi bi-trash" aria-hidden="true"></i> Descartar borrador</button></div>
<div class="detalle-cabecera" aria-hidden="true"><span>Producto / presentación</span><span>Cantidad</span><span>Precio</span><span>Descuento Q</span><span>Importe</span><span></span></div>
<div id="venta-lineas" role="list" aria-label="Detalle de venta"></div><div id="sin-lineas" class="venta-vacia"><i class="bi bi-basket" aria-hidden="true"></i><strong>Tu venta está vacía</strong><span>Agrega productos desde el listado de inventario.</span></div>
<div class="detalle-nota">Los descuentos se aplican al renglón completo y se expresan en quetzales. Se usan primero los lotes vigentes que vencen antes.</div>
</section>
</div>
<aside class="card cobro-panel" id="cobro" aria-labelledby="cobro-titulo"><div class="card-header" id="cobro-titulo"><i class="bi bi-cash-coin" aria-hidden="true"></i> Cobro de la venta</div><div class="card-body">
<label class="form-label" for="cliente">Cliente (opcional)</label><input class="form-control mb-3" id="cliente" name="cliente" maxlength="150" value="<?=h(is_scalar($datos['cliente']??'')?($datos['cliente']??''):'')?>" placeholder="Consumidor final">
<div class="pago-efectivo"><i class="bi bi-cash-stack" aria-hidden="true"></i><span>Efectivo</span><i class="bi bi-check-circle-fill ms-auto" aria-hidden="true"></i></div>
<dl class="resumen-cobro"><div><dt>Subtotal</dt><dd id="suma-subtotal">Q 0.00</dd></div><div><dt>Descuentos</dt><dd id="suma-descuento">Q 0.00</dd></div><div class="total-cobro"><dt>Total a cobrar</dt><dd id="suma-total">Q 0.00</dd></div></dl>
<label class="form-label" for="efectivo">Efectivo recibido (Q) *</label><input class="form-control efectivo-input" id="efectivo" name="efectivo" type="number" min="0" max="99999999.99" step="0.01" required value="<?=h(is_scalar($datos['efectivo']??'')?($datos['efectivo']??''):'')?>" inputmode="decimal" placeholder="0.00">
<div class="cambio-cobro"><span>Cambio a entregar</span><strong id="suma-cambio">Q 0.00</strong></div>
<p id="estado-cobro" class="estado-cobro" role="status">Agrega productos para comenzar.</p>
<button class="btn btn-primary btn-lg w-100" id="confirmar" type="submit" disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> Registrar venta</button>
<p class="small text-muted mt-3 mb-0">El borrador se conserva en esta pestaña. Podrás imprimir el comprobante después de registrar.</p>
</div></aside>
</div>
<div class="cobro-movil"><span>Total <strong id="total-movil">Q 0.00</strong></span><button class="btn btn-primary" type="button" id="ir-cobro">Ir a cobrar <i class="bi bi-arrow-down" aria-hidden="true"></i></button></div>
</form></div>
<noscript><div class="alert alert-warning">Activa JavaScript para buscar productos y registrar la venta.</div></noscript>
<script type="application/json" id="catalogo-ventas"><?=json_encode(['productos'=>$catalogo,'descuento'=>$descuento,'lineas'=>is_array($datos['lineas']??null)?array_values($datos['lineas']):[],'cliente'=>is_scalar($datos['cliente']??'')?($datos['cliente']??''):'','efectivo'=>is_scalar($datos['efectivo']??'')?($datos['efectivo']??''):'','usuario'=>(int)$_SESSION['usuario_id'],'operacion'=>$clave,'operaciones'=>$claves,'confirmadas'=>$confirmadas,'post'=>$_SERVER['REQUEST_METHOD']==='POST'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<script src="/clinica/js/ventas.js?v=4"></script>
<?php require '../includes/footer.php'; ?>
