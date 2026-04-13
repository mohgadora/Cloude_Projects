<?php

/**
 * Generate an article slug similar to the Node.js version.
 */
if (! function_exists('make_slug')) {
    function make_slug(string $title): string
    {
        // Preserve Arabic unicode letters, latin letters and digits
        $slug = mb_strtolower($title, 'UTF-8');
        // Replace whitespace and underscores with hyphen
        $slug = preg_replace('/[\s_]+/u', '-', $slug);
        // Remove any chars not in whitelist
        $slug = preg_replace('/[^\x{0600}-\x{06FF}A-Za-z0-9\-]/u', '', $slug);
        // Collapse multiple hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'article';
        }
        // Limit to 80 chars and append short base36 timestamp for uniqueness
        $slug = mb_substr($slug, 0, 80, 'UTF-8');

        return $slug . '-' . base_convert((string) (microtime(true) * 1000), 10, 36);
    }
}

/**
 * Format a MySQL datetime into Arabic locale date.
 */
if (! function_exists('ar_date')) {
    function ar_date(?string $datetime, string $format = 'long'): string
    {
        if (! $datetime) {
            return '';
        }
        $ts = strtotime($datetime) ?: time();

        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        $weekdays = [
            0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء',
            3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
        ];

        $d = (int) date('j', $ts);
        $m = (int) date('n', $ts);
        $y = (int) date('Y', $ts);
        $w = (int) date('w', $ts);

        if ($format === 'full') {
            return $weekdays[$w] . '، ' . $d . ' ' . $months[$m] . ' ' . $y;
        }
        return $d . ' ' . $months[$m] . ' ' . $y;
    }
}

/**
 * Escape output for HTML safely.
 */
if (! function_exists('e')) {
    function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('str_limit')) {
    function str_limit(?string $s, int $max = 120, string $suffix = '...'): string
    {
        $s = (string) $s;
        if (mb_strlen($s, 'UTF-8') <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max, 'UTF-8') . $suffix;
    }
}

if (! function_exists('base_site_url')) {
    function base_site_url(string $path = ''): string
    {
        $base = rtrim(base_url(), '/');
        if ($path === '') {
            return $base . '/';
        }
        return $base . '/' . ltrim($path, '/');
    }
}
