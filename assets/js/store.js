// Small progressive enhancements. Every page works without JavaScript.
document.addEventListener("DOMContentLoaded", function () {
  // Quantity steppers (+ / − buttons around a number input).
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

  // Sort dropdown submits its form on change.
  document.querySelectorAll("select[data-autosubmit]").forEach(function (el) {
    el.addEventListener("change", function () { el.form.submit(); });
  });

  // Cart quantities update automatically (short delay so repeated clicks batch up).
  var timer;
  document.querySelectorAll("[data-autosubmit-change]").forEach(function (el) {
    el.addEventListener("change", function () {
      clearTimeout(timer);
      timer = setTimeout(function () { el.form.submit(); }, 600);
    });
  });
  document.querySelectorAll("[data-hide-with-js]").forEach(function (el) { el.hidden = true; });

  // Mobile menu: close on backdrop / close button.
  document.querySelectorAll("[data-close-menu]").forEach(function (el) {
    el.addEventListener("click", function () { el.closest("details").removeAttribute("open"); });
  });

  // Checkout: prevent double submits and show progress.
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
});
