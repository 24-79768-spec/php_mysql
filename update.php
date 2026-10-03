<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/image_upload.php';

/* ---------- Find the movie ---------- */
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    flash_set('warning', 'That title could not be found.');
    redirect('read.php');
}

$stmt = $pdo->prepare('SELECT id, title, genre, rating, image_url FROM movies WHERE id = :id');
$stmt->execute([':id' => $id]);
$movie = $stmt->fetch();

if (!$movie) {
    flash_set('warning', 'That title is no longer on your list.');
    redirect('read.php');
}

$title  = $movie['title'];
$genre  = $movie['genre'];
$rating = $movie['rating'] !== null ? (string) $movie['rating'] : '';
$imagePath = (string) ($movie['image_url'] ?? '');
$errors = [];

/* ---------- Save changes ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim((string) ($_POST['title'] ?? ''));
    $genre  = (string) ($_POST['genre'] ?? '');
    $rating = trim((string) ($_POST['rating'] ?? ''));

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($title === '') {
        $errors[] = 'Enter a title.';
    } elseif (mb_strlen($title) > 150) {
        $errors[] = 'The title must be 150 characters or fewer.';
    }
    if (!in_array($genre, GENRES, true) && $genre !== $movie['genre']) {
        $errors[] = 'Choose a genre from the list.';
    }

    $ratingValue = null;
    if ($rating !== '') {
        $check = filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        if ($check === false) {
            $errors[] = 'The rating must be a whole number from 1 to 10.';
        } else {
            $ratingValue = $check;
        }
    }

      $uploadedImagePath = null;
      if (!$errors) {
        try {
          $uploadedImagePath = store_movie_image($_FILES['image_file'] ?? []);
        } catch (RuntimeException $exception) {
          $errors[] = $exception->getMessage();
        }
      }

    if (!$errors) {
        $upd = $pdo->prepare('UPDATE movies SET title = :title, genre = :genre, rating = :rating, image_url = COALESCE(:image_url, image_url) WHERE id = :id');
        $upd->bindValue(':title', $title);
        $upd->bindValue(':genre', $genre);
        $upd->bindValue(':rating', $ratingValue, $ratingValue === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $upd->bindValue(':image_url', $uploadedImagePath, $uploadedImagePath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $upd->bindValue(':id', $id, PDO::PARAM_INT);
        $upd->execute();

        flash_set('success', '"' . $title . '" was updated.');
        redirect('read.php');
    }
}

// Make sure a genre outside the standard list (e.g. from older data) still appears
$genreOptions = GENRES;
if ($genre !== '' && !in_array($genre, $genreOptions, true)) {
    $genreOptions[] = $genre;
}

page_header('Edit Movie Details');
require __DIR__ . '/theme.php';
?>

<h1 class="h2 mb-1">Edit Movie Details</h1>
<p class="text-muted mb-4">Update the title, genre, rating, or poster image.</p>

<div class="panel p-4">
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $movie['id'] ?>">

    <div class="mb-3">
      <label for="title" class="form-label">Title</label>
      <input type="text" id="title" name="title" class="form-control" maxlength="150"
             value="<?= h($title) ?>" required>
    </div>

    <div class="mb-3">
      <label for="genre" class="form-label">Genre</label>
      <select id="genre" name="genre" class="form-select" required>
        <?php foreach ($genreOptions as $g): ?>
          <option value="<?= h($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= h($g) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-4">
      <label for="rating" class="form-label">Rating</label>
      <select id="rating" name="rating" class="form-select">
        <option value="">Not watched yet</option>
        <?php for ($i = 1; $i <= 10; $i++): ?>
          <option value="<?= $i ?>" <?= $rating === (string) $i ? 'selected' : '' ?>><?= $i ?> / 10</option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="mb-4">
      <label for="movieImageFile" class="form-label">Poster Image</label>
      <input type="file" id="movieImageFile" name="image_file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
      <div class="image-paste-target" contenteditable="true" role="textbox" aria-label="Paste a poster image" data-file-input="movieImageFile" data-preview="movieImagePreview">Choose a file or paste a new image</div>
      <img id="movieImagePreview" class="image-preview" alt="Movie poster preview" src="<?= h($imagePath) ?>" <?= $imagePath === '' ? 'hidden' : '' ?>>
      <small class="text-muted">JPG, PNG, GIF, or WebP. Maximum 8 MB. Leave empty to keep the current image.</small>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-gold">Save Movie Details</button>
      <a href="read.php" class="btn btn-outline-soft">Cancel</a>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('movieImageFile');
    const preview = document.getElementById('movieImagePreview');
    const pasteTarget = document.querySelector('.image-paste-target');
    let previewObjectUrl = null;

    const updatePreview = () => {
      if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
      }
      const file = input.files[0];
      if (file) {
        previewObjectUrl = URL.createObjectURL(file);
        preview.src = previewObjectUrl;
        preview.hidden = false;
      }
    };

    input.addEventListener('change', updatePreview);
    pasteTarget.addEventListener('paste', function (event) {
      event.preventDefault();
      const items = Array.from(event.clipboardData?.items ?? []);
      const imageItem = items.find((item) => item.type.startsWith('image/'));
      const imageFile = imageItem?.getAsFile()
        || Array.from(event.clipboardData?.files ?? []).find((file) => file.type.startsWith('image/'));
      if (!imageFile) {
        return;
      }

      const extensions = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp' };
      const filename = imageFile.name || `pasted-poster.${extensions[imageFile.type] || 'png'}`;
      const transfer = new DataTransfer();
      transfer.items.add(new File([imageFile], filename, { type: imageFile.type }));
      input.files = transfer.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
</script>

<style>
  .topnav #navAddMovie::before {
    content: '+';
    margin-right: 6px;
    font-size: 1.15em;
    font-weight: 800;
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
</style>

<?php page_footer(); ?>
