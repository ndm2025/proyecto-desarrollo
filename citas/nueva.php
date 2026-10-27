<?php
ob_start();
$titulo = "Nueva Cita";
require_once '../includes/header.php';

if(!tienePermiso('Citas')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];

// Obtener pacientes activos
try {
    $stmt = $db->query("SELECT PacienteID, NumeroExpediente, 
                        CONCAT(Nombres, ' ', Apellidos) as NombreCompleto
                        FROM Pacientes WHERE Activo = 1 ORDER BY Apellidos, Nombres");
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener médicos
    $stmt = $db->query("SELECT u.UsuarioID, u.NombreCompleto 
                        FROM Usuarios u
                        INNER JOIN Roles r ON u.RolID = r.RolID
                        WHERE r.NombreRol = 'Médico' AND u.Activo = 1
                        ORDER BY u.NombreCompleto");
    $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estados
    $stmt = $db->query("SELECT * FROM EstadosCita");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $errores[] = "Error al cargar datos";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $paciente_id = $_POST['paciente_id'] ?? '';
    $fecha_cita = $_POST['fecha_cita'] ?? '';
    $hora_cita = $_POST['hora_cita'] ?? '';
    $duracion = $_POST['duracion'] ?? 30;
    $motivo = trim($_POST['motivo'] ?? '');
    $medico_id = $_POST['medico_id'] ?? null;
    $estado_id = $_POST['estado_id'] ?? 1;
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if(empty($paciente_id)) $errores[] = "Debe seleccionar un paciente";
    if(empty($fecha_cita)) $errores[] = "La fecha es requerida";
    if(empty($hora_cita)) $errores[] = "La hora es requerida";
    if(empty($motivo)) $errores[] = "El motivo de consulta es requerido";
    
    if(count($errores) == 0) {
        try {
            $fecha_hora = $fecha_cita . ' ' . $hora_cita;
            
            $sql = "INSERT INTO Citas 
                    (PacienteID, FechaCita, DuracionMinutos, MotivoConsulta, 
                     EstadoCitaID, MedicoAsignado, Observaciones, UsuarioCreacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $paciente_id, $fecha_hora, $duracion, $motivo,
                $estado_id, $medico_id ?: null, $observaciones,
                $_SESSION['usuario_id']
            ]);
            
            header("Location: index.php?mensaje=Cita registrada exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-calendar-plus"></i> Nueva Cita
</h1>

<?php if(count($errores) > 0): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-clipboard-data"></i> Información de la Cita
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Paciente *</label>
                    <select class="form-select" name="paciente_id" required>
                        <option value="">Seleccione un paciente...</option>
                        <?php foreach($pacientes as $paciente): ?>
                            <option value="<?php echo $paciente['PacienteID']; ?>"
                                    <?php echo ($_POST['paciente_id'] ?? '') == $paciente['PacienteID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($paciente['NumeroExpediente'] . ' - ' . $paciente['NombreCompleto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Médico Asignado</label>
                    <select class="form-select" name="medico_id">
                        <option value="">Sin asignar</option>
                        <?php foreach($medicos as $medico): ?>
                            <option value="<?php echo $medico['UsuarioID']; ?>"
                                    <?php echo ($_POST['medico_id'] ?? '') == $medico['UsuarioID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($medico['NombreCompleto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fecha *</label>
                    <input type="date" class="form-control" name="fecha_cita" required
                           value="<?php echo $_POST['fecha_cita'] ?? date('Y-m-d'); ?>"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Hora *</label>
                    <input type="time" class="form-control" name="hora_cita" required
                           value="<?php echo $_POST['hora_cita'] ?? '09:00'; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Duración (minutos)</label>
                    <select class="form-select" name="duracion">
                        <option value="15">15 minutos</option>
                        <option value="30" selected>30 minutos</option>
                        <option value="45">45 minutos</option>
                        <option value="60">60 minutos</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado *</label>
                    <select class="form-select" name="estado_id" required>
                        <?php foreach($estados as $estado): ?>
                            <option value="<?php echo $estado['EstadoCitaID']; ?>"
                                    <?php echo ($_POST['estado_id'] ?? 1) == $estado['EstadoCitaID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($estado['NombreEstado']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Motivo de Consulta *</label>
                <textarea class="form-control" name="motivo" rows="3" required><?php echo htmlspecialchars($_POST['motivo'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="2"><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Cita
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>