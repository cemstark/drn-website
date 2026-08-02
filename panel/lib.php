<?php
declare(strict_types=1);

// Bu dosya bir kütüphanedir; doğrudan istendiğinde hiçbir şey yapmaz.
// panel/.htaccess de aynı işi yapar, o kural desteklenmezse bu devrededir.
if (!defined('PANEL_BOOT')) {
    http_response_code(404);
    exit;
}

define('PANEL_ROOT', dirname(__DIR__));
define('PANEL_DATA_DIR', PANEL_ROOT . '/data');
define('PANEL_ATTEMPTS_FILE', PANEL_DATA_DIR . '/panel-attempts.json');

// Yazı içeriğinde izin verilen etiketler. Listede olmayan etiket kaldırılır,
// içindeki metin korunur.
const PANEL_ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h4', 'a'];
const PANEL_MAX_UPLOAD_BYTES = 6291456; // 6 MB
// GD'nin bellek tüketimi piksel sayısıyla doğrusal artar; 4000 kenar paylaşımlı
// bir hostun 128M sınırında güvenle işlenebilecek üst sınır.
const PANEL_MAX_IMAGE_EDGE = 4000;
// Yazının tamamı blog sayfasında tek seferde iniyor; sınırsız içerik sayfayı
// fark ettirmeden ağırlaştırır.
const PANEL_MAX_CONTENT_CHARS = 50000;
// Tarih aralığı: sıralama metin karşılaştırmasına dayandığı için hatalı yıl
// yazımı listeyi kalıcı olarak bozar.
const PANEL_DATE_YEARS_BACK = 20;
const PANEL_DATE_YEARS_AHEAD = 2;

// Bir hizmet kartının slider'ında taşınabilecek en fazla görsel.
const PANEL_MAX_SERVICE_IMAGES = 6;
// Tek kaydetmede sunulan yeni görsel slotu sayısı.
const PANEL_UPLOAD_SLOTS = 4;
const PANEL_MAX_FEATURES = 8;

// Galeri kategorileri: galeri.html'deki filtre butonlarıyla birebir aynı olmalı.
// Panelden yeni kategori tanımlanmaz, her öğe bunlardan biriyle etiketlenir.
const PANEL_GALLERY_CATEGORIES = [
    'bakim' => 'Bakım',
    'kaporta' => 'Kaporta',
    'elektrik' => 'Elektrik',
    'pdr' => 'PDR',
    'diger' => 'Diğer',
];

// Hizmet kartı ikonları beyaz liste: serbest metin alınsaydı panele girilen
// değer doğrudan class özniteliğine yazılırdı. İlk dört blok bugün sitede
// kullanılan ikonlardır; listeden çıkarılırsa o kartlar ikonsuz kalır.
const PANEL_SERVICE_ICONS = [
    'fa-screwdriver-wrench' => 'Anahtar / bakım',
    'fa-car-burst' => 'Kaza / hasar',
    'fa-fire-flame-curved' => 'Boya fırını',
    'fa-bolt' => 'Elektrik / PDR',
    'fa-compress' => 'Atölye / alan',
    'fa-rotate' => 'Rot / balans',
    'fa-magnifying-glass-chart' => 'Ekspertiz',
    'fa-spray-can-sparkles' => 'Boya / temizlik',
    'fa-gears' => 'Mekanik',
    'fa-car-on' => 'Kaporta onarım',
    'fa-car-side' => 'Araç',
    'fa-gauge-high' => 'Gösterge',
    'fa-oil-can' => 'Yağ',
    'fa-temperature-low' => 'Klima',
    'fa-clipboard-check' => 'Kontrol listesi',
    'fa-shield-halved' => 'Güvence',
    'fa-wrench' => 'Genel tamir',
    'fa-truck-ramp-box' => 'Çekici',
    'fa-hand-sparkles' => 'Detaylı temizlik',
    'fa-building' => 'Tesis / ofis',
];

/* ---------------------------------------------------------------- Yapılandırma */

