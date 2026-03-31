/* ====================================================
   EAW Common — Dark Mode + Search Modal
   ==================================================== */

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

const EAW_COURSES = [
  { slug:'mechanic',         title:'Female Mechanic Incubator',             cat:'Auto Mechanics',    bg:'linear-gradient(135deg,#1a1a2e,#16213e)',   img:'Pictures/Female_Mechanic_Incubator.jpg',              link:'course-mechanic.html',         desc:'Hands-on automotive training for women' },
  { slug:'baking',           title:'Baking & Cooking Essentials',           cat:'Baking & Cooking',  bg:'linear-gradient(135deg,#5a3825,#8b4513)',   img:'Pictures/Baking_&_Cooking_Essentials.jpg',            link:'course-baking.html',           desc:'9 cake recipes, healthy juices & rice meals' },
  { slug:'etiquette',        title:'Social & Business Etiquette',           cat:'Business Skills',   bg:'linear-gradient(135deg,#0F172A,#1D4ED8)',   img:'Pictures/Social_&_Business_Etiquette.jpg',            link:'course-etiquette.html',        desc:'Professional conduct & workplace etiquette' },
  { slug:'coding',           title:'Coding & Web Development',              cat:'Technology',        bg:'linear-gradient(135deg,#0c1445,#1D4ED8)',   img:'Pictures/Coding_&_Web_Development.jpg',               link:'course-coding.html',           desc:'HTML, CSS, JavaScript and web basics' },
  { slug:'digital-skills',   title:'Computer Science & Digital Skills',     cat:'Technology',        bg:'linear-gradient(135deg,#1e1b4b,#4338ca)',   img:'Pictures/Computer_Science_&_Digital_Skills.jpg',      link:'course-digital-skills.html',   desc:'Digital literacy, software & internet skills' },
  { slug:'english',          title:'English Language & Communication',      cat:'Language',          bg:'linear-gradient(135deg,#1e3a5f,#2563eb)',   img:'Pictures/English_Language_&_Communication.jpg',       link:'course-english.html',          desc:'Spoken English, grammar and communication' },
  { slug:'entrepreneurship', title:'Business & Entrepreneurship',           cat:'Business Skills',   bg:'linear-gradient(135deg,#1e3a8a,#2563EB)',   img:'Pictures/Business_&_Entrepreneurship.jpg',            link:'course-entrepreneurship.html', desc:'Start and grow your own business' },
  { slug:'finance',          title:'Financial Literacy & Economics',        cat:'Finance',           bg:'linear-gradient(135deg,#14532d,#16a34a)',   img:'Pictures/Financial_Literacy.jpg',                     link:'course-finance.html',          desc:'Budgeting, savings and personal finance' },
  { slug:'health',           title:'Health & Body Awareness',               cat:'Health',            bg:'linear-gradient(135deg,#064e3b,#059669)',   img:'Pictures/Health _&_Body_Awareness.jpg',               link:'course-health.html',           desc:'Wellness, nutrition and healthy living' },
  { slug:'sewing',           title:'Sewing & Fashion Design',               cat:'Fashion & Design',  bg:'linear-gradient(135deg,#4a044e,#7e22ce)',   img:'Pictures/Sewing_&_Fashion_Design.jpg',                link:'course-sewing.html',           desc:'Fabric, patterns and fashion design' },
  { slug:'soft-skills',      title:'Professional Soft Skills',              cat:'Career Skills',     bg:'linear-gradient(135deg,#0F766E,#14B8A6)',   img:'Pictures/Professional_Soft_Skills.jpg',               link:'course-soft-skills.html',      desc:'Communication, teamwork and leadership' },
];

