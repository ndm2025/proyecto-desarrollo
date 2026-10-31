<?php
require_once '../includes/farmacia.php';exigirPermiso('usuarios.ver');$db=farmaciaDb();$id=(int)($_GET['id']??0);
$stmt=$db->prepare('SELECT u.UsuarioID,u.NombreUsuario,u.NombreCompleto,u.Email,u.Telefono,u.Activo,r.NombreRol FROM Usuarios u JOIN Roles r ON r.RolID=u.RolID WHERE UsuarioID=?');$stmt->execute([$id]);$u=$stmt->fetch(PDO::FETCH_ASSOC);if(!$u){http_response_code(404);exit('Usuario no encontrado.');}
$stmt=$db->prepare('SELECT h.*,a.NombreCompleto AS Responsable FROM HistorialUsuarios h JOIN Usuarios a ON a.UsuarioID=h.UsuarioID WHERE h.UsuarioObjetivoID=? ORDER BY h.CambioID DESC LIMIT 50');$stmt->execute([$id]);$historial=$stmt->fetchAll(PDO::FETCH_ASSOC);$titulo='Detalle de usuario';require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-person"></i> <?=h($u['NombreCompleto'])?></h1>
<?php if(isset($_GET['guardado'])): ?><div class="alert alert-success">Operación guardada correctamente.</div><?php endif; ?>
<div class="card"><div class="card-header">Información del usuario</div><div class="card-body"><p><strong>Usuario:</strong> <?=h($u['NombreUsuario'])?></p><p><strong>Rol:</strong> <?=h($u['NombreRol'])?></p><p><strong>Correo:</strong> <?=h($u['Email'])?></p><p><strong>Teléfono:</strong> <?=h($u['Telefono'])?></p><p><strong>Estado:</strong> <?=$u['Activo']?'Activo':'Inactivo'?></p><div class="d-flex flex-wrap gap-2">
<?php if(tienePermiso('usuarios.editar')): ?><a class="btn btn-primary" href="editar.php?id=<?=$id?>">Editar datos</a><?php endif; ?>
<?php if(tienePermiso('usuarios.estado')): ?><a class="btn <?=$u['Activo']?'btn-warning':'btn-success'?>" href="estado.php?id=<?=$id?>"><?=$u['Activo']?'Desactivar':'Activar'?> usuario</a><?php endif; ?>
<?php if(tienePermiso('usuarios.contrasena')): ?><a class="btn btn-outline-primary" href="contrasena.php?id=<?=$id?>">Restablecer contraseña</a><?php endif; ?>
<?php if(tienePermiso('permisos.administrar')): ?><a class="btn btn-outline-primary" href="../permisos/index.php?tipo=usuario&id=<?=$id?>">Rol y permisos</a><?php endif; ?>
</div></div></div>
<div class="card"><div class="card-header">Historial de estado y seguridad</div><div class="card-body table-responsive"><table class="table table-sm"><thead><tr><th>Fecha</th><th>Acción</th><th>Responsable</th><th>Motivo</th></tr></thead><tbody><?php foreach($historial as $h): ?><tr><td><?=h($h['Fecha'])?></td><td><?=h(['usuario.activado'=>'Activación','usuario.desactivado'=>'Desactivación','usuario.clave_restablecida'=>'Restablecimiento de contraseña'][$h['Accion']]??$h['Accion'])?></td><td><?=h($h['Responsable'])?></td><td><?=h($h['Motivo'])?></td></tr><?php endforeach; ?><?php if(!$historial): ?><tr><td colspan="4" class="text-muted">Sin cambios registrados.</td></tr><?php endif; ?></tbody></table><small class="text-muted">Últimos 50 cambios. Las contraseñas nunca se muestran.</small></div></div><a class="btn btn-secondary" href="index.php">Volver</a>
<?php require '../includes/footer.php'; ?>
