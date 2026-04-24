<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=900');

$root = dirname(__DIR__);
$localConfig = $root . '/config/google-reviews.php';
$cacheFile = $root . '/data/google-reviews-cache.json';

$config = [
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

$limit = isset($_GET['limit']) ? max(1, min(20, (int)$_GET['limit'])) : $config['max_reviews'];
$config['max_reviews'] = max(1, min(50, (int)$config['max_reviews']));
$config['cache_ttl'] = max(300, (int)$config['cache_ttl']);

function reviews_json(array $payload, int $status = 200): void
{
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

try {
    $payload = reviews_fetch_google($config, $limit);
    reviews_write_cache($cacheFile, $payload);
    $payload['cached'] = false;
    $payload['stale'] = false;
    reviews_json($payload);
} catch (Throwable $e) {
    if ($cache) {
        $cache['cached'] = true;
        $cache['stale'] = true;
        $cache['warning'] = $e->getMessage();
        $cache['reviews'] = array_slice($cache['reviews'] ?? [], 0, $limit);
        reviews_json($cache);
    }

    reviews_json([
        'success' => false,
        'message' => 'Google yorumları alınamadı.',
        'detail' => $e->getMessage(),
    ], 503);
}
