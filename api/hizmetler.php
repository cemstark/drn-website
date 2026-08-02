<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Panel bu veriyi aynı sunucuda yazıyor; önbelleklenmiş bir kopya editöre
// kaydettiği kartı eski hâliyle gösterirdi.
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$liveFile = $root . '/data/hizmetler.json';
$seedFile = $root . '/data/hizmetler.seed.json';

function hizmetler_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sürüm sorgusu burada ekleniyor ki önbellek kırma kuralı tek yerde kalsın:
 * .htaccess görselleri max-age=31536000, immutable ile sunuyor.
 */
function hizmetler_image($image): ?array
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
    hizmetler_json(['success' => false, 'message' => 'Bu uç yalnızca okuma içindir.'], 405);
}

// Panel ilk kaydında hizmetler.json'ı yazar; o zamana kadar seed tek kaynaktır.
// Yedek ikisinin arasında duruyor: live dosya kaybolduğunda doğrudan seed'e
// dönmek aylar önceki kartları sessizce yayınlamak olurdu.
$source = 'seed';
$file = $seedFile;
if (is_file($liveFile)) {
    $source = 'live';
    $file = $liveFile;
} elseif (is_file($liveFile . '.bak')) {
    $source = 'backup';
    $file = $liveFile . '.bak';
    error_log('hizmetler: data/hizmetler.json is missing, serving from hizmetler.json.bak');
}

if (!is_file($file)) {
    hizmetler_json([
        'success' => true,
        'source' => 'empty',
        'updated_at' => gmdate('c'),
        'services' => [],
    ]);
}

$raw = file_get_contents($file);
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

if (!is_array($data) || !isset($data['services']) || !is_array($data['services'])) {
    // Dosya yolu ve ayrıştırıcı mesajı sunucuya ait bilgidir.
    error_log('hizmetler: unreadable store at ' . $file . ' (' . json_last_error_msg() . ')');
    hizmetler_json([
        'success' => false,
        'message' => 'Hizmetler şu an yüklenemedi.',
    ], 503);
}

$services = array_values(array_filter($data['services'], 'is_array'));

usort($services, function (array $a, array $b): int {
    $byOrder = (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
    return $byOrder !== 0
        ? $byOrder
        : strcmp((string)($a['createdAt'] ?? ''), (string)($b['createdAt'] ?? ''));
});

$public = [];
foreach ($services as $service) {
    $images = [];
    foreach ((array)($service['images'] ?? []) as $image) {
        $hazir = hizmetler_image($image);
        if ($hazir !== null) {
            $images[] = $hazir;
        }
    }

    $features = [];
    foreach ((array)($service['features'] ?? []) as $feature) {
        $metin = trim((string)$feature);
        if ($metin !== '') {
            $features[] = $metin;
        }
    }

    // slug, order, createdAt ve updatedAt editoryal defter bilgisidir;
    // sayfanın onlara ihtiyacı yok, sunucuda kalırlar.
    $public[] = [
        'id' => (string)($service['id'] ?? ''),
        'title' => (string)($service['title'] ?? ''),
        'icon' => (string)($service['icon'] ?? ''),
        'summary' => (string)($service['summary'] ?? ''),
        'description' => (string)($service['description'] ?? ''),
        'features' => $features,
        'images' => $images,
    ];
}

hizmetler_json([
    'success' => true,
    'source' => $source,
    'updated_at' => gmdate('c'),
    'services' => $public,
]);
