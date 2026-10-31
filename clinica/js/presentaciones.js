document.querySelectorAll('.activar-presentacion').forEach(input => {
    const actualizar = () => {
        const campos = input.closest('.presentacion-fila').querySelector('.campos-presentacion');
        campos.hidden = !input.checked;
        campos.querySelectorAll('input').forEach(campo => { campo.disabled = !input.checked; campo.required = input.checked; });
    };
    input.addEventListener('change', actualizar);
    actualizar();
});
