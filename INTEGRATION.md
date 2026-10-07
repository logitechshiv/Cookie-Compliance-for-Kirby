# Integration Guide — Cookie Compliance for Kirby

How to install the plugin on a new Kirby project, what you must wire up by hand,
and how to extend it.

Requires **Kirby 5** and **PHP 8.2+**.

---

## 1. Install

### Step 1 — add the plugin

```
composer require kirbycode/kirby-cookie-compliance
```

or copy the folder to `site/plugins/kirby-cookie-compliance/`.

### Step 2 — add the settings tab to `site.yml`

This is the **only** blueprint change the plugin needs. Add a `consent` tab to
the `tabs:` list in `site/blueprints/site.yml`:

```yaml
# site/blueprints/site.yml
title: Site

tabs:
  # … your existing tabs …

  consent:
    extends: cookie-compliance/settings

  seo:
    extends: meta-kit/site
```

The tab can go anywhere in the list — order only affects where it appears in the
Panel. `cookie-compliance/settings` is registered by the plugin, so there is no
file to create: everything it pulls in lives inside the plugin folder.

If your project has **no** `site/blueprints/site.yml` yet, create one:

```yaml
# site/blueprints/site.yml
title: Site

tabs:
  consent:
    extends: cookie-compliance/settings
```

After saving, the Panel shows a **Datenschutz-Banner** tab under *Site*.

### Step 2b — seed the defaults (recommended)

Run once, from the project root:

```
php site/plugins/kirby-cookie-compliance/bin/seed-defaults.php
```

This writes every default — all the German texts **and** the colour codes — into
the site's content file, so an editor opens the tab and sees real, editable
values rather than blank inputs. Add `--dry-run` to preview first.

It is safe to re-run: a field that already has a value is never overwritten.

Skipping this is fine — the plugin falls back to exactly the same values at
runtime, so the banner renders correctly either way. Seeding only changes what
an editor *sees* in the Panel.

> Kirby has no mechanism for a plugin to inject a tab into an existing site
> blueprint, which is why this one line cannot be avoided. It is a blueprint
> reference, not configuration — no PHP and no `config.php` entry.

### Step 3 — set the GTM container

In the Panel, set the **Google Tag Manager Container-ID**. If your project has no
such field, add one to `site.yml` — see §3.

---

That is the whole install. The plugin places its own markup into every rendered
page, so **no template edits and no JavaScript changes are required** to get a
working consent banner, blocked third-party content and Consent Mode v2.

What it injects automatically, via its `page.render:after` hook:

| Markup | Where | Why there |
|---|---|---|
| `cookie-compliance/consent-mode` | right after `<head …>` | must define `gtag()` before anything can push to `dataLayer` |
| `cookie-compliance/gtm-noscript` | right after `<body …>` | Google requires it there; only rendered when marketing is granted |
| `cookie-compliance/banner` | just before `</body>` | needs the DOM to exist |

To place any of these yourself instead, switch the matching option off and call
the snippet by hand:

```php
// site/config/config.php
'kirbycode.cookie-compliance.inject' => [
    'head'   => false,
    'body'   => false,
    'banner' => false,
],
```

```php
<?php snippet('cookie-compliance/consent-mode') ?>   <!-- FIRST thing in <head> -->
<?php snippet('cookie-compliance/gtm-noscript') ?>   <!-- right after <body> -->
<?php snippet('cookie-compliance/banner') ?>         <!-- near </body> -->
```

---

## 2. How blocking works — two layers

Understanding this is the difference between a real consent gate and a
decorative one.

### Layer 1 — server-side scanner (`src/Scanner.php`)

Runs on the rendered HTML. Gates un-consented third-party resources in `iframe`,
`script`, `img`, `link`, `embed`, `object`, **plus inline script bodies**.

Inline scanning matters more than it sounds: the standard GTM snippet has **no
`src` attribute**. It is inline JavaScript that builds
`j.src='https://www.googletagmanager.com/gtm.js?id='+i` at runtime. A scanner
that only reads attributes will never catch it.

When a category *is* granted, matching elements are left completely untouched —
the page is byte-identical to an ungated render.

### Layer 2 — runtime guard (`assets/consent-guard.js`)

Catches resources created by JavaScript after the page loads, which the scanner
cannot see by definition.

