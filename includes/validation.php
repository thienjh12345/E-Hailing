<?php
/**
 * Generic input rules shared by every part of Crab (drivers, passengers, admin).
 */

/** "0123456789", "+60 12-345 6789" → "012-3456789". Returns null if not a Malaysian mobile number. */
function normalize_phone(string $raw): ?string
{
    $d = preg_replace('/\D/', '', $raw);
    if (str_starts_with($d, '60')) $d = '0' . substr($d, 2);
    if (!preg_match('/^01\d{8,9}$/', $d)) return null;
    return substr($d, 0, 3) . '-' . substr($d, 3);
}

/** Person's name: letters, spaces and . ' / @ - (covers "bin", "a/l", "@" in Malaysian names). */
function validate_person_name(string $name): ?string
{
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) return 'Enter your full name (3–100 characters).';
    if (!preg_match("/^[\p{L} .'\/@-]+$/u", $name)) return 'Name can only contain letters, spaces and . \' / @ -';
    return null;
}

/** Password rule used at registration and when changing a password. */
function validate_new_password(string $pw, string $confirm): ?string
{
    if (strlen($pw) < 8) return 'Use at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Use a mix of letters and numbers.';
    if ($pw !== $confirm) return 'The two passwords don\'t match.';
    return null;
}
