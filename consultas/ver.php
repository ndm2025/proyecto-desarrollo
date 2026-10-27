<?php
$titulo = "Ver Consulta";
require_once '../includes/header.php';

if(!tienePermiso('Expedientes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$consulta_id = $_GET['id'] ?? 0;

try {
    // Obtener datos de la consulta
    $stmt = $db->prepare("SELECT cm.*, 
                          CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                          p.NumeroExpediente, p.FechaNacimiento, p.Sexo,
                          u.NombreCompleto as Medico
                          FROM ConsultasMedicas cm
                          INNER JOIN Pacientes p ON cm.PacienteID = p.PacienteID
                          INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
                          WHERE cm.ConsultaID = ?");
    $stmt->execute([$consulta_id]);
    $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$consulta) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener información de la receta (si existe)
    $stmt = $db->prepare("SELECT r.RecetaID, r.FechaReceta, r.Observaciones
                          FROM Recetas r
                          WHERE r.ConsultaID = ?");
    $stmt->execute([$consulta_id]);
    $receta_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener medicamentos de la receta
    $medicamentos_receta = [];
    if($receta_info) {
        $stmt = $db->prepare("SELECT rd.*, m.NombreMedicamento, m.PrincipioActivo, m.Presentacion
                              FROM RecetaDetalle rd
                              INNER JOIN Medicamentos m ON rd.MedicamentoID = m.MedicamentoID
                              WHERE rd.RecetaID = ?
                              ORDER BY rd.RecetaDetalleID");
        $stmt->execute([$receta_info['RecetaID']]);
        $medicamentos_receta = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Calcular edad
$fecha_nac = new DateTime($consulta['FechaNacimiento']);
$hoy = new DateTime();
$edad = $hoy->diff($fecha_nac)->y;
?>

<h1 class="page-title">
    <i class="bi bi-file-medical-fill"></i> Detalles de Consulta
</h1>

<!-- Información del Paciente -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-person"></i> Información del Paciente
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Expediente:</strong> <?php echo htmlspecialchars($consulta['NumeroExpediente']); ?></p>
                <p><strong>Paciente:</strong> <?php echo htmlspecialchars($consulta['Paciente']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Edad:</strong> <?php echo $edad; ?> años</p>
                <p><strong>Sexo:</strong> <?php echo $consulta['Sexo'] == 'M' ? 'Masculino' : 'Femenino'; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Datos de la Consulta -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-clipboard-pulse"></i> Datos de la Consulta
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>Fecha de Consulta:</strong> <?php echo date('d/m/Y H:i', strtotime($consulta['FechaConsulta'])); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Médico:</strong> <?php echo htmlspecialchars($consulta['Medico']); ?></p>
            </div>
        </div>
        
        <?php if($consulta['MotivoConsulta']): ?>
        <hr>
        <h6><strong>Motivo de Consulta:</strong></h6>
        <p><?php echo nl2br(htmlspecialchars($consulta['MotivoConsulta'])); ?></p>
        <?php endif; ?>
        
        <?php if($consulta['SignosVitales']): ?>
        <hr>
        <h6><strong>Signos Vitales:</strong></h6>
        <p><?php echo nl2br(htmlspecialchars($consulta['SignosVitales'])); ?></p>
        <?php endif; ?>
        
        <hr>
        <h6><strong>Diagnóstico:</strong></h6>
        <p><?php echo nl2br(htmlspecialchars($consulta['Diagnostico'])); ?></p>
        
        <?php if($consulta['Tratamiento']): ?>
        <hr>
        <h6><strong>Tratamiento:</strong></h6>
        <p><?php echo nl2br(htmlspecialchars($consulta['Tratamiento'])); ?></p>
        <?php endif; ?>
        
        <?php if($consulta['Observaciones']): ?>
        <hr>
        <h6><strong>Observaciones:</strong></h6>
        <p><?php echo nl2br(htmlspecialchars($consulta['Observaciones'])); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Receta Médica -->
<?php if($receta_info && count($medicamentos_receta) > 0): ?>
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <i class="bi bi-capsule"></i> Receta Médica
        <span class="badge bg-light text-dark float-end">
            Fecha: <?php echo date('d/m/Y H:i', strtotime($receta_info['FechaReceta'])); ?>
        </span>
    </div>
    <div class="card-body">
        <?php if($receta_info['Observaciones']): ?>
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle"></i> Indicaciones Generales:</strong><br>
                <?php echo nl2br(htmlspecialchars($receta_info['Observaciones'])); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="30%">Medicamento</th>
                        <th width="15%">Dosis</th>
                        <th width="15%">Frecuencia</th>
                        <th width="10%">Duración</th>
                        <th width="25%">Indicaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($medicamentos_receta as $index => $med): ?>
                    <tr>
                        <td class="text-center"><strong><?php echo ($index + 1); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($med['NombreMedicamento']); ?></strong>
                            <br><small class="text-muted">
                                <?php echo htmlspecialchars($med['PrincipioActivo']); ?> - 
                                <?php echo htmlspecialchars($med['Presentacion']); ?>
                            </small>
                        </td>
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($med['Dosis']); ?></span></td>
                        <td><span class="badge bg-success"><?php echo htmlspecialchars($med['Frecuencia']); ?></span></td>
                        <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($med['Duracion']); ?></span></td>
                        <td><?php echo htmlspecialchars($med['Indicaciones']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-warning mt-3 mb-0">
            <i class="bi bi-exclamation-triangle"></i> 
            <strong>Importante:</strong> Siga estrictamente las indicaciones médicas. No suspenda el tratamiento sin consultar a su médico.
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-capsule"></i> Receta Médica
    </div>
    <div class="card-body text-center text-muted">
        <i class="bi bi-info-circle"></i> No se prescribieron medicamentos en esta consulta
    </div>
</div>
<?php endif; ?>

<!-- Botones de Acción -->
<div class="d-flex gap-2 mb-4">
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <a href="/clinica/pacientes/ver.php?id=<?php echo $consulta['PacienteID']; ?>" class="btn btn-info">
        <i class="bi bi-person"></i> Ver Expediente Completo
    </a>

    <!--<?php if($receta_info && count($medicamentos_receta) > 0): ?>
    <button onclick="imprimirReceta()" class="btn btn-success">
        <i class="bi bi-printer-fill"></i> Imprimir Receta
    </button>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer"></i> Imprimir Todo
    </button>-->
</div>

<script>
function imprimirReceta() {
    // Ocultar elementos no deseados
    const elementos = document.querySelectorAll('.btn, .page-title');
    elementos.forEach(el => el.style.display = 'none');
    
    // Ocultar tarjetas que no sean la receta
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        if(index < 2) { // Ocultar las dos primeras tarjetas (paciente y consulta)
            card.style.display = 'none';
        }
    });
    
    window.print();
    
    // Restaurar elementos
    elementos.forEach(el => el.style.display = '');
    cards.forEach(card => card.style.display = '');
}
</script>

<style>
@media print {
    .btn, .page-title {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    @page {
        margin: 2cm;
    }
}

.table-bordered {
    border: 2px solid #dee2e6;
}

.table-bordered td, .table-bordered th {
    border: 1px solid #dee2e6;
}
</style>

<?php require_once '../includes/footer.php'; ?>