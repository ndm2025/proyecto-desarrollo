(() => {for(const select of document.querySelectorAll('select[data-buscable]')){
    const cont=document.createElement('div');cont.className='buscador-selector';const label=document.createElement('label');label.className='form-label';const input=document.createElement('input');input.type='search';input.className='form-control';input.id=select.id+'-buscar';input.placeholder='Escribe código o nombre';label.htmlFor=input.id;label.textContent=select.dataset.buscable;cont.append(label,input);select.before(cont);
    const opciones=[...select.options].map(o=>({value:o.value,text:o.textContent}));const normal=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
    function filtrar(){const actual=select.value,q=normal(input.value);select.replaceChildren();for(const o of opciones)if(!o.value||o.value===actual||normal(o.text).includes(q))select.add(new Option(o.text,o.value));select.value=actual;}
    input.addEventListener('input',filtrar);input.addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();const candidatas=opciones.filter(o=>o.value&&normal(o.text).includes(normal(input.value)));if(candidatas.length===1){select.value=candidatas[0].value;select.dispatchEvent(new Event('change',{bubbles:true}));select.focus();}else select.focus();});
    select.form?.addEventListener('reset',()=>{input.value='';select.replaceChildren(...opciones.map(o=>new Option(o.text,o.value)));});
    select.closest('.modal')?.addEventListener('shown.bs.modal',()=>{input.value='';filtrar();input.focus();});
}})();
