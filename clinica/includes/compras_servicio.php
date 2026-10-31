<?php
function registrarCompra($db,$datos,$usuarioId) {
    if (!fechaValida($datos['fecha']??null) || $datos['fecha']>date('Y-m-d')) throw new InvalidArgumentException('Selecciona una fecha de recepción válida, igual o anterior a hoy.');
    $proveedor=entero($datos['proveedor']??'');
    $documento=trim((string)($datos['documento']??''));$observaciones=trim((string)($datos['observaciones']??''));
    if ($documento===''||mb_strlen($documento)>60||mb_strlen($observaciones)>500) throw new InvalidArgumentException('Indica un documento de referencia de hasta 60 caracteres y observaciones de hasta 500.');
    $clave=$datos['operacion']??'';
    if (!is_string($clave)||!preg_match('/^[a-f0-9]{64}$/D',$clave)) throw new InvalidArgumentException('La operación no es válida. Recarga el formulario.');
    $lineas=$datos['lineas']??[];
    if (!is_array($lineas)||count($lineas)<1||count($lineas)>50) throw new InvalidArgumentException('Agrega entre 1 y 50 renglones de compra.');
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT CompraID FROM Compras WHERE ClaveOperacion=?');$stmt->execute([$clave]);
        if ($existente=$stmt->fetchColumn()) {$db->commit();return (int)$existente;}
        $stmt=$db->prepare('SELECT ProveedorID FROM Proveedores WHERE ProveedorID=? AND Activo=1 FOR UPDATE');$stmt->execute([$proveedor]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('El proveedor no está activo.');
        
        $origen=entero($datos['origen']??0,0,2147483647);
        if($origen){
            if(!tienePermiso('compras.corregir'))throw new InvalidArgumentException('No tienes permiso para corregir compras anuladas.');
            $stmt=$db->prepare("SELECT ProveedorID,NumeroDocumento FROM Compras WHERE CompraID=? AND Estado='Anulada' FOR UPDATE");$stmt->execute([$origen]);$anulada=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$anulada || (int)$anulada['ProveedorID']!==$proveedor || $anulada['NumeroDocumento']!==$documento)throw new InvalidArgumentException('La corrección debe conservar el proveedor y la referencia de la compra anulada.');
        }else{
            $stmt=$db->prepare("SELECT CompraID FROM Compras WHERE ProveedorID=? AND NumeroDocumento=? AND Estado='Anulada' LIMIT 1");$stmt->execute([$proveedor,$documento]);
            if($stmt->fetchColumn())throw new InvalidArgumentException('Esta referencia pertenece a una compra anulada. Usa la opción Corregir compra desde su detalle.');
        }

        $normalizadas=[];$total=0;
        foreach($lineas as $linea) {
            if (!is_array($linea)) throw new InvalidArgumentException('Revisa los renglones de la compra.');
            $producto=entero($linea['producto']??'');$presentacion=entero($linea['presentacion']??'');$cantidad=entero($linea['cantidad']??'');$unidades=entero($linea['unidades']??'');$costo=centavos($linea['costo']??'');
            $lote=trim((string)($linea['lote']??''));$vence=$linea['vence']??'';
            if ($costo<=0||$lote===''||mb_strlen($lote)>50||!fechaValida($vence)||$vence<date('Y-m-d')) throw new InvalidArgumentException('Cada renglón necesita costo positivo, lote y vencimiento vigente.');
            if ($cantidad*$unidades>100000000) throw new InvalidArgumentException('La cantidad de unidades es demasiado grande.');
            $stmt=$db->prepare('SELECT p.*,m.Activo AS ProductoActivo FROM PresentacionesProducto p JOIN Medicamentos m ON m.MedicamentoID=p.MedicamentoID WHERE p.PresentacionID=? AND p.MedicamentoID=? FOR UPDATE');$stmt->execute([$presentacion,$producto]);$p=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p||!$p['Activo']||!$p['ProductoActivo']) throw new InvalidArgumentException('Selecciona un producto y presentación activos.');
            if ($p['Nombre']==='Unidad'&&$unidades!==1) throw new InvalidArgumentException('Una unidad debe equivaler a una unidad base.');
            $subtotal=$cantidad*$costo;$total+=$subtotal;
            if ($total>999999999999) throw new InvalidArgumentException('El total de la compra supera el máximo permitido.');
            $normalizadas[]=[$producto,$presentacion,$p['Nombre'],$cantidad,$unidades,$costo,$subtotal,$lote,$vence];
        }
        $db->prepare('INSERT INTO Compras(ProveedorID,NumeroDocumento,FechaCompra,Total,Observaciones,UsuarioID,ClaveOperacion,CompraOrigenID) VALUES(?,?,?,?,?,?,?,?)')->execute([$proveedor,$documento,$datos['fecha'],decimalImporte($total),$observaciones,$usuarioId,$clave,$origen?:null]);$compra=(int)$db->lastInsertId();
        foreach($normalizadas as [$producto,$presentacion,$nombre,$cantidad,$unidades,$costo,$subtotal,$lote,$vence]) {
            $recibidas=$cantidad*$unidades;
            $stmt=$db->prepare('SELECT * FROM LotesMedicamentos WHERE MedicamentoID=? AND NumeroLote=? FOR UPDATE');$stmt->execute([$producto,$lote]);$actual=$stmt->fetch(PDO::FETCH_ASSOC);
            if ($actual) {
                if (!$actual['Activo']||$actual['FechaVencimiento']!==$vence) throw new InvalidArgumentException('Un lote existente está inactivo o tiene otra fecha de vencimiento.');
                if ((int)$actual['CantidadActual']+$recibidas>2000000000||(int)$actual['CantidadInicial']+$recibidas>2000000000) throw new InvalidArgumentException('El lote supera la cantidad máxima permitida.');
                $loteId=(int)$actual['LoteID'];
                $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=CantidadActual+?,CantidadInicial=CantidadInicial+? WHERE LoteID=?')->execute([$recibidas,$recibidas,$loteId]);
            } else {
                $db->prepare('INSERT INTO LotesMedicamentos(MedicamentoID,NumeroLote,FechaVencimiento,CantidadInicial,CantidadActual) VALUES(?,?,?,?,?)')->execute([$producto,$lote,$vence,$recibidas,$recibidas]);$loteId=(int)$db->lastInsertId();
            }
            $db->prepare('INSERT INTO CompraDetalle(CompraID,MedicamentoID,LoteID,PresentacionID,PresentacionNombre,Cantidad,UnidadesPorPresentacion,UnidadesRecibidas,CostoPresentacion,Subtotal) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$compra,$producto,$loteId,$presentacion,$nombre,$cantidad,$unidades,$recibidas,decimalImporte($costo),decimalImporte($subtotal)]);$detalle=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO MovimientosLote(LoteID,TipoMovimiento,Cantidad,Observaciones,UsuarioID,CompraDetalleID) VALUES(?,'E',?,?,?,?)")->execute([$loteId,$recibidas,'Compra #'.$compra.' · '.$documento,$usuarioId,$detalle]);
        }
        auditar($db,$origen?'compra.corregida':'compra.recibida',$compra);$db->commit();return $compra;
    } catch(Throwable $error) { if($db->inTransaction())$db->rollBack();throw $error; }
}


