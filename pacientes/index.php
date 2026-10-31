<?php
$titulo = "Gestión de Pacientes";
require_once '../includes/header.php';

if(!tienePermiso('Pacientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$busqueda = $_GET['busqueda'] ?? '';
$mensaje = $_GET['mensaje'] ?? '';

try {
    $sql = "SELECT PacienteID, NumeroExpediente, 
            CONCAT(Nombres, ' ', Apellidos) as NombreCompleto,
            FechaNacimiento, Sexo, Telefono, Email,
            DATE_FORMAT(FechaRegistro, '%d/%m/%Y') as FechaRegistroFormato
            FROM Pacientes 
            WHERE Activo = 1";
    
    if($busqueda) {
        $sql .= " AND (Nombres LIKE :busqueda 
                  OR Apellidos LIKE :busqueda 
                  OR NumeroExpediente LIKE :busqueda)";
    }
    
    $sql .= " ORDER BY Apellidos, Nombres";
    
    $stmt = $db->prepare($sql);
    
    if($busqueda) {
        $busqueda_param = "%$busqueda%";
        $stmt->bindParam(':busqueda', $busqueda_param);
    }
    
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error al obtener pacientes: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-people"></i> Gestión de Pacientes
</h1>

<?php if($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Lista de Pacientes</span>
        <a href="nuevo.php" class="btn btn-light btn-sm">
            <i class="bi bi-plus-circle"></i> Nuevo Paciente
        </a>
    </div>
    <div class="card-body">
        <!-- Búsqueda -->
        <form method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" name="busqueda" 
                       placeholder="Buscar por nombre o expediente..." 
                       value="<?php echo htmlspecialchars($busqueda); ?>">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <?php if($busqueda): ?>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Nombre Completo</th>
                        <th>Fecha Nacimiento</th>
                        <th>Sexo</th>
                        <th>Teléfono</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($pacientes) > 0): ?>
                        <?php foreach($pacientes as $paciente): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($paciente['NumeroExpediente']); ?></strong></td>
                            <td><?php echo htmlspecialchars($paciente['NombreCompleto']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($paciente['FechaNacimiento'])); ?></td>
                            <td><?php echo $paciente['Sexo'] == 'M' ? 'Masculino' : 'Femenino'; ?></td>
                            <td><?php echo htmlspecialchars($paciente['Telefono']); ?></td>
                            <td><?php echo $paciente['FechaRegistroFormato']; ?></td>
                            <td>
                                <a href="ver.php?id=<?php echo $paciente['PacienteID']; ?>" 
                                   class="btn btn-sm btn-info" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="editar.php?id=<?php echo $paciente['PacienteID']; ?>" 
                                   class="btn btn-sm btn-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No se encontraron pacientes
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>