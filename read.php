<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/image_upload.php';

$add_title = '';
$add_genre = '';
$add_image_path = null;
$add_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_movie') {
    $add_title = trim((string) ($_POST['title'] ?? ''));
    $add_genre = trim((string) ($_POST['genre'] ?? ''));

    if (!csrf_valid()) {
        $add_error = 'Your session expired. Please try again.';
    }
    if ($add_title === '') {
        $add_error = 'Enter a title.';
    } elseif (mb_strlen($add_title) > 150) {
        $add_error = 'The title must be 150 characters or fewer.';
    }
    if (!in_array($add_genre, GENRES, true)) {
        $add_error = 'Choose a genre from the list.';
    }

    if ($add_error === null) {
      try {
        $add_image_path = store_movie_image($_FILES['image_file'] ?? []);
      } catch (RuntimeException $exception) {
        $add_error = $exception->getMessage();
      }
    }

    if ($add_error === null) {
        $stmt = $pdo->prepare('INSERT INTO movies (title, genre, rating, image_url) VALUES (:title, :genre, NULL, :image_url)');
        $stmt->execute([
            ':title' => $add_title,
            ':genre' => $add_genre,
            ':image_url' => $add_image_path,
        ]);

        flash_set('success', '"' . $add_title . '" was added to your list.');
        redirect('read.php');
    }
}

/* ---------- Filters (search text + status) ---------- */
$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'queue', 'seen'], true)) {
    $status = 'all';
}

$where  = [];
$params = [];

if ($q !== '') {
    $where[]            = '(title LIKE :q1 OR genre LIKE :q2)';
    $params[':q1']      = '%' . $q . '%';
    $params[':q2']      = '%' . $q . '%';
}
if ($status === 'queue') {
    $where[] = 'rating IS NULL';
} elseif ($status === 'seen') {
    $where[] = 'rating IS NOT NULL';
}

$sql = 'SELECT id, title, genre, rating, image_url FROM movies';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY (rating IS NOT NULL), id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll();

/* ---------- Summary numbers ---------- */
$stats = $pdo->query(
    'SELECT COUNT(*) AS total, COUNT(rating) AS watched, AVG(rating) AS avg_rating FROM movies'
)->fetch();
$total   = (int) $stats['total'];
$watched = (int) $stats['watched'];
$avg     = $stats['avg_rating'] !== null ? number_format((float) $stats['avg_rating'], 1) : '–';

page_header('My list', 'list');
require __DIR__ . '/theme.php';
?>

<div class="search-wrap">
  <div class="search-bar">
    <form method="get" action="read.php" style="display: contents;">
      <input type="search" id="movieSearch" name="q" placeholder="Search for movies..." value="<?= h($q) ?>" aria-label="Search movies">
      <button type="submit">Search</button>
    </form>
  </div>
</div>

