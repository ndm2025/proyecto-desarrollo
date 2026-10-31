<?php
ob_start();
$titulo = "Editar Paciente";
require_once '../includes/header.php';

if(!tienePermiso('Pacientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$paciente_id = $_GET['id'] ?? 0;
$errores = [];
$paciente = null;

// Obtener datos del paciente
try {
    $stmt = $db->prepare("SELECT * FROM Pacientes WHERE PacienteID = ? AND Activo = 1");
    $stmt->execute([$paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$paciente) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    $errores[] = "Error al obtener paciente";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $contacto_emergencia = trim($_POST['contacto_emergencia'] ?? '');
    $telefono_emergencia = trim($_POST['telefono_emergencia'] ?? '');
    
    if(empty($nombres)) $errores[] = "El nombre es requerido";
    if(empty($apellidos)) $errores[] = "Los apellidos son requeridos";
    if(empty($fecha_nacimiento)) $errores[] = "La fecha de nacimiento es requerida";
    if(empty($sexo)) $errores[] = "El sexo es requerido";
    
    if(count($errores) == 0) {
        try {
            $sql = "UPDATE Pacientes SET 
                    Nombres = ?, Apellidos = ?, FechaNacimiento = ?, Sexo = ?,
                    Telefono = ?, Email = ?, Direccion = ?, 
                    ContactoEmergencia = ?, TelefonoEmergencia = ?
                    WHERE PacienteID = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $nombres, $apellidos, $fecha_nacimiento, $sexo,
                $telefono, $email, $direccion, $contacto_emergencia, 
                $telefono_emergencia, $paciente_id
            ]);
            
            header("Location: index.php?mensaje=Paciente actualizado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Si hay POST, usar esos valores, si no, usar los de la BD
$datos = $_SERVER['REQUEST_METHOD'] == 'POST' ? $_POST : $paciente;
?>

<h1 class="page-title">
    <i class="bi bi-pencil-square"></i> Editar Paciente
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
        <i class="bi bi-clipboard-data"></i> Expediente: <?php echo htmlspecialchars($paciente['NumeroExpediente']); ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombres *</label>
                    <input type="text" class="form-control" name="nombres" required 
                           value="<?php echo htmlspecialchars($datos['Nombres'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Apellidos *</label>
                    <input type="text" class="form-control" name="apellidos" required
                           value="<?php echo htmlspecialchars($datos['Apellidos'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fecha de Nacimiento *</label>
                    <input type="date" class="form-control" name="fecha_nacimiento" required
                           value="<?php echo $datos['FechaNacimiento'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sexo *</label>
                    <select class="form-select" name="sexo" required>
                        <option value="">Seleccione...</option>
                        <option value="M" <?php echo ($datos['Sexo'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo ($datos['Sexo'] ?? '') == 'F' ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" name="telefono"
                           value="<?php echo htmlspecialchars($datos['Telefono'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email"
                           value="<?php echo htmlspecialchars($datos['Email'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" class="form-control" name="direccion"
                           value="<?php echo htmlspecialchars($datos['Direccion'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contacto de Emergencia</label>
                    <input type="text" class="form-control" name="contacto_emergencia"
                           value="<?php echo htmlspecialchars($datos['ContactoEmergencia'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono de Emergencia</label>
                    <input type="tel" class="form-control" name="telefono_emergencia"
                           value="<?php echo htmlspecialchars($datos['TelefonoEmergencia'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Cambios
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>


<?php require_once '../includes/footer.php'; ?>