function panel_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'password_hash' => '',
        'session_name' => 'drnpanel',
        'max_attempts' => 5,
        'lockout_seconds' => 900,
        // Hareketsizlik toleransı. Uzun tutulursa açık bırakılmış bir tarayıcıda
        // panel saatlerce erişilebilir kalır; kısa tutulursa uzun bir yazı
        // yazarken oturum düşer ve form içeriği kaybolur.
        'idle_seconds' => 1800,
        // Girişten itibaren mutlak üst sınır: sürekli kullanımda bile oturum
        // sonsuza kadar uzamaz.
        'absolute_seconds' => 14400,
    ];

    $file = PANEL_ROOT . '/config/panel.php';
    $loaded = is_file($file) ? require $file : null;

    // Hash'siz bir config, config'in hiç olmamasıyla aynı: kurulum ekranı açılır.
    if (!is_array($loaded) || empty($loaded['password_hash'])) {
        $config = array_merge($defaults, ['__missing__' => true]);
        return $config;
    }

    $config = array_merge($defaults, $loaded);
    $config['__missing__'] = false;
    $config['max_attempts'] = max(1, (int)$config['max_attempts']);
    $config['lockout_seconds'] = max(60, (int)$config['lockout_seconds']);
    $config['idle_seconds'] = max(60, (int)$config['idle_seconds']);
    $config['absolute_seconds'] = max((int)$config['idle_seconds'], (int)$config['absolute_seconds']);

    return $config;
}

function panel_config_missing(): bool
{
    $config = panel_config();
    return !empty($config['__missing__']);
}

/* --------------------------------------------------------------------- Oturum */

function panel_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    // LiteSpeed/proxy TLS'i önde sonlandırır ve yalnızca bu başlığı iletir.
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function panel_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = panel_config();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/panel/',
        'httponly' => true,
        'samesite' => 'Strict',
        // Yerelde http ile test edilebilsin diye koşullu.
        'secure' => panel_is_https(),
    ]);
    session_name((string)$config['session_name']);
    session_start();
}

function panel_send_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function panel_is_authed(): bool
{
    if (empty($_SESSION['auth'])) {
        return false;
    }

    $config = panel_config();
    $lastSeen = (int)($_SESSION['last_seen'] ?? 0);
    $loginAt = (int)($_SESSION['login_at'] ?? 0);
    $now = time();

    $idleDoldu = $lastSeen > 0 && ($now - $lastSeen) > (int)$config['idle_seconds'];
    $omurDoldu = $loginAt > 0 && ($now - $loginAt) > (int)$config['absolute_seconds'];

    if ($idleDoldu || $omurDoldu) {
        panel_logout();
        // Yalnızca session_start() yetmez: kimlik hâlâ istekteki çerezden
        // geldiği için PHP yeni bir Set-Cookie basmaz, tarayıcı ise logout'un
        // silme başlığını uygular. Token eski kimliğin altına yazılır, sonraki
        // istekte okunamaz ve ilk parola denemesi her zaman düşerdi. Kimliği
        // burada zorla yenilemek taze bir çerez gönderilmesini sağlıyor.
        session_id(session_create_id());
        panel_boot_session();
        return false;
    }

    $_SESSION['last_seen'] = time();
    return true;
}

function panel_login(): void
{
    // Oturum sabitlemeye karşı: giriş anında kimlik yenilenir.
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['last_seen'] = time();
    $_SESSION['login_at'] = time();
    unset($_SESSION['csrf']);
}

function panel_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/panel/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

/* ----------------------------------------------------------------------- CSRF */

function panel_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf'];
}

function panel_csrf_check(): bool
{
    $sent = (string)($_POST['csrf'] ?? '');
    $known = (string)($_SESSION['csrf'] ?? '');

    return $known !== '' && $sent !== '' && hash_equals($known, $sent);
}

/* ------------------------------------------------------- Hatalı deneme sayacı */

