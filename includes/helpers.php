<?php
/**
 * Small helpers shared across the app.
 */

/** Escape output for HTML. Use on every piece of user/DB data you echo. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * URL for a CSS/JS/image file with a version stamp (the file's last-modified time),
 * e.g. /assets/css/admin.css?v=1764123456. When the file changes the URL changes,
 * so browsers fetch the new version instead of using a stale cached copy.
 */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function money($amount): string
{
    return CURRENCY . ' ' . number_format((float)$amount, 2);
}

function fmt_datetime(?string $dt, string $format = 'd M Y, h:i A'): string
{
    return $dt ? date($format, strtotime($dt)) : '—';
}

/** "5 min ago", "3 h ago", or a date for older items. */
function time_ago(?string $dt): string
{
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' h ago';
    if ($diff < 172800) return 'yesterday';
    return date('d M', strtotime($dt));
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Status pill. The CSS class maps each status to a colour. */
function badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

/* ---------- Flash messages (one-time notices after a redirect) ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF protection for every POST form ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Your session expired. Please go back and try again.');
    }
}

/* ---------- Pagination ---------- */

/**
 * Returns [page, perPage, offset, totalPages] for a total row count.
 */
function paginate(int $total, int $perPage = 15): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($pages, max(1, (int)($_GET['page'] ?? 1)));
    return [$page, $perPage, ($page - 1) * $perPage, $pages];
}

/** Current query string with some keys replaced, for filter/page links. */
function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '?';
}

/** Accept a posted "return to" query string only if it really is just a query string. */
function safe_qs($qs): string
{
    return (is_string($qs) && preg_match('/^\?[\w%=&.\-+]*$/', $qs)) ? $qs : '';
}

function render_pagination(int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= $page > 1
        ? '<a href="' . e(query_with(['page' => $page - 1])) . '">&lsaquo; Prev</a>'
        : '<span class="disabled">&lsaquo; Prev</span>';
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $html .= $i === $page
            ? '<span class="current">' . $i . '</span>'
            : '<a href="' . e(query_with(['page' => $i])) . '">' . $i . '</a>';
    }
    $html .= $page < $pages
        ? '<a href="' . e(query_with(['page' => $page + 1])) . '">Next &rsaquo;</a>'
        : '<span class="disabled">Next &rsaquo;</span>';
    return $html . '</nav>';
}
