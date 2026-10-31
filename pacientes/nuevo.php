<?php
ob_start();
$titulo = "Nuevo Paciente";
require_once '../includes/header.php';

if(!tienePermiso('Pacientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validaciones
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
            // Generar número de expediente
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(NumeroExpediente, 4) AS UNSIGNED)) as ultimo 
                               FROM Pacientes WHERE NumeroExpediente LIKE 'EXP%'");
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $siguiente = ($resultado['ultimo'] ?? 0) + 1;
            $numero_expediente = 'EXP' . str_pad($siguiente, 6, '0', STR_PAD_LEFT);
            
            // Insertar paciente
            $sql = "INSERT INTO Pacientes 
                    (NumeroExpediente, Nombres, Apellidos, FechaNacimiento, Sexo, 
                     Telefono, Email, Direccion, ContactoEmergencia, TelefonoEmergencia, UsuarioRegistro)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $numero_expediente, $nombres, $apellidos, $fecha_nacimiento, $sexo,
                $telefono, $email, $direccion, $contacto_emergencia, $telefono_emergencia,
                $_SESSION['usuario_id']
            ]);
            
            $success = true;
            header("Location: index.php?mensaje=Paciente registrado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-person-plus"></i> Nuevo Paciente
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
        <i class="bi bi-clipboard-data"></i> Información del Paciente
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombres *</label>
                    <input type="text" class="form-control" name="nombres" required 
                           value="<?php echo $_POST['nombres'] ?? ''; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Apellidos *</label>
                    <input type="text" class="form-control" name="apellidos" required
                           value="<?php echo $_POST['apellidos'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fecha de Nacimiento *</label>
                    <input type="date" class="form-control" name="fecha_nacimiento" required
                           value="<?php echo $_POST['fecha_nacimiento'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sexo *</label>
                    <select class="form-select" name="sexo" required>
                        <option value="">Seleccione...</option>
                        <option value="M" <?php echo ($_POST['sexo'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo ($_POST['sexo'] ?? '') == 'F' ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" name="telefono"
                           value="<?php echo $_POST['telefono'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email"
                           value="<?php echo $_POST['email'] ?? ''; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" class="form-control" name="direccion"
                           value="<?php echo $_POST['direccion'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contacto de Emergencia</label>
                    <input type="text" class="form-control" name="contacto_emergencia"
                           value="<?php echo $_POST['contacto_emergencia'] ?? ''; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono de Emergencia</label>
                    <input type="tel" class="form-control" name="telefono_emergencia"
                           value="<?php echo $_POST['telefono_emergencia'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Paciente
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>