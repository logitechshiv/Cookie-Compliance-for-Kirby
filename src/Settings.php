<?php

namespace Kirbycode\CookieCompliance;

use Kirby\Cms\Page;

/**
 * Panel-editable texts, links and colours.
 *
 * Every value is optional. An empty field falls back to the plugin's own
 * translation string or default colour, so a fresh install works with nothing
 * filled in and an editor only has to touch what they want to change.
 */
final class Settings
{
    /**
     * Colour slot => CSS custom property and default value.
     *
     * Defaults are the addhucate palette. Note these are real values, not
     * var(--color-…) references: the banner must render correctly even where the
     * site's own stylesheet has not loaded or uses different token names.
     */
    public const COLORS = [
        'overlay'     => ['--kcc-overlay',      '#400530b3'],
        'surface'     => ['--kcc-surface',      '#ffffff'],
        'text_color'  => ['--kcc-text',         '#400530'],
        'accent'      => ['--kcc-accent',       '#ff4c5e'],
        'accent_text' => ['--kcc-accent-text',  '#ffffff'],
        'button_text' => ['--kcc-button-text',  '#400530'],
        'link'        => ['--kcc-link',         '#400530'],
        'fab_bg'      => ['--kcc-fab-bg',       '#400530'],
        'fab_icon'    => ['--kcc-fab-icon',     '#ff4c5e'],
    ];

    /**
     * Read a Panel text field, falling back to a translation key.
     */
    public static function text(string $field, string $translationKey): string
    {
        $value = trim((string) site()->content()->get('consent_' . $field)->value());

        return $value !== '' ? $value : (string) t($translationKey);
    }

    /**
     * Resolve a linked page, falling back to a slug.
     */
    public static function page(string $field, string $fallbackSlug): Page|null
    {
        $linked = site()->content()->get('consent_' . $field)->toPage();

        return $linked ?? page($fallbackSlug);
    }

    /**
     * One colour, validated. Anything that is not a plain CSS colour literal is
     * rejected in favour of the default, so a bad value cannot inject CSS.
     */
    public static function color(string $slot): string
    {
        [, $default] = static::COLORS[$slot] ?? [null, 'inherit'];

        $value = trim((string) site()->content()->get('consent_' . $slot)->value());

        if ($value === '') {
            return $default;
        }

        $isHex = preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1;
        $isFn  = preg_match('/^(?:rgb|hsl)a?\([0-9a-z%.,\/\s+-]*\)$/i', $value) === 1;

        return ($isHex || $isFn) ? $value : $default;
    }

    /**
     * The :root block that themes the banner. Emitted inside the plugin's own
     * inlined <style>, so no styling ever leaves the plugin.
     */
    public static function cssVariables(): string
    {
        $lines = [];

        foreach (static::COLORS as $slot => [$property]) {
            $lines[] = $property . ':' . static::color($slot) . ';';
        }

        return ':root{' . implode('', $lines) . '}';
    }

    /**
     * Every label the banner and the gates need, resolved once.
     *
     * @return array<string,string>
     */
    public static function labels(): array
    {
        $labels = [
            'title'         => static::text('title', 'consent.title'),
            'text'          => static::text('text', 'consent.text'),
            'acceptAll'     => static::text('accept_all', 'consent.accept-all'),
            'necessaryOnly' => static::text('necessary_only', 'consent.necessary-only'),
            'save'          => static::text('save', 'consent.save'),
            'settings'      => static::text('settings', 'consent.settings'),
            'privacyLabel'  => static::text('privacy_label', 'consent.privacy-link'),
            'imprintLabel'  => static::text('imprint_label', 'consent.imprint-link'),
        ];

        foreach (array_keys(Consent::CATEGORIES) as $category) {
            $labels[$category]           = static::text($category . '_label', 'consent.' . $category);
            $labels[$category . 'Hint']  = static::text($category . '_hint', 'consent.' . $category . '-hint');
        }

        return $labels;
    }
}
