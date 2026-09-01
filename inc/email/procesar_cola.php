<?php
declare(strict_types=1);

// Processa un lot de la cua (app.email_outbox): reclama els missatges
// pendents i els envia amb EmailService, marcant-los enviats o fallits.
// Aquesta és l'única lògica de processament del lot; tant el worker CLI
// (inc/email/worker.php) com el botó "Enviar cua" del panell de superadmin
// la criden igual, perquè totes dues vies facin exactament el mateix.
function emailProcesarColaPendent(EmailQueue $queue, EmailService $service, int $limite): array
{
    $messages = $queue->claimBatch($limite);
    $enviados = 0;
    $fallidos = 0;

    foreach ($messages as $message) {
        try {
            $service->send($message);
            $queue->markSent((int) $message['id_email']);
            $enviados++;
        } catch (Throwable $e) {
            $queue->markFailed(
                (int) $message['id_email'],
                (int) $message['intentos'] + 1,
                (int) $message['max_intentos'],
                $e->getMessage()
            );
            error_log('Email #' . (int) $message['id_email'] . ' no enviado: ' . $e->getMessage());
            $fallidos++;
        }
    }

    return ['procesados' => count($messages), 'enviados' => $enviados, 'fallidos' => $fallidos];
}
