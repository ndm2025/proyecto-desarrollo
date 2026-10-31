<?php
function textoFiltro($value,$max=150) {
    if(!is_string($value)&&!is_int($value))throw new InvalidArgumentException('Un filtro no tiene un valor válido.');
    $value=trim((string)$value);
    if(mb_strlen($value)>$max)throw new InvalidArgumentException('El texto de búsqueda es demasiado largo.');
    return $value;
}
function opcionFiltro($value,$opciones) {
    if(!is_string($value)||!in_array($value,$opciones,true))throw new InvalidArgumentException('Selecciona un filtro válido.');
    return $value;
}
function filtrosInventario($datos,$lotes=false) {
    $f=['q'=>textoFiltro($datos['q']??''),'estado'=>opcionFiltro($datos['estado']??'',['','0','1']),'pagina'=>entero($datos['pagina']??1,1,2147483647)];
    if($lotes){
        $f['producto']=entero($datos['producto']??0,0,2147483647);
        $f['vence']=opcionFiltro($datos['vence']??'',['','vigentes','proximos','vencidos']);
        $f['disponibilidad']=opcionFiltro($datos['disponibilidad']??'',['','disponible','bloqueado','agotado']);
        $f['dias']=entero($datos['dias']??30,1,365);
        $f['con_unidades']=entero($datos['con_unidades']??0,0,1);
    }else $f['situacion']=opcionFiltro($datos['situacion']??'',['','bajo','sin_stock','con_stock']);
    return $f;
}
function consultaInventario($f,$costos=false) {
    $costo=$costos?"(SELECT CAST(d.CostoPresentacion/NULLIF(d.UnidadesPorPresentacion,0) AS DECIMAL(18,6)) FROM CompraDetalle d JOIN Compras c ON c.CompraID=d.CompraID WHERE d.MedicamentoID=m.MedicamentoID AND c.Estado='Recibida' ORDER BY d.CompraDetalleID DESC LIMIT 1)":'NULL';
    $sql="SELECT * FROM (SELECT m.MedicamentoID,m.Codigo,m.NombreMedicamento,m.PrincipioActivo,m.UnidadBase,m.StockMinimo,m.Activo,
    COALESCE(SUM(l.CantidadActual),0) AS Fisicas,
    COALESCE(SUM(CASE WHEN l.Activo=1 AND m.Activo=1 AND l.FechaVencimiento>=CURDATE() THEN l.CantidadActual ELSE 0 END),0) AS Disponibles,
    COALESCE(SUM(CASE WHEN (l.Activo=0 OR m.Activo=0) AND l.FechaVencimiento>=CURDATE() THEN l.CantidadActual ELSE 0 END),0) AS Bloqueadas,
    COALESCE(SUM(CASE WHEN l.FechaVencimiento<CURDATE() THEN l.CantidadActual ELSE 0 END),0) AS Vencidas,
    $costo AS CostoReferencia FROM Medicamentos m LEFT JOIN LotesMedicamentos l ON l.MedicamentoID=m.MedicamentoID
    GROUP BY m.MedicamentoID,m.Codigo,m.NombreMedicamento,m.PrincipioActivo,m.UnidadBase,m.StockMinimo,m.Activo) inventario WHERE 1=1";
    $params=[];
    if($f['q']!==''){$sql.=' AND (Codigo LIKE ? OR NombreMedicamento LIKE ? OR PrincipioActivo LIKE ?)';$params=array_fill(0,3,'%'.$f['q'].'%');}
    if($f['estado']!==''){$sql.=' AND Activo=?';$params[]=$f['estado'];}
    if($f['situacion']==='bajo')$sql.=' AND Disponibles<=StockMinimo';
    if($f['situacion']==='sin_stock')$sql.=' AND Disponibles=0';
    if($f['situacion']==='con_stock')$sql.=' AND Disponibles>0';
    return [$sql,$params];
}
function consultaLotes($f,$costos=false) {
    $costo=$costos?"(SELECT CAST(d.CostoPresentacion/NULLIF(d.UnidadesPorPresentacion,0) AS DECIMAL(18,6)) FROM CompraDetalle d JOIN Compras c ON c.CompraID=d.CompraID WHERE d.LoteID=l.LoteID AND c.Estado='Recibida' ORDER BY d.CompraDetalleID DESC LIMIT 1)":'NULL';
    $sql="SELECT l.*,m.Codigo,m.NombreMedicamento,m.UnidadBase,m.Activo AS ProductoActivo,DATEDIFF(l.FechaVencimiento,CURDATE()) AS DiasVencimiento,
    CASE WHEN l.Activo=1 AND m.Activo=1 AND l.FechaVencimiento>=CURDATE() THEN l.CantidadActual ELSE 0 END AS Disponibles,
    $costo AS CostoReferencia FROM LotesMedicamentos l JOIN Medicamentos m ON m.MedicamentoID=l.MedicamentoID WHERE 1=1";
    $params=[];
    if($f['q']!==''){$sql.=' AND (m.Codigo LIKE ? OR m.NombreMedicamento LIKE ? OR l.NumeroLote LIKE ?)';$params=array_fill(0,3,'%'.$f['q'].'%');}
    if($f['con_unidades'])$sql.=' AND l.CantidadActual>0';
    if($f['producto']){$sql.=' AND l.MedicamentoID=?';$params[]=$f['producto'];}
    if($f['estado']!==''){$sql.=' AND l.Activo=?';$params[]=$f['estado'];}
    if($f['vence']==='vigentes')$sql.=' AND l.FechaVencimiento>=CURDATE()';
    if($f['vence']==='vencidos')$sql.=' AND l.FechaVencimiento<CURDATE()';
    if($f['vence']==='proximos'){$sql.=' AND l.FechaVencimiento>=CURDATE() AND l.FechaVencimiento<=?';$params[]=(new DateTimeImmutable('today'))->modify('+'.$f['dias'].' days')->format('Y-m-d');}
    if($f['disponibilidad']==='disponible')$sql.=' AND l.Activo=1 AND m.Activo=1 AND l.FechaVencimiento>=CURDATE() AND l.CantidadActual>0';
    if($f['disponibilidad']==='bloqueado')$sql.=' AND (l.Activo=0 OR m.Activo=0) AND l.FechaVencimiento>=CURDATE() AND l.CantidadActual>0';
    if($f['disponibilidad']==='agotado')$sql.=' AND l.CantidadActual=0';
    return [$sql,$params];
}
function paginarInventario($db,$sql,$params,$pagina,$orden,$tamano=20) {
    $stmt=$db->prepare('SELECT COUNT(*) FROM ('.$sql.') conteo');$stmt->execute($params);$total=(int)$stmt->fetchColumn();
    $paginas=max(1,(int)ceil($total/$tamano));$pagina=min($pagina,$paginas);
    $stmt=$db->prepare($sql.' ORDER BY '.$orden.' LIMIT '.$tamano.' OFFSET '.(($pagina-1)*$tamano));$stmt->execute($params);
    return ['filas'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas];
}
function resumenInventario($db,$sql,$params) {
    $stmt=$db->prepare('SELECT COUNT(*) AS Productos,COALESCE(SUM(Fisicas),0) AS Fisicas,COALESCE(SUM(Disponibles),0) AS Disponibles,COALESCE(SUM(Bloqueadas),0) AS Bloqueadas,COALESCE(SUM(Vencidas),0) AS Vencidas FROM ('.$sql.') resumen');
    $stmt->execute($params);return $stmt->fetch(PDO::FETCH_ASSOC);
}
function paginacionInventario($datos,$filtros) {
    if($datos['total']===0)return;
    echo '<div class="d-flex flex-wrap justify-content-between gap-2 mt-3"><span class="text-muted">'.h($datos['total']).' registros · Página '.h($datos['pagina']).' de '.h($datos['paginas']).'</span><nav aria-label="Paginación">';
    foreach(['Anterior'=>$datos['pagina']-1,'Siguiente'=>$datos['pagina']+1] as $label=>$pagina){
        if($pagina<1||$pagina>$datos['paginas'])continue;
        echo '<a class="btn btn-outline-primary btn-sm ms-1" href="?'.h(http_build_query(array_merge($filtros,['pagina'=>$pagina]))).'">'.$label.'</a>';
    }
    echo '</nav></div>';
}
function estadoDisponibilidad($lote) {
    if((int)$lote['CantidadActual']===0)return 'Agotado';
    if($lote['FechaVencimiento']<date('Y-m-d'))return 'Vencido';
    if(!$lote['Activo']||!$lote['ProductoActivo'])return 'Bloqueado';
    return 'Disponible';
}
function costoReferencia($costo) { return $costo===null?'Sin costo de compra vigente':'Q '.number_format((float)$costo,6,'.',','); }
function celdaCsv($valor) {
    if(is_int($valor)||is_float($valor))return $valor;
    $valor=(string)($valor??'');
    if(preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$valor)||preg_match('/^[\t\r\n]/',$valor))return "'".$valor;
    return $valor;
}
function escribirCsv($archivo,$valores) { fputcsv($archivo,array_map('celdaCsv',$valores),',','"',''); }

function iniciarConsultaInventario($db) {
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
}
