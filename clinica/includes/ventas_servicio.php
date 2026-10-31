<?php
function claveVenta($valor) {
    if(!is_string($valor)||!preg_match('/^[a-f0-9]{64}$/D',$valor))throw new InvalidArgumentException('La operación no es válida. Recarga el formulario.');
    return $valor;
}
function catalogoVentas($db) {
    $productos=$db->query("SELECT m.MedicamentoID,m.Codigo,m.NombreMedicamento,m.UnidadBase,COALESCE((SELECT SUM(l.CantidadActual) FROM LotesMedicamentos l WHERE l.MedicamentoID=m.MedicamentoID AND l.Activo=1 AND l.FechaVencimiento>=CURRENT_DATE),0) AS Disponibles FROM Medicamentos m WHERE m.Activo=1 ORDER BY m.NombreMedicamento")->fetchAll(PDO::FETCH_ASSOC);
    $pres=$db->query('SELECT PresentacionID,MedicamentoID,Nombre,UnidadesBase,PrecioVenta FROM PresentacionesProducto WHERE Activo=1 AND PrecioVenta>0 ORDER BY UnidadesBase')->fetchAll(PDO::FETCH_ASSOC);
    foreach($productos as &$p)$p['presentaciones']=array_values(array_filter($pres,fn($x)=>(int)$x['MedicamentoID']===(int)$p['MedicamentoID']));
    return array_values(array_filter($productos,fn($p)=>count($p['presentaciones'])>0));
}
function registrarVenta($db,$datos,$usuarioId) {
    $clave=claveVenta($datos['operacion']??null);
    if(!is_scalar($datos['cliente']??''))throw new InvalidArgumentException('Revisa el nombre del cliente.');
    $cliente=trim((string)($datos['cliente']??''))?:'Consumidor final';
    if(mb_strlen($cliente)>150)throw new InvalidArgumentException('El nombre del cliente admite hasta 150 caracteres.');
    $efectivo=centavos($datos['efectivo']??'');$lineas=$datos['lineas']??[];
    if(!is_array($lineas)||count($lineas)<1||count($lineas)>50)throw new InvalidArgumentException('Agrega entre 1 y 50 productos.');
    $db->beginTransaction();
    try {
        $s=$db->prepare('SELECT VentaID FROM Ventas WHERE ClaveOperacion=?');$s->execute([$clave]);
        if($id=$s->fetchColumn()){$db->commit();return (int)$id;}
        $normal=[];$productos=[];$repetidos=[];
        foreach($lineas as $l){
            if(!is_array($l))throw new InvalidArgumentException('Revisa los productos de la venta.');
            $p=entero($l['producto']??'');$pres=entero($l['presentacion']??'');$cant=entero($l['cantidad']??'');$desc=centavos($l['descuento']??'0');$precio=centavos($l['precio']??'');$factor=entero($l['unidades']??'');
            if(isset($repetidos[$pres]))throw new InvalidArgumentException('Una presentación está repetida. Agrupa su cantidad en un solo renglón.');
            $repetidos[$pres]=true;$productos[$p]=$p;$normal[]=[$p,$pres,$cant,$desc,$precio,$factor];
        }
        sort($productos);$lock=$db->prepare('SELECT Activo FROM Medicamentos WHERE MedicamentoID=? FOR UPDATE');
        foreach($productos as $p){$lock->execute([$p]);if(!$lock->fetchColumn())throw new InvalidArgumentException('Uno de los productos ya no está activo.');}
        $subtotal=0;$descuentos=0;$preparadas=[];
        foreach($normal as [$p,$pres,$cant,$desc,$precio,$factor]){
            $s=$db->prepare('SELECT pr.*,m.Codigo,m.NombreMedicamento,m.UnidadBase FROM PresentacionesProducto pr JOIN Medicamentos m ON m.MedicamentoID=pr.MedicamentoID WHERE pr.PresentacionID=? AND pr.MedicamentoID=? FOR UPDATE');$s->execute([$pres,$p]);$r=$s->fetch(PDO::FETCH_ASSOC);
            if(!$r||!$r['Activo']||centavos($r['PrecioVenta'])<=0)throw new InvalidArgumentException('La presentación no está disponible para vender.');
            if($factor!==(int)$r['UnidadesBase'])throw new InvalidArgumentException('Cambió la cantidad por presentación. Actualiza el catálogo antes de confirmar.');
            if($precio!==centavos($r['PrecioVenta']))throw new InvalidArgumentException('Cambió el precio de un producto. Actualiza el catálogo y revisa el cobro.');
            if($desc&&!tienePermiso('ventas.descuento'))throw new InvalidArgumentException('No tienes permiso para aplicar descuentos.');
            $bruto=$cant*$precio;$unidades=$cant*(int)$r['UnidadesBase'];
            if($desc>$bruto)throw new InvalidArgumentException('El descuento no puede superar el subtotal del producto.');
            if($unidades>100000000||$bruto>999999999999||$subtotal+$bruto>999999999999)throw new InvalidArgumentException('La cantidad o el importe supera el máximo permitido.');
            $subtotal+=$bruto;$descuentos+=$desc;$preparadas[]=[$r,$cant,$unidades,$precio,$bruto,$desc];
        }
        $total=$subtotal-$descuentos;
        if($efectivo<$total)throw new InvalidArgumentException('El efectivo recibido debe cubrir el total de la venta.');
        $db->prepare('INSERT INTO Ventas(UsuarioID,Cliente,Subtotal,Descuento,Total,Efectivo,Cambio,ClaveOperacion) VALUES(?,?,?,?,?,?,?,?)')->execute([$usuarioId,$cliente,decimalImporte($subtotal),decimalImporte($descuentos),decimalImporte($total),decimalImporte($efectivo),decimalImporte($efectivo-$total),$clave]);$venta=(int)$db->lastInsertId();
        foreach($preparadas as [$r,$cant,$unidades,$precio,$bruto,$desc]){
            $s=$db->prepare('SELECT LoteID,CantidadActual FROM LotesMedicamentos WHERE MedicamentoID=? AND Activo=1 AND FechaVencimiento>=CURRENT_DATE AND CantidadActual>0 ORDER BY FechaVencimiento,LoteID FOR UPDATE');$s->execute([$r['MedicamentoID']]);$lotes=$s->fetchAll(PDO::FETCH_ASSOC);
            if(array_sum(array_column($lotes,'CantidadActual'))<$unidades)throw new InvalidArgumentException('No hay suficientes unidades disponibles de '.$r['NombreMedicamento'].'. Revisa el inventario.');
            $db->prepare('INSERT INTO VentaDetalle(VentaID,MedicamentoID,PresentacionID,Codigo,Producto,Presentacion,UnidadBase,UnidadesPorPresentacion,Cantidad,Unidades,Precio,Subtotal,Descuento,Total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$venta,$r['MedicamentoID'],$r['PresentacionID'],$r['Codigo'],$r['NombreMedicamento'],$r['Nombre'],$r['UnidadBase'],$r['UnidadesBase'],$cant,$unidades,decimalImporte($precio),decimalImporte($bruto),decimalImporte($desc),decimalImporte($bruto-$desc)]);$detalle=(int)$db->lastInsertId();
            $pendiente=$unidades;
            foreach($lotes as $lote){
                if(!$pendiente)break;$salida=min($pendiente,(int)$lote['CantidadActual']);
                $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=CantidadActual-? WHERE LoteID=?')->execute([$salida,$lote['LoteID']]);
                $db->prepare('INSERT INTO VentaLotes(DetalleID,LoteID,Unidades) VALUES(?,?,?)')->execute([$detalle,$lote['LoteID'],$salida]);$asignacion=(int)$db->lastInsertId();
                $db->prepare("INSERT INTO MovimientosLote(LoteID,TipoMovimiento,Cantidad,Observaciones,UsuarioID,VentaAsignacionID) VALUES(?,'S',?,?,?,?)")->execute([$lote['LoteID'],$salida,'Venta #'.$venta,$usuarioId,$asignacion]);$pendiente-=$salida;
            }
        }
        auditar($db,'venta.registrada',$venta);$db->commit();return $venta;
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        if($e instanceof PDOException&&($e->errorInfo[1]??0)==1062){$s=$db->prepare('SELECT VentaID FROM Ventas WHERE ClaveOperacion=?');$s->execute([$clave]);if($id=$s->fetchColumn())return (int)$id;}
        throw $e;
    }
}
function reintegrarVenta($db,$ventaId,$tipo,$datos,$usuarioId) {
    $ventaId=entero($ventaId,1,2147483647);$clave=claveVenta($datos['operacion']??null);
    if(!in_array($tipo,['Devolucion','Anulacion'],true))throw new InvalidArgumentException('La operación solicitada no es válida.');
    if(!is_scalar($datos['motivo']??''))throw new InvalidArgumentException('El motivo es obligatorio.');
    $motivo=trim((string)($datos['motivo']??''));
    if($motivo===''||mb_strlen($motivo)>500)throw new InvalidArgumentException('El motivo es obligatorio y admite hasta 500 caracteres.');
    $db->beginTransaction();
    try{
        $s=$db->prepare('SELECT * FROM Ventas WHERE VentaID=? FOR UPDATE');$s->execute([$ventaId]);$venta=$s->fetch(PDO::FETCH_ASSOC);
        if(!$venta)throw new InvalidArgumentException('La venta no existe.');
        $s=$db->prepare('SELECT DevolucionID FROM DevolucionesVenta WHERE ClaveOperacion=? AND VentaID=? AND Tipo=?');$s->execute([$clave,$ventaId,$tipo]);if($id=$s->fetchColumn()){$db->commit();return (int)$id;}
        if(in_array($venta['Estado'],['Anulada','Devuelta'],true))throw new InvalidArgumentException('La venta ya no tiene productos pendientes de devolver.');
        $s=$db->prepare('SELECT * FROM VentaDetalle WHERE VentaID=? ORDER BY DetalleID FOR UPDATE');$s->execute([$ventaId]);$lineas=$s->fetchAll(PDO::FETCH_ASSOC);$elegidas=$datos['cantidades']??[];
        if(!is_array($elegidas))throw new InvalidArgumentException('Revisa las cantidades a devolver.');
        foreach(array_keys($elegidas) as $id)if(!in_array((int)$id,array_column($lineas,'DetalleID')))throw new InvalidArgumentException('Un producto no pertenece a esta venta.');
        $reintegrar=[];$total=0;
        foreach($lineas as $l){
            $pend=(int)$l['Cantidad']-(int)$l['CantidadDevuelta'];$cant=$tipo==='Anulacion'?$pend:entero($elegidas[$l['DetalleID']]??0,0,1000000);
            if($cant>$pend)throw new InvalidArgumentException('No puedes devolver más de lo vendido o pendiente.');
            if(!$cant)continue;
            $acumulado=intdiv(centavos($l['Total'])*((int)$l['CantidadDevuelta']+$cant),(int)$l['Cantidad']);$importe=$acumulado-centavos($l['ImporteDevuelto']);
            $reintegrar[]=[$l,$cant,$cant*(int)$l['UnidadesPorPresentacion'],$importe,$acumulado];$total+=$importe;
        }
        if(!$reintegrar)throw new InvalidArgumentException('Selecciona al menos una presentación para devolver.');
        $db->prepare('INSERT INTO DevolucionesVenta(VentaID,Tipo,Motivo,Total,UsuarioID,ClaveOperacion) VALUES(?,?,?,?,?,?)')->execute([$ventaId,$tipo,$motivo,decimalImporte($total),$usuarioId,$clave]);$devolucion=(int)$db->lastInsertId();
        $s=$db->prepare('SELECT DISTINCT a.LoteID FROM VentaLotes a JOIN VentaDetalle d ON d.DetalleID=a.DetalleID WHERE d.VentaID=? ORDER BY a.LoteID');$s->execute([$ventaId]);$lock=$db->prepare('SELECT CantidadActual FROM LotesMedicamentos WHERE LoteID=? FOR UPDATE');
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id){$lock->execute([$id]);$lock->fetchColumn();}
        foreach($reintegrar as [$l,$cant,$unidades,$importe,$acumulado]){
            $db->prepare('INSERT INTO DevolucionVentaDetalle(DevolucionID,DetalleID,Cantidad,Unidades,Importe) VALUES(?,?,?,?,?)')->execute([$devolucion,$l['DetalleID'],$cant,$unidades,decimalImporte($importe)]);$reintegro=(int)$db->lastInsertId();
            $s=$db->prepare('SELECT * FROM VentaLotes WHERE DetalleID=? ORDER BY AsignacionID FOR UPDATE');$s->execute([$l['DetalleID']]);$asignaciones=$s->fetchAll(PDO::FETCH_ASSOC);$pend=$unidades;
            foreach($asignaciones as $a){
                if(!$pend)break;$cantidad=min($pend,(int)$a['Unidades']-(int)$a['UnidadesDevueltas']);if(!$cantidad)continue;
                $lock->execute([$a['LoteID']]);$fisico=(int)$lock->fetchColumn();if($fisico+$cantidad>2000000000)throw new InvalidArgumentException('El lote supera la cantidad máxima permitida.');
                $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=CantidadActual+? WHERE LoteID=?')->execute([$cantidad,$a['LoteID']]);
                $db->prepare('UPDATE VentaLotes SET UnidadesDevueltas=UnidadesDevueltas+? WHERE AsignacionID=?')->execute([$cantidad,$a['AsignacionID']]);
                $db->prepare('INSERT INTO DevolucionVentaLotes(ReintegroID,AsignacionID,Unidades) VALUES(?,?,?)')->execute([$reintegro,$a['AsignacionID'],$cantidad]);$retorno=(int)$db->lastInsertId();
                $db->prepare("INSERT INTO MovimientosLote(LoteID,TipoMovimiento,Cantidad,Observaciones,UsuarioID,RetornoVentaID) VALUES(?,'E',?,?,?,?)")->execute([$a['LoteID'],$cantidad,($tipo==='Anulacion'?'Anulación':'Devolución').' venta #'.$ventaId.': '.mb_substr($motivo,0,420),$usuarioId,$retorno]);$pend-=$cantidad;
            }
            if($pend)throw new RuntimeException('Las asignaciones originales de la venta están incompletas.');
            $db->prepare('UPDATE VentaDetalle SET CantidadDevuelta=CantidadDevuelta+?,ImporteDevuelto=? WHERE DetalleID=?')->execute([$cant,decimalImporte($acumulado),$l['DetalleID']]);
        }
        $s=$db->prepare('SELECT SUM(Cantidad-CantidadDevuelta) FROM VentaDetalle WHERE VentaID=?');$s->execute([$ventaId]);$estado=$tipo==='Anulacion'?'Anulada':((int)$s->fetchColumn()===0?'Devuelta':'Parcial');
        $db->prepare('UPDATE Ventas SET Estado=? WHERE VentaID=?')->execute([$estado,$ventaId]);auditar($db,$tipo==='Anulacion'?'venta.anulada':'venta.devuelta',$ventaId);$db->commit();return $devolucion;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function datosVenta($db,$id) {
    $s=$db->prepare('SELECT v.*,u.NombreCompleto,COALESCE((SELECT SUM(Total) FROM DevolucionesVenta r WHERE r.VentaID=v.VentaID),0) AS Reintegrado FROM Ventas v JOIN Usuarios u ON u.UsuarioID=v.UsuarioID WHERE v.VentaID=?');$s->execute([$id]);$v=$s->fetch(PDO::FETCH_ASSOC);
    if(!$v)throw new InvalidArgumentException('La venta no existe.');
    $s=$db->prepare('SELECT * FROM VentaDetalle WHERE VentaID=? ORDER BY DetalleID');$s->execute([$id]);$v['lineas']=$s->fetchAll(PDO::FETCH_ASSOC);
    $s=$db->prepare('SELECT a.*,l.NumeroLote,l.FechaVencimiento,l.Activo,m.Activo AS ProductoActivo FROM VentaLotes a JOIN VentaDetalle d ON d.DetalleID=a.DetalleID JOIN LotesMedicamentos l ON l.LoteID=a.LoteID JOIN Medicamentos m ON m.MedicamentoID=l.MedicamentoID WHERE d.VentaID=? ORDER BY a.AsignacionID');$s->execute([$id]);$v['lotes']=$s->fetchAll(PDO::FETCH_ASSOC);
    $s=$db->prepare('SELECT r.*,u.NombreCompleto FROM DevolucionesVenta r JOIN Usuarios u ON u.UsuarioID=r.UsuarioID WHERE r.VentaID=? ORDER BY r.DevolucionID');$s->execute([$id]);$v['devoluciones']=$s->fetchAll(PDO::FETCH_ASSOC);return $v;
}
