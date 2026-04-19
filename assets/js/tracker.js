(function () {
  "use strict";

  if (typeof window.MATTracker === "undefined") {
    return;
  }

  var ajaxUrl = MATTracker.ajaxUrl;
  var nonce = MATTracker.nonce;

  function getElementDescriptor(el) {
    if (!el) return "";
    var tag = (el.tagName || "").toLowerCase();
    var id = el.id ? "#" + el.id : "";
    var cls = "";
    if (el.classList && el.classList.length) {
      cls = "." + Array.prototype.slice.call(el.classList, 0, 2).join(".");
    }
    return (tag + id + cls).slice(0, 180);
  }

  function sendClick(payload) {
    var body = new URLSearchParams();
    body.append("action", "mat_track_click");
    body.append("nonce", nonce);
    body.append("page_url", payload.page_url || "");
    body.append("target_url", payload.target_url || "");
    body.append("label", payload.label || "");
    body.append("element", payload.element || "");

    if (navigator.sendBeacon) {
      var blob = new Blob([body.toString()], {
        type: "application/x-www-form-urlencoded;charset=UTF-8",
      });
      navigator.sendBeacon(ajaxUrl, blob);
      return;
    }

    fetch(ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: body.toString(),
      keepalive: true,
      credentials: "same-origin",
    }).catch(function () {
      return null;
    });
  }

  document.addEventListener(
    "click",
    function (event) {
      var clickable = event.target.closest("a,button");
      if (!clickable) return;

      var targetUrl = "";
      if (clickable.tagName.toLowerCase() === "a") {
        targetUrl = clickable.href || "";
      }

      var label =
        clickable.getAttribute("data-mat-label") ||
        clickable.getAttribute("aria-label") ||
        (clickable.innerText || "").trim().slice(0, 160);

      sendClick({
        page_url: window.location.href,
        target_url: targetUrl,
        label: label,
        element: getElementDescriptor(clickable),
      });
    },
    { passive: true }
  );
})();
