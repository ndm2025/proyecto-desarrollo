<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
$db=(new Database())->getConnection();
if (!$db) exit(1);
$db->exec('CREATE TABLE IF NOT EXISTS MigracionesFarmacia (Version VARCHAR(80) PRIMARY KEY, Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['001_farmacia']);
if (!$stmt->fetchColumn()) $db->exec(file_get_contents(__DIR__.'/001_farmacia.sql'));
require __DIR__.'/sembrar_permisos.php';
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['002_anulacion_compras']);
if (!$stmt->fetchColumn()) $db->exec(file_get_contents(__DIR__.'/002_anulacion_compras.sql'));
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['003_estado_lotes']);
if (!$stmt->fetchColumn()) $db->exec(file_get_contents(__DIR__.'/003_estado_lotes.sql'));
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['004_correcciones']);
if (!$stmt->fetchColumn()) $db->exec(file_get_contents(__DIR__.'/004_correcciones.sql'));
require __DIR__.'/migrar_contrasenas.php';
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['005_inventario_kardex']);
if (!$stmt->fetchColumn()) $db->exec(file_get_contents(__DIR__.'/005_inventario_kardex.sql'));
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['006_ventas']);
if (!$stmt->fetchColumn()) $db->exec(file_get_contents(__DIR__.'/006_ventas.sql'));
echo "Actualización de farmacia completada.\n";
