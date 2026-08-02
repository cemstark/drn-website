<?php
declare(strict_types=1);

// Oturum çerezi dizi imzasıyla kuruluyor; bu PHP 7.3'te geldi. Kontrol burada,
// çünkü daha eski bir sürümde panel tanılama satırını basmadan 500 verirdi ve
// yöneticinin sürümü öğrenmesinin yolu kalmazdı.
if (PHP_VERSION_ID < 70300) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>Panel</title></head><body>'
        . '<h1>Panel açılamıyor</h1><p>Blog paneli için PHP 7.3 veya üstü gerekiyor. '
        . 'Sunucudaki sürüm: <strong>' . htmlspecialchars(PHP_VERSION, ENT_QUOTES) . '</strong>.</p>'
        . '<p>Hosting panelinden PHP sürümünü yükseltip tekrar deneyin.</p></body></html>';
    exit;
}

define('PANEL_BOOT', true);
require __DIR__ . '/lib.php';

panel_boot_session();
panel_send_headers();

/* ------------------------------------------------------------------- Şablon */

/**
 * $activeSection boş bırakılırsa (kurulum ve giriş ekranları) ne bölüm
 * gezinmesi ne de çıkış butonu basılır — o ekranlarda oturum yoktur.
 */
function panel_head(string $title, string $activeSection = '', string $newLabel = ''): void
{
    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= panel_e($title) ?> | DRN_EKİN OTO Panel</title>
<link rel="stylesheet" href="panel.css?v=3">
</head>
<body>
<div class="wrap">
<header class="topbar">
  <h1><?= panel_e($title) ?></h1>
<?php if ($activeSection !== ''): ?>
  <div class="topbar-actions">
<?php if ($newLabel !== ''): ?>
    <a class="btn" href="index.php?bolum=<?= panel_e($activeSection) ?>&amp;view=new"><?= panel_e($newLabel) ?></a>
<?php endif; ?>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="btn btn-ghost">Çıkış</button>
    </form>
  </div>
<?php endif; ?>
</header>
<?php if ($activeSection !== ''): ?>
<nav class="section-nav" aria-label="Panel bölümleri">
  <ul>
<?php foreach (panel_store_names() as $name): ?>
    <li><a href="index.php?bolum=<?= panel_e($name) ?>"<?= $name === $activeSection ? ' aria-current="page"' : '' ?>><?= panel_e(panel_store($name)['label']) ?></a></li>
<?php endforeach; ?>
  </ul>
</nav>
<?php endif; ?>
<main>
<?php
}

function panel_foot(bool $showDiagnostics = false): void
{
    if ($showDiagnostics) {
        $parts = [];
        foreach (panel_diagnostics() as $key => $value) {
            $parts[] = $key . ': ' . $value;
        }
        ?>
<footer class="diag">
  <p><?= panel_e(implode(' · ', $parts)) ?></p>
</footer>
<?php
    }
    ?>
</main>
</div>
</body>
</html>
<?php
}

function panel_error_summary(array $errors): void
{
    if (!$errors) {
        return;
    }
    // tabindex + autofocus: özet sayfa ilk render'ında zaten DOM'da olduğu için
    // canlı bölge gibi duyurulmaz; odağı buraya almak tek güvenilir yol.
    ?>
<div class="alert" role="alert" tabindex="-1" autofocus>
  <p><strong>Kaydedilemedi.</strong> Aşağıdaki alanları düzeltin:</p>
  <ul>
<?php foreach ($errors as $field => $message): ?>
    <li><a href="#alan-<?= panel_e($field) ?>"><?= panel_e($message) ?></a></li>
<?php endforeach; ?>
  </ul>
</div>
<?php
}

/** Hatalı alanı hem ekran okuyucuya hem CSS'e bildirir. */
function panel_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true"' : '';
}

