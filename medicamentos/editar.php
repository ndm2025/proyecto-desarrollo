<?php
ob_start();
$titulo = "Editar Medicamento";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$medicamento_id = $_GET['id'] ?? 0;
$errores = [];

// Obtener datos del medicamento
try {
    $stmt = $db->prepare("SELECT * FROM Medicamentos WHERE MedicamentoID = ?");
    $stmt->execute([$medicamento_id]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$medicamento) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    $errores[] = "Error al cargar los datos del medicamento";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $principio_activo = trim($_POST['principio_activo'] ?? '');
    $presentacion = trim($_POST['presentacion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    if(empty($nombre)) {
        $errores[] = "El nombre del medicamento es requerido";
    }
    
    if(empty($principio_activo)) {
        $errores[] = "El principio activo es requerido";
    }
    
    if(empty($presentacion)) {
        $errores[] = "La presentación es requerida";
    }
    
    // Verificar si el medicamento ya existe (excluyendo el actual)
    if(count($errores) == 0) {
        try {
            $stmt = $db->prepare("SELECT MedicamentoID FROM Medicamentos 
                                  WHERE NombreMedicamento = ? AND MedicamentoID != ?");
            $stmt->execute([$nombre, $medicamento_id]);
            if($stmt->rowCount() > 0) {
                $errores[] = "Ya existe otro medicamento con ese nombre";
            }
        } catch(PDOException $e) {
            $errores[] = "Error al verificar duplicados";
        }
    }
    
    // Actualizar si no hay errores
    if(count($errores) == 0) {
        try {
            $sql = "UPDATE Medicamentos 
                    SET NombreMedicamento = ?, 
                        PrincipioActivo = ?, 
                        Presentacion = ?, 
                        Activo = ?
                    WHERE MedicamentoID = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$nombre, $principio_activo, $presentacion, $activo, $medicamento_id]);
            
            header("Location: index.php?mensaje=Medicamento actualizado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al actualizar: " . $e->getMessage();
        }
    }
} else {
    // Cargar datos existentes en el formulario
    $_POST['nombre'] = $medicamento['NombreMedicamento'];
    $_POST['principio_activo'] = $medicamento['PrincipioActivo'];
    $_POST['presentacion'] = $medicamento['Presentacion'];
    $_POST['activo'] = $medicamento['Activo'];
}
?>

<h1 class="page-title">
    <i class="bi bi-pencil-square"></i> Editar Medicamento
</h1>

<?php if(count($errores) > 0): ?>
    <div class="alert alert-danger">
        <strong>Por favor corrija los siguientes errores:</strong>
        <ul class="mb-0">
            <?php foreach($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-clipboard-data"></i> Información del Medicamento
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Atención:</strong> Está editando el medicamento 
                        <strong><?php echo htmlspecialchars($medicamento['NombreMedicamento']); ?></strong>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre Comercial *</label>
                    <input type="text" class="form-control" name="nombre" 
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" 
                           placeholder="Ej: Paracetamol 500mg"
                           required>
                    <small class="text-muted">Nombre con el que se comercializa el medicamento</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Principio Activo *</label>
                    <input type="text" class="form-control" name="principio_activo" 
                           value="<?php echo htmlspecialchars($_POST['principio_activo'] ?? ''); ?>"
                           placeholder="Ej: Paracetamol"
                           required>
                    <small class="text-muted">Componente químico activo del medicamento</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Presentación *</label>
                    <input type="text" class="form-control" name="presentacion" 
                           value="<?php echo htmlspecialchars($_POST['presentacion'] ?? ''); ?>"
                           placeholder="Ej: Tabletas, Cápsulas, Jarabe, etc."
                           required>
                    <small class="text-muted">Forma farmacéutica del medicamento</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" 
                               id="activo" <?php echo $_POST['activo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">
                            Medicamento Activo
                        </label>
                    </div>
                    <small class="text-muted">Los medicamentos activos pueden ser recetados</small>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-1">
                        <i class="bi bi-calendar"></i> 
                        <strong>Fecha de Registro:</strong> 
                        <?php echo date('d/m/Y H:i', strtotime($medicamento['FechaCreacion'])); ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Cambios
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <a href="ver.php?id=<?php echo $medicamento_id; ?>" class="btn btn-info">
                    <i class="bi bi-eye"></i> Ver Detalles
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>