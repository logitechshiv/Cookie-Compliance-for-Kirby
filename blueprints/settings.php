<?php

use Kirbycode\CookieCompliance\Consent;
use Kirbycode\CookieCompliance\Settings;

/**
 * The Datenschutz-Banner tab.
 *
 * Built in PHP rather than YAML so every `default` can be pulled from the same
 * place the runtime fallback uses: the texts from translations/de.php and the
 * colours from Settings::COLORS. A YAML file would mean typing those strings a
 * second time, and they would silently drift apart the first time one side was
 * edited.
 *
 * The practical effect in the Panel: every field is pre-filled with the real
 * value instead of a grey placeholder, so an editor can see and adjust the
 * wording rather than guess what an empty field will produce.
 */

$t = require dirname(__DIR__) . '/translations/de.php';

/** Category label/description pairs, generated from the category list. */
$categoryFields = [];

foreach (array_keys(Consent::CATEGORIES) as $category) {
    // Use the German category name in the field label too, so the Panel does
    // not read "Necessary – Bezeichnung".
    $name = $t['consent.' . $category] ?? ucfirst($category);

    $categoryFields['consent_' . $category . '_label'] = [
        'label'   => $name . ' – Bezeichnung',
        'type'    => 'text',
        'width'   => '1/2',
        'default' => $name,
    ];

    $categoryFields['consent_' . $category . '_hint'] = [
        'label'   => $name . ' – Beschreibung',
        'type'    => 'textarea',
        'buttons' => false,
        'size'    => 'small',
        'width'   => '1/2',
        'default' => $t['consent.' . $category . '-hint'] ?? '',
    ];
}

/** Colour fields, generated from the same map Settings::color() validates. */
$colorLabels = [
    'overlay'     => ['Overlay (Hintergrund)', 'Abdunklung hinter dem Dialog. Transparenz empfohlen.'],
    'surface'     => ['Dialogfläche', null],
    'text_color'  => ['Textfarbe', null],
    'accent'      => ['Primärer Button – Fläche', null],
    'accent_text' => ['Primärer Button – Schrift', null],
    'button_text' => ['Sekundäre Buttons – Schrift und Rahmen', null],
    'link'        => ['Linkfarbe', null],
    'fab_bg'      => ['Runder Button – Fläche', null],
    'fab_icon'    => ['Runder Button – Symbol', null],
];

$swatches = ['#400530', '#ff4c5e', '#171716', '#f7f7f7', '#ffffff'];

$colorFields = [];

foreach (Settings::COLORS as $slot => [, $default]) {
    [$label, $help] = $colorLabels[$slot] ?? [$slot, null];

    $field = [
        'label'   => $label,
        'type'    => 'color',
        'width'   => '1/3',
        'default' => $default,
        'options' => array_values(array_unique([$default, ...$swatches])),
    ];

    if ($slot === 'overlay') {
        $field['alpha'] = true;
    }

    if ($help !== null) {
        $field['help'] = $help;
    }

    $colorFields['consent_' . $slot] = $field;
}

return [
    'label' => 'Datenschutz-Banner',
    'icon'  => 'shield',

    'sections' => [
        'cookie_compliance_fields' => [
            'type'   => 'fields',
            'fields' => [
                'consent_info' => [
                    'type'  => 'info',
                    'label' => 'Hinweis',
                    'text'  => 'Hier werden Texte, Links und Farben des Cookie-Banners gepflegt. '
                        . 'Die Felder sind mit den Standardwerten vorbelegt – Sie können sie direkt anpassen. '
                        . 'Wird ein Feld geleert, greift automatisch wieder der Standardwert des Plugins.',
                ],

                'consent_headline_texts' => [
                    'type'  => 'headline',
                    'label' => 'Texte',
                ],

                'consent_title' => [
                    'label'   => 'Überschrift',
                    'type'    => 'text',
                    'default' => $t['consent.title'] ?? '',
                ],

                'consent_text' => [
                    'label'   => 'Einleitungstext',
                    'type'    => 'textarea',
                    'buttons' => false,
                    'default' => $t['consent.text'] ?? '',
                    'help'    => 'Erscheint unter der Überschrift. Reiner Text, keine Formatierung.',
                ],

                'consent_headline_categories' => [
                    'type'  => 'headline',
                    'label' => 'Kategorien',
                ],

                ...$categoryFields,

                'consent_headline_buttons' => [
                    'type'  => 'headline',
                    'label' => 'Schaltflächen',
                ],

                'consent_accept_all' => [
                    'label'   => 'Alle akzeptieren',
                    'type'    => 'text',
                    'width'   => '1/3',
                    'default' => $t['consent.accept-all'] ?? '',
                ],
                'consent_necessary_only' => [
                    'label'   => 'Nur notwendige',
                    'type'    => 'text',
                    'width'   => '1/3',
                    'default' => $t['consent.necessary-only'] ?? '',
                ],
                'consent_save' => [
                    'label'   => 'Auswahl speichern',
                    'type'    => 'text',
                    'width'   => '1/3',
                    'default' => $t['consent.save'] ?? '',
                ],
                'consent_settings' => [
                    'label'   => 'Einstellungen öffnen',
                    'type'    => 'text',
                    'width'   => '1/2',
                    'default' => $t['consent.settings'] ?? '',
                    'help'    => 'Beschriftung des Links in der Fußzeile und des runden Buttons.',
                ],

                'consent_headline_links' => [
                    'type'  => 'headline',
                    'label' => 'Rechtliche Links',
                ],

                'consent_privacy_page' => [
                    'label' => 'Datenschutzseite',
                    'type'  => 'pages',
                    'width' => '1/2',
                    'max'   => 1,
                    'help'  => 'Leer lassen, um automatisch die Seite „datenschutz" zu verwenden.',
                ],
                'consent_privacy_label' => [
                    'label'   => 'Beschriftung',
                    'type'    => 'text',
                    'width'   => '1/2',
                    'default' => $t['consent.privacy-link'] ?? '',
                ],
                'consent_imprint_page' => [
                    'label' => 'Impressumsseite',
                    'type'  => 'pages',
                    'width' => '1/2',
                    'max'   => 1,
                    'help'  => 'Leer lassen, um automatisch die Seite „impressum" zu verwenden.',
                ],
                'consent_imprint_label' => [
                    'label'   => 'Beschriftung',
                    'type'    => 'text',
                    'width'   => '1/2',
                    'default' => $t['consent.imprint-link'] ?? '',
                ],

                'consent_headline_colors' => [
                    'type'  => 'headline',
                    'label' => 'Farben',
                ],

                'consent_color_info' => [
                    'type'  => 'info',
                    'theme' => 'notice',
                    'label' => 'Lesbarkeit',
                    'text'  => 'Bitte auf ausreichenden Kontrast achten. Die Schaltflächen '
                        . '„Alle akzeptieren" und „Nur notwendige" müssen gleich gut erkennbar sein – '
                        . 'das ist eine rechtliche Anforderung, keine reine Gestaltungsfrage.',
                ],

                ...$colorFields,
            ],
        ],
    ],
];
