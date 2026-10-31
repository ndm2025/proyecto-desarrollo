<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/autenticacion.php';

$mensajeExito = '';
$error = '';
$usuarioEncontrado = null;
$tabActiva = 'usuario';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // ACCIÓN 1: RECUPERAR USUARIO
    if ($accion === 'recuperar_usuario') {
        $tabActiva = 'usuario';
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Por favor ingrese un correo electrónico válido.";
        } else {
            try {
                $stmt = $db->prepare("SELECT NombreUsuario, NombreCompleto, Email, Activo 
                                      FROM Usuarios 
                                      WHERE LOWER(Email) = LOWER(?)");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    if ($user['Activo']) {
                        $usuarioEncontrado = $user['NombreUsuario'];
                        $mensajeExito = "Su nombre de usuario registrado es: <strong>" . htmlspecialchars($user['NombreUsuario']) . "</strong>";
                    } else {
                        $error = "La cuenta asociada se encuentra desactivada. Contacte al administrador.";
                    }
                } else {
                    $error = "No se encontró ninguna cuenta con el correo: " . htmlspecialchars($email);
                }
            } catch (Throwable $e) {
                $error = "Error al consultar: " . $e->getMessage();
            }
        }
    }
    
    // ACCIÓN 2: RESTABLECER CONTRASEÑA
    if ($accion === 'restablecer_clave') {
        $tabActiva = 'clave';
        $usuario = trim($_POST['usuario_restablecer'] ?? '');
        $email = trim($_POST['email_restablecer'] ?? '');
        $nuevaClave = $_POST['nueva_clave'] ?? '';
        $confirmacion = $_POST['confirmar_clave'] ?? '';
        
        if (empty($usuario) || empty($email)) {
            $error = "Complete el usuario y correo electrónico.";
        } elseif (empty($nuevaClave) || empty($confirmacion)) {
            $error = "Ingrese y confirme su nueva contraseña.";
        } else {
            try {
                validarClaveNueva($nuevaClave, $confirmacion);
                
                $stmt = $db->prepare("SELECT UsuarioID, NombreUsuario, Email, Activo 
                                      FROM Usuarios 
                                      WHERE LOWER(NombreUsuario) = LOWER(?) AND LOWER(Email) = LOWER(?)");
                $stmt->execute([$usuario, $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    if (!$user['Activo']) {
                        $error = "Esta cuenta se encuentra inactiva.";
                    } else {
                        $hash = hashClave($nuevaClave);
                        $usuarioId = (int)$user['UsuarioID'];
                        
                        $db->beginTransaction();
                        $update = $db->prepare("UPDATE Usuarios 
                                                SET PasswordHash = ?, SesionVersion = SesionVersion + 1 
                                                WHERE UsuarioID = ?");
                        $update->execute([$hash, $usuarioId]);
                        
                        try {
                            $hist = $db->prepare("INSERT INTO HistorialUsuarios (UsuarioObjetivoID, UsuarioID, Accion, Motivo) 
                                                  VALUES (?, ?, 'usuario.clave_restablecida', 'Recuperación de contraseña')");
                            $hist->execute([$usuarioId, $usuarioId]);
                        } catch (Throwable $th) {}
                        
                        $db->commit();
                        
                        header("Location: login.php?mensaje=" . urlencode("Contraseña restablecida correctamente. Inicie sesión con su nueva clave."));
                        exit();
                    }
                } else {
                    $error = "Los datos ingresados no coinciden con ningún usuario activo.";
                }
            } catch (InvalidArgumentException $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = $e->getMessage();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = "Error al restablecer contraseña: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Acceso - Sistema Clínica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .nav-tabs .nav-link {
            color: #555;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: var(--dark-blue);
            font-weight: bold;
            border-bottom: 2px solid var(--primary-color);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh; padding: 20px 0;">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 style="color: var(--dark-blue); font-weight: bold;">
                                <i class="bi bi-shield-lock"></i> Recuperar Acceso
                            </h2>
                            <p class="text-muted">Sistema Clínica</p>
                        </div>

                        <?php if($mensajeExito): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-1"></i> <?php echo $mensajeExito; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Pestañas -->
                        <ul class="nav nav-tabs nav-fill mb-3" id="tabRecuperacion" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $tabActiva === 'usuario' ? 'active' : ''; ?>" 
                                        id="user-tab" data-bs-toggle="tab" data-bs-target="#tab-user" type="button" role="tab">
                                    <i class="bi bi-person me-1"></i> Recuperar Usuario
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $tabActiva === 'clave' ? 'active' : ''; ?>" 
                                        id="pass-tab" data-bs-toggle="tab" data-bs-target="#tab-pass" type="button" role="tab">
                                    <i class="bi bi-key me-1"></i> Cambiar Clave
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="tabRecuperacionContent">
                            <!-- TAB USUARIO -->
                            <div class="tab-pane fade <?php echo $tabActiva === 'usuario' ? 'show active' : ''; ?>" id="tab-user" role="tabpanel">
                                <p class="text-muted small mb-3">
                                    Ingrese su correo electrónico registrado para consultar su nombre de usuario.
                                </p>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="accion" value="recuperar_usuario">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Correo Electrónico</label>
                                        <input type="email" class="form-control" name="email" 
                                               placeholder="ejemplo@clinica.com" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="bi bi-search me-1"></i> Consultar Usuario
                                    </button>
                                </form>

                                <?php if($usuarioEncontrado): ?>
                                    <div class="alert alert-info mt-3 text-center">
                                        <div class="small">Nombre de usuario:</div>
                                        <h4 class="mb-2" style="color: var(--dark-blue); font-weight: bold;"><?php echo htmlspecialchars($usuarioEncontrado); ?></h4>
                                        <a href="login.php" class="btn btn-sm btn-primary">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión Ahora
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- TAB CONTRASEÑA -->
                            <div class="tab-pane fade <?php echo $tabActiva === 'clave' ? 'show active' : ''; ?>" id="tab-pass" role="tabpanel">
                                <p class="text-muted small mb-3">
                                    Ingrese su usuario y correo para definir una nueva contraseña.
                                </p>

                                <form method="POST" action="">
                                    <input type="hidden" name="accion" value="restablecer_clave">

                                    <div class="mb-3">
                                        <label class="form-label">Usuario</label>
                                        <input type="text" class="form-control" name="usuario_restablecer" 
                                               value="<?php echo htmlspecialchars($_POST['usuario_restablecer'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Correo Electrónico</label>
                                        <input type="email" class="form-control" name="email_restablecer" 
                                               value="<?php echo htmlspecialchars($_POST['email_restablecer'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Nueva Contraseña (mínimo 8 caracteres)</label>
                                        <input type="password" class="form-control" name="nueva_clave" minlength="8" maxlength="72" required>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Confirmar Contraseña</label>
                                        <input type="password" class="form-control" name="confirmar_clave" minlength="8" maxlength="72" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="bi bi-check2-circle me-1"></i> Restablecer Contraseña
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <a href="login.php" class="text-decoration-none small" style="color: var(--dark-blue);">
                                <i class="bi bi-arrow-left me-1"></i> Volver al Login
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
