<?php
require_once '../includes/farmacia.php';exigirPermiso('compras.crear');require_once '../includes/compras_servicio.php';$db=farmaciaDb();$errores=[];

$origen=(int)($_SERVER['REQUEST_METHOD']==='POST'?($_POST['origen']??0):($_GET['origen']??0));
if($origen){
    exigirPermiso('compras.corregir');
    $stmt=$db->prepare("SELECT * FROM Compras WHERE CompraID=? AND Estado='Anulada'");$stmt->execute([$origen]);$compraOrigen=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$compraOrigen){http_response_code(409);exit('Solo puedes corregir compras anuladas.');}
    if($_SERVER['REQUEST_METHOD']!=='POST'){
        $_POST['proveedor']=$compraOrigen['ProveedorID'];$_POST['documento']=$compraOrigen['NumeroDocumento'];$_POST['fecha']=$compraOrigen['FechaCompra'];$_POST['observaciones']='Corrección de compra #'.$origen;
        $stmt=$db->prepare('SELECT d.MedicamentoID AS producto,d.PresentacionID AS presentacion,d.Cantidad AS cantidad,d.UnidadesPorPresentacion AS unidades,d.CostoPresentacion AS costo,l.NumeroLote AS lote,l.FechaVencimiento AS vence FROM CompraDetalle d JOIN LotesMedicamentos l ON l.LoteID=d.LoteID WHERE d.CompraID=? ORDER BY d.CompraDetalleID');$stmt->execute([$origen]);$_POST['lineas']=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 validarCsrf();
 if(!isset($_SESSION['compras_operaciones'][$_POST['operacion']??''])){http_response_code(419);exit('Formulario vencido. Recarga la página.');}
 try{$id=registrarCompra($db,$_POST,$_SESSION['usuario_id']);redirigir('ver.php?id='.$id.'&guardado=1');}catch(Throwable $error){$errores[]=errorFarmacia($error);}
}
$operacion=$_POST['operacion']??bin2hex(random_bytes(32));$_SESSION['compras_operaciones'][$operacion]=time();
foreach($_SESSION['compras_operaciones'] as $clave=>$fecha)if($fecha<time()-7200)unset($_SESSION['compras_operaciones'][$clave]);
$proveedores=$db->query('SELECT ProveedorID,Nombre FROM Proveedores WHERE Activo=1 ORDER BY Nombre')->fetchAll(PDO::FETCH_ASSOC);
$productos=$db->query('SELECT m.MedicamentoID,m.Codigo,m.NombreMedicamento,m.UnidadBase,p.PresentacionID,p.Nombre,p.UnidadesBase FROM Medicamentos m JOIN PresentacionesProducto p ON p.MedicamentoID=m.MedicamentoID WHERE m.Activo=1 AND p.Activo=1 ORDER BY m.NombreMedicamento,p.UnidadesBase')->fetchAll(PDO::FETCH_ASSOC);
$titulo='Registrar compra recibida';require '../includes/header.php';
?>
<link rel="stylesheet" href="/clinica/css/compras.css?v=2">
<h1 class="page-title"><i class="bi bi-bag-plus"></i> Registrar compra recibida</h1>
<?php erroresVista($errores); ?>
<div class="alert alert-info">Registra productos que ya recibiste. Al confirmar, sus cantidades ingresarán a inventario. El costo corresponde a una presentación completa.</div>
<?php if(!$proveedores||!$productos): ?><div class="alert alert-warning">Primero necesitas al menos un proveedor y un producto con presentaciones activas.</div><?php endif; ?>
<?php if($origen): ?><div class="alert alert-warning">Corrección de compra #<?=$origen?>. Se conservará la compra anulada y se creará una nueva recepción con la misma referencia. Revisa cantidades, costos, lotes y fechas antes de confirmar.</div><?php endif; ?>
<form method="post" id="form-compra"><input type="hidden" name="origen" value="<?=$origen?>"><?=csrfCampo()?><input type="hidden" name="operacion" value="<?=h($operacion)?>">
<div class="card"><div class="card-header">Datos de la recepción</div><div class="card-body"><div class="row">
<div class="col-md-4 mb-3"><label class="form-label" for="proveedor">Proveedor *</label><select class="form-select" id="proveedor" name="proveedor" required><option value="">Selecciona...</option><?php foreach($proveedores as $p): ?><option value="<?=$p['ProveedorID']?>" <?=($_POST['proveedor']??'')==$p['ProveedorID']?'selected':''?>><?=h($p['Nombre'])?></option><?php endforeach; ?></select></div>
<div class="col-md-4 mb-3"><label class="form-label" for="documento">Documento / referencia *</label><input class="form-control" id="documento" name="documento" <?=$origen?'readonly':''?> maxlength="60" value="<?=h($_POST['documento']??'')?>" required></div>
<div class="col-md-4 mb-3"><label class="form-label" for="fecha">Fecha de recepción *</label><input class="form-control" type="date" id="fecha" name="fecha" max="<?=date('Y-m-d')?>" value="<?=h($_POST['fecha']??date('Y-m-d'))?>" required></div>
<div class="col-12"><label class="form-label" for="observaciones">Observaciones</label><textarea class="form-control" id="observaciones" name="observaciones" maxlength="500"><?=h($_POST['observaciones']??'')?></textarea></div></div></div></div>

