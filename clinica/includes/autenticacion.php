<?php
function algoritmoClave() { return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT; }
function hashClave($clave) {
    if (!is_string($clave) || strlen($clave)>255 || str_contains($clave,"\0")) throw new InvalidArgumentException('La contraseña no es válida.');
    if (!defined('PASSWORD_ARGON2ID') && strlen($clave)>72) throw new InvalidArgumentException('Esta instalación admite contraseñas de hasta 72 bytes.');
    return password_hash($clave,algoritmoClave());
}
function validarClaveNueva($clave,$confirmacion) {
    if (!is_string($clave) || strlen($clave)<8 || strlen($clave)>72 || str_contains($clave,"\0")) throw new InvalidArgumentException('La contraseña debe tener entre 8 y 72 bytes.');
    if (!is_string($confirmacion) || $clave!==$confirmacion) throw new InvalidArgumentException('Las contraseñas no coinciden.');
}
function autenticarUsuario($db,$nombre,$clave) {
    if (!is_string($nombre) || !is_string($clave) || strlen($clave)>255) return false;
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT u.*,r.NombreRol,r.Activo AS RolActivo FROM Usuarios u JOIN Roles r ON r.RolID=u.RolID WHERE u.NombreUsuario=? FOR UPDATE');
        $stmt->execute([trim($nombre)]);$usuario=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario || !$usuario['Activo'] || !$usuario['RolActivo'] || !password_verify($clave,$usuario['PasswordHash'])) { $db->rollBack(); return false; }
        if (password_needs_rehash($usuario['PasswordHash'],algoritmoClave())) $db->prepare('UPDATE Usuarios SET PasswordHash=? WHERE UsuarioID=?')->execute([hashClave($clave),$usuario['UsuarioID']]);
        $db->prepare('UPDATE Usuarios SET UltimoAcceso=NOW() WHERE UsuarioID=?')->execute([$usuario['UsuarioID']]);
        $db->commit();unset($usuario['PasswordHash']);return $usuario;
    } catch(Throwable $error) { if($db->inTransaction())$db->rollBack(); throw $error; }
}
