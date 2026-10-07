<?php

namespace Kirbycode\CookieCompliance;

/**
 * Scans rendered HTML for third-party resources and gates the ones the visitor
 * has not consented to.
 *
 * Because consent is known server-side at render time this scanner has a branch
 * a client-side blocker cannot have: when the category is already granted the
 * element is left completely untouched, so the genuine embed is emitted with no
 * placeholder, no base64 round-trip and no client-side DOM surgery.
 *
 * Every rewrite goes through preg_replace_callback with a *callback*, never a
 * replacement string, so markup we inject (SVG paths, query strings) can never
 * be reinterpreted as a $1 backreference.
 */
final class Scanner
{
    /** Tags that carry no closing tag in HTML5. */
    private const VOID_TAGS = ['img', 'link', 'embed'];

    /** Tags whose element spans through a matching closing tag. */
    private const PAIRED_TAGS = ['iframe', 'script', 'object'];

    /** Which attribute holds the URL, per tag. */
    private const URL_ATTRIBUTES = [
        'iframe' => 'src',
        'script' => 'src',
        'img'    => 'src',
        'link'   => 'href',
        'embed'  => 'src',
        'object' => 'data',
    ];

    private static string|null $siteHost = null;

    /** Vendors detected on the current render, keyed by vendor id. */
    private static array $detected = [];

    /**
     * Gate every un-consented third-party resource in the given HTML.
     */
    public static function apply(string $html): string
    {
        if (option('kirbycode.cookie-compliance.scan.enabled', true) === false) {
            return $html;
        }

        // Nothing left to gate.
        if (Consent::allGranted() === true) {
            return $html;
        }

        // Cheap precheck: no absolute or protocol-relative URL anywhere.
        if (str_contains($html, '//') === false) {
            return $html;
        }

        static::$detected = [];

        $tags = (array) option('kirbycode.cookie-compliance.scan.elements', [
            'iframe', 'script', 'img', 'link', 'embed', 'object',
        ]);

        foreach ($tags as $tag) {
            $tag = strtolower($tag);

            if (isset(static::URL_ATTRIBUTES[$tag]) === false) {
                continue;
            }

            $html = static::processTag($html, $tag);
        }

        if (option('kirbycode.cookie-compliance.scan.inlineScripts', true) === true) {
            $html = static::processInlineScripts($html);
        }

        return $html;
    }

    /**
     * Vendors detected during the last apply() call.
     *
     * @return array<string,string> vendor id => category
     */
    public static function detected(): array
    {
        return static::$detected;
    }

    /**
     * Rewrite every element of one tag whose URL needs gating.
     */
    private static function processTag(string $html, string $tag): string
    {
        $attribute = static::URL_ATTRIBUTES[$tag];

        // Opening tag, plus the rest of the element for paired tags.
        $pattern = in_array($tag, static::PAIRED_TAGS, true)
            ? '#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is'
            : '#<' . $tag . '\b[^>]*?/?>#is';

        $result = preg_replace_callback(
            $pattern,
            function (array $match) use ($tag, $attribute): string {
                $element = $match[0];
                $url     = static::attribute($element, $attribute);

                if ($url === null) {
                    return $element;
                }

                $decision = static::decide($url);

                // Same origin, allowlisted, or already consented: untouched.
                if ($decision === null) {
                    return $element;
                }

                static::$detected[$decision['vendor']] = $decision['category'];

                return static::gate($tag, $url, $decision);
            },
            $html
        );

        // A catastrophic backtrack or encoding error must never blank the page.
        return $result ?? $html;
    }

    /**
     * Neutralise inline scripts that reference a known tracker host.
     *
     * This is the case a DOM-attribute scan cannot see: the standard Google Tag
     * Manager snippet has no src attribute at all, it builds
     * j.src='https://www.googletagmanager.com/gtm.js?id='+i at runtime.
     */
    private static function processInlineScripts(string $html): string
    {
        $result = preg_replace_callback(
            '#<script\b(?![^>]*\bsrc\s*=)([^>]*)>(.*?)</script\s*>#is',
            function (array $match): string {
                $element = $match[0];
                $body    = $match[2];

                if (trim($body) === '') {
                    return $element;
                }

                // Our own consent runtime must never gate itself.
                if (str_contains($match[1], 'data-consent-keep') === true) {
                    return $element;
                }

                $decision = static::decideByContent($body);

                if ($decision === null) {
                    return $element;
                }

                static::$detected[$decision['vendor']] = $decision['category'];

                return '<script type="text/plain" data-consent-blocked="'
                    . htmlspecialchars($decision['vendor'], ENT_QUOTES)
                    . '" data-consent-category="'
                    . htmlspecialchars($decision['category'], ENT_QUOTES)
                    . '">' . $body . '</script>';
            },
            $html
        );

        return $result ?? $html;
    }

