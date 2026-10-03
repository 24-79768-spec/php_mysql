<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/image_upload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid movie ID.']);
    exit;
}

if (!csrf_valid()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh and try again.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM movies WHERE id = :id');
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Movie not found.']);
    exit;
}

try {
    $imagePath = store_movie_image($_FILES['image_file'] ?? [], true);
} catch (RuntimeException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
    exit;
}

$update = $pdo->prepare('UPDATE movies SET image_url = :image_url WHERE id = :id');
$update->execute([
    ':image_url' => $imagePath,
    ':id' => $id,
]);

echo json_encode([
    'success' => true,
    'message' => 'Poster image updated successfully.',
    'image_url' => $imagePath,
]);
