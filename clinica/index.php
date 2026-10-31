<?php
$titulo = "Dashboard";
require_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas
$stats = [
    'pacientes' => 0,
    'citas_hoy' => 0,
    'citas_pendientes' => 0,
    'consultas_mes' => 0
];

try {
    // Total pacientes activos
    $stmt = $db->query("SELECT COUNT(*) as total FROM Pacientes WHERE Activo = 1");
    $stats['pacientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Citas de hoy
    $stmt = $db->query("SELECT COUNT(*) as total FROM Citas 
                        WHERE DATE(FechaCita) = CURDATE()");
    $stats['citas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Citas pendientes
    $stmt = $db->query("SELECT COUNT(*) as total FROM Citas 
                        WHERE EstadoCitaID = 1 AND FechaCita >= NOW()");
    $stats['citas_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Consultas este mes
    $stmt = $db->query("SELECT COUNT(*) as total FROM ConsultasMedicas 
                        WHERE MONTH(FechaConsulta) = MONTH(CURDATE()) 
                        AND YEAR(FechaConsulta) = YEAR(CURDATE())");
    $stats['consultas_mes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Próximas citas
    $stmt = $db->query("SELECT c.CitaID, c.FechaCita, c.MotivoConsulta,
                        CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                        e.NombreEstado, e.Color
                        FROM Citas c
                        INNER JOIN Pacientes p ON c.PacienteID = p.PacienteID
                        INNER JOIN EstadosCita e ON c.EstadoCitaID = e.EstadoCitaID
                        WHERE c.FechaCita >= NOW()
                        ORDER BY c.FechaCita ASC
                        LIMIT 10");
    $proximas_citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<h1 class="page-title">
    <i class="bi bi-speedometer2"></i> Dashboard
</h1>

<!-- Tarjetas de Estadísticas -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-people" style="font-size: 2rem;"></i>
            <h3><?php echo $stats['pacientes']; ?></h3>
            <p>Pacientes Registrados</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-calendar-day" style="font-size: 2rem;"></i>
            <h3><?php echo $stats['citas_hoy']; ?></h3>
            <p>Citas</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-clock-history" style="font-size: 2rem;"></i>
            <h3><?php echo $stats['citas_pendientes']; ?></h3>
            <p>Citas Pendientes</p>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <i class="bi bi-file-medical" style="font-size: 2rem;"></i>
            <h3><?php echo $stats['consultas_mes']; ?></h3>
            <p>Consultas Este Mes</p>
        </div>
    </div>
</div>

<!-- Próximas Citas -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar-check"></i> Próximas Citas
            </div>
            <div class="card-body">
                <?php if(count($proximas_citas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Paciente</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($proximas_citas as $cita): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($cita['FechaCita'])); ?></td>
                                    <td><?php echo htmlspecialchars($cita['Paciente']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($cita['MotivoConsulta'], 0, 50)); ?>...</td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $cita['Color']; ?>">
                                            <?php echo $cita['NombreEstado']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/clinica/citas/editar.php?id=<?php echo $cita['CitaID']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No hay citas programadas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>