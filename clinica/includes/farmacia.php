<?php
require_once __DIR__ . '/../config/database.php';
validarSesion();
function farmaciaDb() {
    static $db;
    if (!$db) $db = (new Database())->getConnection();
    if (!$db) throw new RuntimeException('No se pudo conectar con la base de datos.');
    return $db;
}
function h($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function exigirPermiso($codigo) {
    if (!tienePermiso($codigo)) {
        http_response_code(403);
        $titulo = 'Acceso restringido';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger mt-3">No tienes permiso para realizar esta acción.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}
function csrfCampo() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';
}
function validarCsrf() {
    if (empty($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(419);
        exit('La sesión del formulario venció. Recarga la página e inténtalo nuevamente.');
    }
}
function entero($value, $min=1, $max=1000000) {
    if (!is_scalar($value) || !preg_match('/^\d+$/D', (string)$value) || (float)$value < $min || (float)$value > $max) throw new InvalidArgumentException('Revisa las cantidades: deben ser números enteros dentro del rango permitido.');
    return (int)$value;
}
function centavos($value) {
    if (!is_scalar($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string)$value)) throw new InvalidArgumentException('Usa importes positivos con un máximo de dos decimales.');
    $parts=explode('.',(string)$value);
    return (int)$parts[0]*100+(int)str_pad($parts[1] ?? '',2,'0');
}
function decimalImporte($value) { return sprintf('%d.%02d',intdiv($value,100),$value%100); }
function dinero($value) { return 'Q '.number_format((float)$value,2,'.',','); }
function fechaValida($value) {
    if (!is_string($value)) return false;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $date && $date->format('Y-m-d')===$value;
}
function auditar($db,$accion,$id=null) {
    $db->prepare('INSERT INTO AuditoriaFarmacia(UsuarioID,Accion,EntidadID) VALUES(?,?,?)')->execute([$_SESSION['usuario_id'],$accion,$id]);
}
function errorFarmacia($error) {
    if ($error instanceof InvalidArgumentException) return $error->getMessage();
    error_log((string)$error);
    if ($error instanceof PDOException && ($error->errorInfo[1] ?? null)==1062) return 'El código, nombre o documento ya existe. Revisa los datos antes de guardar.';
    return 'No se pudo guardar la operación. No se aplicaron cambios.';
}
function erroresVista($errores) {
    if (!$errores) return;
    echo '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errores as $error) echo '<li>'.h($error).'</li>';
    echo '</ul></div>';
}
function redirigir($url) { header('Location: '.$url); exit; }
