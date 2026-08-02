<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=900');

$root = dirname(__DIR__);
$cacheFile = $root . '/data/google-reviews-cache.json';

// Deploy writes the config next to this file as well: on this host the
// config/ copy does not survive the FTP sync, while api/ does.
$configCandidates = [
    $root . '/config/google-reviews.php',
    __DIR__ . '/google-reviews-config.php',
];
$localConfig = $configCandidates[0];
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $localConfig = $candidate;
        break;
    }
}

/**
 * getenv() misses values set through SetEnv under PHP-FPM/LiteSpeed, where
 * they arrive in $_SERVER instead, so both are checked.
 */
function reviews_env(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? '';
    }

    return trim((string)$value);
}

$config = [
    'places_api_key' => reviews_env('GOOGLE_PLACES_API_KEY'),
    'place_id' => reviews_env('GOOGLE_PLACE_ID'),
    'referrer' => reviews_env('GOOGLE_PLACES_REFERRER'),
    'client_id' => getenv('GOOGLE_REVIEWS_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_REVIEWS_CLIENT_SECRET') ?: '',
    'refresh_token' => getenv('GOOGLE_REVIEWS_REFRESH_TOKEN') ?: '',
    'account_id' => getenv('GOOGLE_REVIEWS_ACCOUNT_ID') ?: '',
    'location_id' => getenv('GOOGLE_REVIEWS_LOCATION_ID') ?: '',
    'cache_ttl' => (int)(getenv('GOOGLE_REVIEWS_CACHE_TTL') ?: 21600),
    'max_reviews' => (int)(getenv('GOOGLE_REVIEWS_MAX_REVIEWS') ?: 12),
];

if (is_file($localConfig)) {
    $fileConfig = require $localConfig;
    if (is_array($fileConfig)) {
        $config = array_merge($config, $fileConfig);
    }
}

/**
 * Last resort for this host: separate config files are reported as uploaded
 * but never appear on the server, while this file itself syncs reliably. The
 * deploy substitutes these markers; in the repository they stay as markers,
 * so no credential is ever committed.
 */
$injected = [
    'places_api_key' => '__PLACES_API_KEY__',
    'place_id' => '__PLACE_ID__',
    'referrer' => '__PLACES_REFERRER__',
];
foreach ($injected as $injectedKey => $injectedValue) {
    if (substr($injectedValue, 0, 2) !== '__' && ($config[$injectedKey] ?? '') === '') {
        $config[$injectedKey] = $injectedValue;
    }
}

// Clamp the configured value first: $limit falls back to it, so a bad config
// value (string, negative) must never reach it unchecked.
$config['max_reviews'] = max(1, min(50, (int)$config['max_reviews']));
$config['cache_ttl'] = max(300, (int)$config['cache_ttl']);
$limit = isset($_GET['limit']) ? max(1, min(20, (int)$_GET['limit'])) : $config['max_reviews'];

function reviews_json(array $payload, int $status = 200): void
{
    // Errors must not sit in a shared cache for 15 minutes.
    if ($status >= 400) {
        header('Cache-Control: no-store');
    }

    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function reviews_read_cache(string $cacheFile): ?array
{
    if (!is_file($cacheFile)) {
        return null;
    }

    $raw = file_get_contents($cacheFile);
    if ($raw === false || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function reviews_write_cache(string $cacheFile, array $payload): void
{
    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(
        $cacheFile,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function reviews_http_json(string $url, array $options = []): array
{
    $method = $options['method'] ?? 'GET';
    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;
    $raw = false;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Google API request failed: ' . $error);
        }
        curl_close($ch);
    } else {
        $context = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 20,
            ],
        ];

        if ($body !== null) {
            $context['http']['content'] = $body;
        }

        $raw = file_get_contents($url, false, stream_context_create($context));
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
                    $status = (int)$match[1];
                    break;
                }
            }
        }
    }

    $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($json)) {
        $message = is_array($json) && isset($json['error'])
            ? (is_array($json['error']) ? ($json['error']['message'] ?? 'Google API error') : (string)$json['error'])
            : 'Google API request failed';
        throw new RuntimeException($message . ' (HTTP ' . $status . ')');
    }

    return $json;
}

function reviews_id_only(string $value, string $prefix): string
{
    $value = trim($value);
    $value = preg_replace('#^' . preg_quote($prefix, '#') . '/#', '', $value);
    return trim((string)$value, '/');
}

function reviews_access_token(array $config): string
{
    $body = http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => $config['refresh_token'],
        'grant_type' => 'refresh_token',
    ]);

    $data = reviews_http_json('https://oauth2.googleapis.com/token', [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
        ],
        'body' => $body,
    ]);

    if (empty($data['access_token'])) {
        throw new RuntimeException('Google OAuth access_token was not returned.');
    }

    return (string)$data['access_token'];
}

