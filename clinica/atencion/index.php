<?php
$titulo = "Atención de la Clínica";
require_once '../includes/header.php';

if(!tienePermiso('Atencion') && !tienePermiso('Citas') && !tienePermiso('Expedientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$estado_filtro = $_GET['estado'] ?? '';
$mensaje = $_GET['mensaje'] ?? '';

// Cambios de estado rápidos
if(isset($_GET['cambiar_estado']) && isset($_GET['cita_id'])) {
    $nuevo_estado = (int)$_GET['cambiar_estado'];
    $cita_id = (int)$_GET['cita_id'];
    try {
        $stmt = $db->prepare("UPDATE Citas SET EstadoCitaID = ? WHERE CitaID = ?");
        $stmt->execute([$nuevo_estado, $cita_id]);
        header("Location: index.php?fecha=" . urlencode($fecha_filtro) . "&mensaje=Estado actualizado correctamente");
        exit();
    } catch(PDOException $e) {
        $error = "No se pudo actualizar el estado: " . $e->getMessage();
    }
}

try {
    // Estados para selector
    $stmt = $db->query("SELECT * FROM EstadosCita");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contadores del día seleccionado
    $stmtCount = $db->prepare("SELECT 
        COUNT(*) as Total,
        SUM(CASE WHEN EstadoCitaID IN (1, 2) THEN 1 ELSE 0 END) as Pendientes,
        SUM(CASE WHEN EstadoCitaID = 4 THEN 1 ELSE 0 END) as Atendidos,
        SUM(CASE WHEN EstadoCitaID = 3 THEN 1 ELSE 0 END) as Cancelados,
        SUM(CASE WHEN EstadoCitaID = 5 THEN 1 ELSE 0 END) as NoAsistio
        FROM Citas WHERE DATE(FechaCita) = ?");
    $stmtCount->execute([$fecha_filtro]);
    $resumenDia = $stmtCount->fetch(PDO::FETCH_ASSOC);

    // Citas para atención médica
    $sql = "SELECT c.CitaID, c.FechaCita, c.MotivoConsulta, c.DuracionMinutos, c.Observaciones,
                   p.PacienteID, CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                   p.NumeroExpediente, p.Telefono, p.FechaNacimiento, p.Sexo,
                   e.EstadoCitaID, e.NombreEstado, e.Color,
                   u.NombreCompleto as Medico,
                   cm.ConsultaID
            FROM Citas c
            INNER JOIN Pacientes p ON c.PacienteID = p.PacienteID
            INNER JOIN EstadosCita e ON c.EstadoCitaID = e.EstadoCitaID
            LEFT JOIN Usuarios u ON c.MedicoAsignado = u.UsuarioID
            LEFT JOIN ConsultasMedicas cm ON cm.CitaID = c.CitaID
            WHERE DATE(c.FechaCita) = ?";

    if($estado_filtro !== '') {
        $sql .= " AND c.EstadoCitaID = ?";
        $stmt = $db->prepare($sql . " ORDER BY c.FechaCita ASC");
        $stmt->execute([$fecha_filtro, $estado_filtro]);
    } else {
        $stmt = $db->prepare($sql . " ORDER BY c.FechaCita ASC");
        $stmt->execute([$fecha_filtro]);
    }
    
    $citasAtencion = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-heart-pulse"></i> Atención de la Clínica
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

<!-- Tarjetas de Resumen idénticas al Dashboard -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-people" style="font-size: 2rem;"></i>
            <h3><?php echo (int)($resumenDia['Total'] ?? 0); ?></h3>
            <p>Citados Hoy</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="bi bi-hourglass-split" style="font-size: 2rem;"></i>
            <h3><?php echo (int)($resumenDia['Pendientes'] ?? 0); ?></h3>
            <p>En Espera</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="bi bi-check2-circle" style="font-size: 2rem;"></i>
            <h3><?php echo (int)($resumenDia['Atendidos'] ?? 0); ?></h3>
            <p>Atendidos</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card stat-secondary">
            <i class="bi bi-person-x" style="font-size: 2rem;"></i>
            <h3><?php echo (int)(($resumenDia['Cancelados'] ?? 0) + ($resumenDia['NoAsistio'] ?? 0)); ?></h3>
            <p>Cancelados / No Asistió</p>
        </div>
    </div>
</div>

<!-- Agenda y Sala de Atención -->
<div class="card mt-2">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Sala de Atención Médica: <?php echo date('d/m/Y', strtotime($fecha_filtro)); ?></span>
        <div class="d-flex gap-2">
            <a href="atender.php" class="btn btn-light btn-sm">
                <i class="bi bi-plus-circle"></i> Nueva Atención
            </a>
            <a href="/clinica/citas/nueva.php" class="btn btn-light btn-sm">
                <i class="bi bi-calendar-plus"></i> Agendar Cita
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Fecha de Atención</label>
                <input type="date" class="form-control" name="fecha" 
                       value="<?php echo htmlspecialchars($fecha_filtro); ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Estado de Cita</label>
                <select class="form-select" name="estado" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <?php foreach($estados as $est): ?>
                        <option value="<?php echo $est['EstadoCitaID']; ?>" <?php echo (string)$estado_filtro === (string)$est['EstadoCitaID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($est['NombreEstado']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Ver Hoy
                </a>
            </div>
        </form>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Expediente</th>
                        <th>Motivo de Consulta</th>
                        <th>Médico</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($citasAtencion) > 0): ?>
                        <?php foreach($citasAtencion as $c): ?>
                            <?php 
                            $esCompletada = (int)$c['EstadoCitaID'] === 4;
                            ?>
                            <tr>
                                <td><strong><?php echo date('H:i', strtotime($c['FechaCita'])); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['Paciente']); ?></strong>
                                    <?php if(!empty($c['Telefono'])): ?>
                                        <br><small class="text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($c['Telefono']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($c['NumeroExpediente']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars(substr($c['MotivoConsulta'], 0, 50)); ?>
                                    <?php if(!empty($c['Observaciones'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($c['Observaciones']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['Medico'] ?? 'No asignado'); ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($c['Color']); ?>">
                                        <?php echo htmlspecialchars($c['NombreEstado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(!$esCompletada): ?>
                                        <a href="atender.php?cita=<?php echo $c['CitaID']; ?>" 
                                           class="btn btn-sm btn-success" title="Iniciar Atención Médica">
                                            <i class="bi bi-heart-pulse"></i> Atender
                                        </a>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="index.php?fecha=<?php echo urlencode($fecha_filtro); ?>&cita_id=<?php echo $c['CitaID']; ?>&cambiar_estado=2">
                                                        <i class="bi bi-check-circle text-success me-2"></i> Confirmar Llegada
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="index.php?fecha=<?php echo urlencode($fecha_filtro); ?>&cita_id=<?php echo $c['CitaID']; ?>&cambiar_estado=5">
                                                        <i class="bi bi-person-x text-warning me-2"></i> No Asistió
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="index.php?fecha=<?php echo urlencode($fecha_filtro); ?>&cita_id=<?php echo $c['CitaID']; ?>&cambiar_estado=3">
                                                        <i class="bi bi-x-circle text-danger me-2"></i> Cancelar Cita
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="/clinica/citas/editar.php?id=<?php echo $c['CitaID']; ?>">
                                                        <i class="bi bi-pencil me-2"></i> Editar Cita
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <?php if(!empty($c['ConsultaID'])): ?>
                                            <a href="/clinica/consultas/ver.php?id=<?php echo $c['ConsultaID']; ?>" 
                                               class="btn btn-sm btn-info" title="Ver Consulta">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="/clinica/consultas/imprimir_receta.php?id=<?php echo $c['ConsultaID']; ?>" 
                                               target="_blank" class="btn btn-sm btn-success" title="Imprimir Receta">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Atendida</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No hay pacientes en la sala para esta fecha.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
