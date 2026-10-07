<?php

namespace Kirbycode\CookieCompliance;

use Kirby\Cms\App;

/**
 * Reads and validates the visitor's consent state.
 *
 * Caching: every cookie we look at is first registered on Kirby's Responder via
 * usesCookie(). Responder::isPrivate() then reports the response as private for
 * any visitor who actually has that cookie set, which makes Kirby skip writing
 * the page cache and emit "Cache-Control: no-store, private" for upstream
 * caches. Without that registration a consented visitor's HTML could be cached
 * and served to someone who has given no consent at all.
 *
 * We deliberately do NOT use Kirby\Http\Cookie::get() here: it only returns
 * values carrying Kirby's own HMAC signature ("<hash>+<value>"), and this cookie
 * is written by JavaScript, which has no access to the server-side salt.
 * Cookie::get() would therefore always return null. We register the cookie
 * ourselves and read the raw value.
 */
final class Consent
{
    /**
     * Schema AND policy version.
     *
     * Bump this whenever the category model or the vendor list changes:
     * every visitor is then re-prompted, because a cookie written against an
     * older version is treated as an incomplete decision.
     */
    public const VERSION = 2;

    /**
     * All known categories and their default (= denied) state.
     * "necessary" is always granted and cannot be switched off.
     */
    public const CATEGORIES = [
        'necessary'  => true,
        'functional' => false,
        'statistics' => false,
        'marketing'  => false,
    ];

    /**
     * Largest cookie payload we are willing to json_decode.
     */
    private const MAX_LENGTH = 1024;

    private static array|null $state = null;

    /**
     * Resolved consent state for the current request.
     *
     * @return array{complete:bool,version:int,ts:int,c:array<string,bool>}
     */
    public static function state(): array
    {
        if (static::$state !== null) {
            return static::$state;
        }

        $names = [
            option('kirbycode.cookie-compliance.cookie', 'kcc_consent'),
            ...(array) option('kirbycode.cookie-compliance.legacyCookies', ['ah_consent', 'zk_consent']),
        ];

        $responder = App::instance(lazy: true)?->response();

        foreach ($names as $name) {
            $name = (string) $name;

            // Must happen for every candidate cookie, even when it is absent:
            // that is what keeps the cached variant and the live variant apart.
            $responder?->usesCookie($name);

            $raw = $_COOKIE[$name] ?? null;

            if (is_string($raw) === false || $raw === '' || strlen($raw) > static::MAX_LENGTH) {
                continue;
            }

            if ($parsed = static::parse($raw)) {
                return static::$state = $parsed;
            }
        }

        return static::$state = static::denied();
    }

    /**
     * Has the visitor granted this category?
     *
     * Fails closed: anything other than an explicit, complete, current-version
     * grant returns false.
     */
    public static function has(string $category): bool
    {
        if ($category === 'necessary') {
            return true;
        }

        $state = static::state();

        return $state['complete'] === true
            && ($state['c'][$category] ?? false) === true;
    }

    /**
     * Has the visitor made a decision we can still honour?
     * False for no cookie, a corrupt cookie, or a cookie from an older version.
     */
    public static function isDecided(): bool
    {
        return static::state()['complete'];
    }

    /**
     * Only the categories that are currently granted.
     *
     * @return list<string>
     */
    public static function granted(): array
    {
        return array_keys(array_filter(static::state()['c']));
    }

    /**
     * True when every category is granted — lets the scanner skip its work.
     */
    public static function allGranted(): bool
    {
        return static::state()['c'] === array_fill_keys(array_keys(static::CATEGORIES), true);
    }

    /**
     * Forget the memoised state. Only needed in tests.
     */
    public static function flush(): void
    {
        static::$state = null;
    }

    /**
     * @return array{complete:bool,version:int,ts:int,c:array<string,bool>}|null
     */
    private static function parse(string $raw): array|null
    {
        $data = json_decode(rawurldecode($raw), true);

        if (is_array($data) === false) {
            return null;
        }

        $version = (int) ($data['v'] ?? 1);

        // A cookie from a newer deploy that was rolled back. We cannot know what
        // its categories meant, so treat it as no decision at all.
        if ($version > static::VERSION) {
            return null;
        }

        // Legacy v1 from the OpenTable plugin: {"ot":bool,"v":1}.
        // "ot" was a functional embed, so it maps to the functional category —
        // but it says nothing about statistics or marketing, so the decision is
        // deliberately marked incomplete and the banner is shown again.
        if ($version < static::VERSION) {
            $state                     = static::denied();
            $state['version']          = $version;
            $state['c']['functional']  = ($data['ot'] ?? false) === true;

            return $state;
        }

        $given = $data['c'] ?? null;

        if (is_array($given) === false) {
            return null;
        }

        $c = [];

        foreach (array_keys(static::CATEGORIES) as $key) {
            // Strict comparison on purpose: a hand-crafted cookie carrying
            // "1", 1 or "true" must not count as a grant.
            $c[$key] = ($given[$key] ?? false) === true;
        }

        $c['necessary'] = true;

        return [
            'complete' => true,
            'version'  => static::VERSION,
            'ts'       => (int) ($data['ts'] ?? 0),
            'c'        => $c,
        ];
    }

    /**
     * @return array{complete:bool,version:int,ts:int,c:array<string,bool>}
     */
    private static function denied(): array
    {
        return [
            'complete' => false,
            'version'  => 0,
            'ts'       => 0,
            'c'        => static::CATEGORIES,
        ];
    }
}
