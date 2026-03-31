/* ── EAW Preloader — inject immediately on script parse ── */
(function () {
  var style = document.createElement('style');
  style.textContent = [
    '#eaw-preloader{',
      'position:fixed;inset:0;z-index:99999;',
      'background:linear-gradient(160deg,#0F172A 0%,#1E3A8A 60%,#1D4ED8 100%);',
      'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px;',
      'transition:opacity .5s ease,visibility .5s ease;',
    '}',
    '#eaw-preloader.hide{opacity:0;visibility:hidden;}',
    '#eaw-preloader .epl-logo{display:flex;align-items:center;gap:12px;animation:epl-fadein .6s ease both;}',
    '#eaw-preloader .epl-logo img{height:52px;filter:drop-shadow(0 4px 16px rgba(29,78,216,.6));}',
    '#eaw-preloader .epl-logo-text{font-family:"Source Serif 4",serif;font-size:20px;font-weight:700;',
      'color:#fff;line-height:1.25;letter-spacing:-.2px;}',
    '#eaw-preloader .epl-logo-text span{display:block;font-size:11px;font-weight:600;',
      'letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.55);}',
    '#eaw-preloader .epl-spinner{',
      'width:40px;height:40px;border-radius:50%;',
      'border:3px solid rgba(255,255,255,.15);',
      'border-top-color:#D97706;',
      'animation:epl-spin .8s linear infinite;',
    '}',
    '#eaw-preloader .epl-tagline{',
      'font-size:13px;color:rgba(255,255,255,.45);letter-spacing:.5px;',
      'animation:epl-fadein .8s ease .3s both;',
    '}',
    '@keyframes epl-spin{to{transform:rotate(360deg)}}',
    '@keyframes epl-fadein{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}',
  ].join('');
  document.head.appendChild(style);

  function buildLoader() {
    var el = document.createElement('div');
    el.id = 'eaw-preloader';
    el.innerHTML =
      '<div class="epl-logo">' +
        '<img src="eaw_icon.png" alt="EAW">' +
        '<div class="epl-logo-text">Empowering African Women<span>DBTWTEi · Nigeria</span></div>' +
      '</div>' +
      '<div class="epl-spinner"></div>' +
      '<div class="epl-tagline">Loading your learning experience...</div>';
    document.body.insertBefore(el, document.body.firstChild);
  }

  if (document.body) {
    buildLoader();
  } else {
    document.addEventListener('DOMContentLoaded', buildLoader);
  }

  var MIN_MS = 2000; // minimum time the preloader stays visible
  var startTime = Date.now();
  var pageLoaded = false;

  function dismiss() {
    var el = document.getElementById('eaw-preloader');
    if (!el) return;
    el.classList.add('hide');
    setTimeout(function () { el.parentNode && el.parentNode.removeChild(el); }, 520);
  }

  window.addEventListener('load', function () {
    pageLoaded = true;
    var elapsed = Date.now() - startTime;
    var remaining = MIN_MS - elapsed;
    if (remaining > 0) {
      setTimeout(dismiss, remaining);
    } else {
      dismiss();
    }
  });

  // Safety fallback: never block longer than 5 seconds
  setTimeout(function () { if (!pageLoaded) dismiss(); }, 5000);
})();