**Why it patches `document.createElement` rather than using a MutationObserver
alone.** The browser begins the network request the moment an element carrying a
`src` is inserted into the document. MutationObserver callbacks run as a
microtask *after* that mutation — by then the request is already in flight, and
removing the node does not un-send it. So the guard intercepts earlier: it
redefines the `src`/`href` property on elements created via `createElement`, and
when the URL belongs to an un-consented vendor it is simply never assigned. No
request is ever made. A MutationObserver is kept only as a best-effort backstop
for markup inserted through `innerHTML`.

Consequence worth knowing: **you do not need to modify your site's JavaScript.**
Click-to-play video players, lazy-loaded embeds and third-party widgets are all
gated automatically, whatever code creates them.

### What neither layer catches

- `fetch()` / `XMLHttpRequest` / dynamic `import()` to third parties
- `url()` inside an external stylesheet
- anything served outside `$page->render()` (custom routes, API endpoints)

Handle those deliberately with `consent('…')` checks.

---

## 3. Site-specific wiring (the only manual steps)

### a) Code-injection fields

If the project has editor-facing "paste code here" fields, gate them. A generic
plugin cannot know they exist.

```php
<?php if (consent('marketing')): ?>
<?= site()->head_code()->value() ?>
<?= $page->head_code()->value() ?>
<?php endif ?>
```

The scanner would catch a GTM snippet pasted there anyway, but it would leave a
`<script type="text/plain">` in the markup. The PHP wrapper means it is never
emitted at all — the stronger position for a tracking-sensitive client.

Also add a dedicated GTM field so editors do not paste the container into
`head_code`:

```yaml
gtm_id:
  label: Google Tag Manager Container-ID
  type: text
  placeholder: GTM-XXXXXXX
  help: "Wird erst nach einer Marketing-Einwilligung geladen. NICHT zusätzlich unten als Code einfügen."
```

And reword the `head_code` / `body_code` help texts to say the content only loads
after marketing consent. Default Kirby starters often tell editors to paste GTM
there — that instruction must go, or it silently defeats the ordering guarantee.

### b) A settings link

A floating button is injected automatically. For a link in the footer nav:

```php
<?php snippet('cookie-compliance/settings-link', ['class' => 'your-link-class']) ?>
```

Not optional if your banner copy promises one — the German text does.

### c) Recommended, unrelated to the plugin

If the site builds Vimeo URLs itself, add `dnt=1`. If it builds YouTube URLs, use
`youtube-nocookie.com`. These reduce tracking *after* consent; the plugin governs
whether the request happens at all.

---

## 3b. Panel settings — texts, links and colours

Everything editor-facing lives in the **Datenschutz-Banner** tab, backed by
`blueprints/settings.php` inside the plugin. No `config.php` entries are
needed for any of it.

**Every field ships pre-filled with its real default**, so an editor sees and
edits the actual wording and colours rather than guessing what an empty field
produces. Clearing a field falls back to the plugin's own translation or default
colour, so nothing can end up blank. `src/Settings.php` resolves this at runtime.

The tab is `blueprints/settings.php` — **PHP, not YAML, and deliberately so**.
It is registered as a closure (Kirby accepts a callable returning an array), which
lets every `default` be pulled from `translations/de.php` and `Settings::COLORS` —
the same values the runtime falls back to. A YAML file would mean retyping all of
them, and the Panel pre-fill would silently drift from the actual fallback the
first time either side was edited. If you add a field, take its default from the
same source rather than hardcoding it.

| Group | Fields |
|---|---|
| Texts | heading, intro paragraph |
| Categories | label + description for all four categories |
| Buttons | accept all, necessary only, save, settings-link label |
| Links | privacy and imprint page pickers, plus their labels |
| Colours | overlay, surface, text, accent, accent text, button text, link, FAB background, FAB icon |

Colours drive `--kcc-*` custom properties emitted into the plugin's own inlined
`<style>`, so **no styling ever leaves the plugin** and nothing depends on the
site's Tailwind build or token names.

> An earlier version referenced `var(--color-primary)` / `var(--color-secondary)`.
> Those exist in very few themes, so the banner silently fell back to the colours
> of the project it was forked from. Every colour now resolves to a value the
> plugin controls. Do not reintroduce references to site-level tokens.

**Colour values are validated.** Only hex (3/4/6/8 digit) and `rgb()`/`rgba()`/
`hsl()` functional notation are accepted; anything else falls back to the
default. This is a security boundary, not a convenience: a value like
`#fff}body{display:none}` would otherwise break out of the `:root{}` block and
restyle the whole page. If you add a colour slot, add it to `Settings::COLORS`
and let `Settings::color()` validate it — never interpolate a raw field value
into CSS.

