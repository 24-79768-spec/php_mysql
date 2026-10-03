<style>
  .theme-toggle {
    display: inline-flex;
    width: 38px;
    height: 38px;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 50%;
    background: rgba(255,255,255,.08);
    color: #fff;
    cursor: pointer;
    transition: background .2s ease, transform .2s ease;
  }
  .theme-toggle:hover { background: rgba(255,255,255,.18); transform: rotate(-8deg); }
  html[data-theme="dark"] {
    color-scheme: dark;
    --page-bg: #11161d;
    --card-bg: #1a222b;
    --soft-border: #303b47;
    --muted: #a1adb9;
    --ink: #e6edf3;
    --nav1: #263b50;
    --nav2: #35414d;
    --navy: #11161d;
    --tag-bg: #26333e;
    --shadow: 0 12px 26px rgba(0,0,0,.28);
  }
  html[data-theme="dark"] body { background: var(--page-bg); color: var(--ink); }
  html[data-theme="dark"] .stat-card,
  html[data-theme="dark"] .movie-grid,
  html[data-theme="dark"] .movie-card,
  html[data-theme="dark"] .panel {
    background: var(--card-bg);
    border-color: var(--soft-border);
    color: var(--ink);
  }
  html[data-theme="dark"] .stat-card .label,
  html[data-theme="dark"] .movie-year,
  html[data-theme="dark"] .text-muted { color: var(--muted) !important; }
  html[data-theme="dark"] .movie-title,
  html[data-theme="dark"] .movie-content,
  html[data-theme="dark"] .section-title { color: var(--ink); }
  html[data-theme="dark"] .poster { border-color: var(--soft-border); }
  html[data-theme="dark"] .search-bar input,
  html[data-theme="dark"] .form-control,
  html[data-theme="dark"] .form-select {
    border-color: var(--soft-border);
    background-color: #171e26;
    color: var(--ink);
  }
  html[data-theme="dark"] .form-control::placeholder { color: #8794a1; }
  html[data-theme="dark"] .movie-modal-dialog {
    border: 1px solid var(--soft-border);
    background: var(--card-bg);
    color: var(--ink);
  }
  html[data-theme="dark"] .movie-modal-header h3,
  html[data-theme="dark"] .close-modal { color: var(--ink); }
  html[data-theme="dark"] .image-paste-target {
    border-color: #475663;
    background: #171e26;
    color: #c0cad3;
  }
  html[data-theme="dark"] .btn-watch-card {
    background: #202c37;
    color: #aec2ff;
  }
  html[data-theme="dark"] .btn-watch-card:hover { background: #2a3947; }
  html[data-theme="dark"] .btn-outline-soft { color: #c6d1dc; border-color: #667480; }
  html[data-theme="dark"] .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
  html[data-theme="dark"] .alert { color: var(--ink); }
  @media (max-width: 560px) {
    .theme-toggle { width: 34px; height: 34px; }
  }
</style>
<script>
  (function () {
    const root = document.documentElement;
    let theme = 'light';
    try {
      theme = localStorage.getItem('watchlist-theme') === 'dark' ? 'dark' : 'light';
    } catch (error) {
      theme = 'light';
    }
    root.dataset.theme = theme;

    const navigation = document.querySelector('.topnav');
    let toggle = document.getElementById('themeToggle');
    if (!toggle && navigation) {
      const item = document.createElement('li');
      item.innerHTML = '<button type="button" id="themeToggle" class="theme-toggle"><i aria-hidden="true"></i></button>';
      navigation.appendChild(item);
      toggle = item.querySelector('button');
    }
    if (!toggle || toggle.dataset.themeBound === 'true') {
      return;
    }
    toggle.dataset.themeBound = 'true';

    const applyTheme = (nextTheme) => {
      const isDark = nextTheme === 'dark';
      root.dataset.theme = isDark ? 'dark' : 'light';
      toggle.innerHTML = isDark
        ? '<i class="bi bi-sun-fill" aria-hidden="true"></i>'
        : '<i class="bi bi-moon-stars-fill" aria-hidden="true"></i>';
      const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
      toggle.setAttribute('aria-label', label);
      toggle.title = label;
    };

    applyTheme(theme);
    toggle.addEventListener('click', function () {
      theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      applyTheme(theme);
      try {
        localStorage.setItem('watchlist-theme', theme);
      } catch (error) {
        // The selected theme remains active for this page if storage is unavailable.
      }
    });
  })();
</script>
