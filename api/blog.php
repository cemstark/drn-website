<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Unlike reviews.php, this data is written by the panel on this very server:
// a cached copy would show the editor a stale page right after saving.
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$liveFile = $root . '/data/blog.json';
$seedFile = $root . '/data/blog.seed.json';

function blog_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * IntlDateFormatter is not guaranteed on shared hosting, so the month names
 * are kept here. The frontend must never format a date a second time.
 */
function blog_date_text(string $isoDate): string
{
    $months = [
        1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık',
    ];

    $parts = explode('-', $isoDate);
    if (count($parts) !== 3) {
        return $isoDate;
    }

    $month = (int)$parts[1];
    if (!isset($months[$month])) {
        return $isoDate;
    }

    return (int)$parts[2] . ' ' . $months[$month] . ' ' . (int)$parts[0];
}

/**
 * The stored version is appended here so the cache-busting rule lives in one
 * place: .htaccess serves images with max-age=31536000, immutable.
 */
function blog_public_image($image): ?array
{
    if (!is_array($image)) {
        return null;
    }

    $src = trim((string)($image['src'] ?? ''));
    if ($src === '') {
        return null;
    }

    $version = trim((string)($image['version'] ?? ''));
    $suffix = $version !== '' ? '?v=' . rawurlencode($version) : '';
    $large = trim((string)($image['srcLarge'] ?? ''));

    return [
        'src' => $src . $suffix,
        'srcLarge' => $large !== '' ? $large . $suffix : null,
        'alt' => (string)($image['alt'] ?? ''),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    blog_json(['success' => false, 'message' => 'Bu uç yalnızca okuma içindir.'], 405);
}

// The panel writes blog.json on its first save; until then the seed is the only
// source. blog.json is git-ignored and excluded from deploy, so a deploy can
// never overwrite what the panel produced. The backup sits between the two for
// the same reason the panel prefers it: if the live file disappears, falling
// straight back to the seed would silently serve months-old posts.
$source = 'seed';
$file = $seedFile;
if (is_file($liveFile)) {
    $source = 'live';
    $file = $liveFile;
} elseif (is_file($liveFile . '.bak')) {
    $source = 'backup';
    $file = $liveFile . '.bak';
    error_log('blog: data/blog.json is missing, serving from blog.json.bak');
}

if (!is_file($file)) {
    blog_json([
        'success' => true,
        'source' => 'empty',
        'updated_at' => gmdate('c'),
        'categories' => [],
        'posts' => [],
    ]);
}

$raw = file_get_contents($file);
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

if (!is_array($data) || !isset($data['posts']) || !is_array($data['posts'])) {
    // The path and the parser message name a server file; they belong in the
    // log, not in the visitor's response.
    error_log('blog: unreadable store at ' . $file . ' (' . json_last_error_msg() . ')');
    blog_json([
        'success' => false,
        'message' => 'Blog yazıları şu an yüklenemedi.',
    ], 503);
}

$posts = array_values(array_filter($data['posts'], 'is_array'));

usort($posts, function (array $a, array $b): int {
    $byDate = strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
    if ($byDate !== 0) {
        return $byDate;
    }

    return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
});

$counts = [];
$public = [];
foreach ($posts as $post) {
    $category = trim((string)($post['category'] ?? ''));
    if ($category !== '') {
        $counts[$category] = ($counts[$category] ?? 0) + 1;
    }

    $date = (string)($post['date'] ?? '');

    // slug, createdAt and updatedAt are editorial bookkeeping; the public page
    // has no use for them, so they stay on the server.
    $public[] = [
        'id' => (string)($post['id'] ?? ''),
        'title' => (string)($post['title'] ?? ''),
        'category' => $category,
        'date' => $date,
        'dateText' => $date !== '' ? blog_date_text($date) : '',
        'readingMinutes' => max(1, (int)($post['readingMinutes'] ?? 1)),
        'excerpt' => (string)($post['excerpt'] ?? ''),
        'content' => (string)($post['content'] ?? ''),
        'image' => blog_public_image($post['image'] ?? null),
    ];
}

$categories = [];
foreach ($counts as $name => $count) {
    $categories[] = ['name' => (string)$name, 'count' => (int)$count];
}

usort($categories, function (array $a, array $b): int {
    if ($a['count'] !== $b['count']) {
        return $b['count'] - $a['count'];
    }

    return strcmp($a['name'], $b['name']);
});

blog_json([
    'success' => true,
    'source' => $source,
    'updated_at' => gmdate('c'),
    'categories' => $categories,
    'posts' => $public,
]);