function panel_attempt_key(): string
{
    // X-Forwarded-For istemci tarafından uydurulabilir; yalnızca soket
    // karşı tarafı güvenilirdir.
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/**
 * Sayaç dosyası kilit altında okunup yazılır. Kilitsiz oku-değiştir-yaz
 * yapılsaydı paralel gönderilen istekler aynı sayacı okuyup aynı değeri yazar,
 * deneme sınırı hiç dolmazdı; ayrıca yazma sürerken yapılan kilitsiz bir okuma
 * yarım JSON görüp bütün sayaçları sıfırlanmış sayardı.
 *
 * $mutator null ise dosya yalnızca okunur (paylaşımlı kilit).
 */
function panel_attempts_access(?callable $mutator): array
{
    if (!is_dir(PANEL_DATA_DIR) && !@mkdir(PANEL_DATA_DIR, 0755, true) && !is_dir(PANEL_DATA_DIR)) {
        return [];
    }

    // Okuma dalında da 'c+' gerekir: 'c' yalnızca yazma için açar ve o
    // tanıtıcıdan okumak EBADF ile düşerdi — kilit sessizce hiç uygulanmazdı.
    // 'c+' dosyayı kırpmaz, yoksa oluşturur.
    $handle = @fopen(PANEL_ATTEMPTS_FILE, 'c+');
    if ($handle === false) {
        return [];
    }

    if (!flock($handle, $mutator === null ? LOCK_SH : LOCK_EX)) {
        fclose($handle);
        return [];
    }

    $raw = stream_get_contents($handle);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data : [];

    if ($mutator !== null) {
        $data = $mutator($data);

        $cutoff = time() - 86400;
        foreach ($data as $key => $row) {
            if (!is_array($row) || (int)($row['seen_at'] ?? 0) < $cutoff) {
                unset($data[$key]);
            }
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES));
        fflush($handle);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    return $data;
}

function panel_lockout_remaining(): int
{
    $row = panel_attempts_access(null)[panel_attempt_key()] ?? null;
    if (!is_array($row)) {
        return 0;
    }

    $until = (int)($row['locked_until'] ?? 0);
    return $until > time() ? $until - time() : 0;
}

/**
 * Bir denemeyi sayaca işler. Kurulum ekranındaki hash üretimi de buraya
 * bağlıdır: orada da kimlik doğrulaması olmadan CPU harcanıyor.
 */
function panel_attempt_failed(): void
{
    $config = panel_config();
    $key = panel_attempt_key();

    panel_attempts_access(function (array $data) use ($key, $config): array {
        $row = is_array($data[$key] ?? null) ? $data[$key] : ['count' => 0];
        $row['count'] = (int)($row['count'] ?? 0) + 1;
        $row['seen_at'] = time();

        if ($row['count'] >= (int)$config['max_attempts']) {
            $row['locked_until'] = time() + (int)$config['lockout_seconds'];
            $row['count'] = 0;
        }

        $data[$key] = $row;
        return $data;
    });

    // Deneme hızını düşürür; parola doğrulama süresini de gizler.
    usleep(300000);
}

function panel_attempt_succeeded(): void
{
    $key = panel_attempt_key();

    panel_attempts_access(function (array $data) use ($key): array {
        unset($data[$key]);
        return $data;
    });
}

/* ------------------------------------------------------------------ Veri deposu */

/**
 * Üç içerik türü (blog, hizmetler, galeri) aynı depolama mantığını paylaşır:
 * kilitli oku-değiştir-yaz, live → .bak → seed zinciri, tam yazım doğrulaması,
 * atomik rename. Aralarındaki tek fark dosya adları, diskteki dizi anahtarı ve
 * yükleme dizinidir; bu fonksiyon o farkı tek yerde toplar.
 *
 * 'key' diskteki dizi anahtarıdır ve DEĞİŞTİRİLEMEZ: data/blog.json'ı
 * api/blog.php "posts" bekleyerek okuyor. Kod içinde her yerde 'records'
 * kullanılır, yazarken bu anahtara geri çevrilir — böylece api/blog.php'ye
 * hiç dokunmadan üç bölüm aynı fonksiyonları paylaşabiliyor.
 */
function panel_store(string $name): array
{
    $tanimlar = [
        'blog' => ['Blog', 'Blog verisi', 'posts', 'blog', 'images/blog/uploads'],
        'hizmetler' => ['Hizmetler', 'Hizmet verisi', 'services', 'hizmetler', 'images/hizmetler/uploads'],
        'galeri' => ['Galeri', 'Galeri verisi', 'items', 'galeri', 'images/galeri/uploads'],
    ];

    if (!isset($tanimlar[$name])) {
        // Bölüm adı URL'den geliyor; beyaz liste dışında hiçbir şey dosya
        // yoluna dönüşmemeli.
        throw new RuntimeException('Bilinmeyen bölüm: ' . $name);
    }

    list($label, $dataLabel, $key, $file, $uploadUrl) = $tanimlar[$name];

    return [
        'name' => $name,
        'label' => $label,
        'dataLabel' => $dataLabel,
        'key' => $key,
        'live' => PANEL_DATA_DIR . '/' . $file . '.json',
        'seed' => PANEL_DATA_DIR . '/' . $file . '.seed.json',
        'lock' => PANEL_DATA_DIR . '/' . $file . '.lock',
        'uploadDir' => PANEL_ROOT . '/' . $uploadUrl,
        'uploadUrl' => $uploadUrl,
    ];
}

function panel_store_names(): array
{
    return ['blog', 'hizmetler', 'galeri'];
}

/**
 * Okunacak dosyayı ve kaynağını verir. Live dosya yalnızca sunucuda oluşur ve
 * deploy'da hariç tutulur. Kaybolduğunda (kota temizliği, hosting hatası)
 * doğrudan seed'e dönmek editöre "kayıtlarım duruyor" izlenimi verir ve ilk
 * kaydetmede aradaki bütün kayıtlar kalıcı olarak kaybolurdu; bu yüzden önce
 * yedeğe bakılır ve kaynak çağırana bildirilir.
 */
function panel_store_file(array $store): array
{
    if (is_file($store['live'])) {
        return [$store['live'], 'live'];
    }

    if (is_file($store['live'] . '.bak')) {
        return [$store['live'] . '.bak', 'backup'];
    }

    return [$store['seed'], 'seed'];
}

function panel_store_read(array $store): array
{
    list($file, $source) = panel_store_file($store);

    if (!is_file($file)) {
        return ['version' => 1, 'records' => [], 'source' => 'empty'];
    }

    $raw = file_get_contents($file);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

    if (!is_array($data) || !isset($data[$store['key']]) || !is_array($data[$store['key']])) {
        // Dosya yolu ve ayrıştırıcı mesajı sunucuya ait bilgidir; ekrana değil
        // günlüğe gider (api/reviews.php'deki aynı ayrım).
        error_log('panel: unreadable store at ' . $file . ' (' . json_last_error_msg() . ')');
        throw new RuntimeException($store['dataLabel'] . ' çözümlenemedi. Ayrıntı sunucu hata günlüğünde.');
    }

    return [
        'version' => (int)($data['version'] ?? 1),
        'records' => array_values(array_filter($data[$store['key']], 'is_array')),
        'source' => $source,
    ];
}

function panel_store_write(array $store, array $data): void
{
    // Diske yalnızca version + store'un kendi anahtarı gider; 'source' ve
    // 'records' okuma tarafının iç gösterimidir.
    $json = json_encode(
        [
            'version' => (int)($data['version'] ?? 1),
            $store['key'] => array_values(array_filter($data['records'] ?? [], 'is_array')),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    // Kodlama hedef dosyaya dokunulmadan ÖNCE başarısız olmalı; yarım yazılmış
    // bir JSON tüm kayıtları kaybettirir.
    if ($json === false) {
        throw new RuntimeException($store['dataLabel'] . ' JSON olarak kodlanamadı: ' . json_last_error_msg());
    }

    // Önce geçici dosya yazılır ve TAM yazıldığı doğrulanır. Disk dolduğunda
    // file_put_contents false değil, yazılan bayt sayısını döndürür; bu kontrol
    // olmadan yarım bir dosya geçerli sayılıp rename ile verinin üzerine geçerdi.
    $tmp = $store['live'] . '.tmp.' . bin2hex(random_bytes(4));
    $written = file_put_contents($tmp, $json);
    if ($written === false || $written !== strlen($json)) {
        @unlink($tmp);
        throw new RuntimeException($store['dataLabel'] . ' diske tam yazılamadı (disk dolu olabilir).');
    }

    // Yedek ancak yeni içerik sağlam yazıldıktan sonra alınır. Önce alınsaydı
    // dolu diskte hem dosya hem yedeği aynı istekte bozulurdu.
    if (is_file($store['live'])) {
        $backup = $store['live'] . '.bak';
        if (!@copy($store['live'], $backup)) {
            // Yarım kalmış bir yedek, yedeksizlikten daha tehlikelidir.
            @unlink($backup);
            error_log('panel: ' . basename($backup) . ' could not be written');
        }
    }

    if (!@rename($tmp, $store['live'])) {
        // Windows hedef dosya varken rename'i reddedebilir. Bu yol atomik
        // değil, o yüzden gerçekleştiğinde loglanıyor.
        $inPlace = file_put_contents($store['live'], $json, LOCK_EX);
        if ($inPlace === false || $inPlace !== strlen($json)) {
            // Sağlam geçici dosya burada hâlâ duruyor: elle kurtarılabilsin
            // diye bilerek silinmiyor.
            throw new RuntimeException(
                $store['dataLabel'] . ' kaydedilemedi. Sunucuda ' . basename($tmp) . ' dosyası kurtarma için bırakıldı.'
            );
        }

        @unlink($tmp);
        error_log('panel: atomic rename failed, wrote ' . basename($store['live']) . ' in place');
    }
}

/**
 * Oku-değiştir-yaz döngüsünün tamamı kilit altında çalışır. Yalnızca yazarken
 * kilitlemek eşzamanlı iki kaydetmede güncelleme kaybına yol açardı. Kilit
 * dosyası store başına ayrıdır: galeri kaydı hizmet kaydını bloklamaz.
 */
function panel_store_update(array $store, callable $mutator): array
{
    if (!is_dir(PANEL_DATA_DIR) && !mkdir(PANEL_DATA_DIR, 0755, true) && !is_dir(PANEL_DATA_DIR)) {
        throw new RuntimeException('data/ klasörü oluşturulamadı.');
    }

    $lock = fopen($store['lock'], 'c');
    if ($lock === false) {
        throw new RuntimeException('Kilit dosyası açılamadı.');
    }

    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('Kilit alınamadı, lütfen tekrar deneyin.');
    }

    try {
        $data = $mutator(panel_store_read($store));
        panel_store_write($store, $data);
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function panel_find_record(array $records, string $id): ?array
{
    foreach ($records as $record) {
        if ((string)($record['id'] ?? '') === $id) {
            return $record;
        }
    }

    return null;
}

/** Blog: tarihe göre yeniden eskiye. */
function panel_sort_posts(array $posts): array
{
    usort($posts, function (array $a, array $b): int {
        $byDate = strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
        return $byDate !== 0
            ? $byDate
            : strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
    });

    return $posts;
}

/** Hizmetler ve galeri: elle verilen sıraya göre, eşitlikte eskiden yeniye. */
function panel_sort_by_order(array $records): array
{
    usort($records, function (array $a, array $b): int {
        $byOrder = (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
        return $byOrder !== 0
            ? $byOrder
            : strcmp((string)($a['createdAt'] ?? ''), (string)($b['createdAt'] ?? ''));
    });

    return $records;
}

/* ------------------------------------------------------------- Metin yardımcıları */

function panel_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Metin geçerli UTF-8 mi? Windows-1254 bir kaynaktan yapıştırılan metin buradan
 * geçemez; erken yakalanmazsa json_encode kaydı reddeder ve editör nedenini
 * anlamadığı bir hata görür.
 */
function panel_is_valid_utf8(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($value, 'UTF-8');
    }

    return preg_match('//u', $value) === 1;
}

function panel_plain(string $value, int $maxLength): string
{
    $value = strip_tags($value);
    // Satır sonları dışındaki kontrol karakterleri temizlenir.
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    // preg_replace geçersiz UTF-8'de null döner; /u olmadan yeniden denenmezse
    // editörün yazdığı metin sessizce boşalırdı.
    if ($cleaned === null) {
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }

    $value = trim((string)$cleaned);

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function panel_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function panel_slug(string $value): string
{
    $map = [
        'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
    ];

    $value = strtr($value, $map);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
    $value = trim((string)$value, '-');

    return $value === '' ? 'yazi' : substr($value, 0, 60);
}

function panel_reading_minutes(string $content): int
{
    $text = trim(strip_tags($content));
    if ($text === '') {
        return 1;
    }

    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $count = is_array($words) ? count($words) : 0;

    return max(1, min(60, (int)ceil($count / 200)));
}

function panel_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return false;
    }

    // Sıralama tarihleri metin olarak karşılaştırdığı için "9999-12-31" gibi
    // bir yazım hatası yazıyı listenin başına kalıcı olarak çivilerdi.
    $year = (int)$date->format('Y');
    $current = (int)gmdate('Y');

    return $year >= ($current - PANEL_DATE_YEARS_BACK) && $year <= ($current + PANEL_DATE_YEARS_AHEAD);
}

function panel_date_min(): string
{
    return ((int)gmdate('Y') - PANEL_DATE_YEARS_BACK) . '-01-01';
}

function panel_date_max(): string
{
    return ((int)gmdate('Y') + PANEL_DATE_YEARS_AHEAD) . '-12-31';
}

function panel_date_display(string $isoDate): string
{
    $date = DateTime::createFromFormat('!Y-m-d', $isoDate);

    return $date === false ? $isoDate : $date->format('d.m.Y');
}

/* ------------------------------------------------------------- İçerik temizleme */

/**
 * Editörün paragrafları elle işaretlemesi gerekmesin diye düz yazıyı HTML'e
 * çevirir: boş satır yeni paragraf, tek satır sonu <br> olur. libxml boş
 * satırları kendiliğinden paragrafa çevirmediği için, bu adım olmadan bütün
 * yazı tek bir <p> içinde birleşip sitede tek blok olarak görünürdü.
 *
 * İçerikte blok etiketi varsa yazar HTML yazmış demektir ve metne dokunulmaz.
 */
function panel_autoformat(string $content): string
{
    if (trim($content) === '') {
        return '';
    }

    if (preg_match('#<\s*(p|ul|ol|h4|li)\b#i', $content) === 1) {
        return $content;
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
    $blocks = preg_split('/\n[ \t]*\n+/', trim($normalized));
    if (!is_array($blocks)) {
        return $content;
    }

    $out = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block !== '') {
            $out .= '<p>' . str_replace("\n", '<br>', $block) . '</p>';
        }
    }

    return $out === '' ? $content : $out;
}

/**
 * Yazı içeriği HTML kabul ettiği için beyaz listeyle temizlenir. DOM eklentisi
 * yoksa kayıt reddedilir: zayıf bir regex temizliğiyle XSS riski almaktansa
 * kapalı başarısız olmak tercih edildi.
 */
function panel_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('Sunucuda DOM eklentisi yok: içerik güvenli biçimde temizlenemiyor.');
    }

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    // Bu meta olmadan libxml baytları UTF-8 saymıyor ve Türkçe karakterler bozuluyor.
    // LIBXML_HTML_NOIMPLIED bilerek kullanılmıyor: o bayrakla libxml tek bir kök
    // düğüm kabul edip geri kalan her şeyi sessizce atıyor (eklenen meta ilk
    // düğüm olduğu için içeriğin tamamı kayboluyordu).
    $loaded = $doc->loadHTML(
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html,
        LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    // Eklenen meta head'e taşınır; gövde yalnızca yazının kendi içeriğini tutar.
    $body = $loaded ? $doc->getElementsByTagName('body')->item(0) : null;
    if ($body === null) {
        throw new RuntimeException('İçerik çözümlenemedi.');
    }

    panel_clean_children($body);

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }

    return trim($out);
}

