(() => {
    const config=JSON.parse(document.getElementById('catalogo-ventas').textContent);
    const productos=config.productos,porId=new Map(productos.map(p=>[String(p.MedicamentoID),p]));
    const form=document.getElementById('form-venta'),buscar=document.getElementById('buscar'),catalogo=document.getElementById('catalogo-resultados'),cuerpo=document.getElementById('venta-lineas');
    const cliente=document.getElementById('cliente'),efectivo=document.getElementById('efectivo'),confirmar=document.getElementById('confirmar'),operacion=document.getElementById('operacion-venta');
    const clave='clinica.venta.borrador.'+config.usuario;let contador=0,iniciando=true,guardando=false,sinAlmacen=false;
    const filas=new Map(),catalogoFilas=new Map();
    const texto=v=>typeof v==='string'||typeof v==='number'?String(v):'';
    const normal=v=>texto(v).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
    const importe=v=>/^\d{1,8}(\.\d{1,2})?$/.test(texto(v))?Math.round(Number(v)*100):NaN;
    const q=v=>'Q '+((Number.isFinite(v)?v:0)/100).toLocaleString('es-GT',{minimumFractionDigits:2,maximumFractionDigits:2});
    function crear(tag,clase,contenido){const e=document.createElement(tag);if(clase)e.className=clase;if(contenido!==undefined)e.textContent=contenido;return e;}
    function icono(nombre){const e=crear('i','bi bi-'+nombre);e.setAttribute('aria-hidden','true');return e;}
    function aviso(mensaje,tipo='info'){const e=document.getElementById('venta-aviso');e.textContent=mensaje;e.className='alert alert-'+tipo+(mensaje?'':' d-none');}
    function presentacion(fila){return fila.p.presentaciones.find(p=>String(p.PresentacionID)===fila.pres.value);}
    function demanda(){const mapa=new Map();for(const f of filas.values()){const n=Number(f.cant.value),p=presentacion(f);if(p&&Number.isInteger(n)&&n>0)mapa.set(String(f.p.MedicamentoID),(mapa.get(String(f.p.MedicamentoID))||0)+n*Number(p.UnidadesBase));}return mapa;}
    function datosBorrador(){return {version:1,fecha:Date.now(),operacion:operacion.value,cliente:cliente.value,efectivo:efectivo.value,lineas:[...filas.values()].map(f=>({producto:f.p.MedicamentoID,presentacion:f.pres.value,cantidad:f.cant.value,descuento:f.desc.value,precio:f.precio.value,unidades:f.factor.value}))};}
    function persistir(){
        if(iniciando||guardando)return;
        try{if(filas.size||cliente.value||efectivo.value)sessionStorage.setItem(clave,JSON.stringify(datosBorrador()));else sessionStorage.removeItem(clave);sinAlmacen=false;document.getElementById('borrador-estado').textContent=filas.size?'Borrador guardado en esta pestaña':'Sin productos';}
        catch(e){sinAlmacen=true;document.getElementById('borrador-estado').textContent='No se pudo guardar el borrador';}
    }
    function actualizarCatalogo(){const usadas=demanda();for(const f of catalogoFilas.values()){const pr=f.p.presentaciones.find(x=>String(x.PresentacionID)===f.pres.value);const disponible=Math.max(0,Number(f.p.Disponibles)-(usadas.get(String(f.p.MedicamentoID))||0));const cantidad=Math.floor(disponible/Number(pr.UnidadesBase));f.stock.textContent=cantidad.toLocaleString('es-GT');f.stock.title=disponible+' '+f.p.UnidadBase+' disponibles';f.base.textContent=disponible+' '+f.p.UnidadBase;f.precio.textContent=q(importe(pr.PrecioVenta));f.boton.disabled=cantidad<1;f.boton.title=cantidad<1?'Sin existencias para esta presentación':'Agregar a la venta';f.row.classList.toggle('sin-existencias',cantidad<1);}}
    function recalcular(){
        let sub=0,des=0,problema='';const usadas=demanda();
        for(const f of filas.values()){
            const pr=presentacion(f),cantidad=Number(f.cant.value),precio=importe(pr.PrecioVenta),d=importe(f.desc.value),bruto=cantidad*precio;
            f.precio.value=pr.PrecioVenta;f.factor.value=pr.UnidadesBase;f.precioTexto.textContent=q(precio);f.unidades.textContent=(Number.isFinite(cantidad)?cantidad*Number(pr.UnidadesBase):0)+' '+f.p.UnidadBase;
            let error='';if(!Number.isInteger(cantidad)||cantidad<1||cantidad>1000000||cantidad*Number(pr.UnidadesBase)>100000000)error='Escribe una cantidad entera válida.';
            else if(!Number.isFinite(d)||d<0||d>bruto)error='El descuento debe estar entre Q 0.00 y '+q(bruto)+'.';
            else if((usadas.get(String(f.p.MedicamentoID))||0)>Number(f.p.Disponibles))error='Solo hay '+f.p.Disponibles+' '+f.p.UnidadBase+' disponibles para este producto.';
            f.cant.setAttribute('aria-invalid',String(!!error));f.desc.setAttribute('aria-invalid',String(!Number.isFinite(d)||d>bruto));f.error.textContent=error;f.row.classList.toggle('renglon-invalido',!!error);if(error&&!problema)problema=f.p.NombreMedicamento+': '+error;
            f.total.textContent=q(Number.isFinite(bruto)&&Number.isFinite(d)&&d<=bruto?bruto-d:bruto);f.desc.max=Number.isFinite(bruto)&&bruto>=0?(bruto/100).toFixed(2):'0';
            if(Number.isFinite(bruto))sub+=bruto;if(Number.isFinite(d))des+=d;
        }
        const total=sub-des,recibido=importe(efectivo.value),cambio=recibido-total;
        document.getElementById('suma-subtotal').textContent=q(sub);document.getElementById('suma-descuento').textContent=q(des);document.getElementById('suma-total').textContent=q(total);document.getElementById('total-movil').textContent=q(total);document.getElementById('suma-cambio').textContent=q(Number.isFinite(cambio)&&cambio>=0?cambio:0);
        let mensaje=!filas.size?'Agrega productos para comenzar.':problema||(!Number.isFinite(recibido)?'Ingresa el efectivo recibido.':cambio<0?'Faltan '+q(-cambio)+' para completar el pago.':'Pago completo. Puedes registrar la venta.');
        if(sub>999999999999)mensaje='El importe supera el máximo permitido.';
        const valido=filas.size>0&&!problema&&Number.isFinite(recibido)&&cambio>=0&&sub<=999999999999;
        document.getElementById('estado-cobro').textContent=mensaje;document.getElementById('estado-cobro').classList.toggle('pago-completo',!!valido);confirmar.disabled=!valido||guardando;
        document.getElementById('sin-lineas').hidden=filas.size>0;document.getElementById('cantidad-renglones').textContent=filas.size;document.getElementById('descartar-borrador').disabled=!filas.size&&!cliente.value&&!efectivo.value;
        actualizarCatalogo();persistir();return !!valido;
    }
    function campoCelda(clase,etiqueta){const e=crear('div',clase);e.append(crear('span','renglon-etiqueta',etiqueta));return e;}
    function quitar(f){filas.delete(f.id);f.row.remove();recalcular();}
    function agregar(p,pr,cantidad='1',descuento='0.00',restaurando=false){
        let existente=[...filas.values()].find(f=>f.pres.value===String(pr.PresentacionID));
        if(existente){existente.cant.value=Number(existente.cant.value)+Number(cantidad);existente.desc.value=((importe(existente.desc.value)||0)+(importe(descuento)||0))/100;recalcular();if(!restaurando)aviso('Sumamos la cantidad en '+p.NombreMedicamento+' · '+pr.Nombre+'.','success');return existente;}
        if(filas.size>=50){aviso('La venta admite hasta 50 presentaciones diferentes.','warning');return null;}
        const id=contador++,f={id,p};f.row=crear('div','venta-renglon');f.row.setAttribute('role','listitem');
        const producto=crear('div','renglon-producto');producto.append(crear('strong','',p.NombreMedicamento),crear('small','text-muted',p.Codigo));
        f.pres=crear('select','form-select form-select-sm');f.pres.name=`lineas[${id}][presentacion]`;f.pres.setAttribute('aria-label','Presentación de '+p.NombreMedicamento);for(const present of p.presentaciones)f.pres.add(new Option(`${present.Nombre} · ${present.UnidadesBase} ${p.UnidadBase}`,present.PresentacionID));f.pres.value=String(pr.PresentacionID);producto.append(f.pres);
        for(const [campo,valor] of [['producto',p.MedicamentoID],['precio',pr.PrecioVenta],['unidades',pr.UnidadesBase]]){const e=crear('input');Object.assign(e,{type:'hidden',name:`lineas[${id}][${campo}]`,value:String(valor)});producto.append(e);if(campo==='precio')f.precio=e;if(campo==='unidades')f.factor=e;}
        const cantCelda=campoCelda('renglon-cantidad','Cantidad');f.cant=crear('input','form-control form-control-sm');Object.assign(f.cant,{type:'number',min:'1',max:'1000000',step:'1',required:true,value:texto(cantidad),name:`lineas[${id}][cantidad]`});f.cant.setAttribute('aria-label','Cantidad de '+p.NombreMedicamento+' · '+pr.Nombre);f.unidades=crear('small','text-muted');cantCelda.append(f.cant,f.unidades);
        const precioCelda=campoCelda('renglon-precio','Precio');f.precioTexto=crear('span');precioCelda.append(f.precioTexto);
        const descCelda=campoCelda('renglon-descuento','Descuento Q');f.desc=crear('input','form-control form-control-sm');Object.assign(f.desc,{type:config.descuento?'number':'hidden',min:'0',step:'0.01',required:true,value:config.descuento?texto(descuento):'0.00',name:`lineas[${id}][descuento]`});f.desc.setAttribute('aria-label','Descuento de '+p.NombreMedicamento+' · '+pr.Nombre);descCelda.append(f.desc);if(!config.descuento)descCelda.append(crear('span','text-muted','—'));
        const totalCelda=campoCelda('renglon-importe','Importe');f.total=crear('strong');totalCelda.append(f.total);
        const borrar=crear('button','btn btn-outline-danger btn-sm renglon-quitar');borrar.type='button';borrar.append(icono('trash'));borrar.title='Quitar producto';borrar.setAttribute('aria-label','Quitar '+p.NombreMedicamento+' · '+pr.Nombre);borrar.addEventListener('click',()=>quitar(f));
        f.error=crear('p','renglon-error');f.error.id='error-renglon-'+id;f.error.setAttribute('role','status');f.cant.setAttribute('aria-describedby',f.error.id);f.desc.setAttribute('aria-describedby',f.error.id);
        f.row.append(producto,cantCelda,precioCelda,descCelda,totalCelda,borrar,f.error);cuerpo.append(f.row);filas.set(id,f);
        f.pres.addEventListener('change',()=>{const actual=presentacion(f);const repetida=[...filas.values()].find(x=>x!==f&&x.pres.value===f.pres.value);f.cant.setAttribute('aria-label','Cantidad de '+p.NombreMedicamento+' · '+actual.Nombre);f.desc.setAttribute('aria-label','Descuento de '+p.NombreMedicamento+' · '+actual.Nombre);if(repetida){repetida.cant.value=Number(repetida.cant.value)+Number(f.cant.value);repetida.desc.value=((importe(repetida.desc.value)||0)+(importe(f.desc.value)||0))/100;quitar(f);aviso('Agrupamos las presentaciones repetidas en una sola línea.','success');}recalcular();});
        f.cant.addEventListener('input',recalcular);f.desc.addEventListener('input',recalcular);recalcular();return f;
    }
    function agregarDesdeCatalogo(f){
        const pr=f.p.presentaciones.find(x=>String(x.PresentacionID)===f.pres.value),n=Number(f.cant.value),disponible=Number(f.p.Disponibles)-(demanda().get(String(f.p.MedicamentoID))||0);
        if(!Number.isInteger(n)||n<1||n>1000000){f.error.textContent='Escribe una cantidad entera mayor que cero.';f.cant.focus();return;}
        if(n*Number(pr.UnidadesBase)>disponible){f.error.textContent='No hay existencias suficientes para agregar esa cantidad.';f.cant.focus();return;}
        f.error.textContent='';const fila=agregar(f.p,pr,String(n));if(fila){f.cant.value='1';aviso(f.p.NombreMedicamento+' · '+pr.Nombre+' agregado. Las presentaciones iguales se agrupan.','success');buscar.focus();}
    }
    function renderCatalogo(){
        const qbus=normal(buscar.value);catalogo.replaceChildren();catalogoFilas.clear();const resultados=productos.filter(p=>normal(p.Codigo+' '+p.NombreMedicamento).includes(qbus));
        document.getElementById('catalogo-cantidad').textContent=resultados.length+(resultados.length===1?' producto':' productos');document.getElementById('catalogo-vacio').hidden=resultados.length>0;
        for(const p of resultados){
            const f={p};f.row=crear('div','catalogo-fila');f.row.setAttribute('role','listitem');const nombre=crear('div','catalogo-producto');nombre.append(crear('strong','',p.NombreMedicamento),crear('small','text-muted',p.Codigo));
            f.pres=crear('select','form-select form-select-sm');f.pres.setAttribute('aria-label','Presentación para agregar '+p.NombreMedicamento);for(const pr of p.presentaciones)f.pres.add(new Option(`${pr.Nombre} (${pr.UnidadesBase})`,pr.PresentacionID));
            const existencias=crear('div','catalogo-stock');f.stock=crear('strong');f.base=crear('small','text-muted');existencias.append(f.stock,f.base);f.precio=crear('strong','catalogo-precio');
            f.cant=crear('input','form-control form-control-sm catalogo-cantidad');Object.assign(f.cant,{type:'number',min:'1',max:'1000000',step:'1',value:'1'});f.cant.setAttribute('aria-label','Cantidad para agregar '+p.NombreMedicamento);
            f.boton=crear('button','btn btn-primary btn-sm catalogo-agregar');f.boton.type='button';f.boton.append(icono('plus-lg'));f.boton.setAttribute('aria-label','Agregar '+p.NombreMedicamento);f.error=crear('span','catalogo-error');f.error.setAttribute('role','status');
            f.row.append(nombre,f.pres,existencias,f.precio,f.cant,f.boton,f.error);catalogo.append(f.row);catalogoFilas.set(String(p.MedicamentoID),f);
            f.pres.addEventListener('change',()=>{f.error.textContent='';actualizarCatalogo();});f.boton.addEventListener('click',()=>agregarDesdeCatalogo(f));f.cant.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();agregarDesdeCatalogo(f);}});
        }actualizarCatalogo();
    }
    buscar.addEventListener('input',renderCatalogo);buscar.addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();const exacto=[...catalogoFilas.values()].find(f=>normal(f.p.Codigo)===normal(buscar.value));const f=exacto||(catalogoFilas.size===1?[...catalogoFilas.values()][0]:null);if(f)agregarDesdeCatalogo(f);else if(catalogoFilas.size){aviso('Hay varios resultados. Elige un producto del listado.');[...catalogoFilas.values()][0].pres.focus();}});
    document.getElementById('buscar-productos').addEventListener('click',()=>{renderCatalogo();buscar.focus();});
    cliente.addEventListener('input',()=>{recalcular();});efectivo.addEventListener('input',recalcular);
    document.getElementById('ir-cobro').addEventListener('click',()=>{document.getElementById('cobro').scrollIntoView({behavior:'smooth',block:'start'});efectivo.focus({preventScroll:true});});
    document.getElementById('descartar-borrador').addEventListener('click',()=>{if(!window.confirm('¿Descartar los productos y el cobro de este borrador?'))return;cuerpo.replaceChildren();filas.clear();cliente.value='';efectivo.value='';operacion.value=config.operacion;aviso('Borrador descartado. Puedes comenzar otra venta.');recalcular();buscar.focus();});
    window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
    window.addEventListener('beforeunload',e=>{if(sinAlmacen&&filas.size&&!guardando){e.preventDefault();e.returnValue='';}});
    form.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.tagName!=='BUTTON'&&e.target!==buscar){e.preventDefault();if(e.target===efectivo&&recalcular())confirmar.focus();}});
    form.addEventListener('submit',e=>{if(!recalcular()){e.preventDefault();return;}persistir();guardando=true;confirmar.disabled=true;confirmar.textContent='Registrando venta…';});
    let previo=null;try{const s=sessionStorage.getItem(clave);if(s){previo=JSON.parse(s);if(previo.version!==1||!Array.isArray(previo.lineas)||previo.lineas.length>50||!Number.isFinite(Number(previo.fecha))||Date.now()-Number(previo.fecha)>86400000){previo=null;sessionStorage.removeItem(clave);}}}catch(e){previo=null;}
    let origen=config.lineas,restaurado=false,actualizado=false,omitidos=0;
    if(!config.post&&previo){
        if(Object.hasOwn(config.confirmadas,previo.operacion)){try{sessionStorage.removeItem(clave);}catch(e){}aviso('El borrador ya se registró como venta #'+config.confirmadas[previo.operacion]+'. Puedes consultarla en Listado de ventas.','success');}
        else{origen=previo.lineas;cliente.value=texto(previo.cliente).slice(0,150);efectivo.value=texto(previo.efectivo);if(config.operaciones.includes(previo.operacion))operacion.value=previo.operacion;restaurado=true;}
    }
    for(const l of origen){if(!l||typeof l!=='object')continue;const p=porId.get(texto(l.producto));const pr=p?.presentaciones.find(x=>String(x.PresentacionID)===texto(l.presentacion));if(!p||!pr){omitidos++;continue;}if(l.precio!==undefined&&(importe(l.precio)!==importe(pr.PrecioVenta)||Number(l.unidades)!==Number(pr.UnidadesBase)))actualizado=true;agregar(p,pr,texto(l.cantidad),config.descuento?texto(l.descuento||'0'):'0',true);}
    renderCatalogo();iniciando=false;recalcular();if(restaurado)aviso('Recuperamos tu borrador.'+(actualizado?' Cambiaron precios o presentaciones; revisa los importes antes de cobrar.':'')+(omitidos?' Se omitieron productos que ya no están disponibles.':''),'info');else if(omitidos)aviso('Revisa la venta: algunos productos ya no están disponibles.','warning');
})();
