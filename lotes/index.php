<?php
$titulo = "Lotes de Medicamentos";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$busqueda = $_GET['busqueda'] ?? '';
$estado = $_GET['estado'] ?? '';
$mensaje = $_GET['mensaje'] ?? '';

try {
    $sql = "SELECT l.*, m.NombreMedicamento, m.PrincipioActivo, m.Presentacion
            FROM LotesMedicamentos l
            INNER JOIN Medicamentos m ON l.MedicamentoID = m.MedicamentoID
            WHERE 1=1";
    
    if($busqueda) {
        $sql .= " AND (m.NombreMedicamento LIKE ? OR l.NumeroLote LIKE ?)";
    }
    
    if($estado !== '') {
        $sql .= " AND l.Activo = ?";
    }
    
    $sql .= " ORDER BY l.FechaVencimiento ASC";
    
    $stmt = $db->prepare($sql);
    
    $params = [];
    if($busqueda) {
        $busqueda_param = "%$busqueda%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    if($estado !== '') {
        $params[] = $estado;
    }
    
    $stmt->execute($params);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-box-seam"></i> Lotes de Medicamentos
</h1>

<?php if($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Inventario de Lotes</span>
        <a href="nuevo.php" class="btn btn-light btn-sm">
            <i class="bi bi-plus-circle"></i> Nuevo Lote
        </a>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="busqueda" 
                       placeholder="Medicamento o número de lote..."
                       value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="1" <?php echo $estado === '1' ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo $estado === '0' ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Limpiar
                </a>
            </div>
        </form>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número Lote</th>
                        <th>Medicamento</th>
                        <th>Vencimiento</th>
                        <th>Cant. Inicial</th>
                        <th>Cant. Actual</th>
                        <th>Estado</th>
                        <th>Fecha Ingreso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($lotes) > 0): ?>
                        <?php foreach($lotes as $lote): 
                            $hoy = new DateTime();
                            $vencimiento = new DateTime($lote['FechaVencimiento']);
                            $dias_vencimiento = $hoy->diff($vencimiento)->days;
                            $vencido = $vencimiento < $hoy;
                        ?>
                        <tr class="<?php echo $vencido ? 'table-danger' : ($dias_vencimiento <= 30 ? 'table-warning' : ''); ?>">
                            <td><strong><?php echo htmlspecialchars($lote['NumeroLote']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($lote['NombreMedicamento']); ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($lote['Presentacion']); ?></small>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($lote['FechaVencimiento'])); ?>
                                <?php if($vencido): ?>
                                    <br><span class="badge bg-danger">Vencido</span>
                                <?php elseif($dias_vencimiento <= 30): ?>
                                    <br><span class="badge bg-warning">Por vencer</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $lote['CantidadInicial']; ?></td>
                            <td>
                                <?php echo $lote['CantidadActual']; ?>
                                <?php if($lote['CantidadActual'] == 0): ?>
                                    <br><span class="badge bg-secondary">Agotado</span>
                                <?php elseif($lote['CantidadActual'] <= 10): ?>
                                    <br><span class="badge bg-warning">Bajo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($lote['Activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($lote['FechaIngreso'])); ?></td>
                            <td>
                                <a href="ver.php?id=<?php echo $lote['LoteID']; ?>" 
                                   class="btn btn-sm btn-info" title="Ver Detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="movimiento.php?lote=<?php echo $lote['LoteID']; ?>" 
                                   class="btn btn-sm btn-primary" title="Registrar Movimiento">
                                    <i class="bi bi-arrow-left-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No se encontraron lotes
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <p class="text-muted">
                <i class="bi bi-info-circle"></i> 
                Total de lotes: <strong><?php echo count($lotes); ?></strong>
            </p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>