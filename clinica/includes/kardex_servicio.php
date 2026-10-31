<?php
require_once __DIR__.'/inventario_servicio.php';
function filtrosKardex($datos) {
    $f=['producto'=>entero($datos['producto']??0,0,2147483647),'lote'=>entero($datos['lote']??0,0,2147483647),'desde'=>textoFiltro($datos['desde']??date('Y-m-01'),10),'hasta'=>textoFiltro($datos['hasta']??date('Y-m-d'),10),'pagina'=>entero($datos['pagina']??1,1,2147483647)];
    foreach(['desde','hasta'] as $campo)if($f[$campo]!==''&&(!fechaValida($f[$campo])||$f[$campo]<'1000-01-01'||$f[$campo]>'9998-12-31'))throw new InvalidArgumentException('Selecciona fechas válidas.');
    $inicio=$f['desde']?:'1000-01-01';$fin=$f['hasta']?:date('Y-m-d');
    if($inicio>$fin)throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
    return $f;
}
function ambitoKardex($f) {
    $sql='l.MedicamentoID=?';$params=[$f['producto']];
    if($f['lote']){$sql.=' AND l.LoteID=?';$params[]=$f['lote'];}
    return [$sql,$params];
}
function prepararKardex($db,$f) {
    $stmt=$db->prepare('SELECT MedicamentoID,Codigo,NombreMedicamento,UnidadBase FROM Medicamentos WHERE MedicamentoID=?');$stmt->execute([$f['producto']]);$producto=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$producto)throw new InvalidArgumentException('Selecciona un producto existente.');
    if($f['lote']){$stmt=$db->prepare('SELECT LoteID FROM LotesMedicamentos WHERE LoteID=? AND MedicamentoID=?');$stmt->execute([$f['lote'],$f['producto']]);if(!$stmt->fetchColumn())throw new InvalidArgumentException('El lote no pertenece al producto seleccionado.');}
    [$ambito,$params]=ambitoKardex($f);
    $desde=($f['desde']?:'1000-01-01').' 00:00:00';$hasta=(new DateTimeImmutable($f['hasta']?:date('Y-m-d')))->modify('+1 day')->format('Y-m-d').' 00:00:00';
    $delta="CASE WHEN m.TipoMovimiento='E' THEN m.Cantidad ELSE -m.Cantidad END";
    $stmt=$db->prepare("SELECT COALESCE(SUM(CASE WHEN m.FechaMovimiento<? THEN $delta ELSE 0 END),0) AS Inicial,
    COALESCE(SUM(CASE WHEN m.FechaMovimiento>=? AND m.FechaMovimiento<? AND m.TipoMovimiento='E' THEN m.Cantidad ELSE 0 END),0) AS Entradas,
    COALESCE(SUM(CASE WHEN m.FechaMovimiento>=? AND m.FechaMovimiento<? AND m.TipoMovimiento IN ('S','A') THEN m.Cantidad ELSE 0 END),0) AS Salidas,
    COALESCE(SUM($delta),0) AS Historico FROM MovimientosLote m JOIN LotesMedicamentos l ON l.LoteID=m.LoteID WHERE $ambito");
    $stmt->execute([$desde,$desde,$hasta,$desde,$hasta,...$params]);$resumen=$stmt->fetch(PDO::FETCH_ASSOC);
    $stmt=$db->prepare('SELECT COALESCE(SUM(l.CantidadActual),0) FROM LotesMedicamentos l WHERE '.$ambito);$stmt->execute($params);$resumen['FisicoActual']=(int)$stmt->fetchColumn();
    $resumen['Diferencia']=$resumen['FisicoActual']-(int)$resumen['Historico'];$resumen['Final']=(int)$resumen['Inicial']+(int)$resumen['Entradas']-(int)$resumen['Salidas'];
    $sql="SELECT m.MovimientoID,m.FechaMovimiento,m.TipoMovimiento,m.Cantidad,m.Observaciones,m.LoteID,m.AnulacionCompraID,m.CorreccionID,vd.VentaID,rv.DevolucionID,rv.Tipo AS TipoRetorno,rv.VentaID AS VentaRetornoID,d.CompraID,l.NumeroLote,u.NombreCompleto,
    CASE WHEN m.TipoMovimiento='E' THEN m.Cantidad ELSE 0 END AS Entrada,
    CASE WHEN m.TipoMovimiento IN ('S','A') THEN m.Cantidad ELSE 0 END AS Salida,
    ".(int)$resumen['Inicial']." + SUM($delta) OVER(ORDER BY m.FechaMovimiento,m.MovimientoID ROWS UNBOUNDED PRECEDING) AS Saldo
    FROM MovimientosLote m JOIN LotesMedicamentos l ON l.LoteID=m.LoteID LEFT JOIN CompraDetalle d ON d.CompraDetalleID=m.CompraDetalleID LEFT JOIN VentaLotes va ON va.AsignacionID=m.VentaAsignacionID LEFT JOIN VentaDetalle vd ON vd.DetalleID=va.DetalleID LEFT JOIN DevolucionVentaLotes rl ON rl.RetornoID=m.RetornoVentaID LEFT JOIN DevolucionVentaDetalle rd ON rd.ReintegroID=rl.ReintegroID LEFT JOIN DevolucionesVenta rv ON rv.DevolucionID=rd.DevolucionID LEFT JOIN Usuarios u ON u.UsuarioID=m.UsuarioID WHERE $ambito AND m.FechaMovimiento>=? AND m.FechaMovimiento<?";
    return ['producto'=>$producto,'resumen'=>$resumen,'sql'=>$sql,'params'=>[...$params,$desde,$hasta]];
}
function origenMovimiento($m) {
    if($m['VentaID']??null)return ['Venta','#'.$m['VentaID'],tienePermiso('ventas.ver')?'/clinica/ventas/ver.php?id='.$m['VentaID']:null];
    if($m['VentaRetornoID']??null)return [($m['TipoRetorno']==='Anulacion'?'Anulación':'Devolución').' de venta','#'.$m['VentaRetornoID'],tienePermiso('ventas.ver')?'/clinica/ventas/ver.php?id='.$m['VentaRetornoID']:null];
    if($m['AnulacionCompraID'])return ['Anulación de compra','#'.$m['AnulacionCompraID'],tienePermiso('compras.ver')?'/clinica/compras/ver.php?id='.$m['AnulacionCompraID']:null];
    if($m['CorreccionID'])return ['Corrección entre lotes','#'.$m['CorreccionID'],null];
    if($m['CompraID'])return ['Compra recibida','#'.$m['CompraID'],tienePermiso('compras.ver')?'/clinica/compras/ver.php?id='.$m['CompraID']:null];
    return [$m['TipoMovimiento']==='E'?'Ajuste de entrada':($m['TipoMovimiento']==='A'?'Ajuste de salida':'Salida manual'),'#'.$m['MovimientoID'],null];
}
