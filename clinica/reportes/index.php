<?php
$titulo = "Reportes y Estadísticas";
require_once '../includes/header.php';

if(!tienePermiso('Reportes')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$mes_actual = date('Y-m');
$mes_filtro = $_GET['mes'] ?? $mes_actual;

try {
    // Estadísticas del mes
    $stats = [];
    
    // Citas por estado
    $stmt = $db->prepare("SELECT e.NombreEstado, e.Color, COUNT(*) as Total
                          FROM Citas c
                          INNER JOIN EstadosCita e ON c.EstadoCitaID = e.EstadoCitaID
                          WHERE DATE_FORMAT(c.FechaCita, '%Y-%m') = ?
                          GROUP BY e.EstadoCitaID, e.NombreEstado, e.Color");
    $stmt->execute([$mes_filtro]);
    $citas_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consultas por médico
    $stmt = $db->prepare("SELECT u.NombreCompleto, COUNT(*) as Total
                          FROM ConsultasMedicas cm
                          INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
                          WHERE DATE_FORMAT(cm.FechaConsulta, '%Y-%m') = ?
                          GROUP BY u.UsuarioID, u.NombreCompleto
                          ORDER BY Total DESC");
    $stmt->execute([$mes_filtro]);
    $consultas_por_medico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nuevos pacientes
    $stmt = $db->prepare("SELECT COUNT(*) as Total FROM Pacientes 
                          WHERE DATE_FORMAT(FechaRegistro, '%Y-%m') = ? AND Activo = 1");
    $stmt->execute([$mes_filtro]);
    $nuevos_pacientes = $stmt->fetch(PDO::FETCH_ASSOC)['Total'];
    
    // Total consultas del mes
    $stmt = $db->prepare("SELECT COUNT(*) as Total FROM ConsultasMedicas 
                          WHERE DATE_FORMAT(FechaConsulta, '%Y-%m') = ?");
    $stmt->execute([$mes_filtro]);
    $total_consultas = $stmt->fetch(PDO::FETCH_ASSOC)['Total'];
    
    // Pacientes más atendidos
    $stmt = $db->prepare("SELECT CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                          p.NumeroExpediente, COUNT(*) as TotalConsultas
                          FROM ConsultasMedicas cm
                          INNER JOIN Pacientes p ON cm.PacienteID = p.PacienteID
                          WHERE DATE_FORMAT(cm.FechaConsulta, '%Y-%m') = ?
                          GROUP BY cm.PacienteID, p.Nombres, p.Apellidos, p.NumeroExpediente
                          ORDER BY TotalConsultas DESC
                          LIMIT 10");
    $stmt->execute([$mes_filtro]);
    $pacientes_frecuentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-bar-chart"></i> Reportes y Estadísticas
</h1>

<!-- Filtro de Mes -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Seleccionar Mes</label>
                <input type="month" class="form-control" name="mes" 
                       value="<?php echo $mes_filtro; ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-2">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Mes Actual
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas Generales -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <i class="bi bi-person-plus" style="font-size: 2rem;"></i>
            <h3><?php echo $nuevos_pacientes; ?></h3>
            <p>Nuevos Pacientes en <?php echo date('F Y', strtotime($mes_filtro . '-01')); ?></p>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="stat-card">
            <i class="bi bi-file-medical" style="font-size: 2rem;"></i>
            <h3><?php echo $total_consultas; ?></h3>
            <p>Consultas Realizadas en <?php echo date('F Y', strtotime($mes_filtro . '-01')); ?></p>
        </div>
    </div>
</div>

<!-- Citas por Estado -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pie-chart"></i> Citas por Estado
            </div>
            <div class="card-body">
                <?php if(count($citas_por_estado) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th class="text-end">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($citas_por_estado as $cita): ?>
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $cita['Color']; ?>">
                                            <?php echo htmlspecialchars($cita['NombreEstado']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><strong><?php echo $cita['Total']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No hay datos para este mes</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Consultas por Médico -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-check"></i> Consultas por Médico
            </div>
            <div class="card-body">
                <?php if(count($consultas_por_medico) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Médico</th>
                                    <th class="text-end">Consultas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($consultas_por_medico as $medico): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($medico['NombreCompleto']); ?></td>
                                    <td class="text-end"><strong><?php echo $medico['Total']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No hay datos para este mes</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Pacientes Más Atendidos -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-people"></i> Pacientes Más Atendidos
    </div>
    <div class="card-body">
        <?php if(count($pacientes_frecuentes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Expediente</th>
                            <th>Paciente</th>
                            <th class="text-end">Consultas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pacientes_frecuentes as $paciente): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($paciente['NumeroExpediente']); ?></strong></td>
                            <td><?php echo htmlspecialchars($paciente['Paciente']); ?></td>
                            <td class="text-end"><strong><?php echo $paciente['TotalConsultas']; ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No hay datos para este mes</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>