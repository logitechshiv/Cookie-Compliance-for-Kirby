<?php

use Kirbycode\CookieCompliance\Consent;
use Kirbycode\CookieCompliance\Injector;
use Kirbycode\CookieCompliance\Scanner;
use Kirby\Cms\App;

require_once __DIR__ . '/src/Consent.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Scanner.php';
require_once __DIR__ . '/src/Injector.php';

/**
 * Global helper.
 *
 * consent()            -> the full state array
 * consent('marketing') -> bool
 *
 * Kirby has no "helpers" extension point, so a function_exists-guarded
 * definition here is the correct pattern.
 */
if (function_exists('consent') === false) {
    function consent(string|null $category = null): bool|array
    {
        return $category === null
            ? Consent::state()
            : Consent::has($category);
    }
}

$strings = require __DIR__ . '/translations/de.php';

App::plugin('kirbycode/cookie-compliance', [
    'license' => 'MIT',
    'version' => '1.0.0',

    'options' => [
        'cookie'        => 'kcc_consent',
        'legacyCookies' => ['ah_consent', 'zk_consent'],
        'maxAgeDays'    => 180,

        // Which consent category third-party media embeds require.
        'videoCategory' => 'marketing',

        // Auto-placement of the plugin's markup. Turn one off to call the
        // matching snippet by hand instead.
        'inject' => [
            'head'   => true,  // consent/consent-mode after <head>
            'body'   => true,  // consent/gtm-noscript after <body>
            'banner' => true,  // consent/banner before </body>
        ],

        // Hosts that are never gated (self-hosted CDNs, your own asset domain).
        'allowHosts'    => [],

        'scan' => [
            'enabled'         => true,
            'elements'        => ['iframe', 'script', 'img', 'link', 'embed', 'object'],
            'inlineScripts'   => true,
            'gateUnknown'     => true,
            'unknownCategory' => 'marketing',

            // Show a visible placeholder where a blocked <script> in the <body>
            // would have rendered something (an embedded form, a widget, a map),
            // instead of leaving an unexplained empty space. Head scripts always
            // become an inert comment.
            'placeholderForScripts' => true,
        ],

        // Consent category -> Google Consent Mode v2 signals.
        'signalMap' => [
            'functional' => ['functionality_storage', 'personalization_storage'],
            'statistics' => ['analytics_storage'],
            'marketing'  => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        ],

        'vendors' => [
            // `scriptPatterns` match the body of an INLINE script. They matter
            // whenever a vendor ships a loader plus a separate initialiser: if
            // only the loader is blocked, the initialiser still runs and throws
            // a ReferenceError. Matching the global it calls blocks both.
            'googletagmanager' => [
                'name'     => 'Google Tag Manager',
                'hosts'    => ['*.googletagmanager.com'],
                'category' => 'marketing',
            ],
            'hubspot' => [
                'name'     => 'HubSpot',
                'hosts'    => [
                    '*.hsforms.net', '*.hsforms.com', '*.hs-scripts.com',
                    '*.hs-analytics.net', '*.hscollectedforms.net', '*.hubspot.com',
                ],
                'category'       => 'marketing',
                'scriptPatterns' => ['\bhbspt\s*\.'],
            ],
            'googleanalytics' => [
                'name'     => 'Google Analytics',
                'hosts'    => ['*.google-analytics.com', '*.analytics.google.com'],
                'category' => 'statistics',
            ],
            'googleads' => [
                'name'     => 'Google Ads',
                'hosts'    => ['*.doubleclick.net', '*.googleadservices.com', '*.googlesyndication.com'],
                'category' => 'marketing',
            ],
            'googlefonts' => [
                'name'     => 'Google Fonts',
                'hosts'    => ['fonts.googleapis.com', 'fonts.gstatic.com'],
                'category' => 'functional',
            ],
            'googlemaps' => [
                'name'     => 'Google Maps',
                'hosts'    => ['maps.googleapis.com', 'maps.google.com'],
                'category' => 'functional',
            ],
            'recaptcha' => [
                'name'     => 'Google reCAPTCHA',
                'hosts'    => ['*.recaptcha.net', 'www.gstatic.com'],
                'category' => 'functional',
            ],
            'youtube' => [
                'name'     => 'YouTube',
                'hosts'    => ['*.youtube.com', '*.youtube-nocookie.com', 'youtu.be', '*.ytimg.com'],
                'category' => 'marketing',
            ],
            'vimeo' => [
                'name'     => 'Vimeo',
                'hosts'    => ['*.vimeo.com', '*.vimeocdn.com'],
                'category' => 'marketing',
            ],
            'instagram' => [
                'name'     => 'Instagram',
                'hosts'    => ['*.instagram.com', '*.cdninstagram.com'],
                'category' => 'marketing',
            ],
            'facebook' => [
                'name'           => 'Facebook',
                'hosts'          => ['*.facebook.com', '*.facebook.net', '*.fbcdn.net'],
                'category'       => 'marketing',
                'scriptPatterns' => ['\bfbq\s*\('],
            ],
            'linkedin' => [
                'name'     => 'LinkedIn',
                'hosts'    => ['*.linkedin.com', '*.licdn.com', '*.ads.linkedin.com'],
                'category' => 'marketing',
            ],
            'x' => [
                'name'     => 'X (Twitter)',
                'hosts'    => ['*.twitter.com', '*.x.com', '*.twimg.com'],
                'category' => 'marketing',
            ],
            'hotjar' => [
                'name'           => 'Hotjar',
                'hosts'          => ['*.hotjar.com', '*.hotjar.io'],
                'category'       => 'statistics',
                // Only the distinctive settings object: a bare `hj(` would
                // false-positive against any minified script with an `hj` local.
                'scriptPatterns' => ['_hjSettings'],
            ],
            'opentable' => [
                'name'     => 'OpenTable',
                'hosts'    => ['*.opentable.com', '*.opentable.de'],
                'category' => 'functional',
            ],
        ],
    ],

    // German strings must also be registered under 'en': this site is
    // single-language, so I18n::$locale is always 'en' and the 'en' fallback
    // is skipped by I18n::translate()'s fallback loop.
    'translations' => [
        'en' => $strings,
        'de' => $strings,
    ],

    // Add `consent: extends: cookie-compliance/settings` to the site blueprint's
    // tabs to surface the Panel settings. See INTEGRATION.md.
    // A closure rather than a file path: the tab is built in PHP so its field
    // defaults come from translations/de.php and Settings::COLORS, instead of
    // being retyped in YAML where they would drift out of sync.
    'blueprints' => [
        'cookie-compliance/settings' => fn () => require __DIR__ . '/blueprints/settings.php',
    ],

    'snippets' => [
        'cookie-compliance/banner'        => __DIR__ . '/snippets/banner.php',
        'cookie-compliance/consent-mode'  => __DIR__ . '/snippets/consent-mode.php',
        'cookie-compliance/gate'          => __DIR__ . '/snippets/gate.php',
        'cookie-compliance/gtm-noscript'  => __DIR__ . '/snippets/gtm-noscript.php',
        'cookie-compliance/settings-link' => __DIR__ . '/snippets/settings-link.php',
    ],

    'siteMethods' => [
        'consent' => function (string|null $category = null) {
            return consent($category);
        },
    ],

    'hooks' => [
        'page.render:after' => function (
            string $contentType,
            array $data,
            string $html,
            Kirby\Cms\Page $page
        ): string {
            if ($contentType !== 'html') {
                return $html;
            }

            // Scan the site's own markup first, then add ours — so the plugin's
            // scripts never pass through its own scanner.
            return Injector::apply(Scanner::apply($html));
        },
    ],
]);
