<?php
require_once '../includes/farmacia.php';exigirPermiso('inventario.ver');exigirPermiso('inventario.exportar');require_once '../includes/inventario_servicio.php';
$db=farmaciaDb();
try{
    $tipo=opcionFiltro($_GET['tipo']??'productos',['productos','lotes']);$costos=tienePermiso('medicamentos.costos');$f=filtrosInventario($_GET,$tipo==='lotes');
    [$sql,$params]=$tipo==='lotes'?consultaLotes($f,$costos):consultaInventario($f,$costos);
    auditar($db,'inventario.exportado');iniciarConsultaInventario($db);
    $stmt=$db->prepare($sql.' ORDER BY '.($tipo==='lotes'?'FechaVencimiento,LoteID':'NombreMedicamento,MedicamentoID'));$stmt->execute($params);
}catch(Throwable $error){if($db->inTransaction())$db->rollBack();http_response_code($error instanceof InvalidArgumentException?422:500);header('Content-Type: text/plain; charset=utf-8');echo $error instanceof InvalidArgumentException?$error->getMessage():'No se pudo generar la exportación.';error_log((string)$error);exit;}
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="inventario-'.$tipo.'-'.date('Y-m-d').'.csv"');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$archivo=fopen('php://output','w');fwrite($archivo,"\xEF\xBB\xBF");
if($tipo==='productos'){
    $cabecera=['Código','Producto','Unidad base','Estado producto','Unidades físicas','Disponibles','Bloqueadas vigentes','Vencidas','Stock mínimo'];if($costos)$cabecera[]='Último costo unitario referencial Q';escribirCsv($archivo,$cabecera);
    while($m=$stmt->fetch(PDO::FETCH_ASSOC)){$fila=[$m['Codigo'],$m['NombreMedicamento'],$m['UnidadBase'],$m['Activo']?'Activo':'Inactivo',(int)$m['Fisicas'],(int)$m['Disponibles'],(int)$m['Bloqueadas'],(int)$m['Vencidas'],(int)$m['StockMinimo']];if($costos)$fila[]=$m['CostoReferencia']??'';escribirCsv($archivo,$fila);}
}else{
    $cabecera=['Código','Producto','Lote','Unidad base','Vencimiento','Días para vencer','Estado lote','Estado producto','Disponibilidad','Unidades físicas','Disponibles'];if($costos)$cabecera[]='Último costo unitario referencial Q';escribirCsv($archivo,$cabecera);
    while($l=$stmt->fetch(PDO::FETCH_ASSOC)){$fila=[$l['Codigo'],$l['NombreMedicamento'],$l['NumeroLote'],$l['UnidadBase'],$l['FechaVencimiento'],(int)$l['DiasVencimiento'],$l['Activo']?'Activo':'Inactivo',$l['ProductoActivo']?'Activo':'Inactivo',estadoDisponibilidad($l),(int)$l['CantidadActual'],(int)$l['Disponibles']];if($costos)$fila[]=$l['CostoReferencia']??'';escribirCsv($archivo,$fila);}
}
$db->commit();fclose($archivo);
