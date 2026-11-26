<?php
/**
 * Driver documents: 4 car photos (front, back, left, right) + PUSPAKOM inspection certificate.
 *
 *  - Required when registering (driver/register.php).
 *  - Can be replaced later from Licence & vehicle (driver/vehicle.php).
 *  - Shown read-only to admins on the driver profile (admin/driver.php).
 *
 * Files are stored in /uploads (random names, scripts blocked) — see includes/uploads.php.
 */

require_once __DIR__ . '/uploads.php';

/** type => [label, short help] */
const DRIVER_DOC_TYPES = [
    'car_front' => ['Front view',  'Whole front of the car, number plate readable'],
    'car_back'  => ['Back view',   'Whole back of the car, number plate readable'],
    'car_left'  => ['Left side',   'Full left side of the car'],
    'car_right' => ['Right side',  'Full right side of the car'],
    'puspakom'  => ['PUSPAKOM inspection certificate', 'Photo or PDF of your valid PUSPAKOM certificate'],
];
const CAR_PHOTO_TYPES = ['car_front', 'car_back', 'car_left', 'car_right'];
const DRIVER_DOC_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB (phone photos are often 2–4 MB)

/** Is this document allowed to be a PDF? (only the certificate) */
function doc_allows_pdf(string $type): bool
{
    return $type === 'puspakom';
}

/** A driver's documents keyed by type. */
function driver_documents(int $driverId): array
{
    $s = db()->prepare('SELECT * FROM driver_documents WHERE driver_id = ?');
    $s->execute([$driverId]);
    $out = [];
    foreach ($s->fetchAll() as $r) $out[$r['doc_type']] = $r;
    return $out;
}

/** Store one uploaded file for a document type. Returns [file info|null, error|null]. */
function store_driver_doc(array $file, string $type): array
{
    return store_document_upload($file, $type === 'puspakom' ? 'puspakom' : 'vehicles', doc_allows_pdf($type), DRIVER_DOC_MAX_BYTES);
}

