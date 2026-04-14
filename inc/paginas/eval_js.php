<script>
// ══════════════════════════════════════════════════════════════════
//  SISTEMA D'AVALUACIÓ — estrellas + comentaris inline
// ══════════════════════════════════════════════════════════════════

(function () {

// ── Helpers ───────────────────────────────────────────────────────
function fmtNota(v, max) {
    if (v === null || v === undefined) return '—';
    return v.toFixed(1).replace('.', ',') + ' <span style="opacity:.5;font-size:.8em">/ ' + max + '</span>';
}

function actualitzarBlocFinal(data) {
    const bloc = document.getElementById('bloque-nota-final');
    if (!bloc || !data) return;
    const slots = {
        tutor:    { el: bloc.querySelector('[data-slot="tutor"]'),    max: 2 },
        memoria:  { el: bloc.querySelector('[data-slot="memoria"]'),  max: 3 },
        proyecto: { el: bloc.querySelector('[data-slot="proyecto"]'), max: 3 },
        defensa:  { el: bloc.querySelector('[data-slot="defensa"]'),  max: 2 },
    };
    for (const [key, cfg] of Object.entries(slots)) {
        if (cfg.el) cfg.el.innerHTML = fmtNota(data[key], cfg.max);
    }
    const finalEl = bloc.querySelector('[data-slot="final"]');
    if (finalEl && data.final !== null) {
        finalEl.textContent = data.final.toFixed(1).replace('.', ',');
    }

    // Actualitzar notaBase global i recalcular notes individuals
    if (data.final !== null) {
        window._notaBaseProjecte = data.final;
        bloc.querySelectorAll('.nota-alumne').forEach(function (span) {
            const ajust = parseFloat(span.dataset.ajust ?? '0');
            const nota  = Math.max(0, Math.min(10, Math.round((data.final + ajust) * 10) / 10));
            span.textContent = nota.toFixed(1).replace('.', ',');
        });
    }
}

// ── Inicialitzar estrellas ────────────────────────────────────────
function inicialitzarEstrelles() {
    document.querySelectorAll('.rating-stars[data-editable="1"]').forEach(function (wrap) {
        if (wrap._evalInit) return;
        wrap._evalInit = true;

        const stars    = wrap.querySelectorAll('.star');
        const tipo     = wrap.dataset.tipo;
        const proyecto = wrap.dataset.proyecto;
        const camp     = wrap.dataset.camp;
        const profesor = wrap.dataset.profesor || null;

        let valorActual = 0;
        stars.forEach(function (s) {
            if (s.classList.contains('filled')) valorActual = parseInt(s.dataset.valor);
        });

        stars.forEach(function (star) {
            star.style.cursor = 'pointer';

            star.addEventListener('mouseenter', function () {
                const v = parseInt(star.dataset.valor);
                stars.forEach(function (s) {
                    s.classList.toggle('filled', parseInt(s.dataset.valor) <= v);
                });
            });

            star.addEventListener('mouseleave', function () {
                stars.forEach(function (s) {
                    s.classList.toggle('filled', parseInt(s.dataset.valor) <= valorActual);
                });
            });

            star.addEventListener('click', async function () {
                const nouValor = parseInt(star.dataset.valor);

                const fd = new FormData();
                fd.append('accio',       'nota');
                fd.append('proyecto_id', proyecto);
                fd.append('camp',        camp);
                fd.append('valor',       nouValor);
                if (profesor) fd.append('profesor_id', profesor);

                const url = tipo === 'tutor'
                    ? '/index.php?main=eval_guardar_tutor&raw=1'
                    : '/index.php?main=eval_guardar_tribunal&raw=1';

                try {
                    const res  = await fetch(url, { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.ok) {
                        valorActual = nouValor;
                        stars.forEach(function (s) {
                            s.classList.toggle('filled', parseInt(s.dataset.valor) <= valorActual);
                        });
                        if (data.nota_html) {
                            actualitzarBlocFinal(JSON.parse(data.nota_html));
                        }
                    } else {
                        alert(data.missatge || 'Error en guardar.');
                        stars.forEach(function (s) {
                            s.classList.toggle('filled', parseInt(s.dataset.valor) <= valorActual);
                        });
                    }
                } catch (e) {
                    alert('Error de connexió: ' + e.message);
                }
            });
        });
    });
}

// ── Inicialitzar comentaris ───────────────────────────────────────
function inicialitzarComentaris() {
    document.querySelectorAll('.comentari-wrap[data-editable="1"]').forEach(function (wrap) {
        if (wrap._evalInit) return;
        wrap._evalInit = true;

        const tipo     = wrap.dataset.tipo;
        const proyecto = wrap.dataset.proyecto;
        const profesor = wrap.dataset.profesor || null;
        const textEl   = wrap.querySelector('.comentari-text');
        const editor   = wrap.querySelector('.comentari-editor');
        const textarea = wrap.querySelector('textarea');
        const btnGuardar  = wrap.querySelector('.btn-guardar-comentari');
        const btnCancelar = wrap.querySelector('.btn-cancelar-comentari');

        if (!textEl || !editor) return;

        textEl.addEventListener('click', function () {
            textEl.classList.add('d-none');
            editor.classList.remove('d-none');
            textarea.focus();
        });

        btnCancelar.addEventListener('click', function () {
            editor.classList.add('d-none');
            textEl.classList.remove('d-none');
        });

        btnGuardar.addEventListener('click', async function () {
            const nouComentari = textarea.value.trim();

            const fd = new FormData();
            fd.append('accio',       'comentari');
            fd.append('proyecto_id', proyecto);
            fd.append('comentari',   nouComentari);
            if (profesor) fd.append('profesor_id', profesor);

            const url = tipo === 'tutor'
                ? '/index.php?main=eval_guardar_tutor&raw=1'
                : '/index.php?main=eval_guardar_tribunal&raw=1';

            btnGuardar.disabled = true;
            btnGuardar.textContent = 'Guardant...';

            try {
                const res  = await fetch(url, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    const display = wrap.querySelector('.comentari-display');
                    if (display) {
                        display.innerHTML = nouComentari !== ''
                            ? nouComentari.replace(/\n/g, '<br>')
                            : '<span class="text-muted fst-italic">Fes clic per afegir un comentari</span>';
                    }
                    editor.classList.add('d-none');
                    textEl.classList.remove('d-none');
                } else {
                    alert(data.missatge || 'Error en guardar.');
                }
            } catch (e) {
                alert('Error de connexió: ' + e.message);
            } finally {
                btnGuardar.disabled = false;
                btnGuardar.textContent = 'Guardar';
            }
        });
    });
}

// ── Arrancar ──────────────────────────────────────────────────────
inicialitzarEstrelles();
inicialitzarComentaris();

})();
</script>
