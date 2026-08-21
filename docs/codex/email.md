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
