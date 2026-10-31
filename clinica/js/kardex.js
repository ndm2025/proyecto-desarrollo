const productoKardex = document.getElementById('producto');
const loteKardex = document.getElementById('lote');
productoKardex.addEventListener('change', () => {
    loteKardex.replaceChildren(new Option('Todos los lotes del producto', '0'));
    if (productoKardex.value) productoKardex.form.requestSubmit();
});
