<?php
require_once '../includes/farmacia.php';exigirPermiso('proveedores.editar');$db=farmaciaDb();$id=isset($editar)?(int)($_GET['id']??0):0;$errores=[];
$datos=['Nombre'=>'','NIT'=>'','Telefono'=>'','Email'=>'','Direccion'=>'','Activo'=>1];
if(isset($editar)){$stmt=$db->prepare('SELECT * FROM Proveedores WHERE ProveedorID=?');$stmt->execute([$id]);$datos=$stmt->fetch(PDO::FETCH_ASSOC);if(!$datos){http_response_code(404);exit('Proveedor no encontrado.');}}
if($_SERVER['REQUEST_METHOD']==='POST'){
 validarCsrf();
 try{
  foreach(['Nombre'=>150,'NIT'=>30,'Telefono'=>30,'Email'=>150,'Direccion'=>255] as $campo=>$max){$datos[$campo]=trim((string)($_POST[$campo]??''));if(mb_strlen($datos[$campo])>$max)throw new InvalidArgumentException('Revisa la longitud de los campos.');}
  $datos['Activo']=isset($_POST['Activo'])?1:0;
  if($datos['Nombre']==='')throw new InvalidArgumentException('El nombre del proveedor es obligatorio.');
  if($datos['Email']!==''&&!filter_var($datos['Email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('El correo no es válido.');
  $params=[$datos['Nombre'],$datos['NIT']?:null,$datos['Telefono'],$datos['Email'],$datos['Direccion'],$datos['Activo']];
  $db->beginTransaction();
  if($id)$db->prepare('UPDATE Proveedores SET Nombre=?,NIT=?,Telefono=?,Email=?,Direccion=?,Activo=? WHERE ProveedorID=?')->execute([...$params,$id]);
  else{$db->prepare('INSERT INTO Proveedores(Nombre,NIT,Telefono,Email,Direccion,Activo) VALUES(?,?,?,?,?,?)')->execute($params);$id=(int)$db->lastInsertId();}
  auditar($db,'proveedor.guardar',$id);$db->commit();redirigir('index.php?guardado=1');
 }catch(Throwable $error){if($db->inTransaction())$db->rollBack();$errores[]=errorFarmacia($error);}
}
$titulo=$id?'Editar proveedor':'Nuevo proveedor';require '../includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-truck"></i> <?=h($titulo)?></h1>
<?php erroresVista($errores); ?>
<div class="card"><div class="card-header">Información del proveedor</div><div class="card-body"><form method="post"><?=csrfCampo()?><div class="row">
<?php foreach(['Nombre'=>['Nombre *',150],'NIT'=>['NIT',30],'Telefono'=>['Teléfono',30],'Email'=>['Correo electrónico',150],'Direccion'=>['Dirección',255]] as $campo=>$config): ?><div class="col-md-6 mb-3"><label class="form-label" for="<?=$campo?>"><?=$config[0]?></label><input class="form-control" id="<?=$campo?>" name="<?=$campo?>" type="<?=$campo==='Email'?'email':'text'?>" maxlength="<?=$config[1]?>" value="<?=h($datos[$campo])?>" <?=$campo==='Nombre'?'required':''?>></div><?php endforeach; ?>
<div class="col-12 mb-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="Activo" <?=$datos['Activo']?'checked':''?>> Proveedor activo</label></div></div><button class="btn btn-primary">Guardar proveedor</button> <a class="btn btn-secondary" href="index.php">Cancelar</a></form></div></div>
<?php require '../includes/footer.php'; ?>