function panel_clean_children(DOMNode $node): void
{
    // Döngü düğüm ekleyip sildiği için canlı NodeList yerine kopya üzerinde gezilir.
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            continue;
        }

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            // Yorum, CDATA, işlem yönergesi: tamamen atılır.
            $node->removeChild($child);
            continue;
        }

        $name = strtolower($child->nodeName);

        if (in_array($name, PANEL_ALLOWED_TAGS, true)) {
            panel_clean_attributes($child, $name);
            panel_clean_children($child);

            // İçeriği boşalmış eleman kaldırılır: adsız bir <a> ekran okuyucuda
            // boş bir sekme durağına dönüşür, izin verilmeyen bir etiketin
            // silinmesinden arta kalan boş <p> ise sayfada ölü boşluk bırakır.
            if (in_array($name, ['a', 'p', 'h4', 'li', 'ul', 'ol'], true)
                && trim($child->textContent) === '') {
                $node->removeChild($child);
            }
            continue;
        }

        // script/style'ın metni de zararlı; gerisinde metin korunur.
        if ($name === 'script' || $name === 'style') {
            $node->removeChild($child);
            continue;
        }

        panel_clean_children($child);
        while ($child->firstChild !== null) {
            $node->insertBefore($child->firstChild, $child);
        }
        $node->removeChild($child);
    }
}

