<?php
$titulo = "Gestión de Citas";
require_once '../includes/header.php';

if(!tienePermiso('Citas')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$estado_filtro = $_GET['estado'] ?? '';
$mensaje = $_GET['mensaje'] ?? '';

try {
    // Obtener estados para el filtro
    $stmt = $db->query("SELECT * FROM EstadosCita");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener citas
    $sql = "SELECT c.CitaID, c.FechaCita, c.MotivoConsulta, c.DuracionMinutos,
            CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
            p.NumeroExpediente,
            e.NombreEstado, e.Color,
            CONCAT(u.NombreCompleto) as Medico
            FROM Citas c
            INNER JOIN Pacientes p ON c.PacienteID = p.PacienteID
            INNER JOIN EstadosCita e ON c.EstadoCitaID = e.EstadoCitaID
            LEFT JOIN Usuarios u ON c.MedicoAsignado = u.UsuarioID
            WHERE DATE(c.FechaCita) = ?";
    
    if($estado_filtro) {
        $sql .= " AND c.EstadoCitaID = ?";
    }
    
    $sql .= " ORDER BY c.FechaCita ASC";
    
    $stmt = $db->prepare($sql);
    
    if($estado_filtro) {
        $stmt->execute([$fecha_filtro, $estado_filtro]);
    } else {
        $stmt->execute([$fecha_filtro]);
    }
    
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-calendar-check"></i> Gestión de Citas
</h1>

<?php if($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Agenda de Citas</span>
        <div class="d-flex gap-2">
            <a href="/clinica/atencion/index.php?fecha=<?php echo urlencode($fecha_filtro); ?>" class="btn btn-success btn-sm">
                <i class="bi bi-heart-pulse-fill me-1"></i> Sala de Atención del Día
            </a>
            <a href="nueva.php" class="btn btn-light btn-sm">
                <i class="bi bi-plus-circle"></i> Nueva Cita
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Fecha</label>
                <input type="date" class="form-control" name="fecha" 
                       value="<?php echo $fecha_filtro; ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <?php foreach($estados as $estado): ?>
                        <option value="<?php echo $estado['EstadoCitaID']; ?>"
                                <?php echo $estado_filtro == $estado['EstadoCitaID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado['NombreEstado']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Limpiar Filtros
                </a>
            </div>
        </form>

        <!-- Tabla de Citas -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Expediente</th>
                        <th>Motivo</th>
                        <th>Médico</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($citas) > 0): ?>
                        <?php foreach($citas as $cita): ?>
                        <tr>
                            <td><strong><?php echo date('H:i', strtotime($cita['FechaCita'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($cita['Paciente']); ?></td>
                            <td><?php echo htmlspecialchars($cita['NumeroExpediente']); ?></td>
                            <td><?php echo htmlspecialchars(substr($cita['MotivoConsulta'], 0, 40)); ?></td>
                            <td><?php echo htmlspecialchars($cita['Medico'] ?? 'No asignado'); ?></td>
                            <td><?php echo $cita['DuracionMinutos']; ?> min</td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $cita['Color']; ?>">
                                    <?php echo $cita['NombreEstado']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="editar.php?id=<?php echo $cita['CitaID']; ?>" 
                                   class="btn btn-sm btn-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if((int)$cita['EstadoCitaID'] !== 4): ?>
                                <a href="/clinica/atencion/atender.php?cita=<?php echo $cita['CitaID']; ?>" 
                                   class="btn btn-sm btn-success" title="Iniciar Atención Médica">
                                    <i class="bi bi-heart-pulse-fill"></i> Atender
                                </a>
                                <?php else: ?>
                                <span class="badge bg-secondary">Atendida</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No hay citas para esta fecha
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>