    /**
     * Decide what to do with a URL.
     *
     * @return array{vendor:string,category:string}|null null = leave untouched
     */
    private static function decide(string $url): array|null
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'blob:')) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Relative or root-relative URL: same origin by definition.
        if (is_string($host) === false || $host === '') {
            return null;
        }

        $host = strtolower(ltrim($host, '.'));

        if (static::isOwnHost($host) === true) {
            return null;
        }

        foreach ((array) option('kirbycode.cookie-compliance.allowHosts', []) as $allowed) {
            if (static::hostMatches($host, (string) $allowed) === true) {
                return null;
            }
        }

        $vendor   = null;
        $category = null;

        foreach (static::vendors() as $id => $config) {
            foreach ((array) ($config['hosts'] ?? []) as $pattern) {
                if (static::hostMatches($host, (string) $pattern) === true) {
                    $vendor   = $id;
                    $category = $config['category'] ?? 'marketing';
                    break 2;
                }
            }
        }

        if ($vendor === null) {
            // Unknown third party: fail closed unless explicitly disabled.
            if (option('kirbycode.cookie-compliance.scan.gateUnknown', true) === false) {
                return null;
            }

            $vendor   = 'unknown:' . $host;
            $category = (string) option('kirbycode.cookie-compliance.scan.unknownCategory', 'marketing');
        }

        if (Consent::has($category) === true) {
            return null;
        }

        return ['vendor' => $vendor, 'category' => $category];
    }

    /**
     * Decide based on an inline script body rather than a URL.
     *
     * @return array{vendor:string,category:string}|null
     */
    private static function decideByContent(string $body): array|null
    {
        foreach (static::vendors() as $id => $config) {
            foreach ((array) ($config['hosts'] ?? []) as $pattern) {
                $needle = str_replace('*.', '', (string) $pattern);

                if ($needle === '' || stripos($body, $needle) === false) {
                    continue;
                }

                $category = $config['category'] ?? 'marketing';

                if (Consent::has($category) === true) {
                    return null;
                }

                return ['vendor' => $id, 'category' => $category];
            }
        }

        return null;
    }

    /**
     * Build the replacement markup for a gated element.
     */
    private static function gate(string $tag, string $url, array $decision): string
    {
        // Scripts, stylesheets and pixels have no meaningful visual placeholder;
        // dropping them to an inert type is enough.
        if ($tag === 'script' || $tag === 'link' || $tag === 'img') {
            return '<!-- blocked by consent: '
                . htmlspecialchars($decision['vendor'], ENT_QUOTES)
                . ' (' . htmlspecialchars($decision['category'], ENT_QUOTES) . ') -->';
        }

        return snippet('cookie-compliance/gate', [
            'vendor'   => $decision['vendor'],
            'category' => $decision['category'],
            'url'      => $url,
        ], true);
    }

    /**
     * Read one attribute out of a raw element string.
     * Handles double-quoted, single-quoted and unquoted values.
     */
    private static function attribute(string $element, string $name): string|null
    {
        // Only look at the opening tag, never at the element's content.
        $end  = strpos($element, '>');
        $open = $end === false ? $element : substr($element, 0, $end + 1);

        $pattern = '#\b' . preg_quote($name, '#') . '\s*=\s*'
            . '(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))#i';

        if (preg_match($pattern, $open, $match) !== 1) {
            return null;
        }

        foreach ([1, 2, 3] as $group) {
            if (isset($match[$group]) === true && $match[$group] !== '') {
                return $match[$group];
            }
        }

        return null;
    }

    /**
     * All configured vendors, keyed by id.
     */
    private static function vendors(): array
    {
        return (array) option('kirbycode.cookie-compliance.vendors', []);
    }

    private static function isOwnHost(string $host): bool
    {
        static::$siteHost ??= strtolower((string) parse_url(site()->url(), PHP_URL_HOST));

        if (static::$siteHost === '') {
            return false;
        }

        return $host === static::$siteHost
            || str_ends_with($host, '.' . static::$siteHost);
    }

    /**
     * Match a host against a pattern, where "*.example.com" also matches
     * "example.com" and any subdomain.
     */
    private static function hostMatches(string $host, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));

        if ($pattern === '') {
            return false;
        }

        if (str_starts_with($pattern, '*.') === true) {
            $base = substr($pattern, 2);

            return $host === $base || str_ends_with($host, '.' . $base);
        }

        return $host === $pattern;
    }
}
