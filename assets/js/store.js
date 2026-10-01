// Progressive enhancements. Every page works without JavaScript.
(function () {
  "use strict";
  var base = document.body ? document.body.dataset.base || "" : "";

  function ready(fn) {
    if (document.readyState !== "loading") fn(); else document.addEventListener("DOMContentLoaded", fn);
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  ready(function () {
    // Quantity steppers.
    document.querySelectorAll(".qty").forEach(function (box) {
      var input = box.querySelector("input[type=number]");
      box.querySelectorAll("[data-step]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var min = input.min === "" ? 1 : Number(input.min);
          var max = input.max === "" ? Infinity : Number(input.max);
          var next = Math.min(max, Math.max(min, (Number(input.value) || 0) + Number(btn.dataset.step)));
          if (next !== Number(input.value)) {
            input.value = next;
            input.dispatchEvent(new Event("change", { bubbles: true }));
          }
        });
      });
    });

    // Sort dropdown submits on change; cart quantities update after a short pause.
    document.querySelectorAll("select[data-autosubmit]").forEach(function (el) {
      el.addEventListener("change", function () { el.form.submit(); });
    });
    var timer;
    document.querySelectorAll("[data-autosubmit-change]").forEach(function (el) {
      el.addEventListener("change", function () {
        clearTimeout(timer);
        timer = setTimeout(function () { el.form.submit(); }, 600);
      });
    });
    document.querySelectorAll("[data-hide-with-js]").forEach(function (el) { el.hidden = true; });

    // Drawers built on <details>: close on backdrop / close button / Escape.
    document.querySelectorAll("[data-close-menu]").forEach(function (el) {
      el.addEventListener("click", function () { el.closest("details").removeAttribute("open"); });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") document.querySelectorAll("details[open]").forEach(function (d) { d.removeAttribute("open"); });
    });

    // Confirm destructive actions.
    document.querySelectorAll("form[data-confirm]").forEach(function (f) {
      f.addEventListener("submit", function (e) { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
    });

    // Checkout: saved address vs new address; prevent double submits.
    var options = document.querySelector("[data-address-options]");
    var newBlock = document.querySelector("[data-new-address]");
    if (options && newBlock) {
      options.addEventListener("change", function (e) {
        if (e.target.name === "address_id") newBlock.classList.toggle("hidden", e.target.value !== "new");
      });
    }
    var checkout = document.querySelector("form[data-checkout]");
    if (checkout) {
      checkout.addEventListener("submit", function () {
        var btn = checkout.querySelector("button[type=submit]");
        setTimeout(function () {
          btn.disabled = true;
          var label = btn.querySelector("span");
          if (label) label.textContent = btn.dataset.submitLabel || "Please wait…";
        }, 0);
      });
    }

    // Show / hide password.
    document.querySelectorAll("[data-toggle-password]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var input = document.getElementById(btn.dataset.togglePassword);
        var show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
        btn.classList.toggle("text-brand", show);
      });
    });

    // Product page tabs.
    document.querySelectorAll("[data-tabs]").forEach(function (root) {
      root.querySelectorAll("[data-tab]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          root.querySelectorAll("[data-tab]").forEach(function (b) {
            var on = b === btn;
            b.setAttribute("aria-selected", on ? "true" : "false");
            b.classList.toggle("border-brand", on); b.classList.toggle("text-brand", on);
            b.classList.toggle("border-transparent", !on); b.classList.toggle("text-ink-soft", !on);
          });
          root.querySelectorAll("[data-panel]").forEach(function (p) { p.classList.toggle("hidden", p.dataset.panel !== btn.dataset.tab); });
        });
      });
    });

    // Product gallery: arrows, thumbnails, counter, keyboard; swipe is native scroll-snap.
    document.querySelectorAll("[data-gallery]").forEach(function (gallery) {
      var track = gallery.querySelector("[data-gallery-track]");
      var slides = track.querySelectorAll("[data-slide]");
      var count = gallery.querySelector("[data-gallery-count]");
      var thumbs = document.querySelectorAll("[data-gallery-thumbs] [data-thumb]");
      var current = 0;
      function go(i) {
        i = (i + slides.length) % slides.length;
        track.scrollTo({ left: slides[i].offsetLeft, behavior: "smooth" });
      }
      function mark(i) {
        current = i;
        if (count) count.textContent = i + 1 + " / " + slides.length;
        thumbs.forEach(function (t) {
          var on = Number(t.dataset.thumb) === i;
          t.classList.toggle("border-brand", on); t.classList.toggle("border-line", !on);
          if (on) t.setAttribute("aria-current", "true"); else t.removeAttribute("aria-current");
        });
      }
      var raf;
      track.addEventListener("scroll", function () {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () { mark(Math.round(track.scrollLeft / track.clientWidth)); });
      });
      gallery.querySelector("[data-gallery-prev]").addEventListener("click", function () { go(current - 1); });
      gallery.querySelector("[data-gallery-next]").addEventListener("click", function () { go(current + 1); });
      track.addEventListener("keydown", function (e) {
        if (e.key === "ArrowLeft") { e.preventDefault(); go(current - 1); }
        if (e.key === "ArrowRight") { e.preventDefault(); go(current + 1); }
      });
      thumbs.forEach(function (t) {
        t.addEventListener("click", function (e) { e.preventDefault(); go(Number(t.dataset.thumb)); });
      });
    });

    // Image zoom follows the pointer.
    document.querySelectorAll("[data-zoom]").forEach(function (frame) {
      frame.addEventListener("mousemove", function (e) {
        var r = frame.getBoundingClientRect();
        frame.style.setProperty("--zx", ((e.clientX - r.left) / r.width) * 100 + "%");
        frame.style.setProperty("--zy", ((e.clientY - r.top) / r.height) * 100 + "%");
      });
    });

    // Live search suggestions.
    document.querySelectorAll(".search-box").forEach(function (form) {
      var input = form.querySelector("[data-suggest]");
      var panel = form.querySelector(".suggest-panel");
      if (!input || !panel || !window.fetch) return;
      var t, last = "";
      function hide() { panel.classList.add("hidden"); }
      input.addEventListener("input", function () {
        clearTimeout(t);
        var q = input.value.trim();
        if (q.length < 2) { hide(); return; }
        t = setTimeout(function () {
          if (q === last) { panel.classList.remove("hidden"); return; }
          last = q;
          fetch(base + "/search_suggest.php?q=" + encodeURIComponent(q), { headers: { Accept: "application/json" } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (input.value.trim() !== q) return;
              var html = "";
              (data.categories || []).forEach(function (c) {
                html += '<a href="' + esc(c.url) + '" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50"><span class="rounded-md bg-brand-soft px-2 py-0.5 text-xs font-bold text-brand">Category</span>' + esc(c.name) + "</a>";
              });
              (data.products || []).forEach(function (p) {
                var img = p.image
                  ? '<img src="' + esc(p.image) + '" alt="" class="h-11 w-11 rounded-lg border border-line bg-white object-contain p-1">'
                  : '<span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-soft text-xs font-extrabold text-brand">' + esc((p.name || "?").slice(0, 2).toUpperCase()) + "</span>";
                html += '<a href="' + esc(p.url) + '" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50">' + img +
                  '<span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">' + esc(p.name) + '</span><span class="block text-xs text-ink-soft">' + esc(p.category || "") + "</span></span>" +
                  '<span class="text-sm font-bold">' + esc(p.price) + "</span></a>";
              });
              html = html
                ? html + '<a href="' + esc(data.all_url) + '" class="block border-t border-line px-4 py-3 text-center text-sm font-bold text-brand hover:bg-slate-50">See all results for “' + esc(q) + "”</a>"
                : '<p class="px-4 py-5 text-center text-sm text-ink-soft">No products match “' + esc(q) + "”</p>";
              panel.innerHTML = html;
              panel.classList.remove("hidden");
            })
            .catch(hide);
        }, 180);
      });
      input.addEventListener("focus", function () { if (panel.innerHTML && input.value.trim().length >= 2) panel.classList.remove("hidden"); });
      document.addEventListener("click", function (e) { if (!form.contains(e.target)) hide(); });
      input.addEventListener("keydown", function (e) { if (e.key === "Escape") hide(); });
    });
  });
})();
