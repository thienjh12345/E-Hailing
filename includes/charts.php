<?php
/**
 * Server-rendered SVG charts (no JS library needed).
 * Hover tooltips are wired up in assets/js/admin.js via data-tip attributes.
 */

/** Round an axis maximum up to a "nice" number and return evenly spaced ticks. */
function nice_ticks(float $max, int $count = 4): array
{
    if ($max <= 0) return [0, 1, 2, 3, 4];
    $raw  = $max / $count;
    $mag  = 10 ** floor(log10($raw));
    $norm = $raw / $mag;
    $step = ($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 2.5 ? 2.5 : ($norm <= 5 ? 5 : 10)))) * $mag;
    $ticks = [];
    for ($v = 0; $v <= $step * $count + 1e-9; $v += $step) $ticks[] = $v;
    while (end($ticks) < $max) $ticks[] = end($ticks) + $step;
    return $ticks;
}

/**
 * Vertical bar chart for a time series.
 *
 * @param array    $points  list of ['label' => 'Mon 3', 'full' => 'Monday, 3 Nov', 'value' => 12.5]
 * @param callable $fmt     formats a value for axis + tooltip
 * @param string   $title   accessible name
 */
function bar_chart(array $points, callable $fmt, string $title, ?callable $axisFmt = null): string
{
    $axisFmt = $axisFmt ?? $fmt;
    $W = 720; $H = 260;
    $padL = 56; $padR = 12; $padT = 22; $padB = 30;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;

    $values = array_column($points, 'value');
    $ticks  = nice_ticks(max($values ?: [0]));
    $top    = end($ticks);
    $n      = max(1, count($points));
    $slot   = $plotW / $n;
    $barW   = min(28, $slot * 0.62);
    $maxIdx = $values ? array_search(max($values), $values) : -1;

    $y = fn($v) => $padT + $plotH - ($top > 0 ? $v / $top * $plotH : 0);

    $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($title) . '">';

    // Gridlines + y-axis labels (recessive)
    foreach ($ticks as $t) {
        $ty = round($y($t), 1);
        $svg .= '<line class="grid' . ($t == 0 ? ' baseline' : '') . '" x1="' . $padL . '" x2="' . ($W - $padR) . '" y1="' . $ty . '" y2="' . $ty . '"/>';
        $svg .= '<text class="axis" x="' . ($padL - 8) . '" y="' . ($ty + 4) . '" text-anchor="end">' . e($axisFmt($t)) . '</text>';
    }

    foreach ($points as $i => $p) {
        $cx = $padL + $slot * $i + $slot / 2;
        $x0 = $cx - $barW / 2;
        $yTop = $y($p['value']);
        $h = $padT + $plotH - $yTop;
        $r = min(4, $h, $barW / 2);

        // Bar with 4px rounded top, square at the baseline
        if ($h > 0.5) {
            $base = $padT + $plotH;
            $d = sprintf('M%.1f %.1fV%.1fQ%.1f %.1f %.1f %.1fH%.1fQ%.1f %.1f %.1f %.1fV%.1fZ',
                $x0, $base, $yTop + $r, $x0, $yTop, $x0 + $r, $yTop,
                $x0 + $barW - $r, $x0 + $barW, $yTop, $x0 + $barW, $yTop + $r, $base);
            $svg .= '<path class="bar" d="' . $d . '"/>';
        }

        // Direct label only on the peak value
        if ($i === $maxIdx && $p['value'] > 0) {
            $svg .= '<text class="peak" x="' . round($cx, 1) . '" y="' . round($yTop - 7, 1) . '" text-anchor="middle">' . e($fmt($p['value'])) . '</text>';
        }

        // X labels: every other day so they never collide, plus the last one
        if ($n <= 8 || $i % 2 === ($n - 1) % 2) {
            $svg .= '<text class="axis" x="' . round($cx, 1) . '" y="' . ($H - 9) . '" text-anchor="middle">' . e($p['label']) . '</text>';
        }

        // Full-height invisible hit target, larger than the bar
        $svg .= '<rect class="hit" x="' . round($padL + $slot * $i, 1) . '" y="' . $padT . '" width="' . round($slot, 1) . '" height="' . $plotH . '"'
            . ' data-tip="' . e($p['full']) . '" data-val="' . e($fmt($p['value'])) . '"/>';
    }

    return $svg . '</svg>';
}

/**
 * Horizontal "share" bars (one hue). Each row: label, bar, value.
 * @param array $rows list of ['label' => 'Cash', 'value' => 12, 'note' => 'RM 120.00']
 */
function share_bars(array $rows): string
{
    $total = array_sum(array_column($rows, 'value')) ?: 1;
    $max   = max(array_column($rows, 'value') ?: [1]) ?: 1;
    $html  = '<ul class="share">';
    foreach ($rows as $r) {
        $pct = round($r['value'] / $total * 100);
        $w   = round($r['value'] / $max * 100, 1);
        $html .= '<li>'
            . '<div class="share-head"><span>' . e($r['label']) . '</span><strong>' . $pct . '%</strong></div>'
            . '<div class="share-track"><div class="share-fill" style="width:' . $w . '%"></div></div>'
            . '<small class="muted">' . e($r['note'] ?? (string)$r['value']) . '</small>'
            . '</li>';
    }
    return $html . '</ul>';
}
