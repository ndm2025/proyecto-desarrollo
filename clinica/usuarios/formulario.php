<?php
require_once '../includes/farmacia.php';
require_once '../includes/usuarios_servicio.php';
exigirPermiso('usuarios.editar');
$id=isset($editar)?(int)($_GET['id']??0):0;
if(!isset($editar))exigirPermiso('permisos.administrar');
$db=farmaciaDb();$errores=[];
$datos=['NombreUsuario'=>'','NombreCompleto'=>'','Email'=>'','Telefono'=>'','RolID'=>''];
if(isset($editar)){
    $stmt=$db->prepare('SELECT UsuarioID,NombreUsuario,NombreCompleto,Email,Telefono,RolID FROM Usuarios WHERE UsuarioID=?');$stmt->execute([$id]);$datos=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$datos){http_response_code(404);exit('Usuario no encontrado.');}
    try{protegerCuenta($db,$id);}catch(InvalidArgumentException $e){http_response_code(403);exit(h($e->getMessage()));}
}
$roles=$db->query('SELECT RolID,NombreRol FROM Roles WHERE Activo=1 ORDER BY NombreRol')->fetchAll(PDO::FETCH_ASSOC);
if($_SERVER['REQUEST_METHOD']==='POST'){
    validarCsrf();
    try{
        foreach(['NombreCompleto'=>150,'Email'=>100,'Telefono'=>20] as $campo=>$max){$datos[$campo]=trim((string)($_POST[$campo]??''));if(mb_strlen($datos[$campo])>$max)throw new InvalidArgumentException('Revisa la longitud de los campos.');}
        if($datos['NombreCompleto']===''||!filter_var($datos['Email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Completa el nombre y un correo válido.');
        $db->beginTransaction();
        if($id){
            $stmt=$db->prepare('SELECT UsuarioID FROM Usuarios WHERE UsuarioID=? FOR UPDATE');$stmt->execute([$id]);protegerCuenta($db,$id);
            $db->prepare('UPDATE Usuarios SET NombreCompleto=?,Email=?,Telefono=? WHERE UsuarioID=?')->execute([$datos['NombreCompleto'],$datos['Email'],$datos['Telefono'],$id]);
        }else{
            $datos['NombreUsuario']=trim((string)($_POST['NombreUsuario']??''));$datos['RolID']=entero($_POST['RolID']??'');
            if(!preg_match('/^[a-zA-Z0-9._-]{3,50}$/D',$datos['NombreUsuario']))throw new InvalidArgumentException('Usa un usuario de 3 a 50 letras, números, puntos, guiones o guiones bajos.');
            validarClaveNueva($_POST['password']??'',$_POST['confirmacion']??'');
            $stmt=$db->prepare('SELECT RolID FROM Roles WHERE RolID=? AND Activo=1');$stmt->execute([$datos['RolID']]);if(!$stmt->fetchColumn())throw new InvalidArgumentException('Selecciona un rol activo.');
            $db->prepare('INSERT INTO Usuarios(NombreUsuario,PasswordHash,NombreCompleto,Email,Telefono,RolID) VALUES(?,?,?,?,?,?)')->execute([$datos['NombreUsuario'],hashClave($_POST['password']),$datos['NombreCompleto'],$datos['Email'],$datos['Telefono'],$datos['RolID']]);$id=(int)$db->lastInsertId();
        }
        auditar($db,'usuario.datos_guardados',$id);$db->commit();redirigir('ver.php?id='.$id.'&guardado=1');
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();$errores[]=errorFarmacia($error);}
}
$titulo=isset($editar)?'Editar usuario':'Nuevo usuario';require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-person"></i> <?=h($titulo)?></h1>
<?php erroresVista($errores); ?>
<div class="card"><div class="card-header">Datos del usuario</div><div class="card-body"><form method="post"><?=csrfCampo()?><div class="row g-3">
<?php if(!isset($editar)): ?>
<div class="col-md-6"><label class="form-label" for="NombreUsuario">Usuario *</label><input class="form-control" id="NombreUsuario" name="NombreUsuario" maxlength="50" required value="<?=h($datos['NombreUsuario'])?>"></div>
<div class="col-md-6"><label class="form-label" for="RolID">Rol inicial *</label><select class="form-select" id="RolID" name="RolID" required><option value="">Selecciona...</option><?php foreach($roles as $rol): ?><option value="<?=$rol['RolID']?>" <?=$datos['RolID']==$rol['RolID']?'selected':''?>><?=h($rol['NombreRol'])?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="password">Contraseña inicial *</label><input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
<div class="col-md-6"><label class="form-label" for="confirmacion">Confirmar contraseña *</label><input class="form-control" type="password" id="confirmacion" name="confirmacion" minlength="8" maxlength="72" autocomplete="new-password" required></div>
<?php else: ?><p class="mb-0"><strong>Usuario:</strong> <?=h($datos['NombreUsuario'])?>. El rol se administra en Roles y permisos. La contraseña se cambia con un permiso independiente.</p><?php endif; ?>
<?php foreach(['NombreCompleto'=>['Nombre completo *',150],'Email'=>['Correo electrónico *',100],'Telefono'=>['Teléfono',20]] as $campo=>$config): ?><div class="col-md-6"><label class="form-label" for="<?=$campo?>"><?=$config[0]?></label><input class="form-control" type="<?=$campo==='Email'?'email':'text'?>" id="<?=$campo?>" name="<?=$campo?>" maxlength="<?=$config[1]?>" value="<?=h($datos[$campo])?>" <?=$campo==='Telefono'?'':'required'?>></div><?php endforeach; ?>
</div><div class="d-flex gap-2 mt-3"><button class="btn btn-primary">Guardar datos</button><a class="btn btn-secondary" href="index.php">Cancelar</a></div></form></div></div>
<?php require '../includes/footer.php'; ?>