function panel_clean_attributes(DOMElement $element, string $name): void
{
    $href = $name === 'a' ? trim((string)$element->getAttribute('href')) : '';

    // Öznitelikler istisnasız silinir: onclick, style, srcset, hepsi.
    foreach (iterator_to_array($element->attributes) as $attribute) {
        $element->removeAttribute($attribute->nodeName);
    }

    // `/(?!/)` protokolsüz `//baska-site` biçimini eler: o adres iç bağlantı
    // gibi görünür ama dış siteye gider, target/rel almaz ve referrer sızdırır.
    if ($name !== 'a' || !preg_match('#^(https?://|/(?!/)|mailto:)#i', $href)) {
        // javascript:, data:, vbscript: buraya düşer ve href geri yazılmaz.
        return;
    }

    $element->setAttribute('href', $href);
    if (preg_match('#^https?://#i', $href)) {
        $element->setAttribute('target', '_blank');
        $element->setAttribute('rel', 'noopener noreferrer');
    }
}

/* --------------------------------------------------------------- Görsel yükleme */

function panel_can_make_webp(): bool
{
    return function_exists('imagewebp')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled');
}

/**
 * GD görüntüyü piksel başına 4 bayt tutar: 4000x3000 bir fotoğraf 48 MB eder ve
 * memory_limit'i aşınca oluşan fatal error catch ile yakalanamaz — editör boş
 * bir sayfa görürdü. Yer yoksa dönüştürme atlanır, dosya olduğu gibi saklanır.
 */
