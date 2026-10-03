<?php
declare(strict_types=1);

const MOVIE_IMAGE_MAX_BYTES = 8 * 1024 * 1024;

function store_movie_image(array $upload, bool $required = false): ?string
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new RuntimeException('Choose or paste an image file.');
        }
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Images must be 8 MB or smaller.');
        }
        throw new RuntimeException('The image upload failed. Please try again.');
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    if ($size < 1 || $size > MOVIE_IMAGE_MAX_BYTES) {
        throw new RuntimeException('Images must be 8 MB or smaller.');
    }
    if (!is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('The uploaded image is invalid.');
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    if (!isset($extensions[$mimeType]) || @getimagesize($temporaryPath) === false) {
        throw new RuntimeException('Choose a JPG, PNG, GIF, or WebP image.');
    }

    $directory = __DIR__ . '/uploads/posters';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The server could not create the poster upload folder.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('The server could not save the poster image.');
    }

    return 'uploads/posters/' . $filename;
}