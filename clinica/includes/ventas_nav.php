<nav class="ventas-tabs" aria-label="Secciones de ventas">
<?php if(tienePermiso('ventas.crear')): ?><a href="/clinica/ventas/nueva.php" class="<?=($seccionVentas??'')==='nueva'?'activa':''?>" <?=($seccionVentas??'')==='nueva'?'aria-current="page"':''?>><i class="bi bi-box-seam" aria-hidden="true"></i> Productos de inventario</a><?php endif; ?>
<a href="/clinica/ventas/index.php" class="<?=($seccionVentas??'')==='listado'?'activa':''?>" <?=($seccionVentas??'')==='listado'?'aria-current="page"':''?>><i class="bi bi-receipt" aria-hidden="true"></i> Listado de ventas</a>
</nav>
