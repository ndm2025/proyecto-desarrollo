<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($usuario) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $stmt = $db->prepare("CALL sp_ValidarLogin(?, ?)");
            $stmt->execute([$usuario, $password]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && $resultado['Activo'] == 1) {
                $_SESSION['usuario_id'] = $resultado['UsuarioID'];
                $_SESSION['usuario'] = $resultado['NombreUsuario'];
                $_SESSION['nombre_completo'] = $resultado['NombreCompleto'];
                $_SESSION['rol'] = $resultado['NombreRol'];
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
        } catch(PDOException $e) {
            $error = "Error en el sistema. Intente nuevamente.";
        }
    } else {
        $error = "Complete todos los campos";
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
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="text-primary">🏥 Sistema Clínica</h2>
                            <p class="text-muted">Ingrese sus credenciales</p>
                        </div>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" name="usuario" required autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Contraseña</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                Iniciar Sesión
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">Usuario por defecto: admin / admin123</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>