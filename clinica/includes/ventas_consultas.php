<?php
require_once __DIR__.'/inventario_servicio.php';
function filtrosVentas($datos){
    $f=['desde'=>textoFiltro($datos['desde']??'',10),'hasta'=>textoFiltro($datos['hasta']??date('Y-m-d'),10),'estado'=>textoFiltro($datos['estado']??'',20),'usuario'=>entero($datos['usuario']??0,0,2147483647),'q'=>textoFiltro($datos['q']??'',150),'pagina'=>entero($datos['pagina']??1,1,2147483647)];
    foreach(['desde','hasta'] as $c)if($f[$c]!==''&&(!fechaValida($f[$c])||$f[$c]<'1000-01-01'||$f[$c]>'9998-12-31'))throw new InvalidArgumentException('Selecciona fechas válidas.');
    if(($f['desde']?:'1000-01-01')>($f['hasta']?:date('Y-m-d')))throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
    if(!in_array($f['estado'],['','Vigente','Parcial','Devuelta','Anulada'],true))throw new InvalidArgumentException('El estado de venta no es válido.');return $f;
}
function consultaVentas($f){
    $where=['v.Fecha>=?','v.Fecha<?'];$params=[($f['desde']?:'1000-01-01').' 00:00:00',(new DateTimeImmutable($f['hasta']?:date('Y-m-d')))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
    if($f['estado']!==''){$where[]='v.Estado=?';$params[]=$f['estado'];}if($f['usuario']){$where[]='v.UsuarioID=?';$params[]=$f['usuario'];}
    if($f['q']!==''){$where[]='(CAST(v.VentaID AS CHAR)=? OR v.Cliente LIKE ?)';$params[]=$f['q'];$params[]='%'.$f['q'].'%';}
    return ["SELECT v.*,u.NombreCompleto,COALESCE(r.Reintegrado,0) AS Reintegrado,v.Total-COALESCE(r.Reintegrado,0) AS Neto FROM Ventas v JOIN Usuarios u ON u.UsuarioID=v.UsuarioID LEFT JOIN (SELECT VentaID,SUM(Total) AS Reintegrado FROM DevolucionesVenta GROUP BY VentaID) r ON r.VentaID=v.VentaID WHERE ".implode(' AND ',$where),$params];
}
