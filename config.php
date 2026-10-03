<?php
declare(strict_types=1);

/* ---------- Database settings (edit these if yours differ) ---------- */
const DB_HOST = 'localhost';
const DB_NAME = 'watchlist_db';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP/WAMP default is empty

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS movies (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(150) NOT NULL,
            genre VARCHAR(50) NOT NULL,
            rating TINYINT UNSIGNED NULL DEFAULT NULL,
            image_url VARCHAR(255) NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $column = $pdo->query("SHOW COLUMNS FROM movies LIKE 'image_url'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE movies ADD COLUMN image_url VARCHAR(255) NULL DEFAULT NULL AFTER rating');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('<h3>Database connection failed.</h3><p>Check the settings in config.php and make sure MySQL is running.</p>');
}

/* ---------- Helpers ---------- */
const GENRES = [
    'Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary',
    'Drama', 'Fantasy', 'Horror', 'Mystery', 'Romance', 'Sci-Fi', 'Thriller', 'Other',
];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
  return isset($_POST['csrf'], $_SESSION['csrf'])
    && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/* ---------- Shared page layout ---------- */
function page_header(string $title, string $active = ''): void
{
    $flash = flash_get();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    try {
      document.documentElement.dataset.theme = localStorage.getItem('watchlist-theme') === 'dark' ? 'dark' : 'light';
    } catch (error) {
      document.documentElement.dataset.theme = 'light';
    }
  </script>
  <title><?= h($title) ?> · Watchlist</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink: #15131a;
      --panel: #1f1c27;
      --line: #322e3d;
      --paper: #efeae0;
      --muted: #9a94a8;
      --gold: #f0b429;
      --seen: #5ec2a0;
    }
    body { background: var(--ink); color: var(--paper); font-family: 'Inter', system-ui, sans-serif; }
    h1, h2, h3, .brand { font-family: 'Bricolage Grotesque', 'Inter', sans-serif; letter-spacing: -.01em; }
    :root {
      --page-bg: #ececf4;
      --card-bg: #f7f7fb;
      --soft-border: #dfe3f4;
      --muted: #5d6478;
      --ink: #1d2436;
      --nav1: #2d4bea;
      --nav2: #4a2ea0;
      --accent: #2f6bff;
      --accent-2: #3d7bff;
      --navy: #1b1830;
      --tag-bg: #eef2ff;
      --shadow: 0 12px 26px rgba(44, 53, 92, 0.12);
    }
    * { box-sizing: border-box; }
    body {
      margin:0;
      background: var(--page-bg);
      color: var(--ink);
      font-family: 'Inter', system-ui, sans-serif;
    }
    a { text-decoration: none; }
    .topbar {
      background: linear-gradient(90deg, var(--nav1), var(--nav2));
      box-shadow: 0 8px 18px rgba(34, 36, 88, 0.2);
    }
    .topbar .container {
      max-width: 1240px;
      margin: 0 auto;
      padding: 0 18px;
      min-height: 72px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .brand {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: #fff;
      font-weight: 800;
      font-size: 1.05rem;
      letter-spacing: -.02em;
    }
    .brand-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 26px;
      height: 26px;
      border-radius: 8px;
      background: rgba(255,255,255,0.13);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.18);
      font-size: .9rem;
    }
    .topnav {
      display: flex;
      align-items: center;
      gap: 12px;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .topnav a {
      color: rgba(255,255,255,0.85);
      background: rgba(255,255,255,0.08);
      border-radius: 999px;
      padding: 8px 16px;
      font-weight: 600;
      font-size: .9rem;
      transition: .2s ease;
    }
    .topnav a:hover, .topnav a.active {
      background: rgba(255,255,255,0.14);
      color: #fff;
    }
    .theme-toggle {
      display: inline-flex;
      width: 38px;
      height: 38px;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
      color: #fff;
      cursor: pointer;
      transition: background .2s ease, transform .2s ease;
    }
    .theme-toggle:hover { background: rgba(255,255,255,0.18); transform: rotate(-8deg); }
    .topnav #navAddMovie::before {
      content: '+';
      margin-right: 6px;
      font-size: 1.15em;
      font-weight: 800;
    }
    .page-shell {
      max-width: 1240px;
      margin: 0 auto;
      padding: 28px 18px 32px;
    }
    .search-wrap {
      display: flex;
      justify-content: center;
      padding: 8px 0 18px;
    }
    .search-bar {
      width: min(620px, 100%);
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
    }
    .search-bar input {
      min-width: 0;
      min-height: 48px;
      border: 1px solid #e3e5e9;
      border-radius: 999px;
      background: #fff;
      padding: 12px 20px;
      font-size: 1rem;
      color: var(--ink);
      outline: none;
    }
    .search-bar input::placeholder { color: #868ea5; }
    .search-bar input:focus-visible {
      border-color: #4563f5;
      box-shadow: 0 0 0 3px rgba(69,99,245,0.14);
    }
    .search-bar button {
      border: none;
      border-radius: 999px;
      background: #4563f5;
      color: #fff;
      font-weight: 700;
      padding: 0 24px;
      min-width: 92px;
      min-height: 48px;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 18px;
      margin-top: 12px;
      margin-bottom: 22px;
    }
    .stat-card {
      background: rgba(255,255,255,0.74);
      border: 1px solid var(--soft-border);
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(33, 39, 73, 0.06);
      padding: 22px 12px;
      text-align: center;
    }
    .stat-card .value {
      font-size: clamp(2rem, 2vw, 2.8rem);
      font-weight: 800;
      color: var(--accent-2);
      letter-spacing: -.05em;
      line-height: 1.1;
    }
    .stat-card .label {
      margin-top: 8px;
      color: var(--muted);
      font-size: 1rem;
      font-weight: 500;
    }
    .filters {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin: 16px 0 18px;
    }
    .filter-pill {
      background: transparent;
      border: 1px solid var(--soft-border);
      color: var(--muted);
      border-radius: 999px;
      padding: 7px 16px;
      font-size: .88rem;
      font-weight: 600;
    }
    .filter-pill.active {
      background: var(--accent-2);
      border-color: var(--accent-2);
      color: white;
    }
    .section-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 20px 0 18px;
      font-weight: 800;
      color: #4a3fb5;
      font-size: 1.05rem;
    }
    .section-title .icon {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width: 26px;
      height: 26px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--accent), var(--nav2));
      color: white;
      font-size: .8rem;
    }
    .movie-grid {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 18px;
      margin-top: 8px;
    }
    .movie-card {
      background: rgba(255,255,255,0.72);
      border: 1px solid var(--soft-border);
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(31, 36, 58, 0.08);
      transition: transform .2s ease, box-shadow .2s ease;
    }
    .movie-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 28px rgba(31, 36, 58, 0.12);
    }
    .poster {
      position: relative;
      height: 290px;
      background-size: cover;
      background-position: center;
      border-bottom: 1px solid rgba(36,39,58,0.08);
    }
    .poster::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(0,0,0,0.02), rgba(0,0,0,0.26));
    }
    .movie-content {
      padding: 12px 12px 14px;
    }
    .movie-title {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.35;
      min-height: 46px;
    }
    .movie-year {
      color: var(--muted);
      margin-top: 6px;
      font-size: .9rem;
    }
    .movie-rating {
      margin-top: 10px;
      color: #f3b41d;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: .92rem;
    }
    .movie-actions {
      margin-top: 14px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .btn-primary-card,
    .btn-secondary-card {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      border-radius: 10px;
      font-weight: 700;
      font-size: .8rem;
      line-height: 1;
      padding: 10px 8px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: .2s ease;
    }
    .btn-primary-card {
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
    }
    .btn-secondary-card {
      background: transparent;
      color: var(--accent-2);
      border-color: rgba(47,107,255,0.6);
    }
    .site-footer {
      margin-top: 46px;
      background: linear-gradient(180deg, #1a1b2c, #111421);
      color: #fff;
      text-align: center;
      padding: 28px 16px 24px;
    }
    .site-footer .brand {
      justify-content: center;
      display: inline-flex;
      margin-bottom: 12px;
      font-size: 1.1rem;
    }
    .site-footer .tagline {
      margin: 10px 0 14px;
      color: rgba(255,255,255,0.75);
      font-size: .96rem;
    }
    .socials {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin-bottom: 14px;
      font-size: 1.1rem;
      color: rgba(255,255,255,0.9);
    }
    .site-footer small {
      color: rgba(255,255,255,0.7);
      font-size: .88rem;
    }
    @media (max-width: 1100px) {
      .movie-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 860px) {
      .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .movie-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 560px) {
      .topbar .container { min-height: 62px; }
      .topnav { gap: 8px; }
      .topnav a { padding: 7px 11px; font-size: .8rem; }
      .theme-toggle { width: 34px; height: 34px; }
      .stats-grid, .movie-grid { grid-template-columns: 1fr; }
      .search-bar { grid-template-columns: minmax(0, 1fr) auto; }
      .search-bar button { min-height: 48px; }
    }
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
  </style>
</head>
<body>
<nav class="topbar">
  <div class="container">
    <a class="brand" href="read.php"><span class="brand-mark"><i class="bi bi-film"></i></span> Movie Watchlist App</a>
    <ul class="topnav">
      <li><a class="active" href="read.php">Home</a></li>
      <li><a href="#" id="navAddMovie">Add Movie</a></li>
      <li><button type="button" id="themeToggle" class="theme-toggle" aria-label="Switch to dark mode" title="Switch to dark mode"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></button></li>
    </ul>
  </div>
</nav>
<main class="page-shell">
<?php if ($flash): ?>
  <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= h($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif;
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="brand"><span class="brand-mark"><i class="bi bi-film"></i></span> Movie Watchlist App</div>
  <div class="tagline">Track your movies and never forget what to watch next</div>
  <div class="socials"><span>f</span><span>◎</span><span>◌</span><span>◍</span></div>
  <small>© 2026 Watchlist App. All rights reserved.</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    const toggle = document.getElementById('themeToggle');
    const root = document.documentElement;
    if (!toggle) {
      return;
    }

    const applyTheme = (theme) => {
      const isDark = theme === 'dark';
      root.dataset.theme = isDark ? 'dark' : 'light';
      toggle.innerHTML = isDark
        ? '<i class="bi bi-sun-fill" aria-hidden="true"></i>'
        : '<i class="bi bi-moon-stars-fill" aria-hidden="true"></i>';
      const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
      toggle.setAttribute('aria-label', label);
      toggle.title = label;
    };

    applyTheme(root.dataset.theme);
    toggle.addEventListener('click', function () {
      const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      applyTheme(nextTheme);
      try {
        localStorage.setItem('watchlist-theme', nextTheme);
      } catch (error) {
        // The selected theme remains active for this page if storage is unavailable.
      }
    });
  })();
</script>
</body>
</html>
<?php
}
