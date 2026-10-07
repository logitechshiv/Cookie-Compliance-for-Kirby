<?php

use Kirbycode\CookieCompliance\Consent;
use Kirbycode\CookieCompliance\Settings;
use Kirby\Filesystem\F;

/**
 * The consent dialog, plus the floating re-open button.
 *
 * Texts, links and colours all come from Settings, which reads the Panel fields
 * and falls back to the plugin's own translations and defaults — so this renders
 * correctly with nothing filled in.
 *
 * The dialog is hidden server-side only when the visitor has a complete,
 * current-version decision, so a legacy or corrupt cookie correctly re-prompts.
 */

$state   = Consent::state();
$decided = $state['complete'];
$labels  = Settings::labels();

$cssFile = dirname(__DIR__) . '/assets/consent.css';
$jsFile  = dirname(__DIR__) . '/assets/consent-ui.js';

// "necessary" is rendered separately as a locked row.
$categories = array_keys(array_slice(Consent::CATEGORIES, 1, null, true));

$privacy = Settings::page('privacy_page', 'datenschutz');
$imprint = Settings::page('imprint_page', 'impressum');
?>
<style><?= Settings::cssVariables() ?><?= is_file($cssFile) ? F::read($cssFile) : '' ?></style>

<div id="consent-banner"
    class="consent-banner"
    <?php if ($decided): ?>hidden<?php endif ?>
    data-lenis-prevent
    role="dialog"
    aria-labelledby="consent-title"
    aria-modal="true">
    <div class="consent-banner__panel">
        <h2 id="consent-title" class="consent-banner__title"><?= html($labels['title']) ?></h2>
        <p class="consent-banner__text"><?= nl2br(html($labels['text'])) ?></p>

        <div class="consent-banner__options">
            <label class="consent-option">
                <span>
                    <strong><?= html($labels['necessary']) ?></strong>
                    <span class="consent-option__hint"><?= html($labels['necessaryHint']) ?></span>
                </span>
                <input type="checkbox" checked disabled>
            </label>

            <?php foreach ($categories as $category): ?>
            <label class="consent-option">
                <span>
                    <strong><?= html($labels[$category] ?? $category) ?></strong>
                    <span class="consent-option__hint"><?= html($labels[$category . 'Hint'] ?? '') ?></span>
                </span>
                <input type="checkbox"
                    data-consent-category="<?= html($category) ?>"
                    <?= $state['c'][$category] ? 'checked' : '' ?>>
            </label>
            <?php endforeach ?>
        </div>

        <div class="consent-banner__actions">
            <button type="button" class="consent-banner__btn consent-banner__btn--primary" data-consent-accept-all><?= html($labels['acceptAll']) ?></button>
            <button type="button" class="consent-banner__btn" data-consent-necessary><?= html($labels['necessaryOnly']) ?></button>
            <button type="button" class="consent-banner__btn" data-consent-save><?= html($labels['save']) ?></button>
        </div>

        <p class="consent-banner__legal">
            <?php if ($privacy): ?>
            <a href="<?= $privacy->url() ?>"><?= html($labels['privacyLabel']) ?></a>
            <?php endif ?>
            <?php if ($imprint): ?>
            <a href="<?= $imprint->url() ?>"><?= html($labels['imprintLabel']) ?></a>
            <?php endif ?>
            <?php if ($decided && $state['ts'] > 0): ?>
            <span class="consent-banner__given"><?= html(tt('consent.given-on', null, [
                'date' => date('d.m.Y', $state['ts']),
            ])) ?></span>
            <?php endif ?>
        </p>
    </div>
</div>

<button type="button" class="consent-fab" data-consent-open aria-label="<?= html($labels['settings']) ?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M12 3a9 9 0 1 0 8.66 11.5A3.5 3.5 0 0 1 15 12a3.5 3.5 0 0 1 2.12-3.22A9 9 0 0 0 12 3Z" stroke="currentColor" stroke-width="1.6"/>
        <circle cx="8.5" cy="10" r="1" fill="currentColor"/>
        <circle cx="11.5" cy="14.5" r="1" fill="currentColor"/>
        <circle cx="14.5" cy="9.5" r="1" fill="currentColor"/>
        <circle cx="9.5" cy="16.5" r="0.85" fill="currentColor"/>
    </svg>
</button>

<?php if (!$decided): ?>
<script data-consent-keep>document.body.classList.add('consent-open');</script>
<?php endif ?>
<?php if (is_file($jsFile)): ?>
<script data-consent-keep><?= F::read($jsFile) ?></script>
<?php endif ?>
