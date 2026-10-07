/**
 * Consent runtime. Inlined in <head> by snippets/consent-mode.php, so
 * window.KirbyConsent exists before any other script on the page runs.
 *
 * Owns: the cookie, the Google Consent Mode v2 "update" signal, and loading the
 * GTM container. The banner UI lives in consent-ui.js and only calls into this.
 */
(function () {
  "use strict";

  var configEl = document.getElementById("ah-consent-config");
  if (!configEl) return;

  var CFG;
  try {
    CFG = JSON.parse(configEl.textContent);
  } catch (e) {
    return;
  }

  var listeners = [];
  var gtmLoaded = false;

  function defaults() {
    var c = {};
    for (var i = 0; i < CFG.categories.length; i++) c[CFG.categories[i]] = false;
    c.necessary = true;
    return c;
  }

  function readCookie() {
    var name = CFG.cookie.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    var match = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
    if (!match) return { complete: false, version: 0, ts: 0, c: defaults() };

    var data;
    try {
      data = JSON.parse(decodeURIComponent(match[1]));
    } catch (e) {
      return { complete: false, version: 0, ts: 0, c: defaults() };
    }

    if (!data || typeof data !== "object") {
      return { complete: false, version: 0, ts: 0, c: defaults() };
    }

    var version = parseInt(data.v, 10) || 1;

    // Older or newer schema: treat as undecided so the banner re-prompts.
    // Mirrors Consent::parse() on the PHP side.
    if (version !== CFG.version) {
      var legacy = defaults();
      if (version < CFG.version && data.ot === true) legacy.functional = true;
      return { complete: false, version: version, ts: 0, c: legacy };
    }

    var given = data.c;
    if (!given || typeof given !== "object") {
      return { complete: false, version: 0, ts: 0, c: defaults() };
    }

    var c = defaults();
    for (var i = 0; i < CFG.categories.length; i++) {
      var key = CFG.categories[i];
      c[key] = given[key] === true;
    }
    c.necessary = true;

    return {
      complete: true,
      version: version,
      ts: parseInt(data.ts, 10) || 0,
      c: c
    };
  }

  function writeCookie(c) {
    var payload = { v: CFG.version, ts: Math.floor(Date.now() / 1000), c: c };
    var value = encodeURIComponent(JSON.stringify(payload));
    var secure = window.location.protocol === "https:" ? "; Secure" : "";
    document.cookie =
      CFG.cookie + "=" + value + "; Path=/; Max-Age=" + CFG.maxAge + "; SameSite=Lax" + secure;
  }

  var state = readCookie();

  /** Translate our categories into Google Consent Mode v2 signals. */
  function signals(c) {
    var out = {};
    for (var category in CFG.signalMap) {
      if (!Object.prototype.hasOwnProperty.call(CFG.signalMap, category)) continue;
      var list = CFG.signalMap[category];
      var value = c[category] === true ? "granted" : "denied";
      for (var i = 0; i < list.length; i++) out[list[i]] = value;
    }
    return out;
  }

  function pushUpdate(c) {
    if (typeof window.gtag !== "function") return;
    window.gtag("consent", "update", signals(c));
  }

  function loadGtm() {
    if (gtmLoaded || !CFG.gtmId) return;
    gtmLoaded = true;

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });

    var script = document.createElement("script");
    script.async = true;
    script.src = "https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(CFG.gtmId);
    document.head.appendChild(script);
  }

  /**
   * Elements the server gated are static placeholders — the real markup only
   * comes back from a fresh render, so granting consent reloads the page.
   *
   * [data-consent-blocked] covers neutralised inline scripts. Those cannot
   * simply be re-run in place: a loader and its initialiser have to execute in
   * order, and the loader is asynchronous. A reload is the only way to restore
   * that ordering correctly.
   */
  function hasServerGates() {
    return !!document.querySelector("[data-consent-gate], [data-consent-blocked]");
  }

  function notify() {
    for (var i = 0; i < listeners.length; i++) {
      try {
        listeners[i](KirbyConsent.state());
      } catch (e) {
        /* a broken listener must not break consent */
      }
    }
    try {
      document.dispatchEvent(new CustomEvent("ah:consent", { detail: KirbyConsent.state() }));
    } catch (e) {
      /* older browsers */
    }
  }

  var KirbyConsent = {
    config: CFG,

    has: function (category) {
      if (category === "necessary") return true;
      return state.complete === true && state.c[category] === true;
    },

    isDecided: function () {
      return state.complete === true;
    },

    state: function () {
      return {
        complete: state.complete,
        version: state.version,
        ts: state.ts,
        c: JSON.parse(JSON.stringify(state.c))
      };
    },

    /**
     * Persist a decision. Accepts a partial map; unlisted categories are denied.
     * Always signals Consent Mode BEFORE the container loads.
     */
    set: function (choices) {
      var c = defaults();
      for (var i = 0; i < CFG.categories.length; i++) {
        var key = CFG.categories[i];
        if (choices && choices[key] === true) c[key] = true;
      }
      c.necessary = true;

      var hadGates = hasServerGates();
      var gained = false;
      for (var j = 0; j < CFG.categories.length; j++) {
        var k = CFG.categories[j];
        if (c[k] === true && state.c[k] !== true) gained = true;
      }

      writeCookie(c);
      state = { complete: true, version: CFG.version, ts: Math.floor(Date.now() / 1000), c: c };

      pushUpdate(c);
      if (c.marketing === true) loadGtm();

      notify();

      // Server-rendered gates can only be replaced by a new render.
      if (hadGates && gained) window.location.reload();
    },

    acceptAll: function () {
      var c = {};
      for (var i = 0; i < CFG.categories.length; i++) c[CFG.categories[i]] = true;
      KirbyConsent.set(c);
    },

    rejectAll: function () {
      KirbyConsent.set({});
    },

    /** Replaced by consent-ui.js once the banner exists. */
    open: function () {},

    onChange: function (fn) {
      if (typeof fn !== "function") return;
      listeners.push(fn);
      try {
        fn(KirbyConsent.state());
      } catch (e) {
        /* ignore */
      }
    }
  };

  window.KirbyConsent = KirbyConsent;

  // Replay an existing decision on every page load.
  if (state.complete === true) {
    pushUpdate(state.c);
    if (state.c.marketing === true) loadGtm();
  }
})();
