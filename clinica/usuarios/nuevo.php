<?php
ob_start();
$titulo = "Nuevo Usuario";
require_once '../includes/header.php';

if(!tienePermiso('Usuarios')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];

// Obtener roles
try {
    $stmt = $db->query("SELECT * FROM Roles WHERE Activo = 1");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errores[] = "Error al cargar roles";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $rol_id = $_POST['rol_id'] ?? '';
    
    // Validaciones
    if(empty($nombre_usuario)) $errores[] = "El nombre de usuario es requerido";
    if(empty($password)) $errores[] = "La contraseña es requerida";
    if($password !== $confirmar_password) $errores[] = "Las contraseñas no coinciden";
    if(empty($nombre_completo)) $errores[] = "El nombre completo es requerido";
    if(empty($email)) $errores[] = "El email es requerido";
    if(empty($rol_id)) $errores[] = "Debe seleccionar un rol";
    
    // Verificar si el usuario ya existe
    if(empty($errores)) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM Usuarios WHERE NombreUsuario = ?");
            $stmt->execute([$nombre_usuario]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($resultado['total'] > 0) {
                $errores[] = "El nombre de usuario ya existe";
            }
        } catch(PDOException $e) {
            $errores[] = "Error al verificar usuario";
        }
    }
    
    if(count($errores) == 0) {
        try {
            $sql = "INSERT INTO Usuarios 
                    (NombreUsuario, PasswordHash, NombreCompleto, Email, Telefono, RolID)
                    VALUES (?, AES_ENCRYPT(?, 'CONTROLCLINICA'), ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $nombre_usuario, $password, $nombre_completo,
                $email, $telefono, $rol_id
            ]);
            
            header("Location: index.php?mensaje=Usuario creado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
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
        <i class="bi bi-clipboard-data"></i> Información del Usuario
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre de Usuario *</label>
                    <input type="text" class="form-control" name="nombre_usuario" required 
                           value="<?php echo htmlspecialchars($_POST['nombre_usuario'] ?? ''); ?>">
                    <small class="text-muted">Sin espacios, solo letras y números</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol *</label>
                    <select class="form-select" name="rol_id" required>
                        <option value="">Seleccione un rol...</option>
                        <?php foreach($roles as $rol): ?>
                            <option value="<?php echo $rol['RolID']; ?>"
                                    <?php echo ($_POST['rol_id'] ?? '') == $rol['RolID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['NombreRol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraseña *</label>
                    <input type="password" class="form-control" name="password" required>
                    <small class="text-muted">Mínimo 6 caracteres</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirmar Contraseña *</label>
                    <input type="password" class="form-control" name="confirmar_password" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Nombre Completo *</label>
                    <input type="text" class="form-control" name="nombre_completo" required
                           value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" name="telefono"
                           value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Crear Usuario
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>