function panel_memory_allows(int $width, int $height): bool
{
    $limit = trim((string)ini_get('memory_limit'));
    if ($limit === '' || $limit === '-1') {
        return true;
    }

    $bytes = (float)$limit;
    $unit = strtoupper(substr($limit, -1));
    if ($unit === 'G') {
        $bytes *= 1073741824;
    } elseif ($unit === 'M') {
        $bytes *= 1048576;
    } elseif ($unit === 'K') {
        $bytes *= 1024;
    }

    // Kaynak görüntü + en büyük hedef tuval + %25 pay.
    $needed = (($width * $height * 4) + (1400 * 1400 * 4)) * 1.25;

    return ($bytes - memory_get_usage(true)) > $needed;
}

function panel_ensure_upload_dir(array $store): void
{
    $dir = $store['uploadDir'];
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }

    // Depodaki kopya sunucuya ulaşmadıysa koruma yine de yerinde olsun.
    $guard = $dir . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents(
            $guard,
            "<FilesMatch \"\\.(?i:php|phtml|phps|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n"
        );
    }
}

/**
 * $_FILES[$field] tek dosya için düz bir dizi, çoklu (name="x[]") için sütun
 * sütun dizilmiş bir dizi verir. Her iki biçimi de tek-dosya dizilerinden oluşan
 * bir listeye çevirir; böylece çoklu görsel de tek görselle aynı doğrulama
 * yolundan geçer. Seçilmemiş slotlar (UPLOAD_ERR_NO_FILE) atılır.
 */
