<?php

/**
 * The GTM <noscript> fallback, emitted right after <body>.
 *
 * Only rendered when marketing consent exists, so it is never present for a
 * visitor who has not consented. Because the category is granted in that case,
 * the scanner leaves the iframe untouched.
 */

$gtmId = trim((string) site()->gtm_id()->value());

if ($gtmId === '' || consent('marketing') === false) {
    return;
}
?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= urlencode($gtmId) ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
