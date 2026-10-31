<?php
function cambiarEstadoLote($db, $loteId, $nuevoEstado, $estadoEsperado, $motivo, $usuarioId) {
    $loteId = entero($loteId,1,2147483647);
    if (!in_array($nuevoEstado,[0,1],true) || !in_array($estadoEsperado,[0,1],true)) {
        throw new InvalidArgumentException('El estado solicitado no es válido.');
    }
    if (!is_string($motivo) || trim($motivo)==='' || mb_strlen(trim($motivo))>500) {
        throw new InvalidArgumentException('Escribe un motivo de hasta 500 caracteres.');
    }
    $motivo = trim($motivo);
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT MedicamentoID,Activo,FechaVencimiento FROM LotesMedicamentos WHERE LoteID=? FOR UPDATE');
        $stmt->execute([$loteId]);
        $lote = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lote) throw new InvalidArgumentException('El lote no existe.');
        $actual = (int)$lote['Activo'];
        if ($actual === $nuevoEstado) {
            $db->commit();
            return false;
        }
        if ($actual !== $estadoEsperado) throw new InvalidArgumentException('El estado cambió mientras tenías el formulario abierto. Recarga la página antes de continuar.');
        if ($nuevoEstado === 1) {
            if ($lote['FechaVencimiento'] < date('Y-m-d')) throw new InvalidArgumentException('Un lote vencido no puede reactivarse.');
            $stmt = $db->prepare('SELECT Activo FROM Medicamentos WHERE MedicamentoID=?');
            $stmt->execute([$lote['MedicamentoID']]);
            if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('Primero debes activar el producto al que pertenece este lote.');
        }
        $db->prepare('UPDATE LotesMedicamentos SET Activo=? WHERE LoteID=?')->execute([$nuevoEstado,$loteId]);
        $db->prepare('INSERT INTO HistorialEstadoLote(LoteID,EstadoAnterior,EstadoNuevo,Motivo,UsuarioID) VALUES(?,?,?,?,?)')->execute([$loteId,$actual,$nuevoEstado,$motivo,$usuarioId]);
        auditar($db,$nuevoEstado ? 'lote.reactivado' : 'lote.desactivado',$loteId);
        $db->commit();
        return true;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}


function corregirEntreLotes($db,$origen,$destino,$cantidad,$motivo,$clave,$usuarioId) {
    $origen=entero($origen,1,2147483647);$destino=entero($destino,1,2147483647);$cantidad=entero($cantidad);
    if($origen===$destino)throw new InvalidArgumentException('Selecciona dos lotes diferentes.');
    if(!is_string($motivo)||trim($motivo)===''||mb_strlen(trim($motivo))>500)throw new InvalidArgumentException('Escribe un motivo de hasta 500 caracteres.');
    if(!is_string($clave)||!preg_match('/^[a-f0-9]{64}$/D',$clave))throw new InvalidArgumentException('La operación no es válida.');
    $motivo=trim($motivo);$db->beginTransaction();
    try{
        $stmt=$db->prepare('SELECT CorreccionID FROM CorreccionesLotes WHERE ClaveOperacion=?');$stmt->execute([$clave]);if($id=$stmt->fetchColumn()){$db->commit();return (int)$id;}
        $ids=[$origen,$destino];sort($ids);$lotes=[];$stmt=$db->prepare('SELECT * FROM LotesMedicamentos WHERE LoteID=? FOR UPDATE');
        foreach($ids as $id){$stmt->execute([$id]);$lote=$stmt->fetch(PDO::FETCH_ASSOC);if(!$lote)throw new InvalidArgumentException('Uno de los lotes no existe.');$lotes[$id]=$lote;}
        if($lotes[$origen]['MedicamentoID']!==$lotes[$destino]['MedicamentoID'])throw new InvalidArgumentException('Los lotes deben pertenecer al mismo producto.');
        if((int)$lotes[$origen]['CantidadActual']<$cantidad)throw new InvalidArgumentException('El lote de origen no tiene unidades suficientes.');
        if((int)$lotes[$destino]['CantidadActual']+$cantidad>2000000000)throw new InvalidArgumentException('El lote de destino supera la cantidad máxima.');
        $db->prepare('INSERT INTO CorreccionesLotes(LoteOrigenID,LoteDestinoID,Cantidad,Motivo,UsuarioID,ClaveOperacion) VALUES(?,?,?,?,?,?)')->execute([$origen,$destino,$cantidad,$motivo,$usuarioId,$clave]);$id=(int)$db->lastInsertId();
        $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=CantidadActual-? WHERE LoteID=?')->execute([$cantidad,$origen]);
        $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=CantidadActual+? WHERE LoteID=?')->execute([$cantidad,$destino]);
        $stmt=$db->prepare('INSERT INTO MovimientosLote(LoteID,TipoMovimiento,Cantidad,Observaciones,UsuarioID,CorreccionID) VALUES(?,?,?,?,?,?)');
        $descripcion='Corrección entre lotes #'.$id.': '.mb_substr($motivo,0,440);
        $stmt->execute([$origen,'S',$cantidad,$descripcion,$usuarioId,$id]);$stmt->execute([$destino,'E',$cantidad,$descripcion,$usuarioId,$id]);
        auditar($db,'inventario.correccion_lotes',$id);$db->commit();return $id;
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}


function registrarAjusteLote($db,$loteId,$tipo,$cantidad,$motivo,$clave,$usuarioId) {
    $loteId=entero($loteId,1,2147483647);$cantidad=entero($cantidad);
    if(!in_array($tipo,['E','S','A'],true))throw new InvalidArgumentException('Selecciona un tipo de ajuste válido.');
    if(!is_string($motivo)||trim($motivo)===''||mb_strlen(trim($motivo))>500)throw new InvalidArgumentException('Escribe un motivo de hasta 500 caracteres.');
    if(!is_string($clave)||!preg_match('/^[a-f0-9]{64}$/D',$clave))throw new InvalidArgumentException('La operación no es válida. Recarga el formulario.');
    $motivo=trim($motivo);$db->beginTransaction();
    try{
        $stmt=$db->prepare('SELECT CantidadActual FROM LotesMedicamentos WHERE LoteID=? FOR UPDATE');$stmt->execute([$loteId]);$saldo=$stmt->fetchColumn();
        if($saldo===false)throw new InvalidArgumentException('El lote no existe.');
        $stmt=$db->prepare('SELECT MovimientoID,LoteID FROM MovimientosLote WHERE ClaveOperacion=?');$stmt->execute([$clave]);$existente=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existente){if((int)$existente['LoteID']!==$loteId)throw new InvalidArgumentException('El formulario pertenece a otro lote.');$db->commit();return (int)$existente['MovimientoID'];}
        $nuevo=(int)$saldo+($tipo==='E'?$cantidad:-$cantidad);
        if($nuevo<0||$nuevo>2000000000)throw new InvalidArgumentException('La cantidad no es válida para el stock actual del lote.');
        $db->prepare('UPDATE LotesMedicamentos SET CantidadActual=? WHERE LoteID=?')->execute([$nuevo,$loteId]);
        $db->prepare('INSERT INTO MovimientosLote(LoteID,TipoMovimiento,Cantidad,Observaciones,UsuarioID,ClaveOperacion) VALUES(?,?,?,?,?,?)')->execute([$loteId,$tipo,$cantidad,$motivo,$usuarioId,$clave]);$id=(int)$db->lastInsertId();
        auditar($db,'inventario.ajuste',$id);$db->commit();return $id;
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
