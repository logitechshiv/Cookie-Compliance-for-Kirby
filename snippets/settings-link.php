<?php

use Kirbycode\CookieCompliance\Settings;

/**
 * Re-opens the consent dialog. Drop this anywhere a "Cookie-Einstellungen"
 * control is needed; the click is handled by a delegated listener, so it works
 * no matter when it is added to the page.
 *
 * Optional: $label to override the Panel text, $class for styling.
 */
?>
<button type="button" data-consent-open<?= isset($class) ? ' class="' . html($class) . '"' : '' ?>><?= html($label ?? Settings::text('settings', 'consent.settings')) ?></button>
