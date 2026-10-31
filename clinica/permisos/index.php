<?php
require_once '../includes/farmacia.php';exigirPermiso('permisos.administrar');$db=farmaciaDb();$errores=[];
$permisos=$db->query('SELECT * FROM Permisos ORDER BY Modulo,PermisoID')->fetchAll(PDO::FETCH_ASSOC);
$tipo=$_GET['tipo']??'';$id=max(0,(int)($_GET['id']??0));
if(!in_array($tipo,['','rol','usuario'],true)){http_response_code(404);exit('Opción inválida.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 validarCsrf();
 try{
  if(!in_array($tipo,['rol','usuario'],true))throw new InvalidArgumentException('Selecciona un rol o usuario.');
  $db->beginTransaction();$db->query('SELECT UsuarioID FROM Usuarios ORDER BY UsuarioID FOR UPDATE')->fetchAll();
  if($tipo==='rol'){
   $nombre=trim((string)($_POST['nombre']??''));$descripcion=trim((string)($_POST['descripcion']??''));
   if($nombre===''||mb_strlen($nombre)>50||mb_strlen($descripcion)>200)throw new InvalidArgumentException('Revisa el nombre y descripción del rol.');
   if($id){$stmt=$db->prepare('SELECT RolID FROM Roles WHERE RolID=?');$stmt->execute([$id]);if(!$stmt->fetchColumn())throw new InvalidArgumentException('El rol no existe.');$db->prepare('UPDATE Roles SET NombreRol=?,Descripcion=? WHERE RolID=?')->execute([$nombre,$descripcion,$id]);}
   else{$db->prepare('INSERT INTO Roles(NombreRol,Descripcion) VALUES(?,?)')->execute([$nombre,$descripcion]);$id=(int)$db->lastInsertId();}
   $db->prepare('DELETE FROM RolPermisos WHERE RolID=?')->execute([$id]);$insert=$db->prepare('INSERT INTO RolPermisos(RolID,PermisoID) VALUES(?,?)');
   $seleccionados=$_POST['permisos']??[];if(!is_array($seleccionados))throw new InvalidArgumentException('Selección de permisos inválida.');
   foreach($permisos as $p)if(in_array((string)$p['PermisoID'],$seleccionados,true))$insert->execute([$id,$p['PermisoID']]);
  }else{
   $rol=entero($_POST['rol']??'');$stmt=$db->prepare('SELECT RolID FROM Roles WHERE RolID=? AND Activo=1');$stmt->execute([$rol]);if(!$stmt->fetchColumn())throw new InvalidArgumentException('Selecciona un rol activo.');
   $stmt=$db->prepare('SELECT UsuarioID FROM Usuarios WHERE UsuarioID=?');$stmt->execute([$id]);if(!$stmt->fetchColumn())throw new InvalidArgumentException('Usuario no encontrado.');
   $db->prepare('UPDATE Usuarios SET RolID=? WHERE UsuarioID=?')->execute([$rol,$id]);
   $db->prepare('DELETE FROM UsuarioPermisos WHERE UsuarioID=?')->execute([$id]);$insert=$db->prepare('INSERT INTO UsuarioPermisos(UsuarioID,PermisoID,Permitido) VALUES(?,?,?)');
   foreach($permisos as $p){$valor=$_POST['excepciones'][$p['PermisoID']]??'heredar';if(!in_array($valor,['heredar','1','0'],true))throw new InvalidArgumentException('Excepción inválida.');if($valor!=='heredar')$insert->execute([$id,$p['PermisoID'],(int)$valor]);}
  }
  $gestores=$db->query("SELECT COUNT(*) FROM Usuarios u JOIN Roles r ON r.RolID=u.RolID JOIN Permisos p ON p.Codigo='permisos.administrar' LEFT JOIN RolPermisos rp ON rp.RolID=u.RolID AND rp.PermisoID=p.PermisoID LEFT JOIN UsuarioPermisos up ON up.UsuarioID=u.UsuarioID AND up.PermisoID=p.PermisoID WHERE u.Activo=1 AND r.Activo=1 AND COALESCE(up.Permitido,CASE WHEN rp.PermisoID IS NULL THEN 0 ELSE 1 END)=1")->fetchColumn();
  if(!$gestores)throw new InvalidArgumentException('Debe quedar al menos un usuario activo con permiso para administrar accesos.');
  auditar($db,'permisos.'.$tipo,$id);$db->commit();redirigir('index.php?guardado=1');
 }catch(Throwable $error){if($db->inTransaction())$db->rollBack();$errores[]=errorFarmacia($error);}
}
$roles=$db->query('SELECT * FROM Roles WHERE Activo=1 ORDER BY NombreRol')->fetchAll(PDO::FETCH_ASSOC);
$usuarios=$db->query('SELECT u.UsuarioID,u.NombreCompleto,u.NombreUsuario,u.RolID,r.NombreRol FROM Usuarios u JOIN Roles r ON r.RolID=u.RolID ORDER BY u.NombreCompleto')->fetchAll(PDO::FETCH_ASSOC);
$seleccion=[];$excepciones=[];$rolUsuario=0;$registro=null;
if($tipo==='rol'){
 $registro=['NombreRol'=>'','Descripcion'=>''];
 if($id){$stmt=$db->prepare('SELECT * FROM Roles WHERE RolID=?');$stmt->execute([$id]);$registro=$stmt->fetch(PDO::FETCH_ASSOC);if(!$registro){http_response_code(404);exit('Rol no encontrado.');}$stmt=$db->prepare('SELECT PermisoID FROM RolPermisos WHERE RolID=?');$stmt->execute([$id]);$seleccion=$stmt->fetchAll(PDO::FETCH_COLUMN);}
 if($_SERVER['REQUEST_METHOD']==='POST'){$registro['NombreRol']=$_POST['nombre']??'';$registro['Descripcion']=$_POST['descripcion']??'';$seleccion=$_POST['permisos']??[];}
}elseif($tipo==='usuario'){
 foreach($usuarios as $u)if((int)$u['UsuarioID']===$id)$registro=$u;
 if(!$registro){http_response_code(404);exit('Usuario no encontrado.');}
 $rolUsuario=(int)($_POST['rol']??$registro['RolID']);
 $stmt=$db->prepare('SELECT PermisoID,Permitido FROM UsuarioPermisos WHERE UsuarioID=?');$stmt->execute([$id]);$excepciones=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
 if($_SERVER['REQUEST_METHOD']==='POST')$excepciones=$_POST['excepciones']??[];
}
$rolPermisos=$db->query('SELECT RolID,PermisoID FROM RolPermisos')->fetchAll(PDO::FETCH_ASSOC);
$titulo='Roles y permisos';require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-shield-lock"></i> Roles y permisos</h1>
<?php erroresVista($errores); if(isset($_GET['guardado'])): ?><div class="alert alert-success">Accesos actualizados. Se aplican desde la siguiente solicitud.</div><?php endif; ?>
<?php if($tipo===''): ?>
<div class="alert alert-info">Los roles agrupan permisos. Cada usuario puede heredarlos, permitirlos o denegarlos individualmente. La excepción individual prevalece.</div>
<div class="card"><div class="card-header d-flex justify-content-between"><span>Roles configurables</span><a class="btn btn-light btn-sm" href="?tipo=rol">Nuevo rol</a></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>Rol</th><th>Descripción</th><th>Acciones</th></tr></thead><tbody><?php foreach($roles as $r): ?><tr><td><?=h($r['NombreRol'])?></td><td><?=h($r['Descripcion'])?></td><td><a class="btn btn-primary btn-sm" href="?tipo=rol&id=<?=$r['RolID']?>">Configurar permisos</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-header">Accesos individuales</div><div class="card-body table-responsive"><table class="table"><thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Acciones</th></tr></thead><tbody><?php foreach($usuarios as $u): ?><tr><td><?=h($u['NombreUsuario'])?></td><td><?=h($u['NombreCompleto'])?></td><td><?=h($u['NombreRol'])?></td><td><a class="btn btn-primary btn-sm" href="?tipo=usuario&id=<?=$u['UsuarioID']?>">Rol y excepciones</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php else: ?>
<form method="post"><?=csrfCampo()?><div class="card"><div class="card-header"><?=$tipo==='rol'?'Configurar rol':'Accesos de '.h($registro['NombreCompleto'])?></div><div class="card-body">
<?php if($tipo==='rol'): ?><div class="row"><div class="col-md-5 mb-3"><label class="form-label" for="nombre">Nombre del rol *</label><input class="form-control" id="nombre" name="nombre" required maxlength="50" value="<?=h($registro['NombreRol'])?>"></div><div class="col-md-7 mb-3"><label class="form-label" for="descripcion">Descripción</label><input class="form-control" id="descripcion" name="descripcion" maxlength="200" value="<?=h($registro['Descripcion'])?>"></div></div>
<?php else: ?><label class="form-label" for="rol">Rol base</label><select class="form-select mb-3" name="rol" id="rol"><?php foreach($roles as $r): ?><option value="<?=$r['RolID']?>" <?=$rolUsuario===$r['RolID']?'selected':''?>><?=h($r['NombreRol'])?></option><?php endforeach; ?></select><?php endif; ?>
<div class="alert alert-info">Configura cada acción de farmacia por separado. Las excepciones individuales prevalecen sobre el rol. Las acciones de venta requieren también Consultar ventas.</div>
<label class="form-label" for="buscar-permisos">Buscar módulo o acción</label><input class="form-control mb-3" type="search" id="buscar-permisos" placeholder="Ejemplo: ventas, descuentos, inventario"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Módulo</th><th>Acción</th><th><?=$tipo==='rol'?'Permitir':'Excepción individual'?></th><?php if($tipo==='usuario'): ?><th>Permiso del rol</th><th>Acceso resultante</th><?php endif; ?></tr></thead><tbody>
<?php foreach($permisos as $p): ?><tr data-permiso="<?=$p['PermisoID']?>"><td><?=h($p['Modulo'])?></td><td><?=h($p['Nombre'])?></td><td><?php if($tipo==='rol'): ?><input class="form-check-input" type="checkbox" name="permisos[]" value="<?=$p['PermisoID']?>" <?=in_array($p['PermisoID'],$seleccion)?'checked':''?> aria-label="<?=h($p['Nombre'])?>"><?php else: ?><select class="form-select excepcion" name="excepciones[<?=$p['PermisoID']?>]" aria-label="<?=h($p['Nombre'])?>"><?php $valor=array_key_exists($p['PermisoID'],$excepciones)?(string)$excepciones[$p['PermisoID']]:'heredar'; foreach(['heredar'=>'Heredar del rol','1'=>'Permitir','0'=>'Denegar'] as $key=>$label): ?><option value="<?=$key?>" <?=$valor===(string)$key?'selected':''?>><?=$label?></option><?php endforeach; ?></select><?php endif; ?></td><?php if($tipo==='usuario'): ?><td class="permiso-rol"></td><td class="permiso-final"></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div><button class="btn btn-primary">Guardar accesos</button> <a class="btn btn-secondary" href="index.php">Cancelar</a></div></div></form>
<?php if($tipo==='usuario'): ?><script type="application/json" id="mapa-roles"><?=json_encode($rolPermisos,JSON_HEX_TAG)?></script><script src="/clinica/js/permisos.js"></script><?php endif; ?>
<?php endif; ?><script src="/clinica/js/buscar_permisos.js?v=4"></script><?php require '../includes/footer.php'; ?>