/* ─── SVG Icons ───────────────────────────────────────────────────────── */
// Filled moon — clearly a crescent, not a banana outline
const MOON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M20.354 15.354A9 9 0 0 1 8.646 3.646 9.003 9.003 0 0 0 12 21a9.003 9.003 0 0 0 8.354-5.646z"/></svg>`;
// Filled sun circle + stroke rays
const SUN_SVG  = `<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4" fill="currentColor"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/></svg>`;
const SEARCH_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="7.5"/><line x1="21" y1="21" x2="15.8" y2="15.8"/></svg>`;

/* ─── Dark Mode ───────────────────────────────────────────────────────── */
function applyDarkMode(on) {
  document.body.classList.toggle('dark-mode', on);
  document.querySelectorAll('.eaw-dark-btn').forEach(function(b) {
    b.innerHTML = on ? SUN_SVG : MOON_SVG;
    b.title = on ? 'Switch to light mode' : 'Switch to dark mode';
  });
}

function toggleEawDark() {
  var next = !document.body.classList.contains('dark-mode');
  try { localStorage.setItem('eaw_theme', next ? 'dark' : 'light'); } catch(e) {}
  applyDarkMode(next);
}
window.toggleEawDark = toggleEawDark; // expose globally

/* ─── Search Modal ────────────────────────────────────────────────────── */
function buildSearchModal() {
  if (document.getElementById('eawSearchModal')) return;
  var el = document.createElement('div');
  el.id = 'eawSearchModal';
  el.setAttribute('role','dialog');
  el.setAttribute('aria-label','Course Search');
  el.innerHTML = [
    '<div class="esm-backdrop" id="esmBackdrop"></div>',
    '<div class="esm-card">',
      '<div class="esm-header">',
        '<div class="esm-header-glow"></div>',
        '<div class="esm-header-content">',
          '<span class="esm-eyebrow">EAW Course Library · 11 Free Courses</span>',
          '<h2 class="esm-heading">What do you want to learn?</h2>',
          '<p class="esm-subhead">Search by skill, topic or keyword — all courses are 100% free.</p>',
        '</div>',
        '<button class="esm-close-btn" id="esmCloseBtn" aria-label="Close search">',
          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        '</button>',
      '</div>',
      '<div class="esm-body">',
        '<div class="esm-search-row" id="esmSearchRow">',
          '<span class="esm-input-icon">',
            '<svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="7.5"/><line x1="21" y1="21" x2="15.8" y2="15.8"/></svg>',
          '</span>',
          '<input id="esmInput" class="esm-input" type="text" placeholder="e.g. Baking, Auto Mechanics, Coding…" autocomplete="off" spellcheck="false">',
          '<button class="esm-search-go" id="esmGoBtn">Search</button>',
        '</div>',
        '<div class="esm-pills" id="esmPills">',
          '<span class="esm-pills-label">Popular:</span>',
          '<button class="esm-pill esm-pill-active" data-cat="">All</button>',
          '<button class="esm-pill" data-cat="Auto Mechanics">Auto Mechanics</button>',
          '<button class="esm-pill" data-cat="Business">Business</button>',
          '<button class="esm-pill" data-cat="Technology">Tech &amp; Coding</button>',
          '<button class="esm-pill" data-cat="Finance">Finance</button>',
          '<button class="esm-pill" data-cat="Health">Health</button>',
          '<button class="esm-pill" data-cat="Fashion">Fashion</button>',
        '</div>',
        '<div id="esmResults" class="esm-results"></div>',
      '</div>',
    '</div>'
  ].join('');
  document.body.appendChild(el);

  // Wire up events
  document.getElementById('esmBackdrop').addEventListener('click', closeEawSearch);
  document.getElementById('esmCloseBtn').addEventListener('click', closeEawSearch);

  var input = document.getElementById('esmInput');
  input.addEventListener('input', function() { esmRender(this.value); });
  input.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEawSearch(); });

  document.getElementById('esmGoBtn').addEventListener('click', function() {
    esmRender(document.getElementById('esmInput').value);
  });

  document.getElementById('esmPills').addEventListener('click', function(e) {
    var pill = e.target.closest('.esm-pill');
    if (!pill) return;
    document.querySelectorAll('.esm-pill').forEach(function(p) { p.classList.remove('esm-pill-active'); });
    pill.classList.add('esm-pill-active');
    var input = document.getElementById('esmInput');
    input.value = '';
    esmRender(pill.dataset.cat || '');
  });

  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEawSearch(); });
}

function esmRender(query) {
  var el = document.getElementById('esmResults');
  if (!el) return;
  var q = (query || '').toLowerCase().trim();
  var results = q
    ? EAW_COURSES.filter(function(c) {
        return c.title.toLowerCase().indexOf(q) > -1 ||
               c.cat.toLowerCase().indexOf(q) > -1 ||
               c.desc.toLowerCase().indexOf(q) > -1;
      })
    : EAW_COURSES;

  if (!results.length) {
    el.innerHTML = '<div class="esm-empty"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><p>No courses found for <strong>"' + escHtml(query) + '"</strong></p></div>';
    return;
  }

  el.innerHTML = results.map(function(c) {
    return '<a href="' + c.link + '" class="esm-result-card" onclick="closeEawSearch()">'
      + '<div class="esm-result-thumb" style="background:' + c.bg + '">'
      + (c.img ? '<img src="' + c.img + '" alt="' + c.title + '" style="width:100%;height:100%;object-fit:cover;">' : '')
      + '</div>'
      + '<div class="esm-result-info">'
      + '<span class="esm-result-cat">' + c.cat + '</span>'
      + '<strong class="esm-result-title">' + c.title + '</strong>'
      + '<span class="esm-result-desc">' + c.desc + '</span>'
      + '</div></a>';
  }).join('');
}

function openEawSearch() {
  var modal = document.getElementById('eawSearchModal');
  if (!modal) return;
  modal.classList.add('esm-open');
  document.body.style.overflow = 'hidden';
  esmRender('');
  setTimeout(function() {
    var inp = document.getElementById('esmInput');
    if (inp) inp.focus();
  }, 120);
}

function closeEawSearch() {
  var modal = document.getElementById('eawSearchModal');
  if (modal) modal.classList.remove('esm-open');
  document.body.style.overflow = '';
}

window.openEawSearch  = openEawSearch;
window.closeEawSearch = closeEawSearch;

/* ─── Dashboard inline search ─────────────────────────────────────────── */
window.eawSearch = function(query, resultsId) {
  var el = document.getElementById(resultsId || 'eawSearchResults');
  if (!el) return;
  var q = (query || '').toLowerCase().trim();
  var results = q
    ? EAW_COURSES.filter(function(c) {
        return c.title.toLowerCase().indexOf(q) > -1 ||
               c.cat.toLowerCase().indexOf(q) > -1 ||
               c.desc.toLowerCase().indexOf(q) > -1;
      })
    : EAW_COURSES;

  if (!results.length) {
    el.innerHTML = '<p style="color:var(--text-mid);text-align:center;padding:20px;">No courses found.</p>';
    return;
  }

  // Collect completed course slugs from localStorage
  var completedIds = new Set();
  try {
    var u = JSON.parse(localStorage.getItem('eaw_user') || 'null');
    if (u) {
      (u.certificates || []).forEach(function(c) { completedIds.add(c.id); });
      (u.enrollments || []).forEach(function(e) { if (e.quizPassed || e.progress >= 100) completedIds.add(e.courseId); });
    }
  } catch(e2) {}

  el.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:14px;">'
    + results.map(function(c) {
        var done = completedIds.has(c.id);
        return '<a href="' + c.link + '" class="dash-search-card" style="position:relative;">'
          + (done ? '<span style="position:absolute;top:8px;right:8px;background:#059669;color:#fff;font-size:10px;font-weight:800;padding:2px 8px;border-radius:99px;z-index:2;letter-spacing:.3px;">✓ Done</span>' : '')
          + '<div class="dash-search-thumb" style="background:' + c.bg + ';">'
          + (c.img ? '<img src="' + c.img + '" alt="' + c.title + '" style="width:100%;height:100%;object-fit:cover;">' : '')
          + '</div>'
          + '<div class="dash-search-info">'
          + '<div class="dash-search-cat">' + c.cat + '</div>'
          + '<strong class="dash-search-title">' + c.title + '</strong>'
          + '<span class="dash-search-desc">' + c.desc + '</span>'
          + '</div></a>';
      }).join('')
    + '</div>';
};

window.eawSearchFromInput = function(inputId, resultsId) {
  var val = document.getElementById(inputId) ? document.getElementById(inputId).value : '';
  window.eawSearch(val, resultsId);
};

window.eawPillSearch = function(cat, btn, resultsId) {
  var container = btn ? btn.closest('.eaw-pills') : null;
  if (container) container.querySelectorAll('.eaw-pill').forEach(function(p) { p.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  window.eawSearch(cat, resultsId || 'eawSearchResults');
};

/* ─── Inject navbar buttons ───────────────────────────────────────────── */
function injectNavButtons() {
  document.querySelectorAll('.nav-cta').forEach(function(navCta) {
    // Dark mode button
    if (!navCta.querySelector('.eaw-dark-btn')) {
      var darkBtn = document.createElement('button');
      darkBtn.className = 'eaw-dark-btn';
      darkBtn.title = 'Switch to dark mode';
      darkBtn.innerHTML = MOON_SVG;
      darkBtn.addEventListener('click', toggleEawDark);
      navCta.prepend(darkBtn);
    }
    // Search button
    if (!navCta.querySelector('.eaw-search-nav-btn')) {
      var searchBtn = document.createElement('button');
      searchBtn.className = 'eaw-search-nav-btn';
      searchBtn.title = 'Search courses';
      searchBtn.innerHTML = SEARCH_SVG;
      searchBtn.addEventListener('click', openEawSearch);
      navCta.prepend(searchBtn);
    }
  });
}

/* ─── Init ────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  buildSearchModal();
  injectNavButtons();
  try {
    if (localStorage.getItem('eaw_theme') === 'dark') applyDarkMode(true);
  } catch(e) {}
});