/** Save (insert or replace) a document row. Deletes the old file after a replacement. */
function save_driver_doc(int $driverId, string $type, array $file): void
{
    $old = driver_documents($driverId)[$type]['file_path'] ?? null;
    db()->prepare('INSERT INTO driver_documents (driver_id, doc_type, file_path, original_name, mime_type, file_size, uploaded_at)
                   VALUES (?, ?, ?, ?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), original_name = VALUES(original_name),
                                           mime_type = VALUES(mime_type), file_size = VALUES(file_size), uploaded_at = NOW()')
        ->execute([$driverId, $type, $file['path'], $file['name'], $file['mime'], $file['size']]);
    if ($old && $old !== $file['path']) delete_upload($old);
}

/**
 * Registration: handle the 5 upload boxes.
 * Files that were uploaded successfully are kept in the session, so if the form comes back
 * with an error somewhere else the driver doesn't have to choose all 5 again.
 * "keep_<type>=0" (the Remove button) throws a kept file away.
 *
 * @return array{0: array, 1: array} [kept files by type, errors by "doc_<type>"]
 */
function registration_docs_process(): array
{
    $held = $_SESSION['reg_docs'] ?? [];
    $errors = [];
    foreach (array_keys(DRIVER_DOC_TYPES) as $type) {
        if (isset($held[$type]) && ($_POST["keep_$type"] ?? '1') === '0') {
            delete_upload($held[$type]['path']);
            unset($held[$type]);
        }
        [$file, $err] = store_driver_doc($_FILES["doc_$type"] ?? [], $type);
        if ($err) {
            $errors["doc_$type"] = $err;
        } elseif ($file) {
            if (isset($held[$type])) delete_upload($held[$type]['path']);   // replaced before submitting
            $held[$type] = $file;
        }
        if (!isset($held[$type]) && !isset($errors["doc_$type"])) {
            $errors["doc_$type"] = $type === 'puspakom' ? 'Upload your PUSPAKOM inspection certificate.' : 'Upload a photo of the ' . strtolower(DRIVER_DOC_TYPES[$type][0]) . ' of your car.';
        }
    }
    $_SESSION['reg_docs'] = $held;
    return [$held, $errors];
}

/** After the driver row exists: save the kept files as their documents. */
function registration_docs_save(int $driverId, array $held): void
{
    foreach ($held as $type => $file) save_driver_doc($driverId, $type, $file);
    unset($_SESSION['reg_docs']);
}

/**
 * One upload box (preview + choose / remove).
 *  $mode 'register': a kept file can be removed (Remove button), the box is required.
 *  $mode 'replace' : the current file stays until a new one is chosen (Undo button).
 *  $current: ['file_path'|'path', 'mime_type'|'mime', 'original_name'|'name'] or null
 */
function doc_slot(string $type, ?array $current, string $mode, ?string $error = null): string
{
    [$label, $help] = DRIVER_DOC_TYPES[$type];
    $id = 'doc_' . $type;
    $accept = 'image/jpeg,image/png,image/webp' . (doc_allows_pdf($type) ? ',application/pdf' : '');
    $path = $current['file_path'] ?? $current['path'] ?? null;
    $mime = $current['mime_type'] ?? $current['mime'] ?? '';
    $name = $current['original_name'] ?? $current['name'] ?? '';

    $preview = $path ? doc_preview_html($path, $mime, $name) : doc_placeholder_html($type);

    return '<div class="doc-slot' . ($type === 'puspakom' ? ' doc-wide' : '') . ($error ? ' has-error' : '') . '" data-doc-slot data-mode="' . e($mode) . '"'
        . ' data-max="' . DRIVER_DOC_MAX_BYTES . '" data-pdf="' . (doc_allows_pdf($type) ? '1' : '0') . '"' . ($path ? ' data-has-file' : '') . '>'
        . '<label class="doc-drop" for="' . $id . '"><span class="doc-preview" data-doc-preview>' . $preview . '</span></label>'
        . '<template data-doc-original>' . $preview . '</template>'
        . '<template data-doc-empty>' . doc_placeholder_html($type) . '</template>'
        . '<div class="doc-info"><strong>' . e($label) . ($mode === 'register' ? ' <em class="req">required</em>' : '') . '</strong>'
        . '<small class="muted" data-doc-status>' . e($path ? ($mode === 'register' ? 'Attached: ' . $name : 'On file: ' . $name) : $help) . '</small></div>'
        . '<input type="file" class="doc-input" id="' . $id . '" name="' . $id . '" accept="' . $accept . '" data-doc-input>'
        . '<div class="doc-actions">'
        . '<label class="btn btn-sm btn-outline" for="' . $id . '">' . ($path ? 'Replace' : 'Choose file') . '</label>'
        . '<button type="button" class="btn btn-sm btn-ghost" data-doc-remove' . ($mode === 'register' && $path ? '' : ' hidden') . '>' . ($mode === 'register' ? 'Remove' : 'Undo') . '</button>'
        . '</div>'
        . ($mode === 'register' ? '<input type="hidden" name="keep_' . $type . '" value="1" data-doc-keep>' : '')
        . ($error ? '<small class="field-error" data-doc-error>' . e($error) . '</small>' : '<small class="field-error" data-doc-error hidden></small>')
        . '</div>';
}

function doc_preview_html(string $path, string $mime, string $name): string
{
    if ($mime === 'application/pdf') {
        return '<span class="doc-pdf">' . icon('receipt', 28) . '<strong>PDF</strong><small>' . e(mb_strimwidth($name, 0, 40, '…')) . '</small></span>';
    }
    return '<img src="' . e(url($path)) . '" alt="">';
}

function doc_placeholder_html(string $type): string
{
    return '<span class="doc-empty">' . icon($type === 'puspakom' ? 'id' : 'camera', 26) . '<small>' . ($type === 'puspakom' ? 'Tap to add certificate' : 'Tap to add photo') . '</small></span>';
}