function reviews_rating_to_number($rating): int
{
    if (is_numeric($rating)) {
        return max(0, min(5, (int)$rating));
    }

    $map = [
        'ONE' => 1,
        'TWO' => 2,
        'THREE' => 3,
        'FOUR' => 4,
        'FIVE' => 5,
    ];

    return $map[(string)$rating] ?? 0;
}

function reviews_initial(string $name): string
{
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($name, 0, 1));
}

function reviews_relative_time(?string $time): string
{
    if (!$time) {
        return '';
    }

    try {
        $date = new DateTimeImmutable($time);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $diff = $date->diff($now);
    } catch (Throwable $e) {
        return '';
    }

    if ($diff->y > 0) {
        return $diff->y . ' yıl önce';
    }
    if ($diff->m > 0) {
        return $diff->m . ' ay önce';
    }
    if ($diff->d >= 7) {
        return floor($diff->d / 7) . ' hafta önce';
    }
    if ($diff->d > 0) {
        return $diff->d . ' gün önce';
    }
    return 'bugün';
}

function reviews_normalize(array $response, int $limit): array
{
    $reviews = [];
    foreach (($response['reviews'] ?? []) as $review) {
        if (!is_array($review)) {
            continue;
        }

        $comment = trim((string)($review['comment'] ?? ''));
        if ($comment === '') {
            continue;
        }

        $reviewer = is_array($review['reviewer'] ?? null) ? $review['reviewer'] : [];
        $name = trim((string)($reviewer['displayName'] ?? 'Google kullanıcısı'));
        $rating = reviews_rating_to_number($review['starRating'] ?? 0);
        $time = (string)($review['updateTime'] ?? $review['createTime'] ?? '');

        $reviews[] = [
            'name' => $name,
            'initial' => reviews_initial($name),
            'rating' => $rating,
            'text' => $comment,
            'relative_time' => reviews_relative_time($time),
            'profile_photo_url' => (string)($reviewer['profilePhotoUrl'] ?? ''),
            'review_id' => basename((string)($review['name'] ?? '')),
        ];

        if (count($reviews) >= $limit) {
            break;
        }
    }

    return [
        'success' => true,
        'source' => 'google_business_profile',
        'updated_at' => gmdate('c'),
        'average_rating' => isset($response['averageRating']) ? round((float)$response['averageRating'], 1) : null,
        'total_review_count' => isset($response['totalReviewCount']) ? (int)$response['totalReviewCount'] : null,
        'reviews' => $reviews,
    ];
}

