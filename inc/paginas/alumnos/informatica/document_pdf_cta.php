<?php
declare(strict_types=1);
$documentPdfCtaUrl = trim((string) ($documentPdfCtaUrl ?? ''));
if ($documentPdfCtaUrl === '') return;
?>
<a href="<?= htmlspecialchars($documentPdfCtaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mega-btn mega-btn-outline mega-btn--natural mega-btn--completat">
    <span class="mega-icon icon-memoria">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path>
            <path d="M14 3v5h5"></path>
            <path d="M9 13h6"></path>
            <path d="M9 17h6"></path>
        </svg>
    </span>
    <span class="mega-text">
        <strong><?= htmlspecialchars((string) $documentPdfCtaTitol, ENT_QUOTES, 'UTF-8') ?> →</strong>
        <small><?= htmlspecialchars((string) $documentPdfCtaSubtitol, ENT_QUOTES, 'UTF-8') ?></small>
    </span>
</a>
