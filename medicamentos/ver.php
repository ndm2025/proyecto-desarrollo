<?php
$titulo = "Ver Medicamento";
require_once '../includes/header.php';

if(!tienePermiso('Configuración')) {
    header("Location: /clinica/index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$medicamento_id = $_GET['id'] ?? 0;

try {
    // Obtener datos del medicamento
    $stmt = $db->prepare("SELECT * FROM Medicamentos WHERE MedicamentoID = ?");
    $stmt->execute([$medicamento_id]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$medicamento) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener estadísticas de uso del medicamento
    $stmt = $db->prepare("SELECT COUNT(*) as total_recetas
                          FROM RecetaDetalle
                          WHERE MedicamentoID = ?");
    $stmt->execute([$medicamento_id]);
    $stats_recetas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener lotes del medicamento
    $stmt = $db->prepare("SELECT * FROM LotesMedicamentos 
                          WHERE MedicamentoID = ?
                          ORDER BY FechaVencimiento ASC");
    $stmt->execute([$medicamento_id]);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener últimas recetas donde se usó este medicamento
    $stmt = $db->prepare("SELECT rd.*, r.FechaReceta, 
                          CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                          u.NombreCompleto as Medico
                          FROM RecetaDetalle rd
                          INNER JOIN Recetas r ON rd.RecetaID = r.RecetaID
                          INNER JOIN ConsultasMedicas cm ON r.ConsultaID = cm.ConsultaID
                          INNER JOIN Pacientes p ON cm.PacienteID = p.PacienteID
                          INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
                          WHERE rd.MedicamentoID = ?
                          ORDER BY r.FechaReceta DESC
                          LIMIT 10");
    $stmt->execute([$medicamento_id]);
    $ultimas_recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h1 class="page-title">
    <i class="bi bi-capsule"></i> Detalles del Medicamento
</h1>

<!-- Información Principal -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-info-circle"></i> Información General
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <?php echo $medicamento['MedicamentoID']; ?></p>
                <p><strong>Nombre Comercial:</strong> <?php echo htmlspecialchars($medicamento['NombreMedicamento']); ?></p>
                <p><strong>Principio Activo:</strong> <?php echo htmlspecialchars($medicamento['PrincipioActivo']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Presentación:</strong> <?php echo htmlspecialchars($medicamento['Presentacion']); ?></p>
                <p><strong>Fecha de Registro:</strong> <?php echo date('d/m/Y H:i', strtotime($medicamento['FechaCreacion'])); ?></p>
                <p><strong>Estado:</strong> 
                    <?php if($medicamento['Activo']): ?>
                        <span class="badge bg-success">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactivo</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas de Uso -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-graph-up"></i> Estadísticas de Uso
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-clipboard-check text-primary" style="font-size: 2rem;"></i>
                    <h3 class="mt-2"><?php echo $stats_recetas['total_recetas']; ?></h3>
                    <p class="text-muted">Veces Recetado</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-box-seam text-success" style="font-size: 2rem;"></i>
                    <h3 class="mt-2"><?php echo count($lotes); ?></h3>
                    <p class="text-muted">Lotes Registrados</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-check-circle text-info" style="font-size: 2rem;"></i>
                    <h3 class="mt-2">
                        <?php 
                        $lotes_activos = array_filter($lotes, function($l) { return $l['Activo']; });
                        echo count($lotes_activos); 
                        ?>
                    </h3>
                    <p class="text-muted">Lotes Activos</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lotes del Medicamento -->
<?php if(count($lotes) > 0): ?>
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-box-seam"></i> Lotes del Medicamento
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Número de Lote</th>
                        <th>Fecha Vencimiento</th>
                        <th>Cantidad Inicial</th>
                        <th>Cantidad Actual</th>
                        <th>Estado</th>
                        <th>Fecha Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lotes as $lote): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lote['NumeroLote']); ?></strong></td>
                        <td>
                            <?php 
                            echo date('d/m/Y', strtotime($lote['FechaVencimiento']));
                            $hoy = new DateTime();
                            $vencimiento = new DateTime($lote['FechaVencimiento']);
                            if($vencimiento < $hoy): ?>
                                <span class="badge bg-danger ms-1">Vencido</span>
                            <?php elseif($vencimiento->diff($hoy)->days <= 30): ?>
                                <span class="badge bg-warning ms-1">Por vencer</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $lote['CantidadInicial']; ?></td>
                        <td>
                            <?php echo $lote['CantidadActual']; ?>
                            <?php if($lote['CantidadActual'] == 0): ?>
                                <span class="badge bg-secondary ms-1">Agotado</span>
                            <?php elseif($lote['CantidadActual'] <= 10): ?>
                                <span class="badge bg-warning ms-1">Bajo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($lote['Activo']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($lote['FechaIngreso'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Últimas Recetas -->
<?php if(count($ultimas_recetas) > 0): ?>
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-clipboard-pulse"></i> Últimas 10 Recetas
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Dosis</th>
                        <th>Frecuencia</th>
                        <th>Duración</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ultimas_recetas as $receta): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($receta['FechaReceta'])); ?></td>
                        <td><?php echo htmlspecialchars($receta['Paciente']); ?></td>
                        <td><?php echo htmlspecialchars($receta['Medico']); ?></td>
                        <td><?php echo htmlspecialchars($receta['Dosis']); ?></td>
                        <td><?php echo htmlspecialchars($receta['Frecuencia']); ?></td>
                        <td><?php echo htmlspecialchars($receta['Duracion']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="d-flex gap-2">
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <a href="editar.php?id=<?php echo $medicamento_id; ?>" class="btn btn-warning">
        <i class="bi bi-pencil"></i> Editar
    </a>
</div>

<style>
.stat-card {
    padding: 20px;
    border-radius: 8px;
    background-color: #f8f9fa;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

@media print {
    .btn, .card-header {
        display: none !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>