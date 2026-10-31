<?php
$titulo = "Consultas Médicas";
require_once '../includes/header.php';

if(!tienePermiso('Expedientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$busqueda = $_GET['busqueda'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$mensaje = $_GET['mensaje'] ?? '';

try {
    $sql = "SELECT cm.ConsultaID, cm.FechaConsulta, cm.MotivoConsulta, cm.Diagnostico,
            CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
            p.NumeroExpediente,
            u.NombreCompleto as Medico,
            (SELECT COUNT(*) FROM Recetas r WHERE r.ConsultaID = cm.ConsultaID) as TieneReceta,
            (SELECT COUNT(*) FROM Recetas r 
             INNER JOIN RecetaDetalle rd ON r.RecetaID = rd.RecetaID 
             WHERE r.ConsultaID = cm.ConsultaID) as CantidadMedicamentos
            FROM ConsultasMedicas cm
            INNER JOIN Pacientes p ON cm.PacienteID = p.PacienteID
            INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
            WHERE DATE(cm.FechaConsulta) BETWEEN ? AND ?";
    
    if($busqueda) {
        $sql .= " AND (p.Nombres LIKE ? OR p.Apellidos LIKE ? OR p.NumeroExpediente LIKE ?)";
    }
    
    $sql .= " ORDER BY cm.FechaConsulta DESC";
    
    $stmt = $db->prepare($sql);
    
    if($busqueda) {
        $busqueda_param = "%$busqueda%";
        $stmt->execute([$fecha_desde, $fecha_hasta, $busqueda_param, $busqueda_param, $busqueda_param]);
    } else {
        $stmt->execute([$fecha_desde, $fecha_hasta]);
    }
    
    $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-file-medical"></i> Consultas Médicas
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
        <span><i class="bi bi-list"></i> Historial de Consultas</span>
        <a href="nueva.php" class="btn btn-light btn-sm">
            <i class="bi bi-plus-circle"></i> Nueva Consulta
        </a>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" name="fecha_desde" 
                       value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" name="fecha_hasta" 
                       value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar Paciente</label>
                <input type="text" class="form-control" name="busqueda" 
                       placeholder="Nombre o expediente..."
                       value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Expediente</th>
                        <th>Motivo</th>
                        <th>Diagnóstico</th>
                        <th>Médico</th>
                        <th>Receta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($consultas) > 0): ?>
                        <?php foreach($consultas as $consulta): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($consulta['FechaConsulta'])); ?></td>
                            <td><?php echo htmlspecialchars($consulta['Paciente']); ?></td>
                            <td><strong><?php echo htmlspecialchars($consulta['NumeroExpediente']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($consulta['MotivoConsulta'], 0, 40)); ?>...</td>
                            <td><?php echo htmlspecialchars(substr($consulta['Diagnostico'], 0, 40)); ?>...</td>
                            <td><?php echo htmlspecialchars($consulta['Medico']); ?></td>
                            <td class="text-center">
                                <?php if($consulta['TieneReceta'] > 0): ?>
                                    <span class="badge bg-success" title="<?php echo $consulta['CantidadMedicamentos']; ?> medicamento(s)">
                                        <i class="bi bi-capsule-pill"></i> 
                                        <?php echo $consulta['CantidadMedicamentos']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x"></i> Sin receta
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="ver.php?id=<?php echo $consulta['ConsultaID']; ?>" 
                                   class="btn btn-sm btn-info" title="Ver Detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No se encontraron consultas
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(count($consultas) > 0): ?>
        <div class="mt-3">
            <p class="text-muted">
                <i class="bi bi-info-circle"></i> 
                Total de consultas: <strong><?php echo count($consultas); ?></strong>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.badge {
    font-size: 0.85rem;
}

.table td {
    vertical-align: middle;
}
</style>

<?php require_once '../includes/footer.php'; ?>