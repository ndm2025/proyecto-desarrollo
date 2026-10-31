<?php
ob_start();
$titulo = "Nueva Atención Médica";
require_once '../includes/header.php';

if(!tienePermiso('Atencion') && !tienePermiso('Expedientes') && !tienePermiso('Citas')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errores = [];
$cita_id = isset($_GET['cita']) ? (int)$_GET['cita'] : null;
$paciente_id_param = isset($_GET['paciente']) ? (int)$_GET['paciente'] : null;

$cita_info = null;
$paciente_seleccionado = null;
$consultas_previas = [];

try {
    // 1. Pacientes activos
    $stmt = $db->query("SELECT PacienteID, NumeroExpediente, FechaNacimiento, Sexo, Telefono,
                        CONCAT(Nombres, ' ', Apellidos) as NombreCompleto
                        FROM Pacientes WHERE Activo = 1 ORDER BY Apellidos, Nombres");
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Medicamentos activos
    $stmt = $db->query("SELECT MedicamentoID, NombreMedicamento, PrincipioActivo, Presentacion
                        FROM Medicamentos WHERE Activo = 1 ORDER BY NombreMedicamento");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Cita si existe
    if($cita_id) {
        $stmt = $db->prepare("SELECT c.*, 
                                     CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                                     p.NumeroExpediente, p.FechaNacimiento, p.Sexo, p.Telefono
                              FROM Citas c
                              INNER JOIN Pacientes p ON c.PacienteID = p.PacienteID
                              WHERE c.CitaID = ?");
        $stmt->execute([$cita_id]);
        $cita_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if($cita_info) {
            $paciente_id_param = (int)$cita_info['PacienteID'];
        }
    }

    // 4. Paciente seleccionado
    if($paciente_id_param) {
        $stmt = $db->prepare("SELECT * FROM Pacientes WHERE PacienteID = ?");
        $stmt->execute([$paciente_id_param]);
        $paciente_seleccionado = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtPrev = $db->prepare("SELECT cm.ConsultaID, cm.FechaConsulta, cm.MotivoConsulta, cm.Diagnostico, cm.Tratamiento,
                                         u.NombreCompleto as Medico
                                  FROM ConsultasMedicas cm
                                  INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
                                  WHERE cm.PacienteID = ?
                                  ORDER BY cm.FechaConsulta DESC LIMIT 5");
        $stmtPrev->execute([$paciente_id_param]);
        $consultas_previas = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    $errores[] = "Error al cargar datos: " . $e->getMessage();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paciente_id = (int)($_POST['paciente_id'] ?? 0);
    $cita_id_post = !empty($_POST['cita_id']) ? (int)$_POST['cita_id'] : null;
    $motivo = trim($_POST['motivo'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $tratamiento = trim($_POST['tratamiento'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $observaciones_receta = trim($_POST['observaciones_receta'] ?? '');

    // Signos vitales
    $pa_sistolica = trim($_POST['pa_sistolica'] ?? '');
    $pa_diastolica = trim($_POST['pa_diastolica'] ?? '');
    $fc = trim($_POST['fc'] ?? '');
    $fr = trim($_POST['fr'] ?? '');
    $temp = trim($_POST['temp'] ?? '');
    $spo2 = trim($_POST['spo2'] ?? '');
    $peso = trim($_POST['peso'] ?? '');
    $talla = trim($_POST['talla'] ?? '');
    $imc = trim($_POST['imc'] ?? '');
    $otros_signos = trim($_POST['otros_signos'] ?? '');

    $lineasSignos = [];
    if($pa_sistolica && $pa_diastolica) $lineasSignos[] = "P/A: {$pa_sistolica}/{$pa_diastolica} mmHg";
    if($fc) $lineasSignos[] = "FC: {$fc} lpm";
    if($temp) $lineasSignos[] = "Temp: {$temp} °C";
    if($fr) $lineasSignos[] = "FR: {$fr} rpm";
    if($spo2) $lineasSignos[] = "SpO2: {$spo2}%";
    if($peso) $lineasSignos[] = "Peso: {$peso} kg";
    if($talla) $lineasSignos[] = "Talla: {$talla} cm";
    if($imc) $lineasSignos[] = "IMC: {$imc}";
    if($otros_signos) $lineasSignos[] = "Otros: {$otros_signos}";
    $signos_vitales_texto = implode(" | ", $lineasSignos);

    // Medicamentos
    $medicamentos_ids = $_POST['medicamento_id'] ?? [];
    $dosis = $_POST['dosis'] ?? [];
    $frecuencias = $_POST['frecuencia'] ?? [];
    $duraciones = $_POST['duracion'] ?? [];
    $indicaciones = $_POST['indicaciones'] ?? [];

    if(empty($paciente_id)) $errores[] = "Debe seleccionar un paciente";
    if(empty($diagnostico)) $errores[] = "El diagnóstico es requerido";

    $medicamentos_validos = [];
    if(is_array($medicamentos_ids)) {
        foreach($medicamentos_ids as $idx => $mId) {
            if(!empty($mId)) {
                if(empty($dosis[$idx]) || empty($frecuencias[$idx]) || empty($duraciones[$idx])) {
                    $errores[] = "Debe completar dosis, frecuencia y duración para todos los medicamentos";
                    break;
                }
                $medicamentos_validos[] = [
                    'id' => (int)$mId,
                    'dosis' => trim($dosis[$idx]),
                    'frecuencia' => trim($frecuencias[$idx]),
                    'duracion' => trim($duraciones[$idx]),
                    'indicaciones' => trim($indicaciones[$idx] ?? '')
                ];
            }
        }
    }

    if(count($errores) === 0) {
        try {
            $db->beginTransaction();

            $medicoId = (int)$_SESSION['usuario_id'];

            // 1. Insertar Consulta
            $sql = "INSERT INTO ConsultasMedicas 
                    (PacienteID, CitaID, FechaConsulta, MotivoConsulta, SignosVitales, 
                     Diagnostico, Tratamiento, Observaciones, MedicoID)
                    VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $paciente_id, $cita_id_post, $motivo, $signos_vitales_texto,
                $diagnostico, $tratamiento, $observaciones, $medicoId
            ]);
            $consulta_id = $db->lastInsertId();

            // 2. Insertar Receta
            if(count($medicamentos_validos) > 0) {
                $sqlReceta = "INSERT INTO Recetas (ConsultaID, FechaReceta, Observaciones) VALUES (?, NOW(), ?)";
                $stmtReceta = $db->prepare($sqlReceta);
                $stmtReceta->execute([$consulta_id, $observaciones_receta]);
                $receta_id = $db->lastInsertId();

                $sqlDetalle = "INSERT INTO RecetaDetalle 
                               (RecetaID, MedicamentoID, Dosis, Frecuencia, Duracion, Indicaciones)
                               VALUES (?, ?, ?, ?, ?, ?)";
                $stmtDetalle = $db->prepare($sqlDetalle);

                foreach($medicamentos_validos as $item) {
                    $stmtDetalle->execute([
                        $receta_id,
                        $item['id'],
                        $item['dosis'],
                        $item['frecuencia'],
                        $item['duracion'],
                        $item['indicaciones']
                    ]);
                }
            }

            // 3. Completar Cita
            if($cita_id_post) {
                $stmtCita = $db->prepare("UPDATE Citas SET EstadoCitaID = 4 WHERE CitaID = ?");
                $stmtCita->execute([$cita_id_post]);
            }

            $db->commit();
            header("Location: /clinica/consultas/ver.php?id=" . $consulta_id . "&atencion_exitosa=1");
            exit();

        } catch(Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Calcular edad
$edadPaciente = '';
if($paciente_seleccionado && !empty($paciente_seleccionado['FechaNacimiento'])) {
    $fn = new DateTime($paciente_seleccionado['FechaNacimiento']);
    $hoy = new DateTime();
    $edadPaciente = $hoy->diff($fn)->y . ' años';
}
?>

<h1 class="page-title">
    <i class="bi bi-heart-pulse"></i> Nueva Atención Médica
</h1>

<?php if(count($errores) > 0): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach($errores as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($cita_info): ?>
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle"></i> 
        Atendiendo cita de: <strong><?php echo htmlspecialchars($cita_info['Paciente']); ?></strong>
        - Fecha: <?php echo date('d/m/Y H:i', strtotime($cita_info['FechaCita'])); ?>
    </div>
<?php endif; ?>

<form method="POST" action="" id="formAtencion">
    <input type="hidden" name="cita_id" value="<?php echo $cita_id ?? ''; ?>">

    <!-- 1. Paciente -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-person"></i> Información del Paciente
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Paciente *</label>
                    <select class="form-select" name="paciente_id" required 
                            <?php echo $cita_info ? 'disabled' : ''; ?>
                            onchange="cargarPaciente(this.value)">
                        <option value="">Seleccione un paciente...</option>
                        <?php foreach($pacientes as $pac): ?>
                            <option value="<?php echo $pac['PacienteID']; ?>"
                                    <?php echo ($paciente_id_param == $pac['PacienteID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pac['NumeroExpediente'] . ' - ' . $pac['NombreCompleto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($cita_info): ?>
                        <input type="hidden" name="paciente_id" value="<?php echo $cita_info['PacienteID']; ?>">
                    <?php endif; ?>
                </div>

                <?php if($paciente_seleccionado): ?>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Expediente:</small>
                        <strong><?php echo htmlspecialchars($paciente_seleccionado['NumeroExpediente']); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Edad / Sexo:</small>
                        <strong><?php echo $edadPaciente ?: 'No indicada'; ?> / <?php echo $paciente_seleccionado['Sexo'] == 'M' ? 'Masculino' : 'Femenino'; ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Teléfono:</small>
                        <strong><?php echo htmlspecialchars($paciente_seleccionado['Telefono'] ?? 'No registrado'); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. Signos Vitales y Triaje -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-activity"></i> Signos Vitales y Triaje
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Presión Arterial</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="pa_sistolica" placeholder="120" value="<?php echo htmlspecialchars($_POST['pa_sistolica'] ?? ''); ?>">
                        <span class="input-group-text">/</span>
                        <input type="number" class="form-control" name="pa_diastolica" placeholder="80" value="<?php echo htmlspecialchars($_POST['pa_diastolica'] ?? ''); ?>">
                        <span class="input-group-text">mmHg</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Frecuencia Cardíaca</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="fc" placeholder="75" value="<?php echo htmlspecialchars($_POST['fc'] ?? ''); ?>">
                        <span class="input-group-text">lpm</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Temperatura</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="temp" placeholder="36.5" value="<?php echo htmlspecialchars($_POST['temp'] ?? ''); ?>">
                        <span class="input-group-text">°C</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Saturación SpO2</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="spo2" placeholder="98" min="50" max="100" value="<?php echo htmlspecialchars($_POST['spo2'] ?? ''); ?>">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Frecuencia Respiratoria</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="fr" placeholder="18" value="<?php echo htmlspecialchars($_POST['fr'] ?? ''); ?>">
                        <span class="input-group-text">rpm</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Peso</label>
                    <div class="input-group">
                        <input type="number" step="0.1" class="form-control" name="peso" id="inputPeso" placeholder="70" value="<?php echo htmlspecialchars($_POST['peso'] ?? ''); ?>" oninput="calcularIMC()">
                        <span class="input-group-text">kg</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Talla / Estatura</label>
                    <div class="input-group">
                        <input type="number" step="0.5" class="form-control" name="talla" id="inputTalla" placeholder="170" value="<?php echo htmlspecialchars($_POST['talla'] ?? ''); ?>" oninput="calcularIMC()">
                        <span class="input-group-text">cm</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">IMC</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="imc" id="inputIMC" readonly placeholder="Calculado" value="<?php echo htmlspecialchars($_POST['imc'] ?? ''); ?>">
                        <span class="input-group-text" id="badgeIMC">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Evaluación Médica -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-clipboard-pulse"></i> Evaluación y Diagnóstico
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Motivo de Consulta</label>
                <textarea class="form-control" name="motivo" rows="2"><?php echo htmlspecialchars($cita_info['MotivoConsulta'] ?? $_POST['motivo'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Diagnóstico *</label>
                <textarea class="form-control" name="diagnostico" rows="3" required placeholder="Escriba el diagnóstico..."><?php echo htmlspecialchars($_POST['diagnostico'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Tratamiento e Indicaciones</label>
                <textarea class="form-control" name="tratamiento" rows="3"><?php echo htmlspecialchars($_POST['tratamiento'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="2"><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <!-- 4. Receta Médica -->
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
                <input type="text" class="form-control" name="observaciones_receta" 
                       placeholder="Indicaciones generales..." 
                       value="<?php echo htmlspecialchars($_POST['observaciones_receta'] ?? ''); ?>">
            </div>

            <div id="medicamentos-container">
                <!-- Se agregan dinámicamente -->
            </div>

            <div id="sin-medicamentos" class="text-center text-muted py-3">
                <i class="bi bi-info-circle"></i> No se han agregado medicamentos a la receta
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Guardar Atención
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </div>
</form>

<script>
const medicamentos = <?php echo json_encode($medicamentos); ?>;
let contadorMedicamentos = 0;

function cargarPaciente(pacienteId) {
    if(pacienteId) {
        window.location.href = 'atender.php?paciente=' + pacienteId;
    }
}

function calcularIMC() {
    const peso = parseFloat(document.getElementById('inputPeso').value);
    const tallaCm = parseFloat(document.getElementById('inputTalla').value);
    const inputIMC = document.getElementById('inputIMC');
    const badgeIMC = document.getElementById('badgeIMC');

    if (peso > 0 && tallaCm > 0) {
        const tallaM = tallaCm / 100;
        const imc = (peso / (tallaM * tallaM)).toFixed(1);
        inputIMC.value = imc;

        if (imc < 18.5) {
            badgeIMC.textContent = 'Bajo peso';
        } else if (imc <= 24.9) {
            badgeIMC.textContent = 'Normal';
        } else if (imc <= 29.9) {
            badgeIMC.textContent = 'Sobrepeso';
        } else {
            badgeIMC.textContent = 'Obesidad';
        }
    } else {
        inputIMC.value = '';
        badgeIMC.textContent = '-';
    }
}

function agregarMedicamento() {
    contadorMedicamentos++;
    const container = document.getElementById('medicamentos-container');
    const sinMedicamentos = document.getElementById('sin-medicamentos');
    sinMedicamentos.style.display = 'none';

    const div = document.createElement('div');
    div.className = 'border rounded p-3 mb-3 position-relative bg-light';
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
                <input type="text" class="form-control" name="dosis[]" placeholder="Ej: 1 tableta, 5ml, etc." required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Frecuencia *</label>
                <input type="text" class="form-control" name="frecuencia[]" placeholder="Ej: Cada 8 horas" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Duración *</label>
                <input type="text" class="form-control" name="duracion[]" placeholder="Ej: 7 días" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Indicaciones</label>
                <input type="text" class="form-control" name="indicaciones[]" placeholder="Ej: Después de comidas">
            </div>
        </div>
    `;

    container.appendChild(div);
}

function eliminarMedicamento(id) {
    const el = document.getElementById('medicamento-' + id);
    if(el) el.remove();
    const container = document.getElementById('medicamentos-container');
    const sinMedicamentos = document.getElementById('sin-medicamentos');
    if(container.children.length === 0) {
        sinMedicamentos.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    calcularIMC();
});
</script>

<?php require_once '../includes/footer.php'; ?>
