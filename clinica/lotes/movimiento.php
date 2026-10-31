<?php
ob_start();
$titulo = "Registrar Movimiento";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$lote_id = $_GET['lote'] ?? 0;
$errores = [];

// Obtener información del lote
try {
    $stmt = $db->prepare("SELECT l.*, m.NombreMedicamento, m.Presentacion
                          FROM LotesMedicamentos l
                          INNER JOIN Medicamentos m ON l.MedicamentoID = m.MedicamentoID
                          WHERE l.LoteID = ?");
    $stmt->execute([$lote_id]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$lote) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    $errores[] = "Error al cargar el lote";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_movimiento = $_POST['tipo_movimiento'] ?? '';
    $cantidad = $_POST['cantidad'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    // Validaciones
    if(empty($tipo_movimiento)) {
        $errores[] = "Debe seleccionar el tipo de movimiento";
    }
    
    if(empty($cantidad) || $cantidad < 1) {
        $errores[] = "La cantidad debe ser mayor a 0";
    }
    
    // Validar que haya suficiente stock para salidas
    if($tipo_movimiento == 'S' && $cantidad > $lote['CantidadActual']) {
        $errores[] = "No hay suficiente stock disponible. Stock actual: " . $lote['CantidadActual'];
    }
    
    if(empty($observaciones)) {
        $errores[] = "Las observaciones son requeridas";
    }
    
    // Registrar movimiento si no hay errores
    if(count($errores) == 0) {
        try {
            $db->beginTransaction();
            
            // Insertar movimiento
            $sql = "INSERT INTO MovimientosLote 
                    (LoteID, TipoMovimiento, Cantidad, Observaciones, UsuarioID)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $lote_id, 
                $tipo_movimiento, 
                $cantidad, 
                $observaciones, 
                $_SESSION['usuario_id']
            ]);
            
            // Actualizar cantidad actual del lote
            $nueva_cantidad = $lote['CantidadActual'];
            
            if($tipo_movimiento == 'E') {
                $nueva_cantidad += $cantidad;
            } else { // S o A
                $nueva_cantidad -= $cantidad;
            }
            
            $sql_update = "UPDATE LotesMedicamentos 
                          SET CantidadActual = ?
                          WHERE LoteID = ?";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->execute([$nueva_cantidad, $lote_id]);
            
            $db->commit();
            
            header("Location: ver.php?id=$lote_id&mensaje=Movimiento registrado exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $db->rollBack();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-arrow-left-right"></i> Registrar Movimiento de Lote
</h1>

<!-- Información del Lote -->
<div class="alert alert-info">
    <strong><i class="bi bi-box-seam"></i> Lote:</strong> <?php echo htmlspecialchars($lote['NumeroLote']); ?>
    <br>
    <strong><i class="bi bi-capsule"></i> Medicamento:</strong> <?php echo htmlspecialchars($lote['NombreMedicamento'] . ' - ' . $lote['Presentacion']); ?>
    <br>
    <strong><i class="bi bi-boxes"></i> Stock Actual:</strong> <?php echo $lote['CantidadActual']; ?> unidades
</div>

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
        <i class="bi bi-clipboard-data"></i> Datos del Movimiento
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Movimiento *</label>
                    <select class="form-select" name="tipo_movimiento" id="tipo_movimiento" required>
                        <option value="">Seleccione...</option>
                        <option value="E" <?php echo (isset($_POST['tipo_movimiento']) && $_POST['tipo_movimiento'] == 'E') ? 'selected' : ''; ?>>
                            Entrada (+)
                        </option>
                        <option value="S" <?php echo (isset($_POST['tipo_movimiento']) && $_POST['tipo_movimiento'] == 'S') ? 'selected' : ''; ?>>
                            Salida (-)
                        </option>
                        <option value="A" <?php echo (isset($_POST['tipo_movimiento']) && $_POST['tipo_movimiento'] == 'A') ? 'selected' : ''; ?>>
                            Ajuste (-)
                        </option>
                    </select>
                    <small class="text-muted">
                        <strong>Entrada:</strong> Ingreso de mercadería<br>
                        <strong>Salida:</strong> Dispensación o venta<br>
                        <strong>Ajuste:</strong> Correcciones de inventario, merma, vencimiento
                    </small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cantidad *</label>
                    <input type="number" class="form-control" name="cantidad" 
                           value="<?php echo htmlspecialchars($_POST['cantidad'] ?? ''); ?>"
                           min="1"
                           placeholder="Cantidad de unidades"
                           required>
                    <small class="text-muted">Cantidad de unidades del movimiento</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Observaciones *</label>
                <textarea class="form-control" name="observaciones" rows="3" 
                          placeholder="Describa el motivo del movimiento..."
                          required><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
                <small class="text-muted">Explique el motivo del movimiento (ejemplo: dispensado en receta, producto vencido, corrección de inventario, etc.)</small>
            </div>
            
            <hr>
            
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Importante:</strong> Los movimientos de tipo Salida y Ajuste disminuirán el stock actual del lote.
                Solo los movimientos de Entrada incrementarán el stock.
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Registrar Movimiento
                </button>
                <a href="ver.php?id=<?php echo $lote_id; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Cambiar color del select según el tipo de movimiento
document.getElementById('tipo_movimiento').addEventListener('change', function() {
    const select = this;
    select.classList.remove('border-success', 'border-danger', 'border-warning');
    
    if(this.value === 'E') {
        select.classList.add('border-success');
    } else if(this.value === 'S') {
        select.classList.add('border-danger');
    } else if(this.value === 'A') {
        select.classList.add('border-warning');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>