function reviews_fetch_google(array $config, int $limit): array
{
    foreach (['client_id', 'client_secret', 'refresh_token', 'account_id', 'location_id'] as $key) {
        if (empty($config[$key])) {
            throw new RuntimeException('Missing Google Reviews configuration: ' . $key);
        }
    }

    $token = reviews_access_token($config);
    $parent = sprintf(
        'accounts/%s/locations/%s',
        rawurlencode(reviews_id_only((string)$config['account_id'], 'accounts')),
        rawurlencode(reviews_id_only((string)$config['location_id'], 'locations'))
    );
    $url = 'https://mybusiness.googleapis.com/v4/' . $parent . '/reviews?' . http_build_query([
        'pageSize' => max($limit, 10),
        'orderBy' => 'updateTime desc',
    ]);

    $response = reviews_http_json($url, [
        'headers' => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);

    return reviews_normalize($response, $limit);
}

/**
 * Places API (New) returns a different review shape than Business Profile v4,
 * so it is mapped onto the same payload the frontend already consumes.
 */
function reviews_normalize_places(array $response, int $limit): array
{
    $reviews = [];
    foreach (($response['reviews'] ?? []) as $review) {
        if (!is_array($review)) {
            continue;
        }

        $text = trim((string)($review['text']['text'] ?? $review['originalText']['text'] ?? ''));
        if ($text === '') {
            continue;
        }

        $author = is_array($review['authorAttribution'] ?? null) ? $review['authorAttribution'] : [];
        $name = trim((string)($author['displayName'] ?? '')) ?: 'Google kullanıcısı';

        // Google already localises this string when languageCode=tr is requested.
        $relative = trim((string)($review['relativePublishTimeDescription'] ?? ''));
        if ($relative === '') {
            $relative = reviews_relative_time((string)($review['publishTime'] ?? ''));
        }

        $reviews[] = [
            'name' => $name,
            'initial' => reviews_initial($name),
            'rating' => reviews_rating_to_number($review['rating'] ?? 0),
            'text' => $text,
            'relative_time' => $relative,
            'profile_photo_url' => (string)($author['photoUri'] ?? ''),
            'review_id' => basename((string)($review['name'] ?? '')),
        ];

        if (count($reviews) >= $limit) {
            break;
        }
    }

    return [
        'success' => true,
        'source' => 'google_places',
        'updated_at' => gmdate('c'),
        'average_rating' => isset($response['rating']) ? round((float)$response['rating'], 1) : null,
        'total_review_count' => isset($response['userRatingCount']) ? (int)$response['userRatingCount'] : null,
        'maps_uri' => (string)($response['googleMapsUri'] ?? ''),
        'reviews' => $reviews,
    ];
}

/**
 * Place Details caps the response at 5 reviews and offers no ordering control.
 * Business Profile (OAuth) is the only way past that ceiling.
 */
function reviews_fetch_places(array $config, int $limit): array
{
    foreach (['places_api_key', 'place_id'] as $key) {
        if (empty($config[$key])) {
            throw new RuntimeException('Missing Google Places configuration: ' . $key);
        }
    }

    $url = 'https://places.googleapis.com/v1/places/'
        . rawurlencode(trim((string)$config['place_id']))
        . '?' . http_build_query(['languageCode' => 'tr', 'regionCode' => 'TR']);

    $headers = [
        'X-Goog-Api-Key: ' . $config['places_api_key'],
        'X-Goog-FieldMask: rating,userRatingCount,googleMapsUri,reviews',
        'Accept: application/json',
    ];

    // The key is locked to an HTTP referrer. Server-side calls send none, so
    // Google rejects them with API_KEY_HTTP_REFERRER_BLOCKED unless we set it.
    if (!empty($config['referrer'])) {
        $headers[] = 'Referer: ' . $config['referrer'];
    }

    $response = reviews_http_json($url, ['headers' => $headers]);

    return reviews_normalize_places($response, $limit);
}

$cache = reviews_read_cache($cacheFile);
if ($cache && isset($cache['updated_at'])) {
    $age = time() - strtotime((string)$cache['updated_at']);
    if ($age >= 0 && $age < $config['cache_ttl']) {
        $cache['cached'] = true;
        $cache['stale'] = false;
        $cache['reviews'] = array_slice($cache['reviews'] ?? [], 0, $limit);
        reviews_json($cache);
    }
}

// Business Profile is preferred when fully configured: it has no 5-review ceiling.
$useBusinessProfile = !empty($config['refresh_token'])
    && !empty($config['account_id'])
    && !empty($config['location_id']);

try {
    $payload = $useBusinessProfile
        ? reviews_fetch_google($config, $limit)
        : reviews_fetch_places($config, $limit);
    reviews_write_cache($cacheFile, $payload);
    $payload['cached'] = false;
    $payload['stale'] = false;
    reviews_json($payload);
} catch (Throwable $e) {
    // Failure details name the config key or the referrer Google rejected,
    // so they belong in the server log, not in the visitor's response.
    $detail = $e->getMessage();
    error_log('google-reviews: ' . $detail);

    // A coarse class of failure is safe to expose and is the only way to
    // diagnose this endpoint without shell access to the host.
    if (stripos($detail, 'Missing Google') !== false) {
        $reason = 'config';
    } elseif (stripos($detail, 'SSL') !== false || stripos($detail, 'resolve') !== false
        || stripos($detail, 'timed out') !== false || stripos($detail, 'Connection') !== false) {
        $reason = 'network';
    } elseif (stripos($detail, 'HTTP 403') !== false) {
        $reason = 'denied';
    } elseif (stripos($detail, 'HTTP 4') !== false || stripos($detail, 'HTTP 5') !== false) {
        $reason = 'api';
    } else {
        $reason = 'unknown';
    }

    if ($cache) {
        $cache['cached'] = true;
        $cache['stale'] = true;
        $cache['reviews'] = array_slice($cache['reviews'] ?? [], 0, $limit);
        reviews_json($cache);
    }

    // TEMPORARY: shows which candidate paths exist so the manually uploaded
    // config can be located. Paths are already public in this repo; no value
    // is exposed. Remove once the config is found.
    $looked = [];
    foreach ($configCandidates as $candidate) {
        $looked[str_replace($root, '', $candidate)] = is_file($candidate) ? 'var' : 'yok';
    }

    reviews_json([
        'success' => false,
        'message' => 'Google yorumları alınamadı.',
        'reason' => $reason,
        'looked_in' => $looked,
    ], 503);
}
