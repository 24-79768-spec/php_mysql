<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$title  = '';
$genre  = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $genre = (string) ($_POST['genre'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($title === '') {
        $errors[] = 'Enter a title.';
    } elseif (mb_strlen($title) > 150) {
        $errors[] = 'The title must be 150 characters or fewer.';
    }
    if (!in_array($genre, GENRES, true)) {
        $errors[] = 'Choose a genre from the list.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO movies (title, genre, rating) VALUES (:title, :genre, NULL)');
        $stmt->execute([':title' => $title, ':genre' => $genre]);

        flash_set('success', '"' . $title . '" was added to your list.');
        redirect('read.php');
    }
}

page_header('Add title', 'add');
require __DIR__ . '/theme.php';
?>
<style>
  .topnav #navAddMovie::before {
    content: '+';
    margin-right: 6px;
    font-size: 1.15em;
    font-weight: 800;
  }
</style>

<h1 class="h2 mb-4">Add a title</h1>

<div class="panel p-4">
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label for="title" class="form-label">Title</label>
      <input type="text" id="title" name="title" class="form-control" maxlength="150"
             placeholder="Movie or series name" value="<?= h($title) ?>" required autofocus>
    </div>
    <div class="mb-4">
      <label for="genre" class="form-label">Genre</label>
      <select id="genre" name="genre" class="form-select" required>
        <option value="">Choose a genre</option>
        <?php foreach (GENRES as $g): ?>
          <option value="<?= h($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= h($g) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">You can rate it after you've watched it.</div>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-gold">Add to list</button>
      <a href="read.php" class="btn btn-outline-soft">Cancel</a>
    </div>
  </form>
</div>

<?php page_footer(); ?>