<div id="addMovieModal" class="movie-modal" aria-hidden="true">
  <div class="movie-modal-backdrop" data-close-modal="true"></div>
  <div class="movie-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="addMovieTitle">
    <div class="movie-modal-header">
      <h3 id="addMovieTitle">Add Movie</h3>
      <button type="button" class="close-modal" aria-label="Close">×</button>
    </div>

    <?php if ($add_error): ?>
      <div class="alert alert-danger mt-3 mb-3"><?= h($add_error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="movie-add-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_movie">
      <div class="mb-3">
        <label for="movieTitle" class="form-label">Title</label>
        <input type="text" id="movieTitle" name="title" class="form-control" maxlength="150" value="<?= h($add_title) ?>" required>
      </div>
      <div class="mb-3">
        <label for="movieGenre" class="form-label">Genre</label>
        <select id="movieGenre" name="genre" class="form-select" required>
          <option value="">Choose a genre</option>
          <?php foreach (GENRES as $g): ?>
            <option value="<?= h($g) ?>" <?= $add_genre === $g ? 'selected' : '' ?>><?= h($g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-4">
        <label for="movieImage" class="form-label">Poster Image (optional)</label>
        <input type="file" id="movieImage" name="image_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="image-paste-target" contenteditable="true" role="textbox" aria-label="Paste a poster image" data-file-input="movieImage" data-preview="addImagePreview">Paste an image from your clipboard</div>
        <img id="addImagePreview" class="image-preview" alt="Poster preview" hidden>
        <small class="text-muted">JPG, PNG, GIF, or WebP. Maximum 8 MB.</small>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-secondary-card close-modal">Cancel</button>
        <button type="submit" class="btn btn-primary-card">Save Movie</button>
      </div>
    </form>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="value"><?= $total ?></div>
    <div class="label">Total Movies</div>
  </div>
  <div class="stat-card">
    <div class="value"><?= $watched ?></div>
    <div class="label">Watched</div>
  </div>
  <div class="stat-card">
    <div class="value"><?= $total - $watched ?></div>
    <div class="label">To Watch</div>
  </div>
  <div class="stat-card">
    <div class="value"><?= h($avg) ?></div>
    <div class="label">Avg Rating</div>
  </div>
</div>

<div class="filters">
  <a href="read.php" class="filter-pill <?= $status === 'all' ? 'active' : '' ?>">All</a>
  <a href="read.php?status=seen" class="filter-pill <?= $status === 'seen' ? 'active' : '' ?>">Watched</a>
  <a href="read.php?status=queue" class="filter-pill <?= $status === 'queue' ? 'active' : '' ?>">To Watch</a>
</div>

<div class="section-title"><span class="icon"><i class="bi bi-search"></i></span> Search Results</div>

<?php if (!$movies): ?>
  <div class="text-center py-5">
    <p class="mb-2"><?= $total === 0 ? 'Nothing here yet.' : 'No titles match your filter.' ?></p>
    <a href="<?= $total === 0 ? 'create.php' : 'read.php' ?>" class="btn btn-primary-card">
      <?= $total === 0 ? 'Add your first title' : 'Clear filter' ?>
    </a>
  </div>
<?php else: ?>
  <div class="movie-grid">
    <?php foreach ($movies as $m): ?>
      <?php
        $title = h($m['title']);
        $cover = [
          'Inception' => 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=900&q=80',
          'Parasite' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=900&q=80',
          'Spirited Away' => 'https://images.unsplash.com/photo-1517479149777-5f3b1511d5ad?auto=format&fit=crop&w=900&q=80',
          'Breaking Bad' => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=900&q=80',
          'The Grand Budapest Hotel' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=900&q=80',
        ];
        $imageUrl = trim((string) ($m['image_url'] ?? ''));
        $poster = $imageUrl !== '' ? $imageUrl : ($cover[$m['title']] ?? 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?auto=format&fit=crop&w=900&q=80');
      ?>
      <article class="movie-card" data-id="<?= (int) $m['id'] ?>">
            <div class="poster" style="background-image: url('<?= h($poster) ?>');">
            </div>
        <div class="movie-content">
          <h3 class="movie-title"><?= $title ?></h3>
          <div class="movie-year"><?= h($m['genre']) ?></div>
              <div class="movie-rating"><span>★</span> <?= $m['rating'] !== null ? number_format((float) $m['rating'], 1) : '—' ?></div>
          <div class="movie-actions">
                <a class="btn-watch-card" href="update.php?id=<?= (int) $m['id'] ?>">
                  Edit Movie Details
                </a>
                <form method="post" action="delete.php" class="delete-movie-form" onsubmit="return confirm('Remove this title from your list?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                  <button type="submit" class="btn-remove-card">
                    Remove
                  </button>
                </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <p id="liveSearchEmpty" class="text-center py-4" role="status" hidden>No titles match your search.</p>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('addMovieModal');
    const closeButtons = document.querySelectorAll('.close-modal');

    const openModal = () => {
      modal.classList.add('show');
      modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
    };

    const navAddMovie = document.getElementById('navAddMovie');
    if (navAddMovie) {
      navAddMovie.addEventListener('click', function (event) {
        event.preventDefault();
        openModal();
      });
    }
    <?php if ($add_error !== null): ?>
    openModal();
    <?php endif; ?>

    closeButtons.forEach(function (btn) {
      btn.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', function (event) {
      if (event.target.dataset.closeModal === 'true') {
        closeModal();
      }
    });

    const clearImagePreview = (preview) => {
      if (preview.dataset.objectUrl) {
        URL.revokeObjectURL(preview.dataset.objectUrl);
        delete preview.dataset.objectUrl;
      }
      preview.removeAttribute('src');
      preview.hidden = true;
    };

    const showImagePreview = (input, preview) => {
      clearImagePreview(preview);
      const file = input.files[0];
      if (file) {
        const objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.dataset.objectUrl = objectUrl;
        preview.hidden = false;
      }
    };

    document.querySelectorAll('.image-paste-target').forEach(function (target) {
      const input = document.getElementById(target.dataset.fileInput);
      const preview = document.getElementById(target.dataset.preview);
      input.addEventListener('change', function () {
        showImagePreview(input, preview);
      });

      target.addEventListener('paste', function (event) {
        event.preventDefault();
        const items = Array.from(event.clipboardData?.items ?? []);
        const imageItem = items.find((item) => item.type.startsWith('image/'));
        const clipboardFile = imageItem?.getAsFile()
          || Array.from(event.clipboardData?.files ?? []).find((file) => file.type.startsWith('image/'));
        if (!clipboardFile) {
          return;
        }

        const extensions = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp' };
        const filename = clipboardFile.name || `pasted-poster.${extensions[clipboardFile.type] || 'png'}`;
        const pastedFile = new File([clipboardFile], filename, { type: clipboardFile.type });
        const transfer = new DataTransfer();
        transfer.items.add(pastedFile);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });

    const searchInput = document.getElementById('movieSearch');
    const movieGrid = document.querySelector('.movie-grid');
    const liveSearchEmpty = document.getElementById('liveSearchEmpty');
    if (searchInput && movieGrid) {
      const movieCards = Array.from(movieGrid.querySelectorAll('.movie-card'));
      const filterMovies = () => {
        const query = searchInput.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        movieCards.forEach(function (card) {
          const title = card.querySelector('.movie-title').textContent.toLocaleLowerCase();
          const genre = card.querySelector('.movie-year').textContent.toLocaleLowerCase();
          const matches = `${title} ${genre}`.includes(query);
          card.hidden = !matches;
          visibleCount += matches ? 1 : 0;
        });

        movieGrid.hidden = visibleCount === 0;
        if (liveSearchEmpty) {
          liveSearchEmpty.hidden = visibleCount !== 0;
        }
      };

      searchInput.addEventListener('input', filterMovies);
      filterMovies();
    }

  });
</script>

<style>
  .topnav #navAddMovie::before {
    content: '+';
    margin-right: 6px;
    font-size: 1.15em;
    font-weight: 800;
  }
  .search-wrap { align-items: center; }
  .search-bar {
    width: min(620px, 100%);
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    overflow: visible;
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
    min-width: 92px;
    min-height: 48px;
    padding: 0 24px;
    border: 0;
    border-radius: 999px;
    background: #4563f5;
    color: #fff;
    font-weight: 700;
  }
  .movie-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    margin: 18px 0 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #e7e9f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(31, 36, 58, 0.07);
  }
  .movie-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e8eaf0;
    border-radius: 7px;
    box-shadow: 0 2px 6px rgba(31, 36, 58, 0.08);
    transition: transform .18s ease, box-shadow .18s ease;
  }
  .movie-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 16px rgba(31, 36, 58, 0.13);
  }
  .poster {
    position: relative;
    height: auto;
    aspect-ratio: .92;
    flex: none;
    background-size: cover;
    background-position: center;
    border-bottom: 1px solid #edf0f5;
  }
  .poster::after { display: none; }
  .movie-content {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 11px 12px 12px;
  }
  .movie-title {
    display: -webkit-box;
    min-height: 36px;
    margin: 0;
    overflow: hidden;
    color: #242b3d;
    font-size: .9rem;
    font-weight: 700;
    line-height: 1.3;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
  }
  .movie-year {
    margin-top: 6px;
    color: #788196;
    font-size: .72rem;
  }
  .movie-rating {
    display: inline-flex;
    min-height: 17px;
    align-items: center;
    gap: 5px;
    margin-top: 8px;
    color: #f29a16;
    font-size: .78rem;
    font-weight: 700;
  }
  .movie-actions {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr);
    gap: 7px;
    margin-top: auto;
    padding-top: 11px;
  }
  .movie-actions a,
  .movie-actions button {
    display: inline-flex;
    min-width: 0;
    min-height: 28px;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 4px 3px;
    border-radius: 4px;
    font-size: .62rem;
    font-weight: 600;
    line-height: 1.15;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
  }
  .movie-actions i { font-size: .72rem; }
  .btn-watch-card {
    border: 1px solid #4563f5;
    background: #fff;
    color: #4563f5;
  }
  .btn-watch-card:hover { background: #f2f5ff; color: #304ed8; }
  .delete-movie-form { min-width: 0; margin: 0; }
  .btn-remove-card {
    width: 100%;
    border: 1px solid #ed3546;
    background: #ed3546;
    color: #fff;
  }
  .btn-remove-card:hover { background: #d92738; color: #fff; }
  @media (max-width: 1100px) {
    .movie-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  }
  @media (max-width: 860px) {
    .movie-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 560px) {
    .movie-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; padding: 12px; }
  }
  .image-paste-target {
    display: flex;
    min-height: 64px;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    padding: 12px;
    border: 1px dashed #b8c4d8;
    border-radius: 7px;
    background: #f8f9fc;
    color: #59647a;
    font-size: .88rem;
    text-align: center;
    cursor: text;
  }
  .image-paste-target:focus-visible {
    border-color: #4563f5;
    outline: 2px solid rgba(69,99,245,.16);
  }
  .image-preview {
    display: block;
    max-width: 100%;
    max-height: 220px;
    margin: 12px auto 0;
    border-radius: 6px;
    object-fit: contain;
  }
  .image-preview[hidden] { display: none; }
  .movie-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }
  .movie-modal.show {
    display: flex;
  }
  .movie-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(10, 12, 22, 0.55);
  }
  .movie-modal-dialog {
    position: relative;
    width: min(520px, calc(100% - 24px));
    background: #fff;
    border-radius: 18px;
    padding: 18px 20px 20px;
    box-shadow: 0 20px 40px rgba(14, 17, 32, 0.25);
  }
  .movie-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }
  .movie-modal-header h3 {
    margin: 0;
    color: #1d2436;
    font-weight: 800;
  }
  .close-modal {
    border: none;
    background: transparent;
    font-size: 2rem;
    line-height: 1;
    color: #47506a;
    cursor: pointer;
  }
  .btn-secondary-card {
    border-radius: 10px;
    font-weight: 700;
    font-size: .8rem;
    line-height: 1;
    padding: 10px 14px;
    border: 1px solid rgba(47,107,255,0.6);
    background: transparent;
    color: var(--accent-2);
  }
</style>

  .search-bar {
    width: min(620px, 100%);
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    overflow: visible;
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
    min-width: 92px;
    min-height: 48px;
    padding: 0 24px;
    border: 0;
    border-radius: 999px;
    background: #4563f5;
    color: #fff;
    font-weight: 700;
  }
