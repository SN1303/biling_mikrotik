/**
 * GlobalLoading — overlay "memuat" sederhana yang dipakai di seluruh aplikasi.
 *
 * API:
 *   GlobalLoading.show('Pesan...')
 *   GlobalLoading.showWithSteps([{ progress: 20, text: '...', delay: 250 }, ...])
 *   GlobalLoading.hide()
 *   GlobalLoading.isLoading()
 *   GlobalLoading.enableAutoLoading()   // kompatibilitas; overlay tetap dikendalikan manual
 */
(function (window, document) {
  'use strict';

  var overlay = null;
  var textEl = null;
  var barWrap = null;
  var bar = null;
  var visible = false;
  var stepTimers = [];
  var safetyTimer = null;

  function build() {
    if (overlay) return;
    overlay = document.createElement('div');
    overlay.className = 'gl-overlay';
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML =
      '<div class="gl-box">' +
      '  <div class="gl-spinner" aria-hidden="true"></div>' +
      '  <div class="gl-text">Memuat...</div>' +
      '  <div class="gl-bar-wrap"><div class="gl-bar"></div></div>' +
      '</div>';
    document.body.appendChild(overlay);
    textEl = overlay.querySelector('.gl-text');
    barWrap = overlay.querySelector('.gl-bar-wrap');
    bar = overlay.querySelector('.gl-bar');
  }

  function clearSteps() {
    stepTimers.forEach(clearTimeout);
    stepTimers = [];
  }

  function open(message, withBar) {
    if (!document.body) return;
    build();
    clearSteps();
    textEl.textContent = message || 'Memuat...';
    barWrap.style.display = withBar ? 'block' : 'none';
    bar.style.width = '0%';
    overlay.classList.add('gl-show');
    visible = true;

    // Pengaman: jangan sampai overlay menutup layar selamanya bila hide() tak terpanggil
    clearTimeout(safetyTimer);
    safetyTimer = setTimeout(hide, 60000);
  }

  function show(message) {
    open(message, false);
  }

  function showWithSteps(steps) {
    steps = Array.isArray(steps) ? steps : [];
    open(steps.length ? steps[0].text : 'Memproses...', true);
    var elapsed = 0;
    steps.forEach(function (step) {
      elapsed += Math.max(0, parseInt(step.delay, 10) || 0);
      stepTimers.push(setTimeout(function () {
        if (!visible) return;
        if (step.text) textEl.textContent = step.text;
        var p = Math.min(100, Math.max(0, parseFloat(step.progress) || 0));
        bar.style.width = p + '%';
      }, elapsed));
    });
  }

  function hide() {
    clearSteps();
    clearTimeout(safetyTimer);
    visible = false;
    if (overlay) overlay.classList.remove('gl-show');
  }

  window.GlobalLoading = {
    show: show,
    showWithSteps: showWithSteps,
    hide: hide,
    isLoading: function () { return visible; },
    enableAutoLoading: function () { /* kompatibilitas: tidak memasang hook otomatis */ }
  };
})(window, document);
