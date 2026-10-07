/**
 * Runtime guard for third-party resources created by JavaScript.
 *
 * The server-side scanner only sees markup that was rendered as HTML. Anything
 * built at runtime — a YouTube iframe created on click, a tracker injected by
 * another script — is invisible to it. This closes that gap, generically: it
 * knows nothing about any particular site's code.
 *
 * Why a MutationObserver is not enough on its own
 * ------------------------------------------------
 * The browser starts the network request the moment an element carrying a src is
 * inserted into the document. MutationObserver callbacks run as a microtask
 * *after* that mutation, so by the time one fires the request to the third party
 * is already in flight. Removing the node afterwards does not un-send it.
 *
 * So the primary mechanism patches document.createElement and guards the
 * src/href property: when the URL belongs to a vendor the visitor has not
 * consented to, it is simply never assigned, and no request is ever made. The
 * observer is kept only as a backstop for markup inserted via innerHTML, where
 * createElement is bypassed.
 *
 * Inlined in <head> directly after consent-core.js, so it is installed before
 * any other script on the page runs.
 */
(function () {
  "use strict";

  if (!window.KirbyConsent) return;

  var api = window.KirbyConsent;
  var CFG = api.config || {};
  var VENDORS = CFG.vendors || {};
  var ALLOW = CFG.allowHosts || [];
  var GATE_UNKNOWN = CFG.gateUnknown !== false;
  var UNKNOWN_CATEGORY = CFG.unknownCategory || "marketing";
  var STRINGS = CFG.strings || {};

  // tag -> [prototype carrying the native accessor, attribute name]
  var GUARDED = {
    iframe: [window.HTMLIFrameElement, "src"],
    script: [window.HTMLScriptElement, "src"],
    img: [window.HTMLImageElement, "src"],
    embed: [window.HTMLEmbedElement, "src"],
    link: [window.HTMLLinkElement, "href"]
  };

  // Elements whose URL we withheld: element -> { attr, url, vendor, category }
  var blocked = new WeakMap();
  var blockedList = [];

  function hostMatches(host, pattern) {
    pattern = String(pattern || "").toLowerCase().trim();
    if (!pattern) return false;
    if (pattern.indexOf("*.") === 0) {
      var base = pattern.slice(2);
      return host === base || host.slice(-(base.length + 1)) === "." + base;
    }
    return host === pattern;
  }

  /**
   * The base every relative URL on this page resolves against.
   *
   * Not always location.href. A document served from an opaque-path URL — a
   * `blob:` document, which is how Panel preview plugins mount a rendered page
   * into an iframe — has an empty location.hostname, and resolving a relative
   * URL against it throws. document.baseURI follows any <base> such a page
   * carries, and is the base the browser itself uses, so it is the correct
   * reference in both cases. Read per call: <base> may be parsed after this
   * script has run.
   */
  function baseHref() {
    var base = document.baseURI || window.location.href || "";
    // An opaque-path base is unusable for resolution; fall back to the real
    // origin, which a blob: URL still carries.
    if (base.indexOf("blob:") === 0 || base.indexOf("data:") === 0) {
      return window.location.origin || "";
    }
    return base;
  }

  /** The host this document counts as its own, or "" when undeterminable. */
  function ownHost() {
    var candidates = [window.location.hostname, baseHref(), window.location.origin];
    for (var i = 0; i < candidates.length; i++) {
      var value = String(candidates[i] || "");
      if (!value) continue;
      if (value.indexOf("/") === -1 && value.indexOf(":") === -1) {
        return value.toLowerCase();
      }
      try {
        var host = new URL(value).hostname;
        if (host) return host.toLowerCase();
      } catch (e) {
        /* try the next candidate */
      }
    }
    return "";
  }

  /**
   * @return {null|{vendor:string,category:string}} null = allow as-is
   */
  function decide(url) {
    if (url === null || url === undefined) return null;
    url = String(url).trim();
    if (!url) return null;
    if (url.indexOf("data:") === 0 || url.indexOf("blob:") === 0) return null;
    if (url.indexOf("about:") === 0 || url.charAt(0) === "#") return null;

    var host;
    try {
      host = new URL(url, baseHref()).hostname.toLowerCase();
    } catch (e) {
      return null;
    }
    if (!host) return null;

    // Same origin and its subdomains are never gated.
    var own = ownHost();
    if (own && (host === own || host.slice(-(own.length + 1)) === "." + own)) return null;

    for (var a = 0; a < ALLOW.length; a++) {
      if (hostMatches(host, ALLOW[a])) return null;
    }

    var vendor = null;
    var category = null;

    for (var id in VENDORS) {
      if (!Object.prototype.hasOwnProperty.call(VENDORS, id)) continue;
      var hosts = VENDORS[id].hosts || [];
      for (var h = 0; h < hosts.length; h++) {
        if (hostMatches(host, hosts[h])) {
          vendor = id;
          category = VENDORS[id].category || "marketing";
          break;
        }
      }
      if (vendor) break;
    }

    if (!vendor) {
      // Fail closed on an unrecognised host — but only once we actually know
      // which host is ours. Without that every same-origin asset looks third
      // party and the document would block itself. Known vendors are still
      // gated in that case, so the privacy guarantee is kept either way.
      if (!GATE_UNKNOWN || !own) return null;
      vendor = "unknown:" + host;
      category = UNKNOWN_CATEGORY;
    }

    if (api.has(category) === true) return null;

    return { vendor: vendor, category: category };
  }

  /** Native property descriptor for a tag's URL attribute. */
  function nativeDescriptor(tag) {
    var entry = GUARDED[tag];
    if (!entry || !entry[0]) return null;
    var desc = Object.getOwnPropertyDescriptor(entry[0].prototype, entry[1]);
    return desc && desc.set && desc.get ? desc : null;
  }

  function assignNative(el, attr, url) {
    var tag = el.tagName ? el.tagName.toLowerCase() : "";
    var desc = nativeDescriptor(tag);
    if (desc) {
      desc.set.call(el, url);
    } else {
      el.setAttribute(attr, url);
    }
  }

  function markBlocked(el, attr, url, decision) {
    blocked.set(el, {
      attr: attr,
      url: url,
      vendor: decision.vendor,
      category: decision.category
    });
    if (blockedList.indexOf(el) === -1) blockedList.push(el);

    try {
      el.setAttribute("data-consent-src", url);
      el.setAttribute("data-consent-vendor", decision.vendor);
      el.setAttribute("data-consent-category", decision.category);
    } catch (e) {
      /* some elements reject attributes; the WeakMap entry is enough */
    }
  }

  /**
   * Replace the native src/href accessor on one element with a guarded one.
   * Everything that is allowed passes straight through to the native setter, so
   * behaviour is unchanged for same-origin and already-consented resources.
   */
  function guard(el, tag) {
    var entry = GUARDED[tag];
    if (!entry) return;

    var attr = entry[1];
    var desc = nativeDescriptor(tag);
    if (!desc) return;

    try {
      Object.defineProperty(el, attr, {
        configurable: true,
        enumerable: true,
        get: function () {
          var held = blocked.get(el);
          return held ? held.url : desc.get.call(el);
        },
        set: function (value) {
          var decision = null;
          try {
            decision = decide(value);
          } catch (e) {
            decision = null; // never break page JS over a consent check
          }

          if (!decision) {
            desc.set.call(el, value);
            return;
          }

          markBlocked(el, attr, value, decision);
        }
      });
    } catch (e) {
      /* non-configurable in some engine; fall back to the observer */
    }
  }

  // --- primary mechanism -----------------------------------------------------

  var createElement = document.createElement;

  document.createElement = function (tagName, options) {
    var el = createElement.call(document, tagName, options);
    try {
      var tag = String(tagName).toLowerCase();
      if (GUARDED[tag]) guard(el, tag);
    } catch (e) {
      /* ignore */
    }
    return el;
  };

  // --- placeholder -----------------------------------------------------------

  function buildGate(record, el) {
    var gate = document.createElement("div");
    gate.className = "consent-gate consent-gate--video";
    gate.setAttribute("data-consent-gate", record.vendor);
    gate.setAttribute("data-consent-category", record.category);

    var title = document.createElement("p");
    title.className = "consent-gate__title";
    title.textContent = STRINGS.videoTitle || "Externer Inhalt blockiert";

    var text = document.createElement("p");
    text.className = "consent-gate__text";
    text.textContent =
      STRINGS.videoText ||
      "Dieser Inhalt wird von einem externen Anbieter geladen. Dabei können personenbezogene Daten übertragen werden.";

    var actions = document.createElement("div");
    actions.className = "consent-gate__actions";

    var once = document.createElement("button");
    once.type = "button";
    once.className = "consent-banner__btn consent-banner__btn--primary";
    once.textContent = STRINGS.videoLoadOnce || "Einmalig laden";
    once.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      release(el);
      if (gate.parentNode) gate.parentNode.removeChild(gate);
    });

    var settings = document.createElement("button");
    settings.type = "button";
    settings.className = "consent-banner__btn";
    settings.setAttribute("data-consent-open", "");
    settings.textContent = STRINGS.videoSettings || "Cookie-Einstellungen";

    actions.appendChild(once);
    actions.appendChild(settings);
    gate.appendChild(title);
    gate.appendChild(text);
    gate.appendChild(actions);

    return gate;
  }

  /** Show a placeholder beside a blocked element once it lands in the DOM. */
  function showGate(el) {
    var record = blocked.get(el);
    if (!record) return;

    var parent = el.parentNode;
    if (!parent || parent.nodeType !== 1) return;

    // Scripts, stylesheets and pixels have no meaningful visual placeholder.
    var tag = el.tagName ? el.tagName.toLowerCase() : "";
    if (tag === "script" || tag === "link" || tag === "img") return;

    if (parent.querySelector(":scope > .consent-gate")) return;

    // The gate is absolutely positioned; anchor it to this box, not an ancestor.
    try {
      if (getComputedStyle(parent).position === "static") {
        parent.style.position = "relative";
      }
    } catch (e) {
      /* ignore */
    }

    parent.appendChild(buildGate(record, el));
  }

  /** Assign the withheld URL to one element. */
  function release(el) {
    var record = blocked.get(el);
    if (!record) return;

    blocked.delete(el);
    var i = blockedList.indexOf(el);
    if (i > -1) blockedList.splice(i, 1);

    try {
      el.removeAttribute("data-consent-src");
      el.removeAttribute("data-consent-vendor");
      el.removeAttribute("data-consent-category");
    } catch (e) {
      /* ignore */
    }

    // Drop the guarded accessor so the native one takes over again.
    try {
      delete el[record.attr];
    } catch (e) {
      /* ignore */
    }

    assignNative(el, record.attr, record.url);
  }

  // --- backstop --------------------------------------------------------------

  /**
   * Neutralise an element that reached the DOM without going through our
   * patched createElement — typically via innerHTML. Best effort: the request
   * may already have started, which is why the server-side scanner remains the
   * primary defence for markup.
   */
  function neutralise(el) {
    var tag = el.tagName ? el.tagName.toLowerCase() : "";
    var entry = GUARDED[tag];
    if (!entry) return;
    if (blocked.has(el)) return;

    var attr = entry[1];
    var url = el.getAttribute(attr);
    if (!url) return;

    var decision = null;
    try {
      decision = decide(url);
    } catch (e) {
      return;
    }
    if (!decision) return;

    try {
      el.removeAttribute(attr);
    } catch (e) {
      /* ignore */
    }
    markBlocked(el, attr, url, decision);
  }

  function scan(node) {
    if (!node || node.nodeType !== 1) return;

    neutralise(node);
    if (blocked.has(node)) showGate(node);

    if (!node.querySelectorAll) return;
    var found = node.querySelectorAll("iframe, script, img, link, embed");
    for (var i = 0; i < found.length; i++) {
      neutralise(found[i]);
      if (blocked.has(found[i])) showGate(found[i]);
    }
  }

  if (window.MutationObserver) {
    new MutationObserver(function (records) {
      for (var i = 0; i < records.length; i++) {
        var added = records[i].addedNodes;
        for (var j = 0; j < added.length; j++) scan(added[j]);
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  // --- react to consent ------------------------------------------------------

  api.onChange(function () {
    // Copy: release() mutates blockedList as it goes.
    var pending = blockedList.slice();
    for (var i = 0; i < pending.length; i++) {
      var record = blocked.get(pending[i]);
      if (record && api.has(record.category) === true) release(pending[i]);
    }

    // Clear placeholders whose category is now granted.
    var gates = document.querySelectorAll("[data-consent-gate]");
    for (var g = 0; g < gates.length; g++) {
      var category = gates[g].getAttribute("data-consent-category");
      if (api.has(category) === true && gates[g].parentNode) {
        gates[g].parentNode.removeChild(gates[g]);
      }
    }
  });
})();
