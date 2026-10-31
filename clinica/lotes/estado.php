<?php
require_once '../includes/farmacia.php';
exigirPermiso('inventario.ver');
exigirPermiso('inventario.estado');
require_once '../includes/lotes_servicio.php';
$db = farmaciaDb();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT l.*,m.NombreMedicamento,m.Activo AS ProductoActivo,m.UnidadBase FROM LotesMedicamentos l JOIN Medicamentos m ON m.MedicamentoID=l.MedicamentoID WHERE LoteID=?');
$stmt->execute([$id]);
$lote = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lote) { http_response_code(404); exit('Lote no encontrado.'); }
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    try {
        if (!in_array($_POST['estado'] ?? null,['0','1'],true) || !in_array($_POST['anterior'] ?? null,['0','1'],true)) {
            throw new InvalidArgumentException('El estado solicitado no es válido.');
        }
        $cambio = cambiarEstadoLote($db,$id,(int)$_POST['estado'],(int)$_POST['anterior'],$_POST['motivo'] ?? '',$_SESSION['usuario_id']);
        redirigir('ver.php?id='.$id.($cambio?'&estado_actualizado=1':'&estado_sin_cambios=1'));
    } catch (Throwable $error) {
        $errores[] = errorFarmacia($error);
    }
}
$activar = !(bool)$lote['Activo'];
$bloqueado = $activar && ($lote['FechaVencimiento']<date('Y-m-d') || !$lote['ProductoActivo']);
$titulo = ($activar?'Reactivar':'Desactivar').' lote';
require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-shield-check" aria-hidden="true"></i> <?=h($titulo)?></h1>
<?php erroresVista($errores); ?>
<div class="card"><div class="card-header">Cambio de estado del lote</div><div class="card-body">
<div class="row"><div class="col-md-6"><p><strong>Producto:</strong> <?=h($lote['NombreMedicamento'])?></p><p><strong>Lote:</strong> <?=h($lote['NumeroLote'])?></p><p><strong>Vencimiento:</strong> <?=h($lote['FechaVencimiento'])?></p></div><div class="col-md-6"><p><strong>Estado actual:</strong> <?=$lote['Activo']?'Activo':'Inactivo'?></p><p><strong>Existencias físicas:</strong> <?=h($lote['CantidadActual'])?> <?=h($lote['UnidadBase'])?></p></div></div>
<?php if ($bloqueado): ?>
<div class="alert alert-warning"><?=$lote['FechaVencimiento']<date('Y-m-d')?'No se puede reactivar un lote vencido.':'Primero debes activar el producto al que pertenece el lote.'?></div>
<a class="btn btn-secondary" href="ver.php?id=<?=$id?>">Volver al lote</a>
<?php else: ?>
<div class="alert alert-info"><?=$activar?'El lote volverá a incluirse en las existencias disponibles si tiene unidades y permanece vigente.':'El lote dejará de incluirse en las existencias disponibles. Su cantidad física y sus movimientos no se modificarán.'?></div>
<form method="post"><?=csrfCampo()?>
<input type="hidden" name="estado" value="<?=$activar?'1':'0'?>"><input type="hidden" name="anterior" value="<?=$lote['Activo']?'1':'0'?>">
<div class="mb-3"><label class="form-label" for="motivo">Motivo <?=$activar?'de reactivación':'de desactivación'?> *</label><textarea class="form-control" id="motivo" name="motivo" rows="3" maxlength="500" required placeholder="Explica por qué cambias el estado del lote..."><?=h($_POST['motivo'] ?? '')?></textarea><small class="text-muted">Se guardarán el motivo, tu usuario y la fecha.</small></div>
<button type="submit" class="btn <?=$activar?'btn-success':'btn-warning'?>">Confirmar <?=$activar?'reactivación':'desactivación'?></button>
<a class="btn btn-secondary" href="ver.php?id=<?=$id?>">Cancelar</a>
</form>
<?php endif; ?>
</div></div>
<?php require '../includes/footer.php'; ?>
