<?php
// calendari_drag.php
// Calendari drag & drop. Tres dies en una sola pàgina.
// Es pot arrossegar entre dies i entre aules. Slots ocupats rebutgen el drop.

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function nomDiaCurt(string $dia): string {
    $ts   = strtotime($dia);
    $dies = ['Dg','Dl','Dm','Dc','Dj','Dv','Ds'];
    $mes  = ['gen','feb','mar','abr','mai','jun','jul','ago','set','oct','nov','des'];
    return $dies[date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mes[date('n', $ts) - 1];
}
?>
<style>

     .container , .main-wrapper {
   
    max-width: 1800px !important;
 
}
/* ── Layout ─────────────────────────────────────────── */
.drag-wrap {
    overflow: auto;          /* scroll en ambdós eixos */
    max-height: calc(100vh - 180px); /* scroll vertical: deixa capçalera de pàgina visible */
}
.drag-table { border-collapse: separate; border-spacing: 0; width: 100%; min-width: 900px; }

/* Capçaleres fixes (scroll vertical) */
.drag-table thead th {
    position: sticky;
    z-index: 3;
}
/* Fila 1 del thead (noms de dia) */
.drag-table thead tr:first-child th { top: 0; }
/* Fila 2 del thead (codis aula) — ha de quedar just a sota de la fila 1 */
.drag-table thead tr:nth-child(2) th { top: 43px; } /* altura aprox. de la fila 1 */

/* Columna hora fixa (scroll horitzontal) */
.drag-table td:first-child,
.drag-table thead th:first-child {
    position: sticky;
    left: 0;
    z-index: 4; /* per sobre de les altres cel·les sticky */
    background: #f8f9fa;
}

/* Capçaleres */
.drag-table th {
    background: #f8f9fa; font-weight: 600;
    padding: .6rem .75rem; border: 1px solid #dee2e6;
    white-space: nowrap; text-align: center;
}
.drag-table th.th-hora { text-align: left; width: 90px; }
.drag-table th.th-dia-0 { background: #e9ecef; }
.drag-table th.th-dia-1 { background: #b6c4d8; }
.drag-table th.th-dia-2 { background: #e9ecef; }
.drag-table th.th-aula-0 { background: #f8f9fa; }
.drag-table th.th-aula-1 { background: #dce6f0; }
.drag-table th.th-aula-2 { background: #f8f9fa; }

/* Cel·les */
.drag-table td {
    border: 1px solid #dee2e6; padding: .4rem;
    vertical-align: top; min-width: 130px; min-height: 80px;
}
.drag-table td.dia-0 { background: #fff; }
.drag-table td.dia-1 { background: #f0f5fb; } /* dia del mig: fons lleugerament blau */
.drag-table td.dia-2 { background: #fff; }
.drag-table td.drag-over  { background: #dbeafe !important; outline: 2px dashed #3b82f6; }
.drag-table td.drag-block { cursor: not-allowed; }

/* Card */
.drag-card {
    background: #fff; border: 1px solid #dee2e6;
    border-radius: .45rem; padding: .4rem .55rem;
    cursor: grab; user-select: none;
    transition: box-shadow .1s, opacity .15s;
    font-size: .78rem;
}
.drag-card:active  { cursor: grabbing; }
.drag-card.dragging { opacity: .35; }
.drag-card .cbadge {
    display: inline-block; font-size: .68rem; font-weight: 700;
    padding: .1rem .45rem; border-radius: 999px; margin-bottom: .25rem;
}
.drag-card .cnom    { font-weight: 600; line-height: 1.2; margin-bottom: .15rem; }
.drag-card .calumne { color: #6c757d; font-size: .7rem; }

/* Columna sense cap defensa: molt més estreta */
.drag-table .col-buida {
    min-width: 44px !important;
    width: 44px;
    padding: .4rem .2rem;
}
.drag-table .col-buida .slot-ph {
    min-height: 66px;
    font-size: 0; /* ocultar el guió */
    border-color: #f0f0f0;
}

/* Slot buit */
.slot-ph {
    border: 2px dashed #e2e8f0; border-radius: .45rem;
    min-height: 66px; display: flex; align-items: center;
    justify-content: center; color: #cbd5e1; font-size: .72rem;
}

/* Toast */
.dtoast {
    position: fixed; bottom: 1.5rem; right: 1.5rem;
    padding: .55rem 1.1rem; border-radius: .5rem;
    font-size: .85rem; font-weight: 500; color: #fff;
    box-shadow: 0 4px 14px rgba(0,0,0,.15);
    opacity: 0; transition: opacity .25s; z-index: 9999;
    background: #198754;
}
.dtoast.show  { opacity: 1; }
.dtoast.error { background: #dc3545; }
</style>

<section class="container-fluid py-4 px-4">

    <!-- CAPÇALERA -->
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0">Calendari · Drag & Drop</h1>
            <div class="small text-muted">Arrossega entre dies, hores i aules. Els slots ocupats no accepten el drop.</div>
        </div>
        <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
            <div class="btn-group btn-group-sm">
                <input type="radio" class="btn-check" name="torn-drag" id="td-mati"  value="mati">
                <label class="btn btn-outline-secondary" for="td-mati">🌅 Matí</label>
                <input type="radio" class="btn-check" name="torn-drag" id="td-tarda" value="tarda" checked>
                <label class="btn btn-outline-secondary" for="td-tarda">🌙 Tarda</label>
            </div>
            <a href="/index.php?main=planificacio" class="btn btn-outline-secondary btn-sm">Generar proposta</a>

        </div>
    </div>

    <!-- TAULA -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <div id="drag-wrap" class="drag-wrap">
                <div class="text-center text-muted py-5">Carregant...</div>
            </div>
        </div>
    </div>

</section>

<div class="dtoast" id="dtoast"></div>

<script>
(function () {

const COLORS = {
    DAM: 'bg-primary-subtle text-primary',
    DAW: 'bg-success-subtle text-success',
    ASIX:'bg-warning-subtle text-dark',
    SMX: 'bg-info-subtle text-dark',
    DEV: 'bg-danger-subtle text-danger',
};
function color(c) { return COLORS[c] || 'bg-secondary-subtle text-secondary'; }
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function nomDia(dia) {
    const d = new Date(dia + 'T00:00:00');
    const dies = ['Dg','Dl','Dm','Dc','Dj','Dv','Ds'];
    const mes  = ['gen','feb','mar','abr','mai','jun','jul','ago','set','oct','nov','des'];
    return dies[d.getDay()] + ' ' + d.getDate() + ' ' + mes[d.getMonth()];
}

// ── Estat ─────────────────────────────────────────────────────────
let _torn        = 'tarda';
let _dies        = [];
let _aules       = [];
let _hores       = [];
let _duracio_min = 45;
let _matriu      = {}; // { dia: { hora: { aula_id: proj|null } } }
let _drag        = null; // { projId, dia, hora, aulaId }

// ── Càrrega ───────────────────────────────────────────────────────
async function carregar() {
    document.getElementById('drag-wrap').innerHTML =
        '<div class="text-center text-muted py-5">Carregant...</div>';
    try {
        const r = await fetch(`/index.php?main=calendari_drag_dades&raw=1&torn=${_torn}`);
        const d = await r.json();
        if (!d.ok) { showErr(d.missatge || 'Error'); return; }
        _dies        = d.dies;
        _aules       = d.aules;
        _hores       = d.hores;
        _duracio_min = d.duracio_min;
        _matriu      = d.matriu;
        render();
    } catch(e) { showErr('Error de connexió'); }
}

// ── Render ────────────────────────────────────────────────────────
function render() {
    if (!_aules.length) {
        document.getElementById('drag-wrap').innerHTML =
            '<div class="alert alert-info rounded-4 m-2">No hi ha defenses per a aquest torn.</div>';
        return;
    }

    // Duració de franja (ve del servidor, calculada sobre hores reals del mateix grup)
    const dur = _duracio_min;

    // Estendre les hores fins als límits del torn, encara que no hi hagi defenses
    const horaMin = _torn === 'mati' ?  9 * 60 : 15 * 60;
    const horaMax = _torn === 'mati' ? 14 * 60 : 21 * 60;
    const horesSet = new Set(_hores);
    for (let m = horaMin; m < horaMax; m += dur) {
        horesSet.add(toHora(m));
    }
    const horesRender = [...horesSet].sort();

    // Detectar quines combinacions dia+aula tenen almenys un projecte
    const aulesAmbProjPerDia = {};
    for (const dia of _dies) {
        aulesAmbProjPerDia[dia] = new Set();
        for (const hora of _hores) {
            for (const aula of _aules) {
                if (_matriu[dia]?.[hora]?.[aula.id_aula] != null) {
                    aulesAmbProjPerDia[dia].add(aula.id_aula);
                }
            }
        }
    }
    // Debug temporal
    for (const dia of _dies) {
        console.log(`${dia} buides:`, _aules.filter(a => !aulesAmbProjPerDia[dia].has(a.id_aula)).map(a => a.codigo));
    }

    // Capçalera fila 1: nom del dia (colspan = nAules), amb color per índex
    let thead = '<tr><th class="th-hora" rowspan="2">Hora</th>';
    _dies.forEach((dia, di) => {
        thead += `<th class="th-dia-${di % 3}" colspan="${_aules.length}">${esc(nomDia(dia))}</th>`;
    });
    thead += '</tr><tr>';
    // Capçalera fila 2: codi aula, amb color per índex de dia
    _dies.forEach((dia, di) => {
        for (const aula of _aules) {
            const buida = !aulesAmbProjPerDia[dia].has(aula.id_aula);
            thead += `<th class="th-aula-${di % 3}${buida ? ' col-buida' : ''}">${esc(aula.codigo)}<div class="small fw-normal text-muted">${esc(aula.nombre)}</div></th>`;
        }
    });
    thead += '</tr>';

    // Files
    let tbody = '';
    for (const hora of horesRender) {
        const horaFi = toHora(toMin(hora) + dur);
        tbody += `<tr><td style="background:#f8f9fa;font-weight:600;white-space:nowrap;font-size:.8rem;">${esc(hora)}<br><span class="text-muted fw-normal">${esc(horaFi)}</span></td>`;
        _dies.forEach((dia, di) => {
            for (const aula of _aules) {
                const proj   = _matriu[dia]?.[hora]?.[aula.id_aula] ?? null;
                const ocupat = proj !== null;
                const buida  = !aulesAmbProjPerDia[dia].has(aula.id_aula);
                tbody += `<td class="dia-${di % 3}${buida ? ' col-buida' : ''}" data-dia="${esc(dia)}" data-hora="${esc(hora)}" data-aula="${aula.id_aula}" data-ocupat="${ocupat?1:0}"
                    ondragover="_dOver(event,this)" ondragleave="_dLeave(this)" ondrop="_dDrop(event,this)">`;
                if (proj) {
                    const label = proj.cicle + (proj.grup ? ' '+proj.grup : '');
                    const al    = proj.alumnes.join(' · ');
                    tbody += `<div class="drag-card" draggable="true"
                        data-proj-id="${proj.id}" data-dia="${esc(dia)}" data-hora="${esc(hora)}" data-aula="${aula.id_aula}"
                        ondragstart="_dStart(event,this)" ondragend="_dEnd(this)">
                        <span class="cbadge ${color(proj.cicle)}">${esc(label)}</span>
                        <div class="cnom">${esc(proj.nom||'—')}</div>
                        ${al ? `<div class="calumne">${esc(al)}</div>` : ''}
                    </div>`;
                } else {
                    tbody += `<div class="slot-ph">—</div>`;
                }
                tbody += '</td>';
            }
        });
        tbody += '</tr>';
    }

    document.getElementById('drag-wrap').innerHTML =
        `<table class="drag-table"><thead>${thead}</thead><tbody>${tbody}</tbody></table>`;

    // Ajustar el top de la segona fila del thead dinàmicament
    const theadRows = document.querySelectorAll('.drag-table thead tr');
    if (theadRows.length >= 2) {
        const h1 = theadRows[0].getBoundingClientRect().height;
        theadRows[1].querySelectorAll('th').forEach(th => {
            th.style.top = h1 + 'px';
        });
    }
}

// ── Drag handlers (globals) ───────────────────────────────────────
window._dStart = function(e, card) {
    _drag = {
        projId: +card.dataset.projId,
        dia:    card.dataset.dia,
        hora:   card.dataset.hora,
        aulaId: +card.dataset.aula,
    };
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
};

window._dEnd = function(card) {
    card.classList.remove('dragging');
};

window._dOver = function(e, td) {
    e.preventDefault();
    if (td.dataset.ocupat === '1') {
        e.dataTransfer.dropEffect = 'none';
        td.classList.add('drag-block');
        return;
    }
    e.dataTransfer.dropEffect = 'move';
    td.classList.add('drag-over');
};

window._dLeave = function(td) {
    td.classList.remove('drag-over', 'drag-block');
};

window._dDrop = function(e, td) {
    e.preventDefault();
    td.classList.remove('drag-over', 'drag-block');
    if (!_drag || td.dataset.ocupat === '1') return;

    const toDia   = td.dataset.dia;
    const toHora  = td.dataset.hora;
    const toAula  = +td.dataset.aula;

    // Mateixa posició → res
    if (_drag.dia === toDia && _drag.hora === toHora && _drag.aulaId === toAula) {
        _drag = null; return;
    }

    guardar({ ..._drag }, toDia, toHora, toAula);
    _drag = null;
};

// ── Guardar ───────────────────────────────────────────────────────
async function guardar(from, toDia, toHora, toAulaId) {
    // Optimista: mou localment
    const proj = _matriu[from.dia][from.hora][from.aulaId];
    _matriu[from.dia][from.hora][from.aulaId] = null;

    // Si la hora de destí no existia en aquell dia, inicialitza
    if (!_matriu[toDia])       _matriu[toDia] = {};
    if (!_matriu[toDia][toHora]) {
        _matriu[toDia][toHora] = {};
        for (const a of _aules) _matriu[toDia][toHora][a.id_aula] = null;
        _hores = [...new Set([..._hores, toHora])].sort();
    }
    _matriu[toDia][toHora][toAulaId] = proj;
    render();

    // POST al servidor
    try {
        const fd = new FormData();
        fd.append('id_proyecto',  from.projId);
        fd.append('nova_data',    toDia);
        fd.append('nova_hora',    toHora);
        fd.append('nova_aula_id', toAulaId);
        fd.append('dia_retorn',   toDia);
        fd.append('torn_retorn',  _torn);

        const r = await fetch('/index.php?main=calendari_modificar&raw=1', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const d = await r.json();

        if (d.ok) {
            toast('✓ Desat', false);
        } else {
            revert(from, toDia, toHora, toAulaId, proj);
            toast('Error: ' + (d.missatge || 'No s\'ha pogut desar'), true);
        }
    } catch(err) {
        revert(from, toDia, toHora, toAulaId, proj);
        toast('Error de connexió', true);
    }
}

function revert(from, toDia, toHora, toAulaId, proj) {
    _matriu[toDia][toHora][toAulaId] = null;
    _matriu[from.dia][from.hora][from.aulaId] = proj;
    render();
}

// ── Utils ─────────────────────────────────────────────────────────
function toMin(h) { const [hh,mm]=h.split(':').map(Number); return hh*60+mm; }
function toHora(m) { return String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0'); }

function toast(msg, err=false) {
    const t = document.getElementById('dtoast');
    t.textContent = msg;
    t.classList.toggle('error', err);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}
function showErr(msg) {
    document.getElementById('drag-wrap').innerHTML =
        `<div class="alert alert-danger rounded-4 m-2">${esc(msg)}</div>`;
}

// ── Controls ──────────────────────────────────────────────────────
document.querySelectorAll('input[name="torn-drag"]').forEach(r =>
    r.addEventListener('change', function() { _torn = this.value; carregar(); })
);

carregar();

})();
</script>
