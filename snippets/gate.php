<?php

/**
 * Placeholder rendered in place of a blocked third-party embed.
 *
 * Receives: $vendor (vendor id, possibly "unknown:<host>"), $category, $url.
 * Rendered server-side by Scanner::gate(), so it is only ever present when the
 * category is NOT granted. The data-consent-gate attribute tells consent-core.js
 * that a reload is needed once consent is given.
 */

$vendors = (array) option('kirbycode.cookie-compliance.vendors', []);

$label = $vendors[$vendor]['name']
    ?? (str_starts_with($vendor, 'unknown:') ? substr($vendor, 8) : $vendor);

// Use the editor's own category wording so the gate matches the banner.
$categoryLabel = \Kirbycode\CookieCompliance\Settings::text($category . '_label', 'consent.' . $category);
?>
<div class="consent-gate" data-consent-gate="<?= html($vendor) ?>" data-consent-category="<?= html($category) ?>">
    <p class="consent-gate__title"><?= html(t('consent.gate.title')) ?></p>
    <p class="consent-gate__text"><?= html(tt('consent.gate.text', null, [
        'vendor'   => $label,
        'category' => $categoryLabel,
    ])) ?></p>
    <button type="button" class="consent-banner__btn consent-banner__btn--primary" data-consent-open>
        <?= html(t('consent.gate.settings')) ?>
    </button>
</div>
