<?php

use Kirbycode\CookieCompliance\Consent;
use Kirby\Filesystem\F;

/**
 * Emitted as the very first thing in <head>, before any other script.
 *
 * The Google Consent Mode v2 "default" block makes NO network request — it only
 * creates window.dataLayer and pushes one array. So it is always safe to emit,
 * even with no consent at all. The GTM container itself is only ever loaded by
 * consent-core.js once marketing consent exists.
 *
 * The data-consent-keep attribute stops our own scanner from gating these
 * scripts: consent-core.js contains the googletagmanager.com URL as a string.
 */

$gtmId = trim((string) site()->gtm_id()->value());

$config = [
    'cookie'        => (string) option('kirbycode.cookie-compliance.cookie', 'kcc_consent'),
    'maxAge'        => (int) option('kirbycode.cookie-compliance.maxAgeDays', 180) * 86400,
    'version'       => Consent::VERSION,
    'categories'    => array_keys(Consent::CATEGORIES),
    'signalMap'     => (array) option('kirbycode.cookie-compliance.signalMap', []),
    'gtmId'         => $gtmId,
    'videoCategory' => (string) option('kirbycode.cookie-compliance.videoCategory', 'marketing'),

    // Used by the client-side video gate in assets/js/script.js.
    'strings' => [
        'videoTitle'    => t('consent.video.title'),
        'videoText'     => t('consent.video.text'),
        'videoLoadOnce' => t('consent.video.load-once'),
        'videoSettings' => t('consent.video.settings'),
    ],
];

// Slim copy of the vendor registry for the runtime guard: it only needs the
// host patterns and the category, never the names or SVG logos. Keeping one
// registry in PHP means the scanner and the guard can never drift apart.
$config['vendors'] = array_map(
    fn (array $vendor): array => [
        'hosts'    => array_values((array) ($vendor['hosts'] ?? [])),
        'category' => $vendor['category'] ?? 'marketing',
    ],
    (array) option('kirbycode.cookie-compliance.vendors', [])
);

$config['allowHosts']      = array_values((array) option('kirbycode.cookie-compliance.allowHosts', []));
$config['gateUnknown']     = (bool) option('kirbycode.cookie-compliance.scan.gateUnknown', true);
$config['unknownCategory'] = (string) option('kirbycode.cookie-compliance.scan.unknownCategory', 'marketing');

$coreJs  = dirname(__DIR__) . '/assets/consent-core.js';
$guardJs = dirname(__DIR__) . '/assets/consent-guard.js';
?>
<script data-consent-keep>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'functionality_storage': 'denied',
  'personalization_storage': 'denied',
  'security_storage': 'granted'
});
gtag('set', 'ads_data_redaction', true);
gtag('set', 'url_passthrough', true);
</script>
<script type="application/json" id="ah-consent-config" data-consent-keep><?= json_encode(
    $config,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<?php if (is_file($coreJs)): ?>
<script data-consent-keep><?= F::read($coreJs) ?></script>
<?php endif ?>
<?php /* Must run before any site JS can create a third-party element. */ ?>
<?php if (is_file($guardJs)): ?>
<script data-consent-keep><?= F::read($guardJs) ?></script>
<?php endif ?>
