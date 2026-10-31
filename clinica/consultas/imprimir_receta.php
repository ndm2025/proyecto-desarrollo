<?php
session_start();
require_once __DIR__ . '/../config/database.php';
validarSesion();

$database = new Database();
$db = $database->getConnection();

$consulta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$consulta_id) {
    die("ID de consulta inválido.");
}

try {
    // Consulta y paciente
    $stmt = $db->prepare("SELECT cm.*, 
                                 CONCAT(p.Nombres, ' ', p.Apellidos) as Paciente,
                                 p.NumeroExpediente, p.FechaNacimiento, p.Sexo, p.Telefono,
                                 u.NombreCompleto as Medico, r.NombreRol as CargoMedico
                          FROM ConsultasMedicas cm
                          INNER JOIN Pacientes p ON cm.PacienteID = p.PacienteID
                          INNER JOIN Usuarios u ON cm.MedicoID = u.UsuarioID
                          LEFT JOIN Roles r ON u.RolID = r.RolID
                          WHERE cm.ConsultaID = ?");
    $stmt->execute([$consulta_id]);
    $consulta = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$consulta) {
        die("Consulta médica no encontrada.");
    }

    // Receta
    $stmtReceta = $db->prepare("SELECT * FROM Recetas WHERE ConsultaID = ?");
    $stmtReceta->execute([$consulta_id]);
    $receta = $stmtReceta->fetch(PDO::FETCH_ASSOC);

    $medicamentos = [];
    if($receta) {
        $stmtMeds = $db->prepare("SELECT rd.*, m.NombreMedicamento, m.PrincipioActivo, m.Presentacion 
                                  FROM RecetaDetalle rd
                                  INNER JOIN Medicamentos m ON rd.MedicamentoID = m.MedicamentoID
                                  WHERE rd.RecetaID = ?
                                  ORDER BY rd.RecetaDetalleID ASC");
        $stmtMeds->execute([$receta['RecetaID']]);
        $medicamentos = $stmtMeds->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    die("Error al cargar la receta médica: " . $e->getMessage());
}

// Calcular edad
$edad = '';
if(!empty($consulta['FechaNacimiento'])) {
    $fn = new DateTime($consulta['FechaNacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fn)->y . ' años';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta Médica - <?php echo htmlspecialchars($consulta['Paciente']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .receta-container {
            max-width: 800px;
            margin: 2rem auto;
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        .header-receta {
            border-bottom: 2px solid #4DB8E8;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .symbol-rx {
            font-size: 2.2rem;
            font-weight: 900;
            font-family: "Georgia", serif;
            color: #2A7A9B;
            line-height: 1;
        }
        .firma-box {
            margin-top: 4rem;
            border-top: 1px dashed #94a3b8;
            padding-top: 0.5rem;
            text-align: center;
            width: 260px;
            margin-left: auto;
        }
        @media print {
            body {
                background: #ffffff;
            }
            .receta-container {
                border: none;
                box-shadow: none;
                margin: 0;
                padding: 1.5rem;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>

    <div class="no-print text-center py-3 bg-light border-bottom sticky-top shadow-sm">
        <button onclick="window.print()" class="btn btn-primary px-4 me-2" style="background-color: #4DB8E8; border-color: #4DB8E8;">
            <i class="bi bi-printer-fill me-1"></i> Imprimir Receta
        </button>
        <button onclick="window.close()" class="btn btn-secondary px-3">
            <i class="bi bi-x-lg me-1"></i> Cerrar
        </button>
    </div>

    <div class="receta-container">
        <!-- Encabezado con Membrete -->
        <div class="header-receta d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-0" style="color: #2A7A9B;">CLÍNICA MÉDICA & FARMACIA</h3>
                <div class="text-muted small">Atención Médica Integral · Prescripción Oficial</div>
            </div>
            <div class="text-end">
                <span class="badge fs-6 px-3 py-2" style="background-color: #4DB8E8;">RECETA MÉDICA</span>
                <div class="small text-muted mt-1">Folio Consulta: #<?php echo $consulta['ConsultaID']; ?></div>
            </div>
        </div>

        <!-- Información del Paciente -->
        <div class="row g-2 mb-4 bg-light p-3 rounded border">
            <div class="col-md-7">
                <div class="small text-muted">PACIENTE:</div>
                <div class="fw-bold fs-6 text-dark"><?php echo htmlspecialchars($consulta['Paciente']); ?></div>
            </div>
            <div class="col-md-5">
                <div class="small text-muted">NO. EXPEDIENTE:</div>
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($consulta['NumeroExpediente']); ?></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">EDAD:</div>
                <div><?php echo $edad ?: 'No especificada'; ?></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">GÉNERO:</div>
                <div><?php echo $consulta['Sexo'] === 'M' ? 'Masculino' : 'Femenino'; ?></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">FECHA DE EMISIÓN:</div>
                <div class="fw-bold"><?php echo date('d/m/Y H:i', strtotime($consulta['FechaConsulta'])); ?></div>
            </div>
            <?php if(!empty($consulta['Diagnostico'])): ?>
                <div class="col-12 mt-2 pt-2 border-top">
                    <span class="small text-muted">DIAGNÓSTICO: </span>
                    <strong class="text-dark"><?php echo htmlspecialchars($consulta['Diagnostico']); ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <!-- Símbolo Rx y Lista de Medicamentos -->
        <div class="d-flex align-items-center mb-3">
            <span class="symbol-rx me-2">℞</span>
            <span class="fw-bold text-secondary text-uppercase small">Medicamentos Prescritos</span>
        </div>

        <?php if(count($medicamentos) > 0): ?>
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;" class="text-center">#</th>
                            <th style="width: 35%;">Medicamento / Presentación</th>
                            <th style="width: 15%;">Dosis</th>
                            <th style="width: 20%;">Frecuencia</th>
                            <th style="width: 25%;">Duración & Indicaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($medicamentos as $index => $m): ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo ($index + 1); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['NombreMedicamento']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['PrincipioActivo'] . ' - ' . $m['Presentacion']); ?></small>
                                </td>
                                <td class="fw-semibold text-primary"><?php echo htmlspecialchars($m['Dosis']); ?></td>
                                <td><?php echo htmlspecialchars($m['Frecuencia']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($m['Duracion']); ?></strong>
                                    <?php if(!empty($m['Indicaciones'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($m['Indicaciones']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light border text-center py-4 mb-4">
                No se registraron medicamentos en esta consulta.
            </div>
        <?php endif; ?>

        <!-- Indicaciones Generales -->
        <?php if(!empty($receta['Observaciones']) || !empty($consulta['Tratamiento'])): ?>
            <div class="p-3 bg-light rounded border mb-4">
                <h6 class="fw-bold text-secondary small text-uppercase mb-2">Indicaciones y Cuidados Generales</h6>
                <?php if(!empty($consulta['Tratamiento'])): ?>
                    <p class="mb-1 text-dark small"><?php echo nl2br(htmlspecialchars($consulta['Tratamiento'])); ?></p>
                <?php endif; ?>
                <?php if(!empty($receta['Observaciones'])): ?>
                    <p class="mb-0 text-dark small"><strong>Nota adicional:</strong> <?php echo nl2br(htmlspecialchars($receta['Observaciones'])); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pie de Firma Médica -->
        <div class="row align-items-end mt-5">
            <div class="col-6">
                <div class="small text-muted">
                    <i class="bi bi-shield-check text-success me-1"></i> Válida para despacho en Farmacia
                </div>
            </div>
            <div class="col-6">
                <div class="firma-box">
                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($consulta['Medico']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($consulta['CargoMedico'] ?? 'Médico Tratante'); ?></div>
                    <div class="small text-muted" style="font-size: 11px;">Firma y Sello Profesional</div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