<div class="card compra-lista"><div class="card-header d-flex justify-content-between align-items-center gap-2"><span>Productos recibidos <span class="badge bg-light text-dark" id="cantidad-lineas">0</span></span><button class="btn btn-light btn-sm" type="button" id="agregar-linea"><i class="bi bi-plus-circle"></i> Agregar producto</button></div>
<div class="card-body p-0">
<div class="compra-encabezado" aria-hidden="true"><span>Producto / lote</span><span>Presentación</span><span>Cantidad</span><span>Costo/pres.</span><span>Subtotal</span><span>Acciones</span></div>
<div id="lineas-compra" role="list" aria-label="Productos de la compra"></div>
<div id="compra-vacia" class="text-center text-muted p-4"><i class="bi bi-bag-plus fs-3 d-block mb-2"></i>Agrega los productos recibidos. Aparecerán aquí en una lista.</div>
<div class="compra-total"><span><strong id="total-unidades">0</strong> unidades base en total</span><strong>Total: Q <span id="total-compra">0.00</span></strong></div>
<div id="campos-lineas" hidden></div>
</div></div>
<button class="btn btn-primary" <?=$proveedores&&$productos?'':'disabled'?>>Confirmar recepción</button> <a class="btn btn-secondary" href="index.php">Cancelar</a>
</form>

<div class="modal fade" id="editor-producto" tabindex="-1" aria-labelledby="editor-titulo" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<form id="form-producto-compra"><div class="modal-header"><h2 class="modal-title fs-5" id="editor-titulo">Agregar producto</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
<div class="modal-body"><div class="row g-3">
<div class="col-12"><label class="form-label" for="editor-producto-select">Producto *</label><select class="form-select" id="editor-producto-select" data-buscable="Buscar producto para la compra" data-campo="producto" required><option value="">Selecciona un producto...</option></select></div>
<div class="col-sm-6"><label class="form-label" for="editor-presentacion">Presentación recibida *</label><select class="form-select" id="editor-presentacion" data-campo="presentacion" required><option value="">Selecciona...</option></select></div>
<div class="col-sm-6"><label class="form-label" for="editor-cantidad">Cantidad de presentaciones *</label><input class="form-control" id="editor-cantidad" data-campo="cantidad" type="number" min="1" max="1000000" step="1" required></div>
<div class="col-sm-6"><label class="form-label" for="editor-unidades">Unidades base por presentación *</label><input class="form-control" id="editor-unidades" data-campo="unidades" type="number" min="1" max="1000000" step="1" required><small class="text-muted" id="editor-equivalencia">Indica cuántas unidades contiene cada presentación.</small></div>
<div class="col-sm-6"><label class="form-label" id="editor-costo-label" for="editor-costo">Costo por presentación (Q) *</label><input class="form-control" id="editor-costo" data-campo="costo" type="number" min="0.01" max="99999999.99" step="0.01" required></div>
<div class="col-sm-6"><label class="form-label" for="editor-lote">Número de lote *</label><input class="form-control" id="editor-lote" data-campo="lote" maxlength="50" required></div>
<div class="col-sm-6"><label class="form-label" for="editor-vence">Fecha de vencimiento *</label><input class="form-control" id="editor-vence" data-campo="vence" type="date" min="<?=date('Y-m-d')?>" required></div>
</div><div class="alert alert-light border d-flex justify-content-between flex-wrap gap-2 mt-3 mb-0" aria-live="polite"><span>Unidades recibidas: <strong id="editor-total-unidades">0</strong></span><strong>Subtotal: Q <span id="editor-subtotal">0.00</span></strong></div><p class="text-danger mt-2 mb-0" id="editor-error" role="alert" hidden></p></div>
<div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" id="guardar-producto-compra">Agregar a la lista</button></div>
</form></div></div></div>
<script type="application/json" id="datos-productos"><?=json_encode($productos,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<script type="application/json" id="datos-lineas"><?=json_encode(array_values(is_array($_POST['lineas']??null)?$_POST['lineas']:[]),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<script src="/clinica/js/compras.js?v=2"></script>
<?php require '../includes/footer.php'; ?>
