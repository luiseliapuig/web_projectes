# Sistema de correo

El correo saliente se centraliza en `/inc/email/` y usa PHPMailer mediante SMTP.
Las páginas no envían directamente: insertan mensajes en `app.email_outbox` y un
worker CLI procesa la cola en lotes.

Los mensajes transaccionales necesarios para completar una operación inmediata
(recuperación de contraseña e invitación inicial de profesorado y alumnado) se envían
directamente mediante el mismo servicio SMTP y no esperan al worker.

## Configuración

Copia las variables de `.env.email.example` al `.env` local. Los secretos no se
guardan en el repositorio. Para Gmail, `MAIL_PASSWORD` debe ser una contraseña de
aplicación, no la contraseña normal de la cuenta.
Si se copia con los espacios visuales que separan sus cuatro bloques, la
configuración los elimina automáticamente para `smtp.gmail.com`.

`MAIL_REPLY_TO_ADDRESS` puede ser la cuenta institucional aunque la cuenta emisora
sea distinta. El remitente real debe coincidir con la identidad admitida por el
servidor SMTP.

`MAIL_DOMAIN` define el dominio institucional que se muestra como sufijo fijo en
los formularios docentes de alumnado. El servidor reconstruye y valida siempre
la dirección completa antes de guardarla.

`APP_URL` es la base canónica para las URLs absolutas incluidas en los correos y
debe ser una URL HTTPS válida, sin una ruta adicional. En producción debe ser:

```text
APP_URL=https://projectes.elpuig.xeill.net
```

Las solicitudes de revisión de Proposta, Document funcional, Preparació de
l’entorn y apartados de Memòria comparten el mismo lenguaje visual de Web
Projectes: tarjeta blanca, identidad, CTA granate y footer sobre un fondo gris
claro. Todas estas notificaciones operativas se dirigen exclusivamente al tutor
formal del proyecto (`app.rel_proyectos_profesores.rol = 'tutor'`), nunca a sus
cotutores ni al resto de profesorado del grupo.

La comunicación de revisión de Memòria es bidireccional: cuando el alumnado
solicita revisar un apartado, se encola un único aviso para el tutor formal;
cuando el tutor resuelve esa revisión como `corregir` o `completo`, se encola un
aviso individual para cada alumno activo del proyecto que tenga un email válido.
El correo incluye el resultado y, cuando existe, el comentario del tutor.

Los mensajes encolados guardan `cuerpo_html` y `cuerpo_texto` ya renderizados,
incluidas sus URLs absolutas. Cada fila de `app.email_outbox` es, por tanto, una
instantánea: cambiar posteriormente `APP_URL` o una plantilla no modifica los
mensajes que ya estaban en la cola.

## Instalación

Instala las dependencias en cada despliegue:

```text
composer install --no-dev --optimize-autoloader
```

## Worker

Ejecución manual:

```text
C:\xampp\php\php.exe D:\Web_proyectos\inc\email\worker.php
```

En producción debe programarse mediante cron o el programador de tareas, por
ejemplo cada minuto. `FOR UPDATE SKIP LOCKED` permite más de un worker sin que dos
procesos reclamen simultáneamente el mismo mensaje. Un envío bloqueado durante más
de quince minutos vuelve a estar disponible.

Los fallos temporales se reintentan con espera creciente hasta `max_intentos`.
Los errores técnicos se registran, pero no se muestran en la interfaz.

## Feedback diario de Autoseguiment

La valoración y el comentario del tutor se editan de forma independiente y no
envían correo desde sus endpoints. `inc/seguimiento/feedback_worker.php`
consolida posteriormente los valores vigentes y encola, como máximo una vez por
`id_seguimiento`, un email dirigido al alumno propietario. Este generador no
envía SMTP; `inc/email/worker.php` continúa siendo el único consumidor de la
cola.

La marca `feedback_email_encolado_en` significa que el mensaje se creó
correctamente en `app.email_outbox`, no que se haya enviado, entregado o leído.
La operación de encolado y la marca se confirman en una misma transacción y la
clave `autoseguiment_feedback:{id_seguimiento}` aporta una segunda garantía de
idempotencia. Los errores SMTP y sus reintentos pertenecen a `EmailQueue`; no
generan un segundo feedback.

