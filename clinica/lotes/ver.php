<?php
$titulo = "Ver Lote";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$lote_id = $_GET['id'] ?? 0;

try {
    // Obtener datos del lote
    $stmt = $db->prepare("SELECT l.*, m.NombreMedicamento, m.PrincipioActivo, m.Presentacion
                          FROM LotesMedicamentos l
                          INNER JOIN Medicamentos m ON l.MedicamentoID = m.MedicamentoID
                          WHERE l.LoteID = ?");
    $stmt->execute([$lote_id]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$lote) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener movimientos del lote
    $stmt = $db->prepare("SELECT m.*, u.NombreCompleto as Usuario,
                          CASE 
                            WHEN m.TipoMovimiento = 'E' THEN 'Entrada'
                            WHEN m.TipoMovimiento = 'S' THEN 'Salida'
                            WHEN m.TipoMovimiento = 'A' THEN 'Ajuste'
                          END as TipoMovimientoTexto
                          FROM MovimientosLote m
                          LEFT JOIN Usuarios u ON m.UsuarioID = u.UsuarioID
                          WHERE m.LoteID = ?
                          ORDER BY m.FechaMovimiento DESC");
    $stmt->execute([$lote_id]);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Calcular días para vencimiento
$hoy = new DateTime();
$vencimiento = new DateTime($lote['FechaVencimiento']);
$dias_vencimiento = $hoy->diff($vencimiento)->days;
$vencido = $vencimiento < $hoy;
?>

<h1 class="page-title">
    <i class="bi bi-box-seam"></i> Detalles del Lote
</h1>

<!-- Información del Lote -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-info-circle"></i> Información General
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Número de Lote:</strong> <?php echo htmlspecialchars($lote['NumeroLote']); ?></p>
                <p><strong>Medicamento:</strong> <?php echo htmlspecialchars($lote['NombreMedicamento']); ?></p>
                <p><strong>Principio Activo:</strong> <?php echo htmlspecialchars($lote['PrincipioActivo']); ?></p>
                <p><strong>Presentación:</strong> <?php echo htmlspecialchars($lote['Presentacion']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Fecha de Vencimiento:</strong> 
                    <?php echo date('d/m/Y', strtotime($lote['FechaVencimiento'])); ?>
                    <?php if($vencido): ?>
                        <span class="badge bg-danger ms-2">Vencido</span>
                    <?php elseif($dias_vencimiento <= 30): ?>
                        <span class="badge bg-warning ms-2">Vence en <?php echo $dias_vencimiento; ?> días</span>
                    <?php endif; ?>
                </p>
                <p><strong>Cantidad Inicial:</strong> <?php echo $lote['CantidadInicial']; ?> unidades</p>
                <p><strong>Cantidad Actual:</strong> 
                    <?php echo $lote['CantidadActual']; ?> unidades
                    <?php if($lote['CantidadActual'] == 0): ?>
                        <span class="badge bg-secondary ms-2">Agotado</span>
                    <?php elseif($lote['CantidadActual'] <= 10): ?>
                        <span class="badge bg-warning ms-2">Stock bajo</span>
                    <?php endif; ?>
                </p>
                <p><strong>Fecha de Ingreso:</strong> <?php echo date('d/m/Y H:i', strtotime($lote['FechaIngreso'])); ?></p>
                <p><strong>Estado:</strong> 
                    <?php if($lote['Activo']): ?>
                        <span class="badge bg-success">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactivo</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-graph-up"></i> Estadísticas
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-6">
                <div class="stat-card">
                    <i class="bi bi-box-arrow-in-down text-success" style="font-size: 2rem;"></i>
                    <h3 class="mt-2"><?php echo $lote['CantidadInicial']; ?></h3>
                    <p class="text-muted">Ingresadas Inicialmente</p>
                </div>
            </div>
            <!-- <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-box-arrow-up text-danger" style="font-size: 2rem;"></i>
                    <h3 class="mt-2"><?php echo $lote['CantidadInicial'] - $lote['CantidadActual']; ?></h3>
                    <p class="text-muted">Dispensadas</p>
                </div>
            </div>-->
            <div class="col-md-6">
                <div class="stat-card">
                    <i class="bi bi-boxes text-primary" style="font-size: 2rem;"></i>
                    <h3 class="mt-2"><?php echo $lote['CantidadActual']; ?></h3>
                    <p class="text-muted">En Stock</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Movimientos del Lote -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-arrow-left-right"></i> Historial de Movimientos</span>
        <a href="movimiento.php?lote=<?php echo $lote_id; ?>" class="btn btn-light btn-sm">
            <i class="bi bi-plus-circle"></i> Registrar Movimiento
        </a>
    </div>
    <div class="card-body">
        <?php if(count($movimientos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Usuario</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($movimientos as $mov): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($mov['FechaMovimiento'])); ?></td>
                            <td>
                                <?php if($mov['TipoMovimiento'] == 'E'): ?>
                                    <span class="badge bg-success">Entrada</span>
                                <?php elseif($mov['TipoMovimiento'] == 'S'): ?>
                                    <span class="badge bg-danger">Salida</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Ajuste</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($mov['TipoMovimiento'] == 'E'): ?>
                                    <span class="text-success">+<?php echo $mov['Cantidad']; ?></span>
                                <?php else: ?>
                                    <span class="text-danger">-<?php echo $mov['Cantidad']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($mov['Usuario'] ?? 'Sistema'); ?></td>
                            <td><?php echo htmlspecialchars($mov['Observaciones']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No hay movimientos registrados</p>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex gap-2">
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <a href="movimiento.php?lote=<?php echo $lote_id; ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left-right"></i> Registrar Movimiento
    </a>
</div>

<style>
.stat-card {
    padding: 20px;
    border-radius: 8px;
    background-color: #f8f9fa;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

@media print {
    .btn, .card-header .btn {
        display: none !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>