<?php
// planificacio.php
// Les aules venen determinades per la taula grupos (cicle+grup → aula+torn).
// No cal seleccionar aules manualment.
?>

<section class="container py-4">

    <!-- CABECERA -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-primary mb-2">
                        Panel intern · Defenses
                    </div>
                    <h1 class="h3 fw-bold mb-1">Assignació automàtica d'aules i horaris</h1>
                    <p class="text-muted mb-0">
                        Configura els dies, els torns i la duració de les franges. El sistema repartirà automàticament
                        els projectes a l'aula del seu grup, distribuint les defenses homogèniament entre els dies.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge rounded-pill text-bg-light border px-3 py-2">3 dies</span>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2">Franges configurables</span>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2">Aula per grup</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLUMNA IZQUIERDA -->
        <div class="col-12 col-xl-8">

            <!-- DIES -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
                    <div class="text-uppercase small fw-semibold text-primary mb-1">Configuració</div>
                    <h2 class="h5 fw-bold mb-0">Dies de defensa</h2>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="dia1" class="form-label fw-semibold">Dia 1</label>
                            <input type="date" class="form-control rounded-3" id="dia1" name="dia1">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="dia2" class="form-label fw-semibold">Dia 2</label>
                            <input type="date" class="form-control rounded-3" id="dia2" name="dia2">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="dia3" class="form-label fw-semibold">Dia 3</label>
                            <input type="date" class="form-control rounded-3" id="dia3" name="dia3">
                        </div>
                    </div>
                </div>
            </div>

            <!-- HORARIS -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
                    <div class="text-uppercase small fw-semibold text-primary mb-1">Torns</div>
                    <h2 class="h5 fw-bold mb-0">Horaris i duració de les franges</h2>
                </div>
                <div class="card-body px-4 pb-4 pt-3">

                    <div class="border rounded-4 p-4 mb-4 bg-light-subtle">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-lg-6">
                                <label for="duracio_franja" class="form-label fw-semibold">Duració de la franja (minuts)</label>
                                <input
                                    type="number"
                                    class="form-control rounded-3"
                                    id="duracio_franja"
                                    name="duracio_franja"
                                    min="20" max="90" step="5" value="45"
                                >
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="small text-muted">
                                    Aquesta duració s'aplicarà tant al torn de matí com al de tarda.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-4 h-100 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3 class="h6 fw-bold mb-0">Torn de matí</h3>
                                    <span class="badge rounded-pill bg-warning-subtle text-dark border px-3 py-2">Grups torn Matí</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label for="hora_inici_mati" class="form-label fw-semibold">Hora inici</label>
                                        <input type="time" class="form-control rounded-3" id="hora_inici_mati" name="hora_inici_mati" value="09:00">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="hora_fi_mati" class="form-label fw-semibold">Hora fi</label>
                                        <input type="time" class="form-control rounded-3" id="hora_fi_mati" name="hora_fi_mati" value="14:00">
                                    </div>
                                </div>
                                <div class="small text-muted mt-3 mb-0">L'aula de cada grup ve determinada per la configuració de grups.</div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="border rounded-4 p-4 h-100 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3 class="h6 fw-bold mb-0">Torn de tarda</h3>
                                    <span class="badge rounded-pill bg-info-subtle text-dark border px-3 py-2">Grups torn Tarda</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label for="hora_inici_tarda" class="form-label fw-semibold">Hora inici</label>
                                        <input type="time" class="form-control rounded-3" id="hora_inici_tarda" name="hora_inici_tarda" value="15:00">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="hora_fi_tarda" class="form-label fw-semibold">Hora fi</label>
                                        <input type="time" class="form-control rounded-3" id="hora_fi_tarda" name="hora_fi_tarda" value="21:00">
                                    </div>
                                </div>
                                <div class="small text-muted mt-3 mb-0">Cada grup defensa a la seva aula assignada.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACCIONS -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Accions</h2>
                            <p class="text-muted mb-0">
                                Pots provar diferents duracions de franja i, si cal, tornar a generar totes les assignacions.
                            </p>
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="sobreescriure" name="sobreescriure" value="1">
                                <label class="form-check-label" for="sobreescriure">
                                    Sobreescriure assignacions existents
                                </label>
                            </div>
                            <div class="small text-muted mt-2">
                                Si està activat, el sistema tornarà a assignar data i aula als projectes afectats, encara que ja tinguin defensa informada.
                            </div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button type="button" class="btn btn-outline-danger px-4" id="btn-eliminar">
                                Eliminar dates
                            </button>
                            <button type="button" class="btn btn-outline-secondary px-4" id="btn-simular">
                                Simular assignació
                            </button>
                            <button type="button" class="btn btn-primary px-4" id="btn-aplicar">
                                Aplicar assignació automàtica
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA -->
        <div class="col-12 col-xl-4">

            <!-- RESUM LÒGICA -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
                    <div class="text-uppercase small fw-semibold text-primary mb-1">Funcionament</div>
                    <h2 class="h5 fw-bold mb-0">Regles d'assignació</h2>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="small text-muted mb-3">El sistema utilitzarà aquestes regles per generar les defenses.</div>
                    <div class="border rounded-4 p-3 mb-3 bg-light-subtle">
                        <div class="fw-semibold mb-1">Aula fixa per grup</div>
                        <div class="small text-muted">Cada grup defensa sempre a l'aula que té assignada a la configuració de grups.</div>
                    </div>
                    <div class="border rounded-4 p-3 mb-3 bg-light-subtle">
                        <div class="fw-semibold mb-1">Distribució homogènia</div>
                        <div class="small text-muted">Les defenses de cada grup es reparteixen equitativament entre els dies indicats.</div>
                    </div>
                    <div class="border rounded-4 p-3 mb-3 bg-light-subtle">
                        <div class="fw-semibold mb-1">Torn per grup</div>
                        <div class="small text-muted">El torn (matí o tarda) ve determinat per la configuració de cada grup, no per cicle.</div>
                    </div>
                    <div class="border rounded-4 p-3 bg-light-subtle">
                        <div class="fw-semibold mb-1">Sense solapaments</div>
                        <div class="small text-muted">Cap dos projectes del mateix grup coincideixen a la mateixa franja horària.</div>
                    </div>
                </div>
            </div>

            <!-- RESUM DE SIMULACIÓ -->
            <div class="card border-0 shadow-sm rounded-4 mb-4" id="card-simulacio">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
                    <div class="text-uppercase small fw-semibold text-primary mb-1">Simulació</div>
                    <h2 class="h5 fw-bold mb-0">Resum de capacitat</h2>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="row g-3" id="simulacio-xifres">
                        <div class="col-6">
                            <div class="border rounded-4 p-3 h-100">
                                <div class="small text-muted mb-1">Projectes matí</div>
                                <div class="h4 fw-bold mb-0" id="sim-proj-mati">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-4 p-3 h-100">
                                <div class="small text-muted mb-1">Franges/grup matí</div>
                                <div class="h4 fw-bold mb-0" id="sim-slots-mati">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-4 p-3 h-100">
                                <div class="small text-muted mb-1">Projectes tarda</div>
                                <div class="h4 fw-bold mb-0" id="sim-proj-tarda">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-4 p-3 h-100">
                                <div class="small text-muted mb-1">Franges/grup tarda</div>
                                <div class="h4 fw-bold mb-0" id="sim-slots-tarda">—</div>
                            </div>
                        </div>
                    </div>
                    <div id="simulacio-alerta" class="mt-3 mb-0"></div>
                </div>
            </div>

            <!-- LOG / RESULTAT -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
                    <div class="text-uppercase small fw-semibold text-primary mb-1">Resultat</div>
                    <h2 class="h5 fw-bold mb-0">Sortida de l'operació</h2>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="border rounded-4 bg-light p-3 small text-muted" id="log-resultat" style="min-height: 220px; white-space: pre-line;">
