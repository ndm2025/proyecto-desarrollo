<?php
require_once '../includes/farmacia.php';
require_once '../includes/usuarios_servicio.php';
if(!isset($operacion)||!in_array($operacion,['clave','estado'],true)){http_response_code(404);exit('Operación no encontrada.');}
$esClave=$operacion==='clave';
exigirPermiso($esClave?'usuarios.contrasena':'usuarios.estado');
$db=farmaciaDb();$id=(int)($_GET['id']??0);$errores=[];
$stmt=$db->prepare('SELECT UsuarioID,NombreUsuario,NombreCompleto,Activo FROM Usuarios WHERE UsuarioID=?');$stmt->execute([$id]);$usuario=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$usuario){http_response_code(404);exit('Usuario no encontrado.');}
try{protegerCuenta($db,$id);}catch(InvalidArgumentException $e){http_response_code(403);exit(h($e->getMessage()));}
if($_SERVER['REQUEST_METHOD']==='POST'){
    validarCsrf();
    try{
        if($esClave)restablecerClaveUsuario($db,$id,$_POST['clave']??'',$_POST['confirmacion']??'',$_POST['motivo']??'');
        else{
            if(!in_array($_POST['estado']??null,['0','1'],true))throw new InvalidArgumentException('Estado inválido.');
            cambiarEstadoUsuario($db,$id,(int)$_POST['estado'],$_POST['motivo']??'');
        }
        redirigir('ver.php?id='.$id.'&guardado=1');
    }catch(Throwable $error){$errores[]=errorFarmacia($error);}
}
$titulo=$esClave?'Restablecer contraseña':($usuario['Activo']?'Desactivar usuario':'Activar usuario');require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-person-lock"></i> <?=h($titulo)?></h1>
<?php erroresVista($errores); ?>
<div class="card"><div class="card-header"><?=h($usuario['NombreCompleto'])?> · <?=h($usuario['NombreUsuario'])?></div><div class="card-body">
<div class="alert alert-info"><?=$esClave?'Se cerrarán las sesiones anteriores de esta cuenta. La contraseña no se mostrará ni quedará en el historial.':'El cambio de estado cerrará las sesiones anteriores. Los registros y operaciones del usuario se conservarán.'?></div>
<form method="post"><?=csrfCampo()?>
<?php if($esClave): ?><div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="clave">Nueva contraseña *</label><input class="form-control" type="password" id="clave" name="clave" minlength="8" maxlength="72" autocomplete="new-password" required></div><div class="col-md-6 mb-3"><label class="form-label" for="confirmacion">Confirmar contraseña *</label><input class="form-control" type="password" id="confirmacion" name="confirmacion" minlength="8" maxlength="72" autocomplete="new-password" required></div></div>
<?php else: ?><input type="hidden" name="estado" value="<?=$usuario['Activo']?'0':'1'?>"><?php endif; ?>
<div class="mb-3"><label class="form-label" for="motivo">Motivo *</label><textarea class="form-control" id="motivo" name="motivo" maxlength="500" rows="3" required><?=h($_POST['motivo']??'')?></textarea></div>
<button class="btn <?=$esClave?'btn-primary':($usuario['Activo']?'btn-warning':'btn-success')?>">Confirmar</button> <a class="btn btn-secondary" href="ver.php?id=<?=$id?>">Cancelar</a>
</form></div></div>
<?php require '../includes/footer.php'; ?>
