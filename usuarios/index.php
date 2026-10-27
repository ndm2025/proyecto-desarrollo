<?php
$titulo = "Gestión de Usuarios";
require_once '../includes/header.php';

if(!tienePermiso('Usuarios')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$mensaje = $_GET['mensaje'] ?? '';

try {
    $stmt = $db->query("SELECT u.*, r.NombreRol,
                        DATE_FORMAT(u.FechaCreacion, '%d/%m/%Y') as FechaCreacionFormato,
                        DATE_FORMAT(u.UltimoAcceso, '%d/%m/%Y %H:%i') as UltimoAccesoFormato
                        FROM Usuarios u
                        INNER JOIN Roles r ON u.RolID = r.RolID
                        WHERE u.Activo = 1
                        ORDER BY u.NombreCompleto");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-person-badge"></i> Gestión de Usuarios
</h1>

<?php if($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Lista de Usuarios</span>
        <a href="nuevo.php" class="btn btn-light btn-sm">
            <i class="bi bi-plus-circle"></i> Nuevo Usuario
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Fecha Registro</th>
                        <th>Último Acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($usuarios) > 0): ?>
                        <?php foreach($usuarios as $usuario): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($usuario['NombreUsuario']); ?></strong></td>
                            <td><?php echo htmlspecialchars($usuario['NombreCompleto']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['Email']); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($usuario['NombreRol']); ?>
                                </span>
                            </td>
                            <td><?php echo $usuario['FechaCreacionFormato']; ?></td>
                            <td><?php echo $usuario['UltimoAccesoFormato'] ?? 'Nunca'; ?></td>
                            <td>
                                <a href="editar.php?id=<?php echo $usuario['UsuarioID']; ?>" 
                                   class="btn btn-sm btn-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No hay usuarios registrados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>