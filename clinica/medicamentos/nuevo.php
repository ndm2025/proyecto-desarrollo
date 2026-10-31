<?php
ob_start();
$titulo = "Nuevo Medicamento";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];

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
    
    // Verificar si el medicamento ya existe
    if(count($errores) == 0) {
        try {
            $stmt = $db->prepare("SELECT MedicamentoID FROM Medicamentos 
                                  WHERE NombreMedicamento = ?");
            $stmt->execute([$nombre]);
            if($stmt->rowCount() > 0) {
                $errores[] = "Ya existe un medicamento con ese nombre";
            }
        } catch(PDOException $e) {
            $errores[] = "Error al verificar duplicados";
        }
    }
    
    // Insertar si no hay errores
    if(count($errores) == 0) {
        try {
            $sql = "INSERT INTO Medicamentos 
                    (NombreMedicamento, PrincipioActivo, Presentacion, Activo)
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$nombre, $principio_activo, $presentacion, $activo]);
            
            header("Location: index.php?mensaje=Medicamento registrado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-capsule-pill"></i> Nuevo Medicamento
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
        <i class="bi bi-plus-circle"></i> Información del Medicamento
    </div>
    <div class="card-body">
        <form method="POST" action="">
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
                               id="activo" <?php echo (!isset($_POST['activo']) || $_POST['activo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">
                            Medicamento Activo
                        </label>
                    </div>
                    <small class="text-muted">Los medicamentos activos pueden ser recetados</small>
                </div>
            </div>
            
            <hr>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                <strong>Nota:</strong> Todos los campos marcados con (*) son obligatorios.
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Medicamento
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>