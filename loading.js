/**
 * AutoManager — overlay de loading inicial
 */
(function () {
  'use strict';

  if (window.__AM_LOADING_INIT__) return;
  window.__AM_LOADING_INIT__ = true;

  var MIN_MS = 520;
  var started = Date.now();
  var logoSrc = 'img/logo-automanager.png';

  document.documentElement.classList.add('am-is-loading');

  function loaderMarkup() {
    return (
      '<div class="am-loader-inner" aria-hidden="false">' +
        '<img src="' + logoSrc + '" alt="AutoManager" class="am-loader-logo" ' +
          'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\';">' +
        '<div class="am-loader-fallback" style="display:none">AutoManager<span class="dot">.</span></div>' +
        '<div class="am-loader-bar" aria-hidden="true"><span></span></div>' +
      '</div>'
    );
  }

  function mount() {
    if (document.getElementById('am-page-loader')) return;

    var el = document.createElement('div');
    el.id = 'am-page-loader';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-label', 'A carregar AutoManager');
    el.innerHTML = loaderMarkup();

    var parent = document.body || document.documentElement;
    parent.insertBefore(el, parent.firstChild || null);
  }

  function hide() {
    var loader = document.getElementById('am-page-loader');
    if (!loader || loader.classList.contains('is-done')) return;

    loader.classList.add('is-done');
    document.documentElement.classList.remove('am-is-loading');

    window.setTimeout(function () {
      if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
    }, 480);
  }

  function tryHide() {
    if (document.readyState !== 'complete') return;
    var wait = Math.max(0, MIN_MS - (Date.now() - started));
    window.setTimeout(hide, wait);
  }

  if (document.body) {
    mount();
  } else {
    document.addEventListener('DOMContentLoaded', mount);
  }

  window.addEventListener('load', tryHide);
  if (document.readyState === 'complete') tryHide();
})();
