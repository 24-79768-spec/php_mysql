<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Deleting is only allowed through the POST form on read.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('read.php');
}

if (!csrf_valid()) {
    flash_set('danger', 'Your session expired. Please try again.');
    redirect('read.php');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    flash_set('warning', 'That title could not be found.');
    redirect('read.php');
}

$stmt = $pdo->prepare('SELECT title FROM movies WHERE id = :id');
$stmt->execute([':id' => $id]);
$movie = $stmt->fetch();

if (!$movie) {
    flash_set('warning', 'That title is already gone from your list.');
    redirect('read.php');
}

$del = $pdo->prepare('DELETE FROM movies WHERE id = :id');
$del->execute([':id' => $id]);

flash_set('success', '"' . $movie['title'] . '" was removed from your list.');
redirect('read.php');
