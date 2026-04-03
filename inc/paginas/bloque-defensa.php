<style>
.defensa-banner-v3 {
    display: grid;
    grid-template-columns: 1.25fr 1.75fr;
    gap: 22px;
    align-items: stretch;
    background: #D97706;
    border-radius: 26px;
    padding: 20px 24px;
    margin-bottom: 28px;
    color: #fff;
    box-shadow: 0 14px 34px rgba(217, 119, 6, 0.18);
}

.defensa-banner-v3-date {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 6px 4px;
}

.defensa-banner-v3-kicker {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    opacity: 0.9;
    margin-bottom: 8px;
    line-height: 1;
}

.defensa-banner-v3-date-value {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1.02;
    letter-spacing: -0.02em;
    max-width: 340px;
}

.defensa-banner-v3-side {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 14px;
}

.defensa-banner-v3-top {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.defensa-banner-v3-box,
.defensa-banner-v3-tribunal {
    background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 18px;
    padding: 16px 18px;
   
}

.defensa-banner-v3-label {
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.9;
    margin-bottom: 6px;
    line-height: 1;
}

.defensa-banner-v3-value {
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1.15;
    color: #fff;
}

.defensa-banner-v3-tribunal {
    min-height: auto;

}

.defensa-banner-v3-tribunal-value {
    font-size: 1.1rem;
    font-weight: 600;
    line-height: 1.35;
    color: #fff;
}

@media (max-width: 991.98px) {
    .defensa-banner-v3 {
        grid-template-columns: 1fr;
    }

    .defensa-banner-v3-date-value {
        max-width: none;
        font-size: 1.7rem;
    }
}

@media (max-width: 575.98px) {
    .defensa-banner-v3 {
        padding: 18px;
        gap: 16px;
    }

    .defensa-banner-v3-top {
        grid-template-columns: 1fr;
    }

    .defensa-banner-v3-date-value {
        font-size: 1.5rem;
    }

    .defensa-banner-v3-box,
    .defensa-banner-v3-tribunal {
        padding: 14px 16px;
        min-height: auto;
    }
}
</style>

<?php
// ── Defensa banner ────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT
            p.defensa_fecha,
            TO_CHAR(p.defensa_fecha, 'HH24:MI') AS hora,
            a.codigo                             AS aula_codigo,
            a.nombre                             AS aula_nombre,
            string_agg(
                TRIM(pr.nombre || ' ' || pr.apellidos),
                ' · ' ORDER BY pr.apellidos, pr.nombre
            ) AS tribunal
        FROM app.proyectos p
        LEFT JOIN app.aulas a
            ON a.id_aula = p.defensa_aula_id
        LEFT JOIN app.rel_profesores_tribunal rpt
            ON rpt.id_proyecto = p.id_proyecto
        LEFT JOIN app.profesores pr
            ON pr.id_profesor = rpt.profesor_id
        WHERE p.id_proyecto = ?
          AND p.defensa_fecha IS NOT NULL
        GROUP BY p.defensa_fecha, a.codigo, a.nombre
    ");
    $stmt->execute([$idProyecto]);
    $defensa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $defensa = null;
}

if ($defensa):
    $dies  = ['Diumenge','Dilluns','Dimarts','Dimecres','Dijous','Divendres','Dissabte'];
    $mesos = ['','gener','febrer','març','abril','maig','juny',
               'juliol','agost','setembre','octubre','novembre','desembre'];
    $ts    = strtotime($defensa['defensa_fecha']);
    $dia_setmana = $dies[(int)date('w', $ts)];
    $dia_num     = (int)date('j', $ts);
    $mes         = $mesos[(int)date('n', $ts)];
    $any         = date('Y', $ts);
    $aula        = trim(($defensa['aula_codigo'] ?? '') . ($defensa['aula_nombre'] ? ' · ' . $defensa['aula_nombre'] : ''));
?>
<div class="defensa-banner-v3">
    <div class="defensa-banner-v3-date">
        <div class="defensa-banner-v3-kicker">Defensa</div>
        <div class="defensa-banner-v3-date-value">
            <?= h($dia_setmana) ?>,<br><?= $dia_num . ' de ' . h($mes) . ' de ' . $any ?>
        </div>
    </div>
    <div class="defensa-banner-v3-side">
        <div class="defensa-banner-v3-top">
            <div class="defensa-banner-v3-box">
                <div class="defensa-banner-v3-label">Hora</div>
                <div class="defensa-banner-v3-value"><?= h($defensa['hora']) ?></div>
            </div>
            <div class="defensa-banner-v3-box">
                <div class="defensa-banner-v3-label">Aula</div>
                <div class="defensa-banner-v3-value"><?= h($aula ?: '—') ?></div>
            </div>
        </div>
        <div class="defensa-banner-v3-tribunal">
            <div class="defensa-banner-v3-label">Tribunal</div>
            <div class="defensa-banner-v3-tribunal-value">
                <?= h($defensa['tribunal'] ?? '—') ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
