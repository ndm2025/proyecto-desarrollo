<?php
require_once __DIR__.'/autenticacion.php';
function usuarioEsGestor($db,$id) {
    $stmt=$db->prepare("SELECT COALESCE(up.Permitido,CASE WHEN rp.PermisoID IS NULL THEN 0 ELSE 1 END) FROM Usuarios u JOIN Roles r ON r.RolID=u.RolID JOIN Permisos p ON p.Codigo='permisos.administrar' LEFT JOIN RolPermisos rp ON rp.RolID=u.RolID AND rp.PermisoID=p.PermisoID LEFT JOIN UsuarioPermisos up ON up.UsuarioID=u.UsuarioID AND up.PermisoID=p.PermisoID WHERE u.UsuarioID=?");
    $stmt->execute([$id]);return (bool)$stmt->fetchColumn();
}
function protegerCuenta($db,$id) {
    if(usuarioEsGestor($db,$id) && !tienePermiso('permisos.administrar')) throw new InvalidArgumentException('Para modificar una cuenta que administra accesos también necesitas permiso de administración de accesos.');
}
function motivoUsuario($motivo) {
    if(!is_string($motivo)||trim($motivo)===''||mb_strlen(trim($motivo))>500)throw new InvalidArgumentException('Escribe un motivo de hasta 500 caracteres.');
    return trim($motivo);
}
function registrarHistorialUsuario($db,$id,$accion,$motivo) {
    $db->prepare('INSERT INTO HistorialUsuarios(UsuarioObjetivoID,UsuarioID,Accion,Motivo) VALUES(?,?,?,?)')->execute([$id,$_SESSION['usuario_id'],$accion,$motivo]);
    auditar($db,$accion,$id);
}
function cambiarEstadoUsuario($db,$id,$estado,$motivo) {
    $motivo=motivoUsuario($motivo);
    if(!in_array($estado,[0,1],true))throw new InvalidArgumentException('Estado inválido.');
    if($estado===0 && $id===(int)$_SESSION['usuario_id'])throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
    $db->beginTransaction();
    try {
        $db->query('SELECT UsuarioID FROM Usuarios ORDER BY UsuarioID FOR UPDATE')->fetchAll();
        protegerCuenta($db,$id);
        $stmt=$db->prepare('SELECT Activo FROM Usuarios WHERE UsuarioID=?');$stmt->execute([$id]);$actual=$stmt->fetchColumn();
        if($actual===false)throw new InvalidArgumentException('Usuario no encontrado.');
        if((int)$actual===$estado){$db->commit();return false;}
        $db->prepare('UPDATE Usuarios SET Activo=?,SesionVersion=SesionVersion+1 WHERE UsuarioID=?')->execute([$estado,$id]);
        $cantidad=$db->query("SELECT COUNT(*) FROM Usuarios u JOIN Roles r ON r.RolID=u.RolID JOIN Permisos p ON p.Codigo='permisos.administrar' LEFT JOIN RolPermisos rp ON rp.RolID=u.RolID AND rp.PermisoID=p.PermisoID LEFT JOIN UsuarioPermisos up ON up.UsuarioID=u.UsuarioID AND up.PermisoID=p.PermisoID WHERE u.Activo=1 AND r.Activo=1 AND COALESCE(up.Permitido,CASE WHEN rp.PermisoID IS NULL THEN 0 ELSE 1 END)=1")->fetchColumn();
        if(!$cantidad)throw new InvalidArgumentException('Debe quedar al menos un administrador de accesos activo.');
        registrarHistorialUsuario($db,$id,$estado?'usuario.activado':'usuario.desactivado',$motivo);$db->commit();return true;
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
function restablecerClaveUsuario($db,$id,$clave,$confirmacion,$motivo) {
    $motivo=motivoUsuario($motivo);validarClaveNueva($clave,$confirmacion);$hash=hashClave($clave);
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT UsuarioID FROM Usuarios WHERE UsuarioID=? FOR UPDATE');$stmt->execute([$id]);
        if(!$stmt->fetchColumn())throw new InvalidArgumentException('Usuario no encontrado.');
        protegerCuenta($db,$id);
        $db->prepare('UPDATE Usuarios SET PasswordHash=?,SesionVersion=SesionVersion+1 WHERE UsuarioID=?')->execute([$hash,$id]);
        registrarHistorialUsuario($db,$id,'usuario.clave_restablecida',$motivo);$db->commit();
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
