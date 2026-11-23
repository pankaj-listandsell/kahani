{{-- 🏷️ Category <select> ko checkbox wale dropdown me badlo (kai category ek saath).
     Use: const cat = MultiCategory(document.getElementById('category'));  cat.values() / cat.labels()
     <select> ke selected options = shuru ki pasand; value="" wala option (koi category nahi) list me nahi aata. --}}
<script>
window.MultiCategory = function (select) {
    const key = 'multicat.' + location.pathname;
    const items = []; // {value, label, cb}

    const wrap = document.createElement('div');
    wrap.className = 'relative';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = select.className + ' text-left flex items-center justify-between gap-2 bg-white';
    btn.innerHTML = '<span class="truncate summary"></span><span class="text-slate-400 shrink-0">▾</span>';
    const panel = document.createElement('div');
    panel.className = 'hidden absolute z-30 mt-1 left-0 w-[min(92vw,26rem)] max-h-96 overflow-auto bg-white border border-slate-200 rounded-lg shadow-lg p-2 text-sm';
    wrap.append(btn, panel);

    // Upar: badhu hatao / badhu pasand
    const tools = document.createElement('div');
    tools.className = 'flex items-center justify-between gap-2 px-1 pb-2 mb-1 border-b border-slate-100 text-xs';
    tools.innerHTML = '<span class="text-slate-500">Ek thi vadhaare pasand kari shako</span>'
        + '<span class="flex gap-3"><button type="button" class="all text-amber-700 font-semibold hover:underline">Badhi</button>'
        + '<button type="button" class="none text-red-600 font-semibold hover:underline">Hatao</button></span>';
    panel.appendChild(tools);

    const addOption = (opt, parent) => {
        if (opt.value === '') return; // "koi category nahi" = kuch bhi tick na karo
        const row = document.createElement('label');
        row.className = 'flex items-start gap-2 px-2 py-1.5 rounded hover:bg-amber-50 cursor-pointer';
        row.innerHTML = '<input type="checkbox" class="mt-0.5 accent-amber-600 w-4 h-4 shrink-0"><span></span>';
        row.querySelector('span').textContent = opt.textContent.trim();
        const cb = row.querySelector('input');
        cb.checked = opt.selected && opt.value !== '';
        cb.addEventListener('change', () => { save(); render(); });
        items.push({ value: opt.value, label: opt.textContent.trim(), cb });
        parent.appendChild(row);
    };
    [...select.children].forEach(node => {
        if (node.tagName === 'OPTGROUP') {
            const h = document.createElement('div');
            h.className = 'px-2 pt-2 pb-1 text-[11px] font-bold text-slate-500 uppercase tracking-wide';
            h.textContent = node.label;
            panel.appendChild(h);
            [...node.children].forEach(o => addOption(o, panel));
        } else {
            addOption(node, panel);
        }
    });

    // Pichhli pasand yaad (browser me) — na mile to <select> ka default
    try {
        const saved = JSON.parse(localStorage.getItem(key) || 'null');
        if (Array.isArray(saved)) items.forEach(it => { it.cb.checked = saved.includes(it.value); });
    } catch (e) {}

    function save() {
        try { localStorage.setItem(key, JSON.stringify(items.filter(i => i.cb.checked).map(i => i.value))); } catch (e) {}
    }
    function render() {
        const sel = items.filter(i => i.cb.checked);
        btn.querySelector('.summary').textContent = !sel.length
            ? '✍️ Koi category nahi — fakt Topic par'
            : sel.length === 1 ? sel[0].label : `✅ ${sel.length} category: ` + sel.map(i => i.label.replace(/^\S+\s/, '')).join(', ');
    }

    tools.querySelector('.all').addEventListener('click', () => { items.forEach(i => { i.cb.checked = true; }); save(); render(); });
    tools.querySelector('.none').addEventListener('click', () => { items.forEach(i => { i.cb.checked = false; }); save(); render(); });
    btn.addEventListener('click', () => panel.classList.toggle('hidden'));
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) panel.classList.add('hidden'); });

    select.style.display = 'none';
    select.after(wrap);
    render();

    return {
        values: () => items.filter(i => i.cb.checked).map(i => i.value),
        labels: () => items.filter(i => i.cb.checked).map(i => i.label),
    };
};
</script>