Encara no s'ha executat cap operació.
Prem "Simular" per veure el resum de capacitat, o "Aplicar" per fer l'assignació real.
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
(function () {

    function recollirDades() {
        return {
            dia1:             document.getElementById('dia1').value,
            dia2:             document.getElementById('dia2').value,
            dia3:             document.getElementById('dia3').value,
            hora_inici_mati:  document.getElementById('hora_inici_mati').value,
            hora_fi_mati:     document.getElementById('hora_fi_mati').value,
            hora_inici_tarda: document.getElementById('hora_inici_tarda').value,
            hora_fi_tarda:    document.getElementById('hora_fi_tarda').value,
            duracio_franja:   document.getElementById('duracio_franja').value,
            sobreescriure:    document.getElementById('sobreescriure').checked ? '1' : '0',
        };
    }

    function validarDades(dades) {
        if (!dades.dia1 || !dades.dia2 || !dades.dia3) return 'Has d\'indicar els tres dies de defensa.';
        if (!dades.hora_inici_mati || !dades.hora_fi_mati) return 'Has d\'indicar l\'horari del torn de matí.';
        if (!dades.hora_inici_tarda || !dades.hora_fi_tarda) return 'Has d\'indicar l\'horari del torn de tarda.';
        return null;
    }

    function setLog(text) {
        document.getElementById('log-resultat').textContent = text;
    }

    function setBotoCarregant(btn, carregant) {
        btn.disabled = carregant;
        if (carregant) {
            btn.dataset.textorig = btn.textContent;
            btn.textContent = 'Carregant...';
        } else {
            btn.textContent = btn.dataset.textorig;
        }
    }

    function mostrarSimulacio(data) {
        document.getElementById('sim-proj-mati').textContent   = data.proj_mati;
        document.getElementById('sim-slots-mati').textContent  = data.slots_mati;
        document.getElementById('sim-proj-tarda').textContent  = data.proj_tarda;
        document.getElementById('sim-slots-tarda').textContent = data.slots_tarda;

        const alerta = document.getElementById('simulacio-alerta');

        if (data.ok) {
            alerta.innerHTML = `
                <div class="alert alert-success rounded-4 mb-0" role="alert">
                    <div class="fw-semibold mb-1">Capacitat suficient</div>
                    <div class="small mb-0">Hi ha prou franges disponibles per fer l'assignació automàtica.</div>
                </div>`;
        } else {
            const problemes = data.problemes.map(p => `<li>${p}</li>`).join('');
            alerta.innerHTML = `
                <div class="alert alert-danger rounded-4 mb-0" role="alert">
                    <div class="fw-semibold mb-1">Capacitat insuficient</div>
                    <ul class="small mb-0 ps-3">${problemes}</ul>
                </div>`;
        }
    }

    // ── ELIMINAR DATES ───────────────────────────────────────
    document.getElementById('btn-eliminar').addEventListener('click', async function () {
        if (!confirm('Esborraràs totes les dates i aules de defensa de tots els projectes. Continuar?')) return;

        setBotoCarregant(this, true);
        setLog('Eliminant dates...');

        try {
            const resp = await fetch('/index.php?main=planificacio_eliminar&raw=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            const data = await resp.json();
            if (data.ok) {
                setLog(`✓ Dates eliminades. ${data.projectes} projectes actualitzats.`);
                document.getElementById('sim-proj-mati').textContent   = '—';
                document.getElementById('sim-slots-mati').textContent  = '—';
                document.getElementById('sim-proj-tarda').textContent  = '—';
                document.getElementById('sim-slots-tarda').textContent = '—';
                document.getElementById('simulacio-alerta').innerHTML  = '';
            } else {
                setLog('Error: ' + data.missatge);
            }
        } catch (e) {
            setLog('Error de connexió amb el servidor.');
        } finally {
            setBotoCarregant(this, false);
        }
    });

    // ── SIMULAR ──────────────────────────────────────────────
    document.getElementById('btn-simular').addEventListener('click', async function () {
        const dades = recollirDades();
        const error = validarDades(dades);
        if (error) { setLog('⚠ ' + error); return; }

        setBotoCarregant(this, true);
        setLog('Calculant simulació...');

        try {
            const resp = await fetch('/index.php?main=planificacio_simular&raw=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dades),
            });
            const data = await resp.json();
            mostrarSimulacio(data);

            let log = '';

            // Resum per torn
            const grupsMati  = (data.resum_grups || []).filter(g => g.torn === 'Matí');
            const grupsTarda = (data.resum_grups || []).filter(g => g.torn === 'Tarda');

            if (grupsMati.length > 0) {
                log += `── MATÍ ──────────────────────────\n`;
                log += `Projectes totals: ${data.proj_mati}  |  Franges/grup: ${data.slots_mati}\n`;
                grupsMati.forEach(g => {
                    const estat = g.ok ? '✓' : '✗';
                    log += `  ${estat} ${g.grup.padEnd(8)} Aula: ${g.aula.padEnd(6)}  ${g.projectes} proj / ${g.slots} franges\n`;
                });
            }

            if (grupsTarda.length > 0) {
                log += `\n── TARDA ─────────────────────────\n`;
                log += `Projectes totals: ${data.proj_tarda}  |  Franges/grup: ${data.slots_tarda}\n`;
                grupsTarda.forEach(g => {
                    const estat = g.ok ? '✓' : '✗';
                    log += `  ${estat} ${g.grup.padEnd(8)} Aula: ${g.aula.padEnd(6)}  ${g.projectes} proj / ${g.slots} franges\n`;
                });
            }

            if (data.sense_config > 0) {
                log += `\n⚠ ${data.sense_config} projectes sense configuració de grup a la BD.\n`;
            }

            if (!data.ok) {
                log += `\n── PROBLEMES ─────────────────────\n`;
                data.problemes.forEach(p => log += '⚠ ' + p + '\n');
            } else {
                log += `\n✓ Cap problema detectat. Pots aplicar l'assignació.`;
            }

            setLog(log);
        } catch (e) {
            setLog('Error de connexió amb el servidor.');
        } finally {
            setBotoCarregant(this, false);
        }
    });

    // ── APLICAR ──────────────────────────────────────────────
    document.getElementById('btn-aplicar').addEventListener('click', async function () {
        const dades = recollirDades();
        const error = validarDades(dades);
        if (error) { setLog('⚠ ' + error); return; }

        const sobreescriure = dades.sobreescriure === '1';
        const confirm_msg = sobreescriure
            ? 'Sobreescriuràs totes les assignacions existents. Continuar?'
            : 'S\'assignaran els projectes que encara no tenen defensa informada. Continuar?';

        if (!confirm(confirm_msg)) return;

        setBotoCarregant(this, true);
        setLog('Aplicant assignació...');

        try {
            const resp = await fetch('/index.php?main=planificacio_accion&raw=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dades),
            });
            const data = await resp.json();

            let log = '';
            if (data.ok) {
                log += `✓ Assignació completada.\n\n`;
                log += `Projectes assignats: ${data.assignats}\n`;
                if (data.sense_slot > 0) {
                    log += `Sense franja disponible: ${data.sense_slot}\n`;
                    log += 'IDs sense assignar:\n';
                    data.ids_sense_slot.forEach(id => log += '  · Projecte #' + id + '\n');
                }
                if (data.sense_config > 0) {
                    log += `Sense configuració de grup: ${data.sense_config}\n`;
                    log += 'IDs sense configuració:\n';
                    data.ids_sense_config.forEach(id => log += '  · Projecte #' + id + '\n');
                }
            } else {
                log += `Error: ${data.missatge}`;
            }
            setLog(log);
        } catch (e) {
            setLog('Error de connexió amb el servidor.');
        } finally {
            setBotoCarregant(this, false);
        }
    });

})();
</script>
