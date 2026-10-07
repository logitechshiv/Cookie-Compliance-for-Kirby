/**
 * Banner UI. Inlined near </body> by snippets/banner.php.
 *
 * Holds no consent logic of its own — it reads and writes through
 * window.KirbyConsent, which consent-core.js defined in <head>.
 */
(function () {
  "use strict";

  var banner = document.getElementById("consent-banner");
  if (!banner || !window.KirbyConsent) return;

  var api = window.KirbyConsent;
  var toggles = banner.querySelectorAll("[data-consent-category]");

  function syncToggles() {
    var state = api.state();
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].checked = state.c[toggles[i].dataset.consentCategory] === true;
    }
  }

  function open() {
    syncToggles();
    banner.hidden = false;
    document.body.classList.add("consent-open");
  }

  function close() {
    banner.hidden = true;
    document.body.classList.remove("consent-open");
  }

  function selection() {
    var choices = {};
    for (var i = 0; i < toggles.length; i++) {
      choices[toggles[i].dataset.consentCategory] = toggles[i].checked === true;
    }
    return choices;
  }

  function commit(choices, event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    // Close first: set() may reload the page when server-rendered gates exist.
    close();
    api.set(choices);
  }

  // consent-core.js exposes open() as a no-op until the banner is on the page.
  api.open = open;

  var acceptAll = banner.querySelector("[data-consent-accept-all]");
  var necessary = banner.querySelector("[data-consent-necessary]");
  var save = banner.querySelector("[data-consent-save]");

  if (acceptAll) {
    acceptAll.addEventListener("click", function (e) {
      var all = {};
      for (var i = 0; i < api.config.categories.length; i++) {
        all[api.config.categories[i]] = true;
      }
      commit(all, e);
    });
  }

  if (necessary) {
    necessary.addEventListener("click", function (e) {
      commit({}, e);
    });
  }

  if (save) {
    save.addEventListener("click", function (e) {
      commit(selection(), e);
    });
  }

  // Clicks inside the dialog must not reach the page behind it.
  banner.addEventListener("click", function (e) {
    e.stopPropagation();
  });

  // Delegated so any [data-consent-open] control works, including ones added
  // later by script.js video gates or by the server-rendered embed gates.
  document.addEventListener(
    "click",
    function (e) {
      var trigger = e.target.closest("[data-consent-open]");
      if (!trigger) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    },
    true
  );

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && !banner.hidden && api.isDecided()) close();
  });

  syncToggles();
})();
