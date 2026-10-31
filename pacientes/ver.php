<?php
$titulo = "Ver Paciente";
require_once '../includes/header.php';

if(!tienePermiso('Pacientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$paciente_id = $_GET['id'] ?? 0;

try {
    // Obtener datos del paciente
    $stmt = $db->prepare("SELECT * FROM Pacientes WHERE PacienteID = ? AND Activo = 1");
    $stmt->execute([$paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$paciente) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener citas del paciente
    $stmt = $db->prepare("SELECT c.*, e.NombreEstado, e.Color,
                          CONCAT(u.NombreCompleto) as Medico
                          FROM Citas c
                          INNER JOIN EstadosCita e ON c.EstadoCitaID = e.EstadoCitaID
                          LEFT JOIN Usuarios u ON c.MedicoAsignado = u.UsuarioID
                          WHERE c.PacienteID = ?
                          ORDER BY c.FechaCita DESC
                          LIMIT 10");
    $stmt->execute([$paciente_id]);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener consultas del paciente
    $stmt = $db->prepare("SELECT cm.*, u.NombreCompleto as Medico
                          FROM ConsultasMedicas cm
                          INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
                          WHERE cm.PacienteID = ?
                          ORDER BY cm.FechaConsulta DESC
                          LIMIT 10");
    $stmt->execute([$paciente_id]);
    $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Calcular edad
$fecha_nac = new DateTime($paciente['FechaNacimiento']);
$hoy = new DateTime();
$edad = $hoy->diff($fecha_nac)->y;
?>

<h1 class="page-title">
    <i class="bi bi-person-circle"></i> Expediente del Paciente
</h1>

<!-- Datos del Paciente -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-vcard"></i> Información Personal</span>
        <a href="editar.php?id=<?php echo $paciente_id; ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-pencil"></i> Editar
        </a>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="180">Expediente:</th>
                        <td><strong><?php echo htmlspecialchars($paciente['NumeroExpediente']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Nombre Completo:</th>
                        <td><?php echo htmlspecialchars($paciente['Nombres'] . ' ' . $paciente['Apellidos']); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha Nacimiento:</th>
                        <td><?php echo date('d/m/Y', strtotime($paciente['FechaNacimiento'])); ?> (<?php echo $edad; ?> años)</td>
                    </tr>
                    <tr>
                        <th>Sexo:</th>
                        <td><?php echo $paciente['Sexo'] == 'M' ? 'Masculino' : 'Femenino'; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="180">Teléfono:</th>
                        <td><?php echo htmlspecialchars($paciente['Telefono']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($paciente['Email']); ?></td>
                    </tr>
                    <tr>
                        <th>Dirección:</th>
                        <td><?php echo htmlspecialchars($paciente['Direccion']); ?></td>
                    </tr>
                    <tr>
                        <th>Contacto Emergencia:</th>
                        <td>
                            <?php echo htmlspecialchars($paciente['ContactoEmergencia']); ?>
                            <?php if($paciente['TelefonoEmergencia']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($paciente['TelefonoEmergencia']); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Historial de Citas -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-calendar-check"></i> Historial de Citas
    </div>
    <div class="card-body">
        <?php if(count($citas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th>Médico</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($citas as $cita): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($cita['FechaCita'])); ?></td>
                            <td><?php echo htmlspecialchars($cita['MotivoConsulta']); ?></td>
                            <td><?php echo htmlspecialchars($cita['Medico'] ?? 'No asignado'); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $cita['Color']; ?>">
                                    <?php echo $cita['NombreEstado']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No hay citas registradas</p>
        <?php endif; ?>
    </div>
</div>

<!-- Historial de Consultas -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-file-medical"></i> Historial de Consultas
    </div>
    <div class="card-body">
        <?php if(count($consultas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th>Diagnóstico</th>
                            <th>Médico</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($consultas as $consulta): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($consulta['FechaConsulta'])); ?></td>
                            <td><?php echo htmlspecialchars(substr($consulta['MotivoConsulta'], 0, 50)); ?>...</td>
                            <td><?php echo htmlspecialchars(substr($consulta['Diagnostico'], 0, 50)); ?>...</td>
                            <td><?php echo htmlspecialchars($consulta['Medico']); ?></td>
                            <td>
                                <a href="/clinica/consultas/ver.php?id=<?php echo $consulta['ConsultaID']; ?>" 
                                   class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No hay consultas registradas</p>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<?php require_once '../includes/footer.php'; ?>