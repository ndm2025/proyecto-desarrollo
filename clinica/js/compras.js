const productosCompra = JSON.parse(document.getElementById('datos-productos').textContent);
const lineasPrevias = JSON.parse(document.getElementById('datos-lineas').textContent);
const contenedorCompra = document.getElementById('lineas-compra');
const formularioEditor = document.getElementById('form-producto-compra');
const modalEditor = document.getElementById('editor-producto');
const entradasCompra = Object.fromEntries([...formularioEditor.querySelectorAll('[data-campo]')].map(input => [input.dataset.campo, input]));
const productosUnicos = new Map(productosCompra.map(p => [String(p.MedicamentoID), p]));
let lineasCompra = lineasPrevias.map(linea => ({...linea}));
let indiceEdicion = null;
productosUnicos.forEach(p => entradasCompra.producto.add(new Option(`${p.Codigo} - ${p.NombreMedicamento}`, p.MedicamentoID)));
function monedaCompra(centavos) { return (centavos / 100).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function importesCompra(datos) {
    const cantidad = Number(datos.cantidad) || 0;
    const costo = Math.round((Number(datos.costo) || 0) * 100);
    return {unidades: cantidad * (Number(datos.unidades) || 0), subtotal: cantidad * costo};
}
function datosEditorCompra() { return Object.fromEntries(Object.entries(entradasCompra).map(([campo, input]) => [campo, input.value])); }
function actualizarEditorCompra() {
    const {unidades, subtotal} = importesCompra(datosEditorCompra());
    document.getElementById('editor-total-unidades').textContent = unidades.toLocaleString('es-GT');
    document.getElementById('editor-subtotal').textContent = monedaCompra(subtotal);
}
function cargarPresentacionesCompra() {
    entradasCompra.presentacion.replaceChildren(new Option('Selecciona presentación...', ''));
    productosCompra.filter(p => String(p.MedicamentoID) === entradasCompra.producto.value).forEach(p => entradasCompra.presentacion.add(new Option(`${p.Nombre} (${p.UnidadesBase} ${p.UnidadBase})`, p.PresentacionID)));
    entradasCompra.unidades.value = '';
    entradasCompra.unidades.readOnly = true;
    document.getElementById('editor-costo-label').textContent = 'Costo por presentación (Q) *';
    actualizarEditorCompra();
}
function cargarEquivalenciaCompra() {
    const p = productosCompra.find(p => String(p.PresentacionID) === entradasCompra.presentacion.value);
    entradasCompra.unidades.value = p ? p.UnidadesBase : '';
    entradasCompra.unidades.readOnly = !p || p.Nombre === 'Unidad';
    document.getElementById('editor-costo-label').textContent = p ? `Costo por ${p.Nombre.toLocaleLowerCase('es-GT')} (Q) *` : 'Costo por presentación (Q) *';
    document.getElementById('editor-equivalencia').textContent = p ? `Cantidad de ${p.UnidadBase} que contiene una ${p.Nombre.toLocaleLowerCase('es-GT')}.` : 'Indica cuántas unidades contiene cada presentación.';
    actualizarEditorCompra();
}
function abrirEditorCompra(indice = null) {
    if (indice === null && lineasCompra.length >= 50) { alert('Puedes agregar hasta 50 renglones por compra.'); return; }
    indiceEdicion = indice;
    formularioEditor.reset();
    document.getElementById('editor-error').hidden = true;
    document.getElementById('editor-titulo').textContent = indice === null ? 'Agregar producto' : 'Editar producto';
    document.getElementById('guardar-producto-compra').textContent = indice === null ? 'Agregar a la lista' : 'Guardar cambios';
    const datos = indice === null ? {cantidad: '1'} : lineasCompra[indice];
    entradasCompra.producto.value = datos.producto || '';
    cargarPresentacionesCompra();
    entradasCompra.presentacion.value = datos.presentacion || '';
    cargarEquivalenciaCompra();
    ['cantidad', 'unidades', 'costo', 'lote', 'vence'].forEach(campo => { if (datos[campo] !== undefined) entradasCompra[campo].value = datos[campo]; });
    actualizarEditorCompra();
    bootstrap.Modal.getOrCreateInstance(modalEditor).show();
}
function crearCeldaCompra(clase, etiqueta, valor) {
    const celda = document.createElement('div'); celda.className = clase;
    const label = document.createElement('span'); label.className = 'compra-etiqueta'; label.textContent = etiqueta;
    const contenido = document.createElement('span'); contenido.textContent = valor;
    celda.append(label, contenido);
    return celda;
}
function renderizarCompra() {
    contenedorCompra.replaceChildren();
    const ocultos = document.getElementById('campos-lineas'); ocultos.replaceChildren();
    let total = 0; let unidades = 0;
    lineasCompra.forEach((datos, indice) => {
        const producto = productosUnicos.get(String(datos.producto));
        const presentacion = productosCompra.find(p => String(p.PresentacionID) === String(datos.presentacion));
        const importes = importesCompra(datos); total += importes.subtotal; unidades += importes.unidades;
        const fila = document.createElement('div'); fila.className = 'compra-fila'; fila.setAttribute('role', 'listitem');
        const nombre = document.createElement('div'); nombre.className = 'compra-producto';
        const titulo = document.createElement('strong'); titulo.textContent = producto ? `${producto.Codigo} - ${producto.NombreMedicamento}` : 'Producto no disponible';
        const lote = document.createElement('small'); lote.textContent = `Lote: ${datos.lote || 'Sin lote'} · Vence: ${datos.vence || 'Sin fecha'}`;
        nombre.append(titulo, lote);
        const pres = crearCeldaCompra('compra-presentacion', 'Presentación', presentacion ? presentacion.Nombre : 'No disponible');
        const factor = document.createElement('small'); factor.textContent = `${datos.unidades || 0} ${producto?.UnidadBase || 'unidades'} por presentación`; pres.append(factor);
        const cantidad = crearCeldaCompra('compra-cantidad', 'Cantidad', `${datos.cantidad || 0} (${importes.unidades.toLocaleString('es-GT')} unidades)`);
        const costo = crearCeldaCompra('compra-costo', 'Costo/presentación', `Q ${monedaCompra(Math.round((Number(datos.costo) || 0) * 100))}`);
        const subtotal = crearCeldaCompra('compra-subtotal', 'Subtotal', `Q ${monedaCompra(importes.subtotal)}`);
        const acciones = document.createElement('div'); acciones.className = 'compra-acciones';
        const editar = document.createElement('button'); editar.type = 'button'; editar.className = 'btn btn-outline-primary btn-sm'; editar.innerHTML = '<i class="bi bi-pencil" aria-hidden="true"></i>'; editar.setAttribute('aria-label', `Editar producto ${indice + 1}`); editar.title = 'Editar'; editar.addEventListener('click', () => abrirEditorCompra(indice));
        const quitar = document.createElement('button'); quitar.type = 'button'; quitar.className = 'btn btn-outline-danger btn-sm'; quitar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>'; quitar.setAttribute('aria-label', `Quitar producto ${indice + 1}`); quitar.title = 'Quitar'; quitar.addEventListener('click', () => { lineasCompra.splice(indice, 1); renderizarCompra(); });
        acciones.append(editar, quitar); fila.append(nombre, pres, cantidad, costo, subtotal, acciones); contenedorCompra.append(fila);
        Object.entries(datos).filter(([campo]) => Object.hasOwn(entradasCompra, campo)).forEach(([campo, valor]) => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = `lineas[${indice}][${campo}]`; input.value = valor; ocultos.append(input);
        });
    });
    document.getElementById('total-compra').textContent = monedaCompra(total);
    document.getElementById('total-unidades').textContent = unidades.toLocaleString('es-GT');
    document.getElementById('cantidad-lineas').textContent = String(lineasCompra.length);
    document.getElementById('compra-vacia').hidden = lineasCompra.length > 0;
}
entradasCompra.producto.addEventListener('change', cargarPresentacionesCompra);
entradasCompra.presentacion.addEventListener('change', cargarEquivalenciaCompra);
Object.values(entradasCompra).forEach(input => input.addEventListener('input', actualizarEditorCompra));
formularioEditor.addEventListener('submit', event => {
    event.preventDefault();
    if (!formularioEditor.reportValidity()) return;
    const datos = datosEditorCompra();
    datos.lote = datos.lote.trim();
    if (!datos.lote) { document.getElementById('editor-error').textContent = 'Escribe el número de lote.'; document.getElementById('editor-error').hidden = false; return; }
    if (indiceEdicion === null) lineasCompra.push(datos); else lineasCompra[indiceEdicion] = datos;
    renderizarCompra();
    bootstrap.Modal.getOrCreateInstance(modalEditor).hide();
});
modalEditor.addEventListener('shown.bs.modal', () => entradasCompra.producto.focus());
document.getElementById('agregar-linea').addEventListener('click', () => abrirEditorCompra());
document.getElementById('form-compra').addEventListener('submit', event => {
    if (!lineasCompra.length) { event.preventDefault(); alert('Agrega al menos un producto a la lista.'); }
});
renderizarCompra();
