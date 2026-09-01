# Generació i recuperació de l’Autoseguiment

## Operació reutilitzable

`inc/seguimiento/funciones.php` és la capacitat canònica compartida. Primer
determina el dilluns de la setmana natural que conté la data d’execució i hi
suma set dies; aquest és el dilluns objectiu, i el diumenge objectiu és sis dies
després. Per tant, el procés es pot executar qualsevol dia i totes les
execucions d’una mateixa setmana natural apunten exactament a la setmana
natural immediatament posterior. Després calcula el número de setmana des de la
data inicial configurada i selecciona alumnat actiu del curs acadèmic vigent
vinculat a projectes actius del mateix curs. El worker CLI i l’acció manual de
superadministració invoquen `seguimientoReconciliarPeriodoActual()` amb origen
`cron` o `manual`; diversos cron i execucions manuals dins la mateixa setmana no
alteren el període objectiu.

La pàgina de control reutilitza `seguimientoContextoCanonico()` i calcula en viu
els seguiments existents segons la mateixa clau que protegeix la idempotència.
No reconstrueix candidats amb una consulta paral·lela ni dedueix l’estat del
log. El botó manual només reconcilia el període canònic i no accepta dates,
projectes ni alumnes.

## Transacció, idempotència i concurrència

Cada execució adquireix `pg_advisory_xact_lock(1936028277, 1)`. El bloqueig
serialitza cron i accions manuals, inclou la creació dels seguiments i permet
assignar sota el mateix bloqueig `MAX(numero_ejecucion) + 1` per període sense
la carrera que tindria aquest càlcul aïllat. La restricció
`UNIQUE (fecha_inicio, fecha_fin, numero_ejecucion)` continua sent l’última
garantia del log.

La idempotència funcional continua protegida per PostgreSQL amb
`ON CONFLICT (proyecto_id, alumno_id, semana) DO NOTHING`; no hi ha cap
`SELECT` previ que substitueixi aquesta garantia. Un conflicte incrementa
`ya_existentes`, no `errores`.

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
