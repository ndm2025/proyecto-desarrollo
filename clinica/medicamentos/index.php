<?php
$titulo = "Medicamentos";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$busqueda = $_GET['busqueda'] ?? '';
$estado = $_GET['estado'] ?? '';
$mensaje = $_GET['mensaje'] ?? '';

try {
    $sql = "SELECT MedicamentoID, NombreMedicamento, PrincipioActivo, 
            Presentacion, Activo, FechaCreacion
            FROM Medicamentos
            WHERE 1=1";
    
    if($busqueda) {
        $sql .= " AND (NombreMedicamento LIKE ? OR PrincipioActivo LIKE ?)";
    }
    
    if($estado !== '') {
        $sql .= " AND Activo = ?";
    }
    
    $sql .= " ORDER BY NombreMedicamento ASC";
    
    $stmt = $db->prepare($sql);
    
    $params = [];
    if($busqueda) {
        $busqueda_param = "%$busqueda%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    if($estado !== '') {
        $params[] = $estado;
    }
    
    $stmt->execute($params);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-capsule"></i> Gestión de Medicamentos
</h1>

<?php if($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list"></i> Catálogo de Medicamentos</span>
        <a href="nuevo.php" class="btn btn-light btn-sm">
            <i class="bi bi-plus-circle"></i> Nuevo Medicamento
        </a>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="busqueda" 
                       placeholder="Nombre o principio activo..."
                       value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="1" <?php echo $estado === '1' ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo $estado === '0' ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Limpiar
                </a>
            </div>
        </form>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Comercial</th>
                        <th>Principio Activo</th>
                        <th>Presentación</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($medicamentos) > 0): ?>
                        <?php foreach($medicamentos as $medicamento): ?>
                        <tr>
                            <td><?php echo $medicamento['MedicamentoID']; ?></td>
                            <td><strong><?php echo htmlspecialchars($medicamento['NombreMedicamento']); ?></strong></td>
                            <td><?php echo htmlspecialchars($medicamento['PrincipioActivo']); ?></td>
                            <td><?php echo htmlspecialchars($medicamento['Presentacion']); ?></td>
                            <td>
                                <?php if($medicamento['Activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($medicamento['FechaCreacion'])); ?></td>
                            <td>
                                <a href="editar.php?id=<?php echo $medicamento['MedicamentoID']; ?>" 
                                   class="btn btn-sm btn-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="ver.php?id=<?php echo $medicamento['MedicamentoID']; ?>" 
                                   class="btn btn-sm btn-info" title="Ver Detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                              
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No se encontraron medicamentos
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <p class="text-muted">
                <i class="bi bi-info-circle"></i> 
                Total de medicamentos: <strong><?php echo count($medicamentos); ?></strong>
            </p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>