<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/autenticacion.php';
$db=(new Database())->getConnection();
if (!$db) exit(1);
$db->beginTransaction();
try {
    $cuentas=$db->query('SELECT UsuarioID,PasswordHash FROM Usuarios ORDER BY UsuarioID FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $leer=$db->prepare("SELECT AES_DECRYPT(PasswordHash,'CONTROLCLINICA') FROM Usuarios WHERE UsuarioID=?");
    $guardar=$db->prepare('UPDATE Usuarios SET PasswordHash=? WHERE UsuarioID=?');$cantidad=0;
    foreach($cuentas as $cuenta) {
        if ((password_get_info($cuenta['PasswordHash'])['algo']??null)!==null) continue;
        $leer->execute([$cuenta['UsuarioID']]);$clave=$leer->fetchColumn();
        if (!is_string($clave)) throw new RuntimeException('No se pudo migrar una contraseña heredada. No se aplicaron cambios.');
        $guardar->execute([hashClave($clave),$cuenta['UsuarioID']]);unset($clave);$cantidad++;
    }
    $db->commit();
    $db->exec('DROP PROCEDURE IF EXISTS sp_ValidarLogin');
    echo 'Contraseñas migradas: '.$cantidad."\n";
} catch(Throwable $error) { if($db->inTransaction())$db->rollBack();throw $error; }
