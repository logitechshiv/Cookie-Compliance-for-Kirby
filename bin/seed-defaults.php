<?php

/**
 * Seed the Panel settings with their defaults.
 *
 * Writes every default from the plugin's settings tab into the site's content
 * file, so an editor opens the Datenschutz-Banner tab and sees real, editable
 * texts and colour codes instead of blank inputs.
 *
 * Safe to run more than once: a field that already has a value is never
 * overwritten, and fields the editor deliberately cleared are left alone only
 * until the next run — clear a field and re-run and it will be re-seeded, which
 * is why you normally run this once, right after install.
 *
 * Usage, from the Kirby project root:
 *
 *     php site/plugins/kirby-cookie-compliance/bin/seed-defaults.php
 *
 * Pass --dry-run to preview without writing.
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

// Walk up from bin/ to find the Kirby project root.
$root = dirname(__DIR__, 4);

if (is_file($root . '/kirby/bootstrap.php') === false) {
    exit("Could not locate Kirby at {$root}/kirby. Run this from the project root.\n");
}

require $root . '/kirby/bootstrap.php';

$kirby = new Kirby\Cms\App([
    'roots' => [
        'index'   => $root,
        'base'    => $root,
        'site'    => $root . '/site',
        'content' => $root . '/content',
    ],
]);

$site = $kirby->site();

try {
    $tab = $site->blueprint()->tab('consent');
} catch (Throwable $e) {
    $tab = null;
}

if (empty($tab)) {
    exit(
        "No 'consent' tab found in the site blueprint.\n"
        . "Add this to site/blueprints/site.yml first:\n\n"
        . "  tabs:\n    consent:\n      extends: cookie-compliance/settings\n"
    );
}

// Flatten the tab's fields.
$fields = [];

foreach (($tab['columns'] ?? []) as $column) {
    foreach (($column['sections'] ?? []) as $section) {
        $fields = array_merge($fields, $section['fields'] ?? []);
    }
}

$existing = $site->content()->toArray();
$seed     = [];

foreach ($fields as $name => $field) {
    // Structural fields carry no value; page pickers resolve by slug instead.
    if (in_array($field['type'] ?? '', ['headline', 'info', 'pages', 'line', 'gap'], true)) {
        continue;
    }

    if (($field['default'] ?? null) === null) {
        continue;
    }

    // Never overwrite an editor's own value.
    if (trim((string) ($existing[strtolower($name)] ?? '')) !== '') {
        continue;
    }

    $seed[$name] = $field['default'];
}

if ($seed === []) {
    exit("Nothing to seed — every field already has a value.\n");
}

printf("%s %d field(s):\n", $dryRun ? 'Would seed' : 'Seeding', count($seed));

foreach ($seed as $name => $value) {
    printf("  %-30s %s\n", $name, mb_substr((string) $value, 0, 48));
}

if ($dryRun === true) {
    exit("\nDry run — nothing written.\n");
}

try {
    $site->update($seed);
} catch (Throwable $e) {
    exit("\nFailed to write: " . $e->getMessage() . "\n");
}

echo "\nDone. Open the Panel and adjust the wording and colours as needed.\n";
