<?php

/**
 * German UI strings.
 *
 * These are registered under BOTH the 'en' and 'de' locale keys in index.php.
 * Registering them under 'de' alone would render nothing: this site is
 * single-language, so Kirby forces I18n::$locale to 'en' on every request and
 * the only fallback locale is 'en' itself, which the fallback loop skips.
 */

return [
    'consent.title' => 'Datenschutzeinstellungen',

    'consent.text' => 'Wir verwenden Cookies und ähnliche Technologien. Einige sind für den Betrieb der Website notwendig, andere helfen uns, unser Angebot zu verbessern oder Inhalte von Drittanbietern anzuzeigen. Sie entscheiden selbst, welche Kategorien Sie zulassen. Ihre Auswahl können Sie jederzeit über den Link „Cookie-Einstellungen" in der Fußzeile ändern.',

    'consent.necessary'       => 'Notwendig',
    'consent.necessary-hint'  => 'Erforderlich für den Betrieb der Website. Diese Cookies können nicht deaktiviert werden.',

    'consent.functional'      => 'Funktional',
    'consent.functional-hint' => 'Ermöglichen zusätzliche Funktionen wie eingebettete Schriften oder Kartendienste.',

    'consent.statistics'      => 'Statistik',
    'consent.statistics-hint' => 'Helfen uns zu verstehen, wie die Website genutzt wird. Die Auswertung erfolgt pseudonymisiert.',

    'consent.marketing'       => 'Marketing',
    'consent.marketing-hint'  => 'Erlauben das Laden externer Inhalte wie Videos sowie Reichweiten- und Werbemessung. Dabei können Daten an Drittanbieter übertragen werden.',

    'consent.accept-all'    => 'Alle akzeptieren',
    'consent.necessary-only' => 'Nur notwendige',
    'consent.save'          => 'Auswahl speichern',
    'consent.settings'      => 'Cookie-Einstellungen',

    'consent.privacy-link' => 'Datenschutzerklärung',
    'consent.imprint-link' => 'Impressum',

    'consent.given-on' => 'Ihre Einwilligung vom {date}',

    // Placeholder shown where a third-party embed was blocked.
    'consent.gate.title'    => 'Externer Inhalt blockiert',
    'consent.gate.text'     => 'Dieser Inhalt wird von {vendor} bereitgestellt. Beim Laden können personenbezogene Daten an den Anbieter übertragen werden. Bitte stimmen Sie der Kategorie „{category}" zu, um ihn anzuzeigen.',
    'consent.gate.settings' => 'Cookie-Einstellungen öffnen',

    // Placeholder shown in place of a YouTube or Vimeo player.
    'consent.video.title'     => 'Video blockiert',
    'consent.video.text'      => 'Dieses Video wird von einem externen Anbieter geladen. Dabei können personenbezogene Daten übertragen werden.',
    'consent.video.load-once' => 'Video einmalig laden',
    'consent.video.settings'  => 'Cookie-Einstellungen',
];
