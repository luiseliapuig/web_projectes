<?php
// calendari_tribunal.php
// Calendari de tribunals.
// Files=hora, Cols=Tribunal 1/2/...N. Selector torn → dia.

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>
<style>
/* ── Banner ──────────────────────────────────────────────────────── */
.trib-banner {
    display: flex; align-items: flex-start; gap: .85rem;
    background: #eef4ff; border: 1px solid #c7d9f8;
    border-radius: .6rem; padding: .7rem 1rem;
    margin-bottom: 1.1rem; font-size: .875rem; color: #1e3a8a;
}
.trib-banner .banner-icon { font-size: 1.3rem; flex-shrink: 0; margin-top: .1rem; }
.trib-banner .banner-rang { font-weight: 800; font-size: 1rem; color: #1d4ed8; margin: 0 .15rem; }
.trib-banner .banner-sub  { font-size: .73rem; color: #3b5fad; margin-top: .15rem; }

/* ── Selector torn ───────────────────────────────────────────────── */
.trib-torn-wrap { display: flex; align-items: center; gap: .4rem; margin-bottom: .55rem; }
.trib-torn-pill {
    padding: .32rem .85rem; border-radius: 999px;
    border: 1.5px solid #dee2e6; background: #fff;
    font-size: .82rem; font-weight: 600; color: #495057;
    cursor: pointer; transition: all .15s; white-space: nowrap;
}
.trib-torn-pill:hover { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
.trib-torn-pill.actiu { background: #1e293b; border-color: #1e293b; color: #fff; }

/* ── Selector dies ───────────────────────────────────────────────── */
.trib-filter { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; margin-bottom: 1rem; }
.trib-day-pill {
    padding: .28rem .7rem; border-radius: 999px;
    border: 1.5px solid #dee2e6; background: #fff;
    font-size: .78rem; font-weight: 500; color: #495057;
    cursor: pointer; transition: all .15s; white-space: nowrap;
}
.trib-day-pill:hover  { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
.trib-day-pill.activa { background: #1d4ed8; border-color: #1d4ed8; color: #fff; font-weight: 700; }

/* ── Scroll ──────────────────────────────────────────────────────── */
.trib-scroll { overflow-x: auto; border-radius: .5rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }

/* ── Taula ───────────────────────────────────────────────────────── */
.trib-table { border-collapse: separate; border-spacing: 0; width: 100%; font-size: .82rem; }
.trib-table th, .trib-table td { border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }

.trib-table thead th {
    position: sticky; top: 0; z-index: 3;
    background: #f1f5f9; font-weight: 700; font-size: .78rem; text-align: center;
    white-space: nowrap; padding: .5rem 1rem; color: #374151;
    border-bottom: 2px solid #cbd5e1;
}
.trib-table thead th:first-child {
    position: sticky; left: 0; z-index: 5;
    text-align: left; min-width: 105px; width: 105px; background: #e2e8f0;
}

.trib-table td.trib-hora {
    position: sticky; left: 0; z-index: 2;
    background: #f8fafc; padding: .55rem .75rem;
    font-size: .78rem; white-space: nowrap; vertical-align: middle;
    min-width: 105px; width: 105px; border-right: 2px solid #cbd5e1;
}
.trib-hora .hora-ini { font-weight: 700; color: #1e293b; display: block; }
.trib-hora .hora-fin { font-size: .68rem; color: #94a3b8; display: block; margin-top: .05rem; }

.trib-table tbody tr:nth-child(even) td           { background-color: #fafbfc; }
.trib-table tbody tr:nth-child(even) td.trib-hora { background-color: #f1f5f9; }

.trib-table td.trib-cel       { vertical-align: top; padding: .5rem .55rem; min-width: 230px; max-width: 290px; }
.trib-table td.trib-cel-buida {
    min-width: 230px; max-width: 290px;
    background: repeating-linear-gradient(135deg, transparent, transparent 6px, rgba(0,0,0,.018) 6px, rgba(0,0,0,.018) 7px);
}

/* ── Card ────────────────────────────────────────────────────────── */
.trib-card {
    display: flex; flex-direction: column; gap: .22rem;
    padding: .45rem .5rem; background: #fff;
    border: 1px solid #e5e7eb; border-radius: .45rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.trib-pills { display: flex; flex-wrap: wrap; gap: .28rem; margin-bottom: .5rem; }

.trib-badge {
    display: inline-flex; align-items: center; font-size: .64rem; font-weight: 700;
    padding: .09rem .42rem; border-radius: 999px; line-height: 1.4; white-space: nowrap;
}
.badge-DAM  { background: #dbeafe; color: #1d4ed8; }
.badge-DAW  { background: #dcfce7; color: #15803d; }
.badge-ASIX { background: #fef9c3; color: #854d0e; }
.badge-SMX  { background: #e0f2fe; color: #0369a1; }
.badge-DEV  { background: #fee2e2; color: #b91c1c; }
.badge-GEN  { background: #f3e8ff; color: #6b21a8; }

.trib-badge-meta {
    display: inline-flex; align-items: center; gap: .2rem; font-size: .64rem; font-weight: 500;
    padding: .09rem .4rem; border-radius: 999px; line-height: 1.4;
    background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; white-space: nowrap;
}

.trib-nom-proj {
    font-weight: 600; font-size: .79rem; line-height: 1.3; color: #111827; text-decoration: none;
}
.trib-nom-proj:hover { text-decoration: underline; color: #1d4ed8; }
.trib-alumnes { font-size: .7rem; color: #6b7280; line-height: 1.35; }

.trib-divider {
    font-size: .61rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
    color: #9ca3af; margin-top: .28rem; display: flex; align-items: center; gap: .3rem;
}
.trib-divider::after { content: ''; flex: 1; height: 1px; background: #f0f0f0; }

.trib-membre {
    display: flex; align-items: center; gap: .25rem; font-size: .74rem; line-height: 1.4;
    padding: .07rem .2rem; border-radius: .3rem;
}
.trib-membre.es-jo     { font-weight: 700; color: #1d4ed8; }
.trib-membre.clickable { cursor: pointer; }
.trib-membre.clickable:hover { background: #fff1f2; }
.membre-nom       { }
.membre-desapunta { display: none; color: #dc2626; font-size: .71rem; font-weight: 700; }
.trib-membre.clickable:hover .membre-nom       { display: none; }
.trib-membre.clickable:hover .membre-desapunta { display: inline; }

.slot-lliure { display: flex; align-items: center; gap: .25rem; font-size: .7rem; color: #d1d5db; padding: .07rem .2rem; }

.btn-apuntar {
    display: inline-flex; align-items: center; gap: .25rem; font-size: .72rem; font-weight: 600;
    color: #15803d; background: #f0fdf4; border: 1px solid #86efac;
    border-radius: .35rem; padding: .15rem .5rem; cursor: pointer; transition: background .12s; line-height: 1.4;
}
.btn-apuntar:hover:not(:disabled) { background: #dcfce7; border-color: #4ade80; }
.btn-apuntar:disabled { opacity: .5; cursor: not-allowed; }

.btn-admin-afegir {
    display: inline-flex; align-items: center; gap: .22rem; font-size: .66rem; font-weight: 600;
    color: #7c3aed; background: #f5f3ff; border: 1px dashed #c4b5fd;
    border-radius: .3rem; padding: .1rem .4rem; cursor: pointer; margin-top: .15rem; transition: background .12s;
}
.btn-admin-afegir:hover { background: #ede9fe; }

/* ── Toast ───────────────────────────────────────────────────────── */
.trib-toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; padding: .55rem 1.1rem; border-radius: .5rem;
    font-size: .85rem; font-weight: 500; color: #fff; box-shadow: 0 4px 14px rgba(0,0,0,.15);
    opacity: 0; transition: opacity .25s; z-index: 9999; background: #198754; pointer-events: none;
}
.trib-toast.show  { opacity: 1; }
.trib-toast.error { background: #dc3545; }

/* ── Modal ───────────────────────────────────────────────────────── */
#modalAdminProfs .prof-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: .45rem .75rem; border-radius: .35rem; cursor: pointer;
    transition: background .12s; border: 1px solid transparent; margin-bottom: .2rem;
}
#modalAdminProfs .prof-item:hover { background: #f0f9ff; border-color: #bae6fd; }
#modalAdminProfs .prof-nom   { font-weight: 500; font-size: .88rem; }
#modalAdminProfs .prof-count { font-size: .75rem; color: #6c757d; background: #f1f3f5; padding: .1rem .5rem; border-radius: 999px; }

.trib-empty-bloc {
    background: #f8f9fa; border-radius: .5rem; padding: 1.5rem;
    text-align: center; color: #adb5bd; font-size: .88rem;
}
</style>

<section class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0">Calendari de Tribunals</h1>
            <div class="small text-muted">Apunta't als tribunals de defensa. Màxim 3 membres per projecte.</div>
        </div>
    </div>



    <!-- Selector torn -->
    <div class="trib-torn-wrap" id="trib-torn-wrap">
        <span class="text-muted small fst-italic">Carregant...</span>
    </div>

    <!-- Selector dia -->
    <div class="trib-filter" id="trib-filter" style="display:none"></div>

  
    <!-- Banner dinàmic -->
    <div class="trib-banner" id="trib-banner" style="display:none">
        <span class="banner-icon">🎓</span>
        <div>
            <div>Et queden per apuntar-te <span class="banner-rang" id="banner-rang">—</span> tribunals</div>
            <div class="banner-sub" id="banner-sub"></div>
        </div>
    </div>





    <!-- Taula -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <div class="trib-scroll" id="trib-taula-wrap">
                <div class="text-center text-muted py-4 small fst-italic">Carregant...</div>
            </div>
        </div>
    </div>

</section>

<div class="trib-toast" id="trib-toast"></div>

<div class="modal fade" id="modalAdminProfs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold">Apuntar professor al tribunal</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" id="modal-profs-llista"></div>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const URL_DADES = '/index.php?main=calendari_tribunal_dades&raw=1';
const URL_ACCIO = '/index.php?main=calendari_tribunal_accio&raw=1';

let _esSuperadmin    = false;
let _profIdActual    = 0;
let _professorsDisp  = [];
let _modalProjecteId = 0;
let _blocs           = { mati: null, tarda: null };
let _tornSeleccionat = null;
let _diaSeleccionat  = null;

// ── Utils ─────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function nomDia(dia) {
    const d    = new Date(dia + 'T00:00:00');
    const dies = ['Dg','Dl','Dm','Dc','Dj','Dv','Ds'];
    const mes  = ['gen','feb','mar','abr','mai','jun','jul','ago','set','oct','nov','des'];
    return dies[d.getDay()] + ' ' + d.getDate() + ' ' + mes[d.getMonth()];
}
function horaFi(hora, dur) {
    const [hh, mm] = hora.split(':').map(Number);
    const t = hh * 60 + mm + (parseInt(dur, 10) || 45);
    return String(Math.floor(t / 60)).padStart(2,'0') + ':' + String(t % 60).padStart(2,'0');
}
function badgeClass(cicle) {
    return { DAM:'badge-DAM', DAW:'badge-DAW', ASIX:'badge-ASIX', SMX:'badge-SMX', DEV:'badge-DEV' }[cicle] || 'badge-GEN';
}
function toast(msg, err = false) {
    const t = document.getElementById('trib-toast');
    t.textContent = msg; t.classList.toggle('error', err); t.classList.add('show');
    clearTimeout(t._tid); t._tid = setTimeout(() => t.classList.remove('show'), 2800);
}
function postJSON(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    }).then(r => r.json());
}

// ── Càrrega ───────────────────────────────────────────────────────
async function carregar(mantenirSeleccio) {
    try {
        const r = await fetch(URL_DADES);
        const d = await r.json();
        if (!d.ok) { showErr(d.missatge || 'Error'); return; }

        _esSuperadmin   = !!d.es_superadmin;
        _profIdActual   = parseInt(d.prof_id_actual, 10);
        _professorsDisp = d.professors_disponibles || [];
        _blocs          = d.blocs || { mati: null, tarda: null };

        if (d.stats) renderBanner(d.stats);

        const tornsDisp = ['tarda', 'mati'].filter(t => _blocs[t] && _blocs[t].dies.length > 0);
        if (!tornsDisp.length) {
            document.getElementById('trib-torn-wrap').innerHTML = '<span class="text-muted small">No hi ha defenses planificades.</span>';
            document.getElementById('trib-taula-wrap').innerHTML = '<div class="trib-empty-bloc">No hi ha defenses planificades.</div>';
            return;
        }

        if (!mantenirSeleccio || !tornsDisp.includes(_tornSeleccionat)) {
            _tornSeleccionat = tornsDisp[0];
        }
        renderSelectorTorn(tornsDisp);
        actualitzarDiesITaula(!!mantenirSeleccio);

    } catch(e) {
        showErr('Error de connexió: ' + e.message);
    }
}

// ── Banner ────────────────────────────────────────────────────────
function renderBanner(stats) {
    const min = parseInt(stats.rang_min, 10);
    const max = parseInt(stats.rang_max, 10);
    const txt = min === max ? String(min) : `${min}–${max}`;
    document.getElementById('banner-rang').textContent = txt;
    document.getElementById('banner-sub').textContent =
        `Basat en ${stats.total_projectes} projectes × 3 places / ${stats.profs_actius} professors actius. Tu ja n'estàs a ${stats.apuntats_jo}.`;
    document.getElementById('trib-banner').style.display = '';
}

// ── Selectors ─────────────────────────────────────────────────────
function renderSelectorTorn(tornsDisp) {
    const labels = { mati: '🌅 Torn de Matí', tarda: '🌆 Torn de Tarda' };
    document.getElementById('trib-torn-wrap').innerHTML = tornsDisp.map(torn =>
        `<button class="trib-torn-pill${torn === _tornSeleccionat ? ' actiu' : ''}"
            data-torn="${esc(torn)}" onclick="_seleccionarTorn('${esc(torn)}')">${esc(labels[torn])}</button>`
    ).join('');
}

function actualitzarDiesITaula(mantenirDia) {
    const bloc = _blocs[_tornSeleccionat];
    if (!bloc) return;
    if (!mantenirDia || !bloc.dies.includes(_diaSeleccionat)) {
        _diaSeleccionat = bloc.dies[0];
    }
    renderSelectorDies(bloc.dies);
    renderTaula();
}

function renderSelectorDies(dies) {
    const wrap = document.getElementById('trib-filter');
    wrap.innerHTML = dies.map(dia =>
        `<button class="trib-day-pill${dia === _diaSeleccionat ? ' activa' : ''}"
            data-dia="${esc(dia)}" onclick="_seleccionarDia('${esc(dia)}')">${esc(nomDia(dia))}</button>`
    ).join('');
    wrap.style.display = '';
}

// ── Taula ─────────────────────────────────────────────────────────
function renderTaula() {
    const wrap = document.getElementById('trib-taula-wrap');
    const bloc = _blocs[_tornSeleccionat];
    const dia  = _diaSeleccionat;
    if (!bloc || !dia) { wrap.innerHTML = '<div class="trib-empty-bloc">Selecciona un torn i un dia.</div>'; return; }

    const { hores, celdas, max_cols } = bloc;
    const dur     = parseInt(bloc.duracio_min, 10) || 45;
    const numCols = parseInt(max_cols, 10) || 1;

    let thead = '<tr><th>Hora</th>';
    for (let i = 1; i <= numCols; i++) { thead += `<th>Tribunal ${i}</th>`; }
    thead += '</tr>';

    let tbody = '';
    for (const hora of hores) {
        const projs = celdas?.[dia]?.[hora] ?? [];
        tbody += '<tr>';
        tbody += `<td class="trib-hora">
            <span class="hora-ini">${esc(hora)}</span>
            <span class="hora-fin">${esc(horaFi(hora, dur))}</span>
        </td>`;
        // Si el professor ja és a qualsevol projecte d'aquesta franja,
        // els botons "Apuntar-me" dels altres es mostren com Slot lliure
        const jaApuntatAquestaHora = projs.some(p =>
            (p.tribunal || []).some(m => parseInt(m.profesor_id, 10) === _profIdActual)
        );

        for (let col = 0; col < numCols; col++) {
            const proj = projs[col] ?? null;
            tbody += proj
                ? `<td class="trib-cel">${renderCard(proj, dur, jaApuntatAquestaHora)}</td>`
                : `<td class="trib-cel-buida"></td>`;
        }
        tbody += '</tr>';
    }

    wrap.innerHTML = `<table class="trib-table"><thead>${thead}</thead><tbody>${tbody}</tbody></table>`;
}

// ── Card ──────────────────────────────────────────────────────────
// jaApuntatAquestaHora: true si el prof ja és a un altre projecte de la mateixa franja
function renderCard(proj, dur, jaApuntatAquestaHora = false) {
    const label    = proj.cicle + (proj.grup ? ' ' + proj.grup : '');
    const alumnes  = (proj.alumnes || []).join(' · ');
    const tribunal = proj.tribunal || [];

    // Comparació int/int — parseInt en ambdós costats
    let jaApuntat = false;
    for (const m of tribunal) {
        if (parseInt(m.profesor_id, 10) === _profIdActual) { jaApuntat = true; break; }
    }
    // Si ja està a un altre projecte de la mateixa hora, bloquejar el botó
    const bloquejatPerHora = !jaApuntat && jaApuntatAquestaHora;
    const slotsLliures = Math.max(0, 3 - tribunal.length);
    const aulaText = proj.aula_nombre ? `${proj.aula_codigo} · ${proj.aula_nombre}` : proj.aula_codigo;
    const horaText = proj.hora ? `${proj.hora}–${horaFi(proj.hora, dur)}` : '';

    let html = `<div class="trib-card">
        <div class="trib-pills">
            <span class="trib-badge ${badgeClass(proj.cicle)}">${esc(label)}</span>
            <span class="trib-badge-meta">📍 ${esc(aulaText)}</span>
            ${horaText ? `<span class="trib-badge-meta">🕐 ${esc(horaText)}</span>` : ''}
        </div>
        <a href="/projecte/${proj.id}" class="trib-nom-proj" target="_blank">${esc(proj.nom)}</a>
        ${alumnes ? `<div class="trib-alumnes">${esc(alumnes)}</div>` : ''}
        <div class="trib-divider">🎓 Tribunal</div>`;

    for (const m of tribunal) {
        const esJo      = (parseInt(m.profesor_id, 10) === _profIdActual);
        const clickable = esJo || _esSuperadmin;
        html += `<div class="trib-membre${esJo ? ' es-jo' : ''}${clickable ? ' clickable' : ''}"
            ${clickable ? `onclick="_desapuntarMembre(${proj.id},${m.profesor_id})"` : ''}>
            <span>🎓</span>
            <span class="membre-nom">${esc(m.nom_complet)}</span>
            ${clickable ? `<span class="membre-desapunta">✕ Desapuntar</span>` : ''}
        </div>`;
    }

    for (let i = 0; i < slotsLliures; i++) {
        if (!jaApuntat && !bloquejatPerHora && i === 0) {
            html += `<div><button class="btn-apuntar" onclick="_apuntarMe(${proj.id},this)">＋ Apuntar-me</button></div>`;
        } else {
            html += `<div class="slot-lliure"><span>○</span><span>Slot lliure</span></div>`;
        }
    }

    if (_esSuperadmin && slotsLliures > 0) {
        html += `<button class="btn-admin-afegir" onclick="_obrirModalAdmin(${proj.id})">⚙ Apuntar professor</button>`;
    }

    return html + `</div>`;
}

// ── Accions globals ───────────────────────────────────────────────
window._seleccionarTorn = function(torn) {
    if (torn === _tornSeleccionat) return;
    _tornSeleccionat = torn;
    document.querySelectorAll('.trib-torn-pill').forEach(p => p.classList.toggle('actiu', p.dataset.torn === torn));
    actualitzarDiesITaula(false);
};

window._seleccionarDia = function(dia) {
    if (dia === _diaSeleccionat) return;
    _diaSeleccionat = dia;
    document.querySelectorAll('.trib-day-pill').forEach(p => p.classList.toggle('activa', p.dataset.dia === dia));
    renderTaula();
};

window._apuntarMe = async function(projecteId, btn) {
    btn.disabled = true; btn.textContent = '…';
    try {
        const d = await postJSON(URL_ACCIO, { accio: 'apuntar', proj_id: projecteId });
        if (d.ok) {
            toast('✓ Apuntat correctament');
            await carregar(true);
        } else {
            toast(d.missatge || 'Error', true);
            btn.disabled = false; btn.textContent = '＋ Apuntar-me';
        }
    } catch(e) {
        toast('Error de connexió', true);
        btn.disabled = false; btn.textContent = '＋ Apuntar-me';
    }
};

window._desapuntarMembre = async function(projecteId, targetProfId) {
    try {
        const d = await postJSON(URL_ACCIO, {
            accio: 'desapuntar', proj_id: projecteId,
            target_profesor_id: parseInt(targetProfId, 10),
        });
        if (d.ok) {
            toast('✓ Desapuntat');
            await carregar(true);
        } else {
            toast(d.missatge || 'Error', true);
        }
    } catch(e) { toast('Error de connexió', true); }
};

window._obrirModalAdmin = function(projecteId) {
    _modalProjecteId = projecteId;
    const html = _professorsDisp.map(prof => {
        const c = parseInt(prof.total_tribunals ?? 0, 10);
        return `<div class="prof-item" onclick="_apuntarAdminProf(${prof.id_profesor})">
            <span class="prof-nom">${esc(prof.nom_complet)}</span>
            <span class="prof-count">${c} tribunal${c !== 1 ? 's' : ''}</span>
        </div>`;
    }).join('') || '<div class="text-muted small p-3">No hi ha professors disponibles.</div>';
    document.getElementById('modal-profs-llista').innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalAdminProfs')).show();
};

window._apuntarAdminProf = async function(targetProfId) {
    bootstrap.Modal.getInstance(document.getElementById('modalAdminProfs'))?.hide();
    try {
        const d = await postJSON(URL_ACCIO, {
            accio: 'apuntar_admin', proj_id: _modalProjecteId,
            target_profesor_id: parseInt(targetProfId, 10),
        });
        if (d.ok) {
            toast('✓ Professor apuntat');
            await carregar(true);
        } else {
            toast(d.missatge || 'Error', true);
        }
    } catch(e) { toast('Error de connexió', true); }
};

function showErr(msg) {
    document.getElementById('trib-taula-wrap').innerHTML =
        `<div class="alert alert-danger rounded-4 m-2">${esc(msg)}</div>`;
}

carregar(false);
})();
</script>
