const mapaRoles = JSON.parse(document.getElementById('mapa-roles').textContent);
function actualizarAccesos() {
    const rol = document.getElementById('rol').value;
    document.querySelectorAll('tr[data-permiso]').forEach(fila => {
        const heredado = mapaRoles.some(p => String(p.RolID) === rol && String(p.PermisoID) === fila.dataset.permiso);
        const excepcion = fila.querySelector('.excepcion').value;
        const permitido = excepcion === 'heredar' ? heredado : excepcion === '1';
        fila.querySelector('.permiso-rol').textContent = heredado ? 'Permitido' : 'Sin permiso';
        const resultado = fila.querySelector('.permiso-final');
        resultado.textContent = permitido ? 'Permitido' : 'Denegado';
        resultado.className = 'permiso-final ' + (permitido ? 'text-success' : 'text-danger');
    });
}
document.getElementById('rol').addEventListener('change', actualizarAccesos);
document.querySelectorAll('.excepcion').forEach(input => input.addEventListener('change', actualizarAccesos));
actualizarAccesos();