function panel_upload_files(string $field): array
{
    $raw = $_FILES[$field] ?? null;
    if (!is_array($raw) || !isset($raw['error'])) {
        return [];
    }

    if (!is_array($raw['error'])) {
        return (int)$raw['error'] === UPLOAD_ERR_NO_FILE ? [] : [$raw];
    }

    $files = [];
    foreach (array_keys($raw['error']) as $i) {
        if ((int)$raw['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $raw['name'][$i] ?? '',
            'type' => $raw['type'][$i] ?? '',
            'tmp_name' => $raw['tmp_name'][$i] ?? '',
            'error' => $raw['error'][$i],
            'size' => $raw['size'][$i] ?? 0,
        ];
    }

    return $files;
}

function panel_load_image(string $path, int $type)
{
    if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        return @imagecreatefromjpeg($path);
    }
    if ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        return @imagecreatefrompng($path);
    }
    if ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }

    return false;
}

function panel_resize_to_webp($source, int $srcW, int $srcH, int $targetW, string $path): bool
{
    // Büyütme yapılmaz: 600 piksellik bir kaynak 1400'e çıkarılınca yalnızca bulanıklaşır.
    $width = min($targetW, $srcW);
    $height = max(1, (int)round($srcH * ($width / $srcW)));

    $canvas = imagecreatetruecolor($width, $height);
    if ($canvas === false) {
        return false;
    }

    // PNG şeffaflığı korunsun.
    if (function_exists('imagealphablending') && function_exists('imagesavealpha')) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
    }

    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $srcW, $srcH);
    $ok = @imagewebp($canvas, $path, 82);
    imagedestroy($canvas);

    // Başarısız bir imagewebp yarım dosya bırakabilir; hiçbir kayıt ona işaret
    // etmeyeceği için diskte sonsuza kadar kalırdı.
    if (!$ok && is_file($path)) {
        @unlink($path);
    }

    return (bool)$ok;
}

/**
 * Yüklenen dosyanın adı ve türü istemciden gelen değerlerle değil, sunucuda
 * üretilen slug ve dosyanın kendi başlığıyla belirlenir.
 */
