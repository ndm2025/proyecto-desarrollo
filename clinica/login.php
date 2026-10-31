<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/autenticacion.php';

// Si ya está autenticado, redirigir al Dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$mensaje = $_GET['mensaje'] ?? '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'sesion_invalida') {
        $error = "Su sesión expiró o sus credenciales fueron actualizadas. Inicie sesión nuevamente.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($usuario) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            try {
                $resultado = autenticarUsuario($db, $usuario, $password);
                
                if ($resultado && !empty($resultado['Activo'])) {
                    session_regenerate_id(true);
                    
                    $_SESSION['usuario_id'] = (int)$resultado['UsuarioID'];
                    $_SESSION['usuario'] = $resultado['NombreUsuario'];
                    $_SESSION['nombre_completo'] = $resultado['NombreCompleto'];
                    $_SESSION['rol'] = $resultado['NombreRol'];
                    $_SESSION['rol_id'] = (int)$resultado['RolID'];
                    $_SESSION['sesion_version'] = (int)$resultado['SesionVersion'];
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Usuario o contraseña incorrectos, o cuenta inactiva.";
                }
            } catch (Throwable $e) {
                $error = "Error al verificar credenciales. Intente nuevamente.";
            }
        } else {
            $error = "No se pudo conectar con la base de datos.";
        }
    } else {
        $error = "Por favor complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Clínica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 style="color: var(--dark-blue); font-weight: bold;">
                                <i class="bi bi-hospital"></i> Sistema Clínica
                            </h2>
                            <p class="text-muted">Ingrese sus credenciales de acceso</p>
                        </div>
                        
                        <?php if($mensaje): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($mensaje); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" name="usuario" 
                                       value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" required autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Contraseña</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                            </button>
                        </form>
                        
                        <div class="text-center mt-3 pt-2">
                            <a href="recuperar.php" class="text-decoration-none small" style="color: var(--dark-blue);">
                                <i class="bi bi-question-circle me-1"></i> ¿Olvidó su usuario o contraseña?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>