Como criterio reutilizable, cuando una acción genera feedback destinado a otra
persona, la edición del feedback puede desacoplarse de su notificación. En
Autoseguiment, el feedback semanal se consolida y se notifica posteriormente
como máximo una vez.

## Automatización en producción con systemd

Los generadores funcionales deciden qué mensajes deben existir y los insertan
en `app.email_outbox`; no transportan correo. Entre ellos,
`inc/seguimiento/feedback_worker.php` consolida diariamente el feedback de
Autoseguiment alrededor de las 07:00 (`Europe/Madrid`). Por separado,
`inc/email/worker.php`
procesa aproximadamente cada minuto los mensajes pendientes y es el responsable
del transporte SMTP. El worker SMTP no contiene reglas funcionales de
Autoseguiment.

Las plantillas de unidad se distribuyen en `deploy/systemd/`, pero systemd no
lee unidades desde el repositorio. Deben copiarse explícitamente a
`/etc/systemd/system/` durante el despliegue. Desde `/var/www/html`:

```bash
sudo install -m 0644 deploy/systemd/web-proyectos-email.service /etc/systemd/system/web-proyectos-email.service
sudo install -m 0644 deploy/systemd/web-proyectos-email.timer /etc/systemd/system/web-proyectos-email.timer
sudo install -m 0644 deploy/systemd/web-proyectos-autoseguiment-feedback.service /etc/systemd/system/web-proyectos-autoseguiment-feedback.service
sudo install -m 0644 deploy/systemd/web-proyectos-autoseguiment-feedback.timer /etc/systemd/system/web-proyectos-autoseguiment-feedback.timer
sudo systemctl daemon-reload
```

Abans d'activar el feedback cal haver aplicat la migració
`migrations/20260902_autoseguiment_feedback_email.sql`. Les proves manuals,
que poden encolar o enviar correu real segons el servei, s'executen una a una:

```bash
sudo systemctl start web-proyectos-autoseguiment-feedback.service
sudo systemctl start web-proyectos-email.service
```

Quan s'autoritzi l'activació automàtica, els timers s'habiliten així:

```bash
sudo systemctl enable --now web-proyectos-email.timer
sudo systemctl enable --now web-proyectos-autoseguiment-feedback.timer
```

Estat, pròximes execucions i logs recents:

```bash
systemctl status web-proyectos-email.service
systemctl status web-proyectos-email.timer
systemctl status web-proyectos-autoseguiment-feedback.service
systemctl status web-proyectos-autoseguiment-feedback.timer
systemctl list-timers web-proyectos-email.timer web-proyectos-autoseguiment-feedback.timer
journalctl -u web-proyectos-email.service -n 100 --no-pager
journalctl -u web-proyectos-autoseguiment-feedback.service -n 100 --no-pager
```

Tots dos serveis s'executen com `www-data`, amb `/var/www/html` com a directori
de treball, carreguen la configuració des del `.env` de l'aplicació i escriuen
stdout/stderr al journal. En reiniciar el servidor, el timer SMTP programa una
execució al cap d'uns 15 segons i continua cada minut. El timer diari utilitza
`Persistent=true`: si les 07:00 passen mentre el servidor està apagat, systemd
executa la tasca pendent després de tornar a arrencar.

`inc/seguimiento/recordatorio_worker.php` declara que la seva periodicitat és
de divendres, però ni el codi ni la documentació canònica fixen una hora. No es
distribueix encara cap timer per a aquest recordatori: falta decidir l'hora de
divendres en què s'ha d'encolar.

## Uso desde PHP

```php
require_once __DIR__ . '/../../email/bootstrap.php';

$queue = new EmailQueue($pdo);
$queue->enqueue([
    'destinatario' => 'persona@example.org',
    'asunto' => 'Assumpte',
    'cuerpo_html' => '<p>Missatge</p>',
    'cuerpo_texto' => 'Missatge',
    'tipo' => 'notificacion_proyecto',
    'proyecto_id' => $proyectoId,
    'creado_por' => $profesorId,
    'clave_idempotencia' => 'notificacion:' . $proyectoId . ':2026-08-20',
]);
```

Usa una `clave_idempotencia` estable en automatismos para que repetir una acción
no genere dos correos. Los envíos manuales pueden omitirla.
