<?php
ob_start();
$titulo = "Nuevo Lote de Medicamento";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];

// Obtener medicamentos activos
try {
    $stmt = $db->query("SELECT MedicamentoID, NombreMedicamento, PrincipioActivo, Presentacion
                        FROM Medicamentos 
                        WHERE Activo = 1 
                        ORDER BY NombreMedicamento");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errores[] = "Error al cargar medicamentos";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $medicamento_id = $_POST['medicamento_id'] ?? '';
    $numero_lote = trim($_POST['numero_lote'] ?? '');
    $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
    $cantidad_inicial = $_POST['cantidad_inicial'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    if(empty($medicamento_id)) {
        $errores[] = "Debe seleccionar un medicamento";
    }
    
    if(empty($numero_lote)) {
        $errores[] = "El número de lote es requerido";
    }
    
    if(empty($fecha_vencimiento)) {
        $errores[] = "La fecha de vencimiento es requerida";
    }
    
    if(empty($cantidad_inicial) || $cantidad_inicial < 1) {
        $errores[] = "La cantidad inicial debe ser mayor a 0";
    }
    
    // Verificar que el número de lote no exista
    if(count($errores) == 0) {
        try {
            $stmt = $db->prepare("SELECT LoteID FROM LotesMedicamentos WHERE NumeroLote = ?");
            $stmt->execute([$numero_lote]);
            if($stmt->rowCount() > 0) {
                $errores[] = "Ya existe un lote con ese número";
            }
        } catch(PDOException $e) {
            $errores[] = "Error al verificar duplicados";
        }
    }
    
    // Insertar si no hay errores
    if(count($errores) == 0) {
        try {
            $sql = "INSERT INTO LotesMedicamentos 
                    (MedicamentoID, NumeroLote, FechaVencimiento, 
                     CantidadInicial, CantidadActual, Activo)
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $medicamento_id, 
                $numero_lote, 
                $fecha_vencimiento,
                $cantidad_inicial, 
                $cantidad_inicial, // CantidadActual = CantidadInicial al crear
                $activo
            ]);
            
            $lote_id = $db->lastInsertId();
            
            // Registrar movimiento de entrada (E)
            $sql_mov = "INSERT INTO MovimientosLote 
                        (LoteID, TipoMovimiento, Cantidad, Observaciones, UsuarioID)
                        VALUES (?, 'E', ?, 'Ingreso inicial del lote', ?)";
            $stmt_mov = $db->prepare($sql_mov);
            $stmt_mov->execute([$lote_id, $cantidad_inicial, $_SESSION['usuario_id']]);
            
            header("Location: index.php?mensaje=Lote registrado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-box-seam"></i> Nuevo Lote de Medicamento
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
        <i class="bi bi-plus-circle"></i> Información del Lote
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Medicamento *</label>
                    <select class="form-select" name="medicamento_id" required>
                        <option value="">Seleccione un medicamento...</option>
                        <?php foreach($medicamentos as $medicamento): ?>
                            <option value="<?php echo $medicamento['MedicamentoID']; ?>"
                                    <?php echo (isset($_POST['medicamento_id']) && $_POST['medicamento_id'] == $medicamento['MedicamentoID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($medicamento['NombreMedicamento'] . ' - ' . $medicamento['Presentacion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Número de Lote *</label>
                    <input type="text" class="form-control" name="numero_lote" 
                           value="<?php echo htmlspecialchars($_POST['numero_lote'] ?? ''); ?>" 
                           placeholder="Ej: L2025-001"
                           required>
                    <small class="text-muted">Código único que identifica este lote</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Fecha de Vencimiento *</label>
                    <input type="date" class="form-control" name="fecha_vencimiento" 
                           value="<?php echo htmlspecialchars($_POST['fecha_vencimiento'] ?? ''); ?>"
                           min="<?php echo date('Y-m-d'); ?>"
                           required>
                    <small class="text-muted">Fecha límite de uso del medicamento</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cantidad Inicial *</label>
                    <input type="number" class="form-control" name="cantidad_inicial" 
                           value="<?php echo htmlspecialchars($_POST['cantidad_inicial'] ?? ''); ?>"
                           min="1"
                           placeholder="Cantidad de unidades"
                           required>
                    <small class="text-muted">Cantidad de unidades que ingresan al inventario</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" 
                               id="activo" <?php echo (!isset($_POST['activo']) || $_POST['activo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">
                            Lote Activo
                        </label>
                    </div>
                    <small class="text-muted">Los lotes activos están disponibles para dispensar</small>
                </div>
            </div>
            
            <hr>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                <strong>Nota:</strong> Al crear el lote, se registrará automáticamente un movimiento de entrada por la cantidad inicial especificada.
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Lote
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>