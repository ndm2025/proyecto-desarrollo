<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
validarSesion();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo ?? 'Sistema Clínica'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/clinica/css/style.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/clinica/index.php">
                <i class="bi bi-hospital"></i> Sistema Clínica
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['nombre_completo']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">
                                    <i class="bi bi-shield-check"></i> <?php echo $_SESSION['rol']; ?>
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="/clinica/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                                </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/clinica/index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>

                        <?php if (tienePermiso('Pacientes')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/pacientes/index.php">
                                    <i class="bi bi-people"></i> Pacientes
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (tienePermiso('Citas')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/citas/index.php">
                                    <i class="bi bi-calendar-check"></i> Citas
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (tienePermiso('Expedientes')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/consultas/index.php">
                                    <i class="bi bi-file-medical"></i> Consultas
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (tienePermiso('Medicamentos')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/medicamentos/index.php">
                                    <i class="bi bi-bar-chart"></i> Medicamentos
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (tienePermiso('Medicamentos')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/lotes/index.php">
                                    <i class="bi bi-bar-chart"></i> Lotes
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (tienePermiso('Usuarios')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/usuarios/index.php">
                                    <i class="bi bi-person-badge"></i> Usuarios
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (tienePermiso('Reportes')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/clinica/reportes/index.php">
                                    <i class="bi bi-bar-chart"></i> Reportes
                                </a>
                            </li>
                        <?php endif; ?>

                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto content-wrapper">