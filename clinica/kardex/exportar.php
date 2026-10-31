<?php
require_once '../includes/farmacia.php';exigirPermiso('kardex.ver');exigirPermiso('kardex.exportar');require_once '../includes/kardex_servicio.php';
$db=farmaciaDb();
try{
    $f=filtrosKardex($_GET);auditar($db,'kardex.exportado',$f['producto']?:null);iniciarConsultaInventario($db);$kardex=prepararKardex($db,$f);$r=$kardex['resumen'];
    $stmt=$db->prepare('SELECT * FROM ('.$kardex['sql'].') movimientos ORDER BY FechaMovimiento,MovimientoID');$stmt->execute($kardex['params']);
}catch(Throwable $error){if($db->inTransaction())$db->rollBack();http_response_code($error instanceof InvalidArgumentException?422:500);header('Content-Type: text/plain; charset=utf-8');echo $error instanceof InvalidArgumentException?$error->getMessage():'No se pudo generar el kardex.';error_log((string)$error);exit;}
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="kardex-producto-'.$f['producto'].'-'.date('Y-m-d').'.csv"');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$archivo=fopen('php://output','w');fwrite($archivo,"\xEF\xBB\xBF");
escribirCsv($archivo,['Código','Producto','Unidad base','Movimiento','Fecha','Lote','Origen','Referencia','Entrada','Salida','Saldo','Usuario','Motivo']);
$base=[$kardex['producto']['Codigo'],$kardex['producto']['NombreMedicamento'],$kardex['producto']['UnidadBase']];
escribirCsv($archivo,[...$base,'',($f['desde']?:'Desde el inicio'),'', 'Saldo inicial','','','',(int)$r['Inicial'],'','Saldo anterior al período seleccionado']);
while($m=$stmt->fetch(PDO::FETCH_ASSOC)){[$origen,$ref]=origenMovimiento($m);escribirCsv($archivo,[...$base,(int)$m['MovimientoID'],$m['FechaMovimiento'],$m['NumeroLote'],$origen,$ref,(int)$m['Entrada'],(int)$m['Salida'],(int)$m['Saldo'],$m['NombreCompleto']??'Sistema',$m['Observaciones']]);}
escribirCsv($archivo,[...$base,'',($f['hasta']?:date('Y-m-d')),'','Saldo final','','','',(int)$r['Final'],'','Entradas del período: '.$r['Entradas'].'; salidas: '.$r['Salidas']]);
if((int)$r['Diferencia']!==0)escribirCsv($archivo,[...$base,'','','','Diferencia actual','',0,0,(int)$r['Diferencia'],'','Existencias físicas actuales: '.$r['FisicoActual'].'; movimientos registrados: '.$r['Historico'].'. Sin ajustes automáticos.']);
$db->commit();fclose($archivo);
