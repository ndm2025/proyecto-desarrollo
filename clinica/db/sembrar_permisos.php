<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
$db=(new Database())->getConnection();
$stmt=$db->prepare('SELECT 1 FROM MigracionesFarmacia WHERE Version=?');
$stmt->execute(['001_permisos']);
if ($stmt->fetchColumn()) { echo "Permisos ya inicializados.\n"; return; }
$modulos=[
 'dashboard'=>['ver'=>'Ver dashboard'],
 'medicamentos'=>['ver'=>'Consultar productos','crear'=>'Crear productos','editar'=>'Editar productos','precios'=>'Definir precios de venta','costos'=>'Consultar costos de compra'],
 'proveedores'=>['ver'=>'Consultar proveedores','editar'=>'Crear y editar proveedores'],
 'compras'=>['ver'=>'Consultar compras recibidas','crear'=>'Registrar compras recibidas'],
 'inventario'=>['ver'=>'Consultar lotes y existencias','ajustar'=>'Registrar ajustes','exportar'=>'Exportar inventario'],
 'ventas'=>['ver'=>'Consultar ventas','crear'=>'Realizar ventas','descuento'=>'Aplicar descuento en quetzales','anular'=>'Anular ventas','devolver'=>'Registrar devoluciones'],
 'usuarios'=>['ver'=>'Consultar usuarios','editar'=>'Crear y editar usuarios'],
 'permisos'=>['administrar'=>'Administrar roles y accesos individuales'],
 'pacientes'=>['gestionar'=>'Gestionar pacientes'], 'citas'=>['gestionar'=>'Gestionar citas'], 'atencion'=>['gestionar'=>'Gestionar atención clínica'], 'expedientes'=>['gestionar'=>'Gestionar consultas y expedientes'], 'reportes'=>['ver'=>'Consultar reportes']
];
$db->beginTransaction();
try {
    $insert=$db->prepare('INSERT IGNORE INTO Permisos(Codigo,Modulo,Nombre) VALUES(?,?,?)');
    foreach($modulos as $modulo=>$acciones) foreach($acciones as $accion=>$nombre) $insert->execute([$modulo.'.'.$accion,ucfirst($modulo),$nombre]);
    $db->exec("INSERT IGNORE INTO Roles(NombreRol,Descripcion) VALUES('Encargado de inventario','Productos, proveedores y compras'),('Vendedor','Venta y consulta de existencias')");
    $bases=[
      'Médico'=>['dashboard.ver','pacientes.gestionar','citas.gestionar','atencion.gestionar','expedientes.gestionar'],
      'Recepcionista'=>['dashboard.ver','pacientes.gestionar','citas.gestionar','atencion.gestionar'],
      'Enfermera'=>['dashboard.ver','pacientes.gestionar','atencion.gestionar','expedientes.gestionar'],
      'Encargado de inventario'=>['dashboard.ver','medicamentos.ver','medicamentos.crear','medicamentos.editar','medicamentos.precios','medicamentos.costos','proveedores.ver','proveedores.editar','compras.ver','compras.crear','inventario.ver','inventario.ajustar','inventario.exportar'],
      'Vendedor'=>['dashboard.ver','medicamentos.ver','inventario.ver','ventas.ver','ventas.crear']
    ];
    $roles=$db->query('SELECT RolID,NombreRol FROM Roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $permisos=$db->query('SELECT Codigo,PermisoID FROM Permisos')->fetchAll(PDO::FETCH_KEY_PAIR);
    $insert=$db->prepare('INSERT IGNORE INTO RolPermisos(RolID,PermisoID) VALUES(?,?)');
    foreach($roles as $id=>$nombre) foreach($nombre==='Administrador'?array_keys($permisos):($bases[$nombre]??[]) as $codigo) $insert->execute([$id,$permisos[$codigo]]);
    $db->exec("INSERT INTO MigracionesFarmacia(Version) VALUES('001_permisos')");
    $db->commit();
    echo "Permisos inicializados.\n";
} catch(Throwable $error) { $db->rollBack(); throw $error; }
