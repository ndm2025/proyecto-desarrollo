<?php
ob_start();
$titulo = "Editar Usuario";
require_once '../includes/header.php';

if(!tienePermiso('Usuarios')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$usuario_id = $_GET['id'] ?? 0;
$errores = [];
$usuario = null;

// Obtener datos del usuario
try {
    $stmt = $db->prepare("SELECT * FROM Usuarios WHERE UsuarioID = ? AND Activo = 1");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$usuario) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener roles
    $stmt = $db->query("SELECT * FROM Roles WHERE Activo = 1");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $errores[] = "Error al cargar datos";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $rol_id = $_POST['rol_id'] ?? '';
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    if(empty($nombre_completo)) $errores[] = "El nombre completo es requerido";
    if(empty($email)) $errores[] = "El email es requerido";
    if(empty($rol_id)) $errores[] = "Debe seleccionar un rol";
    
    // Validar password si se está cambiando
    if(!empty($nueva_password)) {
        if($nueva_password !== $confirmar_password) {
            $errores[] = "Las contraseñas no coinciden";
        }
    }
    
    if(count($errores) == 0) {
        try {
            if(!empty($nueva_password)) {
                // Actualizar con nueva contraseña
                $sql = "UPDATE Usuarios SET 
                        PasswordHash = AES_ENCRYPT(?, 'CONTROLCLINICA'),
                        NombreCompleto = ?, Email = ?, Telefono = ?, RolID = ?
                        WHERE UsuarioID = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$nueva_password, $nombre_completo, $email, $telefono, $rol_id, $usuario_id]);
            } else {
                // Actualizar sin cambiar contraseña
                $sql = "UPDATE Usuarios SET 
                        NombreCompleto = ?, Email = ?, Telefono = ?, RolID = ?
                        WHERE UsuarioID = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$nombre_completo, $email, $telefono, $rol_id, $usuario_id]);
            }
            
            header("Location: index.php?mensaje=Usuario actualizado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}

$datos = $_SERVER['REQUEST_METHOD'] == 'POST' ? $_POST : $usuario;
?>

<h1 class="page-title">
    <i class="bi bi-pencil-square"></i> Editar Usuario
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
        <i class="bi bi-clipboard-data"></i> Usuario: <?php echo htmlspecialchars($usuario['NombreUsuario']); ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario['NombreUsuario']); ?>" disabled>
                    <small class="text-muted">El nombre de usuario no se puede cambiar</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol *</label>
                    <select class="form-select" name="rol_id" required>
                        <?php foreach($roles as $rol): ?>
                            <option value="<?php echo $rol['RolID']; ?>"
                                    <?php echo ($datos['RolID'] ?? '') == $rol['RolID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['NombreRol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Deje los campos de contraseña vacíos si no desea cambiarla
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-control" name="nueva_password">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-control" name="confirmar_password">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Nombre Completo *</label>
                    <input type="text" class="form-control" name="nombre_completo" required
                           value="<?php echo htmlspecialchars($datos['NombreCompleto'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required
                           value="<?php echo htmlspecialchars($datos['Email'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" name="telefono"
                           value="<?php echo htmlspecialchars($datos['Telefono'] ?? ''); ?>">
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