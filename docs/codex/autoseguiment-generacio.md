# Generació i recuperació de l’Autoseguiment

## Operació reutilitzable

`inc/seguimiento/funciones.php` és la capacitat canònica compartida. Parteix
sempre del dilluns de la setmana natural que conté la data d'execució. La
generació ordinària hi suma set dies i prepara així la setmana natural següent;
la recuperació administrativa conserva aquell dilluns i reconcilia la setmana
actual. En tots dos casos, el diumenge és sis dies després. Després calcula el número de setmana des de la
data inicial configurada i selecciona alumnat actiu amb matrícula vigent a un
grup del curs acadèmic actual. No cal que existeixi projecte; si n'hi ha un
d'actiu, el seu identificador es conserva només com a context opcional. El worker CLI i l’acció manual de
superadministració invoquen la mateixa reconciliació canònica amb origen `cron`
o `manual`; diversos cron i execucions manuals sobre el mateix període no creen
duplicats.

La pàgina de control reutilitza `seguimientoContextoCanonico()` i calcula en viu
els seguiments existents segons la mateixa clau que protegeix la idempotència.
No reconstrueix candidats amb una consulta paral·lela ni dedueix l’estat del
log. El control permet recuperar idempotentment la setmana actual o preparar la
següent. El POST només accepta aquestes dues intencions tancades i resol les
dates en servidor; no accepta dates, projectes ni alumnes del navegador.

## Transacció, idempotència i concurrència

Cada execució adquireix `pg_advisory_xact_lock(1936028277, 1)`. El bloqueig
serialitza cron i accions manuals, inclou la creació dels seguiments i permet
assignar sota el mateix bloqueig `MAX(numero_ejecucion) + 1` per període sense
la carrera que tindria aquest càlcul aïllat. La restricció
`UNIQUE (fecha_inicio, fecha_fin, numero_ejecucion)` continua sent l’última
garantia del log.

La idempotència funcional continua protegida per PostgreSQL amb
`ON CONFLICT (alumno_id, curso_academico, fecha_inicio, fecha_fin) DO NOTHING`;
no hi ha cap
`SELECT` previ que substitueixi aquesta garantia. Un conflicte incrementa
`ya_existentes`, no `errores`.

L'historial es consulta per alumne i curs. Les setmanes creades abans del
projecte romanen amb `proyecto_id = NULL`, no es reassignen quan el projecte
apareix i enllacen els objectius amb les setmanes posteriors sense discontinuïtat.
Eliminar el projecte tampoc elimina l'historial: la FK aplica `ON DELETE SET NULL`.

Sense projecte actiu amb tutor formal, el professorat autoritzat del grup pot
valorar i comentar. Des que existeix tutor formal, només aquest pot escriure i
pot fer-ho sobre tot l'historial del curs. No es registra autoria individual de
la valoració ni del comentari.

## Notificació consolidada del feedback

La valoració i el comentari del tutor s'editen independentment i no generen cap
email immediat. Un procés CLI diari independent,
`inc/seguimiento/feedback_worker.php`, selecciona els seguiments nous habilitats
que ja tenen `valoracion_tutor` i encara no tenen
`feedback_email_encolado_en`. El missatge incorpora els valors existents en el
moment de l'execució, inclòs el comentari només quan n'hi ha.

Cada `id_seguimiento` genera com a màxim un email. El generador bloqueja la fila
amb `FOR UPDATE` i confirma en una sola transacció l'alta a `email_outbox` i la
marca d'encolat. La clau `autoseguiment_feedback:{id_seguimiento}` protegeix
també reexecucions i concurrència. Les modificacions posteriors continuen
visibles a la web, però no generen notificacions noves.

La migració d'activació separa explícitament les cohorts: els seguiments
històrics queden amb `feedback_email_habilitado = false` i els creats després
utilitzen el valor per defecte `true`. No es dedueix l'activació a partir de cap
data arbitrària ni es marquen històrics com si haguessin estat notificats.

## Errors i observabilitat

Una execució correcta queda registrada encara que creï zero files. Si falla la
transacció funcional, es desfà completament i s’intenta registrar una execució
fallida en una transacció nova, amb un missatge administratiu sense excepcions,
traces ni secrets. Si la connexió o la mateixa taula de log no estan disponibles,
el rollback impedeix registrar el falliment; el worker retorna error i el detall
tècnic queda exclusivament al log del servidor.

L’absència de configuració o una setmana objectiu fora del període configurat
conserven el comportament no-op del worker anterior: no creen seguiments i es
registren com una execució sense candidats. El control queda deshabilitat fins
que existeixi un període processable.