$flash = '';
if (!empty($_SESSION['flash'])) {
    $flash = (string)$_SESSION['flash'];
    unset($_SESSION['flash']);
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// post_max_size aşıldığında PHP hem $_POST'u hem $_FILES'ı boş bırakır; CSRF
// alanı da gelmediği için bu, "geçersiz istek" gibi görünürdü.
$postTooLarge = $isPost
    && empty($_POST)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

/* --------------------------------------------------------------- Kurulum ekranı */

if (panel_config_missing()) {
    $generated = '';
    $setupError = '';

    if ($isPost && ($_POST['action'] ?? '') === 'setup') {
        $password = (string)($_POST['password'] ?? '');
        $lockRemaining = panel_lockout_remaining();

        if ($lockRemaining > 0) {
            $setupError = 'Çok fazla deneme yapıldı. ' . (int)ceil($lockRemaining / 60)
                . ' dakika sonra tekrar deneyin.';
        } elseif ($password === '') {
            // Uzunluk/karmaşıklık şartı bilerek yok: parolayı seçmek panel
            // sahibinin kararı. Yalnızca boş parola kabul edilmiyor.
            $setupError = 'Parola boş bırakılamaz.';
        } else {
            // Config sunucuya ulaşana kadar bu ekran herkese açık. password_hash
            // bilerek pahalı bir işlem; sayaca bağlanmazsa sınırsız bcrypt
            // çağrısıyla paylaşımlı CPU kotası tüketilebilirdi.
            panel_attempt_failed();
            $generated = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    panel_head('Panel kurulumu');
    ?>
<p class="lead">Panel henüz yapılandırılmamış. <code>config/panel.php</code> dosyası bulunamadı.</p>

<?php if ($setupError !== ''): ?>
<div class="alert" role="alert"><p><?= panel_e($setupError) ?></p></div>
<?php endif; ?>

<?php if ($generated === ''): ?>
<form method="post" class="card">
  <input type="hidden" name="action" value="setup">
  <div class="field">
    <label for="password">Panel parolası</label>
    <input type="password" id="password" name="password" required
           autocomplete="new-password" aria-describedby="password-help" autofocus>
    <p class="help" id="password-help">İstediğiniz parolayı yazabilirsiniz; uzunluk şartı yok.
      Parola hiçbir yere kaydedilmez, yalnızca hash'i üretilir.</p>
  </div>
  <button type="submit" class="btn">Hash üret</button>
</form>
<?php else: ?>
<div class="card">
  <p><strong>Aşağıdaki içeriği <code>config/panel.php</code> olarak kaydedin</strong> ve sunucuda
     <code>public_html/config/</code> altına yükleyin. Dosya <code>.gitignore</code>'dadır, depoya girmez.</p>
  <label class="sr-only" for="generated">Üretilen config dosyası</label>
  <textarea id="generated" class="code" rows="12" readonly><?= panel_e(
    "<?php\nreturn [\n    'password_hash' => '" . $generated . "',\n"
    . "    'session_name' => 'drnpanel',\n    'max_attempts' => 5,\n    'lockout_seconds' => 900,\n"
    . "    'idle_seconds' => 1800,\n    'absolute_seconds' => 14400,\n];\n"
  ) ?></textarea>
  <p class="help">Dosyayı yükledikten sonra bu sayfayı yenileyin; giriş ekranı gelecek.</p>
</div>
<?php endif; ?>
<?php
    // Tanılama satırı burada bilerek gizli: bu ekran config gelene kadar
    // kimlik doğrulaması olmadan açık ve sunucu parmak izi vermemeli.
    panel_foot();
    exit;
}

/* ----------------------------------------------------------------- Giriş ekranı */

if (!panel_is_authed()) {
    $loginError = '';
    $lockRemaining = panel_lockout_remaining();

    if ($isPost && ($_POST['action'] ?? '') === 'login') {
        if (!panel_csrf_check()) {
            $loginError = 'Oturum doğrulanamadı. Lütfen tekrar deneyin.';
        } elseif ($lockRemaining > 0) {
            $loginError = 'Çok fazla hatalı deneme yapıldı.';
        } elseif (password_verify((string)($_POST['password'] ?? ''), (string)panel_config()['password_hash'])) {
            panel_attempt_succeeded();
            panel_login();
            header('Location: index.php');
            exit;
        } else {
            panel_attempt_failed();
            $lockRemaining = panel_lockout_remaining();
            $loginError = 'Parola hatalı.';
        }
    }

    panel_head('Panel girişi');
    ?>
<?php if ($loginError !== ''): ?>
<div class="alert" role="alert">
  <p><?= panel_e($loginError) ?><?php if ($lockRemaining > 0): ?>
    Giriş <?= (int)ceil($lockRemaining / 60) ?> dakika boyunca kilitli.<?php endif; ?></p>
</div>
<?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
  <input type="hidden" name="action" value="login">
  <div class="field">
    <label for="password">Parola</label>
    <input type="password" id="password" name="password" required
           autocomplete="current-password" autofocus <?= $lockRemaining > 0 ? 'disabled' : '' ?>>
  </div>
  <button type="submit" class="btn" <?= $lockRemaining > 0 ? 'disabled' : '' ?>>Giriş yap</button>
</form>
<?php
    panel_foot();
    exit;
}

/* ------------------------------------------------------------ Bölüm yönlendirme */

// Bölüm adı URL'den geliyor. Beyaz liste dışında hiçbir değer dosya yoluna
// dönüşmemeli; bilinmeyen bölüm sessizce blog'a düşer, böylece eski yer imleri
// ve panelden çıkan "index.php" yönlendirmeleri çalışmaya devam eder.
// is_string kontrolü şart: ?bolum[]=x dizi gönderir ve (string) dönüşümü
// "Array to string conversion" uyarısı basardı.
$bolum = $_GET['bolum'] ?? 'blog';
if (!is_string($bolum) || !in_array($bolum, panel_store_names(), true)) {
    $bolum = 'blog';
}

// Bölüm dosyası henüz eklenmemişse panel kırılmasın diye blog'a düşülür.
if (!is_file(__DIR__ . '/sections/' . $bolum . '.php')) {
    $bolum = 'blog';
}

require __DIR__ . '/sections/' . $bolum . '.php';

