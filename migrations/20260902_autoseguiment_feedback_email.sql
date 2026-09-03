BEGIN;

ALTER TABLE app.seguimiento_alumnos
    ADD COLUMN feedback_email_encolado_en timestamptz NULL,
    ADD COLUMN feedback_email_habilitado boolean;

-- Cohort d'activació explícita: els seguiments anteriors al desplegament no
-- generen una allau de correus. Els nous seguiments hi entren per defecte.
UPDATE app.seguimiento_alumnos
SET feedback_email_habilitado = false
WHERE feedback_email_habilitado IS NULL;

ALTER TABLE app.seguimiento_alumnos
    ALTER COLUMN feedback_email_habilitado SET DEFAULT true,
    ALTER COLUMN feedback_email_habilitado SET NOT NULL;

COMMENT ON COLUMN app.seguimiento_alumnos.feedback_email_encolado_en IS
    'Moment en què el feedback setmanal es va generar correctament a email_outbox; no acredita enviament ni lliurament SMTP.';
COMMENT ON COLUMN app.seguimiento_alumnos.feedback_email_habilitado IS
    'Inclou el seguiment en la notificació diària de feedback. False identifica la cohort històrica anterior a l’activació.';

CREATE INDEX seguimiento_feedback_email_pendent_idx
    ON app.seguimiento_alumnos (id_seguimiento)
    WHERE feedback_email_habilitado = true
      AND feedback_email_encolado_en IS NULL
      AND valoracion_tutor IS NOT NULL;

COMMIT;
