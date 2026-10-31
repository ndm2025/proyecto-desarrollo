<link rel="stylesheet" href="/clinica/css/inventario.css?v=1">
<div class="inventario-nav d-flex flex-wrap gap-2 mb-3" aria-label="Secciones de inventario">
<?php if(tienePermiso('inventario.ver')): ?>
<a class="btn btn-sm <?=$seccionInventario==='productos'?'btn-primary':'btn-outline-primary'?>" href="/clinica/inventario/index.php">Existencias por producto</a>
<a class="btn btn-sm <?=$seccionInventario==='lotes'?'btn-primary':'btn-outline-primary'?>" href="/clinica/inventario/lotes.php">Existencias por lote</a>
<a class="btn btn-sm <?=$seccionInventario==='alertas'?'btn-primary':'btn-outline-primary'?>" href="/clinica/inventario/alertas.php">Alertas</a>
<?php endif; ?>
<?php if(tienePermiso('kardex.ver')): ?><a class="btn btn-sm <?=$seccionInventario==='kardex'?'btn-primary':'btn-outline-primary'?>" href="/clinica/kardex/index.php">Kardex</a><?php endif; ?>
</div>

<p class="d-lg-none text-muted small">Desliza las tablas hacia los lados para ver todas las columnas.</p>
