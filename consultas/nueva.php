<?php
ob_start();
$titulo = "Nueva Consulta Médica";
require_once '../includes/header.php';

if(!tienePermiso('Expedientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];
$cita_id = $_GET['cita'] ?? null;

// Obtener pacientes activos
try {
    $stmt = $db->query("SELECT PacienteID, NumeroExpediente, 
                        CONCAT(Nombres, ' ', Apellidos) as NombreCompleto
                        FROM Pacientes WHERE Activo = 1 ORDER BY Apellidos, Nombres");
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener medicamentos activos
    $stmt = $db->query("SELECT MedicamentoID, NombreMedicamento, PrincipioActivo, Presentacion
                        FROM Medicamentos WHERE Activo = 1 ORDER BY NombreMedicamento");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si viene de una cita, obtener información
    $cita_info = null;
    if($cita_id) {
        $stmt = $db->prepare("SELECT c.*, CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente
                              FROM Citas c
                              INNER JOIN Pacientes p ON c.PacienteID = p.PacienteID
                              WHERE c.CitaID = ?");
        $stmt->execute([$cita_id]);
        $cita_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    $errores[] = "Error al cargar datos";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $paciente_id = $_POST['paciente_id'] ?? '';
    $cita_id_post = $_POST['cita_id'] ?? null;
    $motivo = trim($_POST['motivo'] ?? '');
    $signos_vitales = trim($_POST['signos_vitales'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $tratamiento = trim($_POST['tratamiento'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $observaciones_receta = trim($_POST['observaciones_receta'] ?? '');
    
    // Datos de medicamentos
    $medicamentos_ids = $_POST['medicamento_id'] ?? [];
    $dosis = $_POST['dosis'] ?? [];
    $frecuencias = $_POST['frecuencia'] ?? [];
    $duraciones = $_POST['duracion'] ?? [];
    $indicaciones = $_POST['indicaciones'] ?? [];
    
    if(empty($paciente_id)) $errores[] = "Debe seleccionar un paciente";
    if(empty($diagnostico)) $errores[] = "El diagnóstico es requerido";
    
    // Validar medicamentos si se agregaron
    if(count($medicamentos_ids) > 0) {
        foreach($medicamentos_ids as $index => $med_id) {
            if(empty($med_id)) continue;
            if(empty($dosis[$index])) $errores[] = "Debe especificar la dosis para todos los medicamentos";
            if(empty($frecuencias[$index])) $errores[] = "Debe especificar la frecuencia para todos los medicamentos";
            if(empty($duraciones[$index])) $errores[] = "Debe especificar la duración para todos los medicamentos";
        }
    }
    
    if(count($errores) == 0) {
        try {
            $db->beginTransaction();
            
            // 1. Insertar consulta médica
            $sql = "INSERT INTO ConsultasMedicas 
                    (PacienteID, CitaID, MotivoConsulta, SignosVitales, 
                     Diagnostico, Tratamiento, Observaciones, MedicoID)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $paciente_id, $cita_id_post ?: null, $motivo, $signos_vitales,
                $diagnostico, $tratamiento, $observaciones, $_SESSION['usuario_id']
            ]);
            
            $consulta_id = $db->lastInsertId();
            
            // 2. Si hay medicamentos, crear receta
            $medicamentos_validos = array_filter($medicamentos_ids, function($id) { return !empty($id); });
            
            if(count($medicamentos_validos) > 0) {
                $sql_receta = "INSERT INTO Recetas (ConsultaID, Observaciones) VALUES (?, ?)";
                $stmt_receta = $db->prepare($sql_receta);
                $stmt_receta->execute([$consulta_id, $observaciones_receta]);
                
                $receta_id = $db->lastInsertId();
                
                // 3. Insertar detalle de receta
                $sql_detalle = "INSERT INTO RecetaDetalle 
                               (RecetaID, MedicamentoID, Dosis, Frecuencia, Duracion, Indicaciones)
                               VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_detalle = $db->prepare($sql_detalle);
                
                foreach($medicamentos_ids as $index => $med_id) {
                    if(empty($med_id)) continue;
                    
                    $stmt_detalle->execute([
                        $receta_id,
                        $med_id,
                        $dosis[$index],
                        $frecuencias[$index],
                        $duraciones[$index],
                        $indicaciones[$index] ?? ''
                    ]);
                }
            }
            
            // 4. Si viene de una cita, actualizar el estado a "Completada"
            if($cita_id_post) {
                $stmt = $db->prepare("UPDATE Citas SET EstadoCitaID = 4 WHERE CitaID = ?");
                $stmt->execute([$cita_id_post]);
            }
            
            $db->commit();
            
            header("Location: index.php?mensaje=Consulta registrada exitosamente");
            exit();
            
        } catch(PDOException $e) {
            $db->rollBack();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<h1 class="page-title">
    <i class="bi bi-file-medical-fill"></i> Nueva Consulta Médica
</h1>

<?php if($cita_info): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> 
        Consulta para la cita del paciente: <strong><?php echo htmlspecialchars($cita_info['Paciente']); ?></strong>
        - Fecha: <?php echo date('d/m/Y H:i', strtotime($cita_info['FechaCita'])); ?>
    </div>
<?php endif; ?>

<?php if(count($errores) > 0): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach($errores as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" id="formConsulta">
    <input type="hidden" name="cita_id" value="<?php echo $cita_id ?? ''; ?>">
    
    <!-- Información de la Consulta -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-clipboard-data"></i> Información de la Consulta
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Paciente *</label>
                    <select class="form-select" name="paciente_id" required 
                            <?php echo $cita_info ? 'disabled' : ''; ?>>
                        <option value="">Seleccione un paciente...</option>
                        <?php foreach($pacientes as $paciente): ?>
                            <option value="<?php echo $paciente['PacienteID']; ?>"
                                    <?php echo ($cita_info && $cita_info['PacienteID'] == $paciente['PacienteID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($paciente['NumeroExpediente'] . ' - ' . $paciente['NombreCompleto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($cita_info): ?>
                        <input type="hidden" name="paciente_id" value="<?php echo $cita_info['PacienteID']; ?>">
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Motivo de Consulta</label>
                <textarea class="form-control" name="motivo" rows="2"><?php echo htmlspecialchars($cita_info['MotivoConsulta'] ?? $_POST['motivo'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Signos Vitales</label>
                <textarea class="form-control" name="signos_vitales" rows="3" 
                          placeholder="Ej: Presión: 120/80, Temperatura: 36.5°C, Pulso: 72 lpm, Peso: 70kg"><?php echo htmlspecialchars($_POST['signos_vitales'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Diagnóstico *</label>
                <textarea class="form-control" name="diagnostico" rows="4" required><?php echo htmlspecialchars($_POST['diagnostico'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Tratamiento</label>
                <textarea class="form-control" name="tratamiento" rows="4"><?php echo htmlspecialchars($_POST['tratamiento'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="3"><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- Receta Médica -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-capsule"></i> Receta Médica (Opcional)</span>
            <button type="button" class="btn btn-light btn-sm" onclick="agregarMedicamento()">
                <i class="bi bi-plus-circle"></i> Agregar Medicamento
            </button>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Observaciones de la Receta</label>
                <textarea class="form-control" name="observaciones_receta" rows="2" 
                          placeholder="Indicaciones generales para el paciente..."><?php echo htmlspecialchars($_POST['observaciones_receta'] ?? ''); ?></textarea>
            </div>
            
            <div id="medicamentos-container">
                <!-- Los medicamentos se agregarán aquí dinámicamente -->
            </div>
            
            <div id="sin-medicamentos" class="text-center text-muted py-3">
                <i class="bi bi-info-circle"></i> No se han agregado medicamentos a la receta
            </div>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Guardar Consulta
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </div>
</form>

<script>
let contadorMedicamentos = 0;
const medicamentos = <?php echo json_encode($medicamentos); ?>;

function agregarMedicamento() {
    contadorMedicamentos++;
    const container = document.getElementById('medicamentos-container');
    const sinMedicamentos = document.getElementById('sin-medicamentos');
    
    sinMedicamentos.style.display = 'none';
    
    const div = document.createElement('div');
    div.className = 'medicamento-item border rounded p-3 mb-3 position-relative';
    div.id = 'medicamento-' + contadorMedicamentos;
    
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" 
                onclick="eliminarMedicamento(${contadorMedicamentos})">
            <i class="bi bi-x"></i>
        </button>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Medicamento *</label>
                <select class="form-select" name="medicamento_id[]" required>
                    <option value="">Seleccione...</option>
                    ${medicamentos.map(m => `
                        <option value="${m.MedicamentoID}">
                            ${m.NombreMedicamento} - ${m.Presentacion}
                        </option>
                    `).join('')}
                </select>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Dosis *</label>
                <input type="text" class="form-control" name="dosis[]" 
                       placeholder="Ej: 1 tableta, 5ml, etc." required>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Frecuencia *</label>
                <input type="text" class="form-control" name="frecuencia[]" 
                       placeholder="Ej: Cada 8 horas" required>
            </div>
            
            <div class="col-md-4 mb-3">
                <label class="form-label">Duración *</label>
                <input type="text" class="form-control" name="duracion[]" 
                       placeholder="Ej: 7 días" required>
            </div>
            
            <div class="col-md-4 mb-3">
                <label class="form-label">Indicaciones</label>
                <input type="text" class="form-control" name="indicaciones[]" 
                       placeholder="Ej: Después de comidas">
            </div>
        </div>
    `;
    
    container.appendChild(div);
}

function eliminarMedicamento(id) {
    const elemento = document.getElementById('medicamento-' + id);
    elemento.remove();
    
    const container = document.getElementById('medicamentos-container');
    const sinMedicamentos = document.getElementById('sin-medicamentos');
    
    if(container.children.length === 0) {
        sinMedicamentos.style.display = 'block';
    }
}

// Validar antes de enviar
document.getElementById('formConsulta').addEventListener('submit', function(e) {
    const container = document.getElementById('medicamentos-container');
    const medicamentosAgregados = container.children.length;
    
    if(medicamentosAgregados > 0) {
        // Validar que todos los campos estén completos
        const selects = container.querySelectorAll('select[name="medicamento_id[]"]');
        let hayError = false;
        
        selects.forEach(select => {
            if(!select.value) {
                hayError = true;
            }
        });
        
        if(hayError) {
            e.preventDefault();
            alert('Por favor complete todos los campos de los medicamentos o elimine las filas vacías');
        }
    }
});
</script>

<style>
.medicamento-item {
    background-color: #f8f9fa;
    transition: all 0.3s;
}

.medicamento-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<?php require_once '../includes/footer.php'; ?>