function anularCompra($db, $compraId, $motivo, $usuarioId) {
    $compraId = entero($compraId, 1, 2147483647);
    if (!is_string($motivo)) throw new InvalidArgumentException('El motivo de anulación es obligatorio.');
    $motivo = trim($motivo);
    if ($motivo === '' || mb_strlen($motivo) > 500) throw new InvalidArgumentException('Escribe el motivo de anulación, con un máximo de 500 caracteres.');
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT Estado FROM Compras WHERE CompraID=? FOR UPDATE');
        $stmt->execute([$compraId]);
        $estado = $stmt->fetchColumn();
        if (!$estado) throw new InvalidArgumentException('La compra no existe.');
        if ($estado === 'Anulada') {
            $db->commit();
            return false;
        }
        $stmt = $db->prepare('SELECT LoteID,SUM(UnidadesRecibidas) AS Unidades FROM CompraDetalle WHERE CompraID=? GROUP BY LoteID ORDER BY LoteID');
        $stmt->execute([$compraId]);
        $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$lotes) throw new InvalidArgumentException('La compra no tiene ingresos que se puedan revertir.');
        $buscar = $db->prepare('SELECT NumeroLote,CantidadActual FROM LotesMedicamentos WHERE LoteID=? FOR UPDATE');
        foreach ($lotes as $lote) {
            $buscar->execute([$lote['LoteID']]);
            $actual = $buscar->fetch(PDO::FETCH_ASSOC);
            if (!$actual || (int)$actual['CantidadActual'] < (int)$lote['Unidades']) {
                throw new InvalidArgumentException('No se puede anular: el lote '.($actual['NumeroLote'] ?? $lote['LoteID']).' no tiene las unidades suficientes para revertir la compra. Revisa las salidas registradas.');
            }
        }
        $actualizar = $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=CantidadActual-? WHERE LoteID=? AND CantidadActual>=?');
        $movimiento = $db->prepare("INSERT INTO MovimientosLote(LoteID,TipoMovimiento,Cantidad,Observaciones,UsuarioID,AnulacionCompraID) VALUES(?,'S',?,?,?,?)");
        foreach ($lotes as $lote) {
            $cantidad = (int)$lote['Unidades'];
            $actualizar->execute([$cantidad,$lote['LoteID'],$cantidad]);
            if ($actualizar->rowCount() !== 1) throw new InvalidArgumentException('No hay existencias suficientes para completar la anulación.');
            $movimiento->execute([$lote['LoteID'],$cantidad,'Anulación compra #'.$compraId.': '.mb_substr($motivo,0,450),$usuarioId,$compraId]);
        }
        $db->prepare("UPDATE Compras SET Estado='Anulada',MotivoAnulacion=?,UsuarioAnulacion=?,FechaAnulacion=NOW() WHERE CompraID=?")->execute([$motivo,$usuarioId,$compraId]);
        auditar($db,'compra.anulada',$compraId);
        $db->commit();
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