**On letting clients pick colours.** The banner is a compliance component. German
DSK guidance requires the reject option to have equal prominence to accept, so an
illegible "Nur notwendige" button is a compliance defect, not a cosmetic one. The
colour fields ship with preset swatches for this reason, and the Panel tab
carries a contrast warning. If a client is likely to misuse them, set
`mode: options` on the colour fields to remove free picking entirely.

## 4. Extending

### Add a vendor

```php
// site/config/config.php
'kirbycode.cookie-compliance.vendors' => [
    'tiktok' => [
        'name'     => 'TikTok',
        'hosts'    => ['*.tiktok.com', '*.tiktokcdn.com'],
        'category' => 'marketing',
    ],
],
```

One entry serves **both** layers — the PHP scanner reads it directly, and the JS
guard receives a slimmed copy through the config element. Never fork the list
into the JavaScript.

Then **bump `Consent::VERSION`** so everyone is re-prompted. A cookie written
against an older version counts as an incomplete decision, which is the supported
way to re-ask after the vendor list changes.

### Add a category

1. `Consent::CATEGORIES` in `src/Consent.php`
2. `signalMap` in `index.php`, if it maps to a Consent Mode signal
3. `consent.<name>` and `consent.<name>-hint` in `translations/de.php`
4. Bump `Consent::VERSION`

The banner renders categories from `CATEGORIES`, so no snippet edit is needed.

### Host matching

`*.example.com` matches `example.com` and every subdomain. A bare
`fonts.googleapis.com` matches that host only. Unknown hosts fail closed as
`unknown:<host>` under `scan.unknownCategory`; add trusted ones to `allowHosts`.

---

## 5. The caching contract

**Never read `$_COOKIE` directly** in consent-dependent code. Use `consent()`.

`Consent::state()` registers every cookie it inspects on Kirby's `Responder` via
`usesCookie()`. `Responder::isPrivate()` then reports the response as private
*only for visitors who actually have the cookie*, so:

- **no cookie** → cacheable, and the cached HTML is the fully-gated variant,
  served only to other visitors without the cookie;
- **cookie present** → never cached, `Cache-Control: no-store, private`.

Server-side gating is therefore safe with Kirby's page cache enabled. Reading
`$_COOKIE` directly skips that registration, and a consented visitor's HTML could
be cached and served to someone who consented to nothing.

Note this is also why `Kirby\Http\Cookie::get()` is *not* used: it only returns
values carrying Kirby's HMAC signature (`<hash>+<value>`), and this cookie is
written by JavaScript, which has no access to the server-side salt.

A CDN set to "Cache Everything" that ignores origin cache headers would still
defeat this. Only well-behaved caches honour `no-store, private`.

---

## 6. Porting to a non-Kirby site

The parts that are framework-agnostic:

- `assets/consent-core.js`, `consent-guard.js`, `consent-ui.js`, `consent.css`
- the cookie schema and `Consent::VERSION` re-prompt mechanism
- the vendor registry shape

The parts you must re-implement: the `page.render:after` hook (any output-buffer
filter works), `src/Consent.php` (any cookie read, as long as it fails closed),
and whatever your framework's equivalent of `usesCookie()` is for caching.

The guard needs only the JSON config element — give it `vendors`, `allowHosts`,
`gateUnknown`, `unknownCategory`, `categories`, `strings`, and a `window.KirbyConsent`
exposing `has()`, `config` and `onChange()`.

---

## 7. Verifying an install

1. Fresh incognito: banner appears, and the Network tab shows **zero** requests to
   `googletagmanager.com`, `youtube.com` or `vimeo.com`.
2. Click a video with no consent: a placeholder appears and still **zero**
   requests. This is the test that proves the guard works — an observer-only
   implementation fails it.
3. Accept marketing: `dataLayer` shows `consent default` → `consent update` →
   container load, in that order, with no reload.
4. Re-open via the settings link, choose "necessary only", reload: GTM absent.
5. Every banner label renders real text, not blanks (see the translations note in
   `README.md` — on a single-language site the strings must be registered under
   the `en` key).
6. With a consent cookie set, confirm the response carries
   `Cache-Control: no-store, private`.

---

## 8. Not included

There is **no server-side consent log**. Art. 7(1) GDPR requires the controller to
*demonstrate* consent; the cookie carries a timestamp and a version, which is a
foundation but not an audit record. Build one before a client needs proof of
consent, or use a commercial CMP for audit-grade records.

This is not legal advice. Have the client's DPO or counsel sign off on the
category model and the banner copy.
