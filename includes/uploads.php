<?php
/**
 * Safe image uploads (profile photos).
 *
 * Files are stored under /uploads (git-ignored) with random names. The folder gets an
 * .htaccess that stops scripts from running, so a disguised .php file can't execute.
 */

const UPLOAD_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const UPLOAD_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function uploads_root(): string
{
    return dirname(__DIR__) . '/uploads';
}

function ensure_upload_dir(string $subdir): string
{
    $root = uploads_root();
    $dir = $root . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ht = $root . '/.htaccess';
    if (!file_exists($ht)) {
        file_put_contents($ht, "# Uploaded files are data, never code.\nOptions -Indexes\n<FilesMatch \"\\.(php\\d?|phtml|phar|pl|py|cgi|sh|asp|aspx|jsp|htaccess)$\">\n    Require all denied\n</FilesMatch>\n");
    }
    return $dir;
}

/**
 * Validate and store an uploaded image.
 *
 * @param array  $file    one entry from $_FILES
 * @param string $subdir  e.g. 'drivers'
 * @return array{0: ?string, 1: ?string}  [relative path like "uploads/drivers/ab12.jpg", error message]
 */
function store_image_upload(array $file, string $subdir): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return [null, 'The photo is too large (max 2 MB).'];
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return [null, 'The upload failed. Please try again.'];
    if ($file['size'] > UPLOAD_MAX_BYTES) return [null, 'The photo is too large (max 2 MB).'];

    // Check the real content type, not the file name or the browser's claim.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return [null, 'Please upload a JPG, PNG or WebP image.'];
    }

    $dir  = ensure_upload_dir($subdir);
    $name = bin2hex(random_bytes(16)) . '.' . UPLOAD_IMAGE_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        return [null, 'Could not save the photo. Please try again.'];
    }
    return ["uploads/$subdir/$name", null];
}

/**
 * Validate and store a document upload: an image, or (if $allowPdf) a PDF.
 * Used for driver car photos and the PUSPAKOM certificate.
 *
 * @return array{0: ?array, 1: ?string}  [['path','mime','size','name'] or null, error message]
 */
function store_document_upload(array $file, string $subdir, bool $allowPdf, int $maxBytes): array
{
    $mb = round($maxBytes / 1048576) . ' MB';
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return [null, "The file is too large (max $mb)."];
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return [null, 'The upload failed. Please try again.'];
    if ($file['size'] > $maxBytes) return [null, "The file is too large (max $mb)."];

    // Check the real content, not the file name or the browser's claim.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (isset(UPLOAD_IMAGE_TYPES[$mime]) && @getimagesize($file['tmp_name']) !== false) {
        $ext = UPLOAD_IMAGE_TYPES[$mime];
    } elseif ($allowPdf && $mime === 'application/pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) === '%PDF-') {
        $ext = 'pdf';
    } else {
        return [null, $allowPdf ? 'Please upload a JPG, PNG, WebP image or a PDF.' : 'Please upload a JPG, PNG or WebP photo.'];
    }

    $dir  = ensure_upload_dir($subdir);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        return [null, 'Could not save the file. Please try again.'];
    }
    $original = mb_substr(preg_replace('/[^\p{L}\p{N} ._()-]/u', '_', basename((string)($file['name'] ?? 'file'))), 0, 200);
    return [['path' => "uploads/$subdir/$name", 'mime' => $mime, 'size' => (int)$file['size'], 'name' => $original ?: 'file.' . $ext], null];
}

/** Delete a previously stored upload. Ignores anything outside /uploads. */
function delete_upload(?string $relPath): void
{
    if (!$relPath || !preg_match('#^uploads/[a-z]+/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#', $relPath)) return;
    $full = dirname(__DIR__) . '/' . $relPath;
    if (is_file($full)) unlink($full);
}
