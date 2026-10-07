# Cookie Compliance for Kirby

Category-based GDPR/DSGVO consent manager for Kirby 5, with **server-side
gating**, Google Consent Mode v2, automatic third-party scanning and a runtime
guard for JavaScript-created embeds.

By [kirbycode](https://www.kirbycode.com/) · MIT licensed. See `LICENSE` for
copyright and attribution.

## Install

```
composer require kirbycode/kirby-cookie-compliance
```

or copy this folder to `site/plugins/kirby-cookie-compliance/`. Then add one
line to your site blueprint — see [INTEGRATION.md](INTEGRATION.md) §1.

**Requires** Kirby 5 and PHP 8.2+.

> **Installing this on a new project? Read [INTEGRATION.md](INTEGRATION.md).**
> It covers install, the two blocking layers, the manual wiring, extending the
> vendor list and the caching contract.

## What makes this different

Consent is stored in a **cookie**, not localStorage, so PHP can read it while the
page is still being rendered. That gives two things a client-side blocker cannot:

1. **Tracking code is never emitted.** GTM and gated code-injection fields are not
   printed into the HTML at all before consent — there is no `type="text/plain"`
   tag to revive and nothing in the markup to find.
2. **Consented pages are byte-identical to an ungated render.** When a category is
   granted the scanner leaves those elements completely untouched: no placeholder
   wrapper, no base64 round-trip, no flash of a gate.

A second layer, `assets/consent-guard.js`, covers embeds built by JavaScript at
runtime — which the server-side scanner cannot see by definition. It prevents the
URL ever being assigned, so no request is made. See INTEGRATION.md §2 for why a
MutationObserver alone is not sufficient.

**Drop-in:** the plugin injects its own markup, so a clean install needs no
template edits and no changes to your site's JavaScript.

## Panel settings

All texts, legal links and colours are edited in the **Datenschutz-Banner** tab —
no `config.php` entries needed. Clearing a field falls back to the plugin's own
translation or default colour, so nothing can end up blank.

Run once after install to fill the tab with editable values instead of blank
inputs:

```
php site/plugins/kirby-cookie-compliance/bin/seed-defaults.php
```

Colours drive `--kcc-*` custom properties inside the plugin's inlined `<style>`,
so nothing depends on the site's CSS build or token names. Values are validated
as hex or `rgb()`/`hsl()` only; anything else falls back to the default, which
stops a field value breaking out of the `:root{}` block.

Requires one line in the site blueprint — see [INTEGRATION.md](INTEGRATION.md) §1.

## Categories

| Category | Always on | Typical use |
|---|---|---|
| `necessary` | yes | session, the consent cookie itself |
| `functional` | no | Google Fonts, Maps, reCAPTCHA, booking widgets |
| `statistics` | no | Analytics, Hotjar |
| `marketing` | no | GTM, Ads, YouTube, Vimeo, Instagram, Meta |

## API

```php
consent()              // full state array
consent('marketing')   // bool
$site->consent('statistics')
```

```js
KirbyConsent.has('marketing')   // bool
KirbyConsent.state()            // { complete, version, ts, c: {…} }
KirbyConsent.set({ marketing: true })
KirbyConsent.acceptAll() / KirbyConsent.rejectAll()
KirbyConsent.open()             // open the dialog
KirbyConsent.onChange(fn)       // also fires once immediately
```

Any element with `data-consent-open` opens the dialog — handled by a delegated
listener, so it works for markup added at any time.

## Cookie

`ah_consent`, 180 days, `SameSite=Lax`, `Secure` on https, not `HttpOnly`
(JavaScript must read it).

```json
{ "v": 2, "ts": 1759708800,
  "c": { "necessary": true, "functional": false, "statistics": false, "marketing": false } }
```

`v` is the schema **and policy** version. Bump `Consent::VERSION` whenever the
category model or vendor list changes — every visitor is then re-prompted.

## Options

Override in `site/config/config.php` under `kirbycode.cookie-compliance`:

| Option | Default |
|---|---|
| `cookie` | `ah_consent` |
| `legacyCookies` | `['zk_consent']` |
| `maxAgeDays` | `180` |
| `videoCategory` | `marketing` |
| `allowHosts` | `[]` |
| `inject.head` / `inject.body` / `inject.banner` | `true` |
| `scan.enabled` | `true` |
| `scan.elements` | `iframe, script, img, link, embed, object` |
| `scan.inlineScripts` | `true` |
| `scan.gateUnknown` | `true` |
| `scan.unknownCategory` | `marketing` |
| `signalMap` | category → Consent Mode v2 signals |
| `vendors` | host → vendor/category registry |

## Translations

On a **single-language** Kirby site, `I18n::$locale` is forced to `'en'` on every
request and `'en'` is skipped in the fallback chain — so German strings must be
registered under the **`en`** key, not just `de`. This plugin registers them under
both. Registering under `de` alone renders empty labels.

## Files

| File | Role |
|---|---|
| `src/Consent.php` | cookie read/validate, fails closed, cache-safe |
| `src/Scanner.php` | server-side HTML scanning and gating |
| `src/Injector.php` | auto-placement of the plugin's markup |
| `assets/consent-core.js` | `window.KirbyConsent`, cookie, Consent Mode, GTM loader |
| `assets/consent-guard.js` | runtime interception of JS-created resources |
| `assets/consent-ui.js` | banner bindings |

## Not included

No server-side consent log — see INTEGRATION.md §8.
