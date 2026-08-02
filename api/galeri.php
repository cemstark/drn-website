<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Panel bu veriyi aynı sunucuda yazıyor; önbelleklenmiş bir kopya editöre
// eklediği kareyi göstermezdi.
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$liveFile = $root . '/data/galeri.json';
$seedFile = $root . '/data/galeri.seed.json';

// galeri.html'deki filtre butonlarıyla aynı sıra: sayımı sıfır olan butonu
// gizleyebilmek için etiketler de buradan gidiyor.
$categories = [
    'bakim' => 'Bakım',
    'kaporta' => 'Kaporta',
    'elektrik' => 'Elektrik',
    'pdr' => 'PDR',
    'diger' => 'Diğer',
];

function galeri_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function galeri_image($image): ?array
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
    galeri_json(['success' => false, 'message' => 'Bu uç yalnızca okuma içindir.'], 405);
}

$source = 'seed';
$file = $seedFile;
if (is_file($liveFile)) {
    $source = 'live';
    $file = $liveFile;
} elseif (is_file($liveFile . '.bak')) {
    $source = 'backup';
    $file = $liveFile . '.bak';
    error_log('galeri: data/galeri.json is missing, serving from galeri.json.bak');
}

if (!is_file($file)) {
    galeri_json([
        'success' => true,
        'source' => 'empty',
        'updated_at' => gmdate('c'),
        'categories' => [],
        'items' => [],
    ]);
}

$raw = file_get_contents($file);
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    error_log('galeri: unreadable store at ' . $file . ' (' . json_last_error_msg() . ')');
    galeri_json([
        'success' => false,
        'message' => 'Galeri şu an yüklenemedi.',
    ], 503);
}

$items = array_values(array_filter($data['items'], 'is_array'));

usort($items, function (array $a, array $b): int {
    $byOrder = (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
    return $byOrder !== 0
        ? $byOrder
        : strcmp((string)($a['createdAt'] ?? ''), (string)($b['createdAt'] ?? ''));
});

$counts = [];
$public = [];
foreach ($items as $item) {
    $image = galeri_image($item['image'] ?? null);
    if ($image === null) {
        // Görselsiz kare galeride bir işe yaramaz; sessizce atlanır.
        continue;
    }

    $category = trim((string)($item['category'] ?? ''));
    if (!isset($categories[$category])) {
        $category = 'diger';
    }
    $counts[$category] = ($counts[$category] ?? 0) + 1;

    $title = (string)($item['title'] ?? '');
    $caption = trim((string)($item['caption'] ?? ''));

    $public[] = [
        'id' => (string)($item['id'] ?? ''),
        'title' => $title,
        // Lightbox alt yazısı boşsa başlık kullanılır; karar burada verilir ki
        // sayfa tarafında ikinci bir kural olmasın.
        'caption' => $caption !== '' ? $caption : $title,
        'category' => $category,
        'image' => $image,
    ];
}

$categoryList = [];
foreach ($categories as $key => $label) {
    $categoryList[] = [
        'key' => $key,
        'label' => $label,
        'count' => (int)($counts[$key] ?? 0),
    ];
}

galeri_json([
    'success' => true,
    'source' => $source,
    'updated_at' => gmdate('c'),
    'categories' => $categoryList,
    'items' => $public,
]);
