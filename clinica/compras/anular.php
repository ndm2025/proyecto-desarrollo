<?php
require_once '../includes/farmacia.php';
exigirPermiso('compras.anular');
exigirPermiso('compras.ver');
require_once '../includes/compras_servicio.php';
$db = farmaciaDb();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT c.*,p.Nombre FROM Compras c JOIN Proveedores p ON p.ProveedorID=c.ProveedorID WHERE CompraID=?');
$stmt->execute([$id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$compra) { http_response_code(404); exit('Compra no encontrada.'); }
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    try {
        $anulada = anularCompra($db,$id,$_POST['motivo'] ?? '',$_SESSION['usuario_id']);
        redirigir('ver.php?id='.$id.($anulada?'&anulada=1':'&ya_anulada=1'));
    } catch (Throwable $error) {
        $errores[] = errorFarmacia($error);
    }
}
$titulo = 'Anular compra #'.$id;
require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-x-circle"></i> Anular compra #<?=$id?></h1>
<?php erroresVista($errores); ?>
<?php if ($compra['Estado'] === 'Anulada'): ?>
<div class="alert alert-info">Esta compra ya está anulada. Sus unidades no se descontarán nuevamente.</div>
<a class="btn btn-secondary" href="ver.php?id=<?=$id?>">Ver compra</a>
<?php else: ?>
<div class="card"><div class="card-header">Confirmación de anulación</div><div class="card-body">
<p><strong>Proveedor:</strong> <?=h($compra['Nombre'])?></p>
<p><strong>Documento:</strong> <?=h($compra['NumeroDocumento'])?></p>
<?php if (tienePermiso('medicamentos.costos')): ?><p><strong>Total original:</strong> <?=dinero($compra['Total'])?></p><?php endif; ?>
<div class="alert alert-warning">Al confirmar se descontarán de sus lotes todas las unidades ingresadas por esta compra. Si algún lote no tiene existencias suficientes, no se aplicará ningún cambio. La compra y sus movimientos originales permanecerán en el historial.</div>
<form method="post"><?=csrfCampo()?>
<div class="mb-3"><label class="form-label" for="motivo">Motivo de anulación *</label><textarea class="form-control" id="motivo" name="motivo" rows="3" maxlength="500" required placeholder="Explica por qué se anula esta compra..."><?=h($_POST['motivo'] ?? '')?></textarea><small class="text-muted">Obligatorio. Máximo 500 caracteres.</small></div>
<button class="btn btn-danger" type="submit"><i class="bi bi-x-circle"></i> Confirmar anulación</button>
<a class="btn btn-secondary" href="ver.php?id=<?=$id?>">Volver sin anular</a>
</form></div></div>
<?php endif; require '../includes/footer.php'; ?>
