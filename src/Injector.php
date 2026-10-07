<?php

namespace Kirbycode\CookieCompliance;

/**
 * Places the plugin's own markup into the rendered page, so a clean install
 * needs no template edits at all.
 *
 * Each injection point can be turned off via the `inject.*` options when a
 * project wants to call the snippets by hand instead.
 */
final class Injector
{
    public static function apply(string $html): string
    {
        if (option('kirbycode.cookie-compliance.inject.head', true) === true) {
            $html = static::afterOpeningTag($html, 'head', 'cookie-compliance/consent-mode');
        }

        if (option('kirbycode.cookie-compliance.inject.body', true) === true) {
            $html = static::afterOpeningTag($html, 'body', 'cookie-compliance/gtm-noscript');
        }

        if (Consent::isDecided() === false) {
            $html = static::lockScroll($html);
        }

        if (option('kirbycode.cookie-compliance.inject.banner', true) === true) {
            $html = static::beforeClosingBody($html, 'cookie-compliance/banner');
        }

        return $html;
    }

    /**
     * Insert a snippet directly after the opening <head> or <body> tag.
     *
     * The pattern deliberately requires whitespace or '>' straight after the tag
     * name: a naive `<head[^>]*>` also matches `<header class="…">`, which would
     * drop the consent scripts into the middle of the page.
     */
    private static function afterOpeningTag(string $html, string $tag, string $snippet): string
    {
        $pattern = '#<' . $tag . '(\s[^>]*)?>#i';

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        $markup = static::render($snippet);

        if ($markup === '') {
            return $html;
        }

        $at = $match[0][1] + strlen($match[0][0]);

        return substr($html, 0, $at) . "\n" . $markup . substr($html, $at);
    }

    /**
     * Put the scroll-lock class on <body> server-side, in the markup itself.
     *
     * This has to happen here rather than from JavaScript. Adding the class
     * after the page has painted removes the scrollbar at that moment, the
     * viewport widens by its width, and every percentage-width box on the page
     * shifts horizontally — a large, entirely avoidable CLS penalty that hits
     * the whole document at once. With the class present in the first byte of
     * <body>, no scrollbar is ever rendered, so there is nothing to take away.
     */
    private static function lockScroll(string $html): string
    {
        if (preg_match('#<body(\s[^>]*)?>#i', $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        $tag    = $match[0][0];
        $offset = $match[0][1];

        // Already locked (a second pass, or the template set it itself).
        if (preg_match('#\bclass\s*=\s*["\'][^"\']*\bconsent-open\b#i', $tag) === 1) {
            return $html;
        }

        $updated = preg_replace(
            '#(\bclass\s*=\s*)(["\'])(.*?)\2#is',
            '$1$2$3 consent-open$2',
            $tag,
            1,
            $count
        );

        // No class attribute at all — add one.
        if ($count === 0) {
            $updated = preg_replace('#<body#i', '<body class="consent-open"', $tag, 1);
        }

        if (is_string($updated) === false) {
            return $html;
        }

        return substr($html, 0, $offset) . $updated . substr($html, $offset + strlen($tag));
    }

    /**
     * Insert a snippet directly before the final </body>.
     */
    private static function beforeClosingBody(string $html, string $snippet): string
    {
        $at = strripos($html, '</body>');

        if ($at === false) {
            return $html;
        }

        $markup = static::render($snippet);

        if ($markup === '') {
            return $html;
        }

        return substr($html, 0, $at) . $markup . "\n" . substr($html, $at);
    }

    private static function render(string $snippet): string
    {
        return trim((string) snippet($snippet, [], true));
    }
}
