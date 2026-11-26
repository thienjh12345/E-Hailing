<?php
/**
 * Shared UI building blocks used by both the admin console and the driver portal:
 * icons, the brand logo, avatars, and form fields.
 */

/** Inline SVG icons (stroke style) so pages work fully offline. */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'rides'     => '<path d="M5 17h14M6 17l1.5-6.5A2 2 0 0 1 9.45 9h5.1a2 2 0 0 1 1.95 1.5L18 17"/><path d="M4 17v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2M17 17v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2"/><circle cx="7.5" cy="14" r=".6"/><circle cx="16.5" cy="14" r=".6"/>',
        'driver'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 14.5V21M9.6 11.2 3.4 9.6M14.4 11.2l6.2-1.6"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'money'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'star'      => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'pin'       => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'flag'      => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'alert'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'home'      => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'id'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.5-1.5 1.6-2 3-2s2.5.5 3 2M15 10h3M15 13h3"/>',
        'lock'      => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'camera'    => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'power'     => '<path d="M12 3v9"/><path d="M5.6 7.6a8 8 0 1 0 12.8 0"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'sun'       =>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'chat'      => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/>',
        'navigate'  => '<path d="M3 11 21 3l-8 18-2-8z"/>',
        'wallet'    => '<path d="M20 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V5"/><path d="M16 14h.01"/>',
        'receipt'   => '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'inbox'     =>'<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6z"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Brand mark: a stylised red crab claw. */
function crab_logo(int $size = 32): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" aria-hidden="true">'
        . '<rect width="32" height="32" rx="9" fill="var(--brand)"/>'
        . '<path d="M9 21c0-5 3-9 8-9.5L15 9h5a5 5 0 0 1 3 9l-3-2.2a2 2 0 0 0-.6-3.6L18 12.5c-3 .7-5.6 3.6-5.6 8.5z" fill="#fff"/>'
        . '<circle cx="11" cy="23.5" r="1.6" fill="#fff"/><circle cx="16" cy="23.5" r="1.6" fill="#fff"/><circle cx="21" cy="23.5" r="1.6" fill="#fff"/>'
        . '</svg>';
}

/**
 * Round avatar: the profile photo when there is one, otherwise initials.
 * $extra adds classes, e.g. 'xl' for the big profile header version.
 */
function avatar(string $name, ?string $photo = null, string $extra = ''): string
{
    $cls = trim('avatar ' . $extra);
    if ($photo) {
        return '<span class="' . e($cls) . '"><img src="' . e(url($photo)) . '" alt=""></span>';
    }
    return '<span class="' . e($cls) . '">' . e(initials($name)) . '</span>';
}

/**
 * One labelled form field with its validation error.
 *
 * $opts: type (text|email|tel|date|number|password|select|textarea), options (for select),
 *        required, placeholder, help, autocomplete, attrs (raw extra attributes), class.
 */
function form_field(string $name, string $label, $value, array $errors = [], array $opts = []): string
{
    $type  = $opts['type'] ?? 'text';
    $id    = 'f_' . $name;
    $err   = $errors[$name] ?? null;
    $attrs = ' id="' . $id . '" name="' . e($name) . '"'
        . (!empty($opts['required']) ? ' required' : '')
        . (isset($opts['placeholder']) ? ' placeholder="' . e($opts['placeholder']) . '"' : '')
        . (isset($opts['autocomplete']) ? ' autocomplete="' . e($opts['autocomplete']) . '"' : '')
        . ($err ? ' aria-invalid="true" aria-describedby="' . $id . '_err"' : '')
        . (isset($opts['attrs']) ? ' ' . $opts['attrs'] : '');

    if ($type === 'select') {
        $control = '<select' . $attrs . '>';
        foreach ($opts['options'] ?? [] as $val => $text) {
            $control .= '<option value="' . e($val) . '"' . ((string)$val === (string)$value ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        $control .= '</select>';
    } elseif ($type === 'textarea') {
        $control = '<textarea' . $attrs . ' rows="3">' . e($value) . '</textarea>';
    } else {
        $valAttr = $type === 'password' ? '' : ' value="' . e($value) . '"';
        $control = '<input type="' . e($type) . '"' . $attrs . $valAttr . '>';
    }

    return '<label class="field' . ($err ? ' has-error' : '') . (isset($opts['class']) ? ' ' . e($opts['class']) : '') . '" for="' . $id . '">'
        . '<span>' . e($label) . (!empty($opts['required']) ? '' : ' <em class="optional">optional</em>') . '</span>'
        . $control
        . ($err ? '<small class="field-error" id="' . $id . '_err">' . e($err) . '</small>' : '')
        . (!$err && isset($opts['help']) ? '<small class="field-help">' . e($opts['help']) . '</small>' : '')
        . '</label>';
}

/* ---------------------------------------------------------------------------
 * Light / dark theme switch
 *  - By default pages follow the device setting (prefers-color-scheme).
 *  - Clicking the switch saves "light" or "dark" in localStorage ("crab-theme"),
 *    shared by every Crab page, and sets <html data-theme="..."> which the CSS obeys.
 * ------------------------------------------------------------------------- */

/** Put inside <head>: applies the saved theme before the page is drawn (no flash). */
function theme_head(): string
{
    return "<script>try{var t=localStorage.getItem('crab-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>";
}

/** The switch button. Pass 'floating' for the sign-in pages (top-right corner). */
function theme_toggle(string $extra = ''): string
{
    return '<button type="button" class="icon-btn theme-toggle ' . e($extra) . '" data-theme-toggle aria-label="Switch theme" title="Switch theme">'
        . '<span class="i-moon">' . icon('moon', 18) . '</span><span class="i-sun">' . icon('sun', 18) . '</span></button>';
}

/* ---------------------------------------------------------------------------
 * Settings section (Profile + Password) — same in every portal
 * ------------------------------------------------------------------------- */

/** Tab bar shown at the top of the Settings pages. $portal = 'admin' | 'driver' | 'passenger'. */
function settings_tabs(string $portal, string $active): string
{
    $tabs = [
        'profile'  => ['Profile',  "$portal/profile.php",  'user'],
        'password' => ['Password', "$portal/password.php", 'lock'],
    ];
    $h = '<nav class="tabs settings-tabs" aria-label="Settings">';
    foreach ($tabs as $key => [$label, $href, $ic]) {
        $h .= '<a href="' . e(url($href)) . '" class="tab' . ($key === $active ? ' active" aria-current="page' : '') . '">'
            . icon($ic, 15) . ' ' . e($label) . '</a>';
    }
    return $h . '</nav>';
}

/** Sidebar account block (photo + name) that opens Settings when clicked. */
function sidebar_me(string $portal, string $name, ?string $photo, string $subtitle, bool $active): string
{
    return '<a class="me' . ($active ? ' active' : '') . '" href="' . e(url("$portal/profile.php")) . '" title="Account settings">'
        . avatar($name, $photo)
        . '<span class="me-text"><strong>' . e($name) . '</strong><small>' . e($subtitle) . '</small></span>'
        . '<span class="me-gear">' . icon('settings', 16) . '</span></a>';
}