function panel_handle_upload(array $store, array $file, string $slug): array
{
    if (!isset($file['error'])) {
        throw new RuntimeException('Görsel alınamadı.');
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Görsel çok büyük.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Görsel yüklenemedi.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Görsel doğrulanamadı.');
    }

    if ((int)($file['size'] ?? 0) > PANEL_MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Görsel 6 MB sınırını aşıyor.');
    }

    $info = @getimagesize($tmp);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!is_array($info) || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Yalnızca JPG, PNG veya WEBP yükleyebilirsiniz.');
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string)finfo_file($finfo, $tmp) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Dosya türü doğrulanamadı.');
        }
    }

    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width < 200 || $height < 200 || $width > PANEL_MAX_IMAGE_EDGE || $height > PANEL_MAX_IMAGE_EDGE) {
        throw new RuntimeException(
            'Görsel kenarları 200 ile ' . PANEL_MAX_IMAGE_EDGE . ' piksel arasında olmalı.'
        );
    }

    panel_ensure_upload_dir($store);

    // Slug yalnızca [a-z0-9-] içerir; ada rastgele son ek eklenerek eski
    // dosyaların üzerine yazılması da engellenir.
    $base = panel_slug($slug) . '-' . bin2hex(random_bytes(4));
    $version = gmdate('Ymd');

    if (panel_can_make_webp() && panel_memory_allows($width, $height)) {
        $source = panel_load_image($tmp, (int)$info[2]);
        if ($source !== false) {
            $smallPath = $store['uploadDir'] . '/' . $base . '-800.webp';
            if (panel_resize_to_webp($source, $width, $height, 800, $smallPath)) {
                $image = [
                    'src' => $store['uploadUrl'] . '/' . $base . '-800.webp',
                    'srcLarge' => null,
                    'version' => $version,
                ];

                if ($width > 800) {
                    $largePath = $store['uploadDir'] . '/' . $base . '-1400.webp';
                    if (panel_resize_to_webp($source, $width, $height, 1400, $largePath)) {
                        $image['srcLarge'] = $store['uploadUrl'] . '/' . $base . '-1400.webp';
                    }
                }

                imagedestroy($source);
                return $image;
            }

            imagedestroy($source);
        }

        error_log('panel: GD is available but webp conversion failed; storing the original');
    }

    // GD yoksa (ya da dönüştürme başarısızsa) dosya olduğu gibi saklanır.
    // İkinci boyut üretilemediği için frontend srcset basmaz.
    $target = $store['uploadDir'] . '/' . $base . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Görsel kaydedilemedi.');
    }

    return [
        'src' => $store['uploadUrl'] . '/' . $base . '.' . $allowed[$info[2]],
        'srcLarge' => null,
        'version' => $version,
    ];
}

/**
 * Yalnızca panelin o bölüm için kendi ürettiği dosyalar silinir. Silme store'un
 * kendi dizinine kilitli: images/ kökündeki depo görselleri ve başka bir
 * bölümün yüklemeleri hiçbir koşulda silinemez.
 */
function panel_delete_image(array $store, $image): void
{
    if (!is_array($image)) {
        return;
    }

    $uploadDir = realpath($store['uploadDir']);
    if ($uploadDir === false) {
        return;
    }

    foreach (['src', 'srcLarge'] as $key) {
        $path = trim((string)($image[$key] ?? ''));
        if ($path === '' || strpos($path, $store['uploadUrl'] . '/') !== 0) {
            continue;
        }

        // Ayırıcıyla karşılaştırılır: aksi hâlde "uploads-eski" gibi bir kardeş
        // dizin önek testini geçerdi.
        $real = realpath(PANEL_ROOT . '/' . $path);
        if ($real !== false && strpos($real, $uploadDir . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            @unlink($real);
        }
    }
}

/* --------------------------------------------------------------------- Tanılama */

function panel_diagnostics(): array
{
    $rapor = [
        'PHP' => PHP_VERSION,
        'GD' => function_exists('imagecreatetruecolor') ? 'var' : 'yok',
        'webp' => function_exists('imagewebp') ? 'var' : 'yok',
        'DOM' => class_exists('DOMDocument') ? 'var' : 'yok',
        'data/ yazılabilir' => is_writable(PANEL_DATA_DIR) ? 'evet' : 'hayır',
    ];

    // Dizin henüz oluşmamış olabilir; o zaman üst dizinin yazılabilirliği
    // oluşturulup oluşturulamayacağını söyler.
    foreach (panel_store_names() as $name) {
        $dir = panel_store($name)['uploadDir'];
        $yazilabilir = is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));
        $rapor[basename(dirname($dir)) . '/uploads yazılabilir'] = $yazilabilir ? 'evet' : 'hayır';
    }

    return $rapor;
}
