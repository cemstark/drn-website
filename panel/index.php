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

function panel_head(string $title, bool $showNav = false): void
{
    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= panel_e($title) ?> | DRNEKİN OTO Panel</title>
<link rel="stylesheet" href="panel.css?v=1">
</head>
<body>
<div class="wrap">
<header class="topbar">
  <h1><?= panel_e($title) ?></h1>
<?php if ($showNav): ?>
  <div class="topbar-actions">
    <a class="btn" href="index.php?view=new">Yeni Yazı</a>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="btn btn-ghost">Çıkış</button>
    </form>
  </div>
<?php endif; ?>
</header>
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
        } elseif (panel_length($password) < 12) {
            $setupError = 'Parola en az 12 karakter olmalı. En az 16 karakterlik rastgele bir parola önerilir.';
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
    <input type="password" id="password" name="password" required minlength="12"
           autocomplete="new-password" aria-describedby="password-help" autofocus>
    <p class="help" id="password-help">Bu parola hiçbir yere kaydedilmez; yalnızca hash'i üretilir.</p>
  </div>
  <button type="submit" class="btn">Hash üret</button>
</form>
<?php else: ?>
<div class="card">
  <p><strong>Aşağıdaki içeriği <code>config/panel.php</code> olarak kaydedin</strong> ve sunucuda
     <code>public_html/config/</code> altına yükleyin. Dosya <code>.gitignore</code>'dadır, depoya girmez.</p>
  <label class="sr-only" for="generated">Üretilen config dosyası</label>
  <textarea id="generated" class="code" rows="12" readonly><?= panel_e(
    "<?php\nreturn [\n    'password_hash' => '" . $generated . "',\n    'session_name' => 'drnpanel',\n    'max_attempts' => 5,\n    'lockout_seconds' => 900,\n    'idle_seconds' => 7200,\n];\n"
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

/* ------------------------------------------------- Buradan sonrası girişli alan */

$errors = [];
$form = [
    'id' => '',
    'title' => '',
    'category' => '',
    'date' => date('Y-m-d'),
    'readingMinutes' => '',
    'excerpt' => '',
    'content' => '',
    'imageAlt' => '',
    'baseUpdatedAt' => '',
];
$existingImage = null;
$view = (string)($_GET['view'] ?? 'list');

if ($postTooLarge) {
    $errors['image'] = 'Gönderilen dosya sunucunun izin verdiği boyutu aştı, form sunucuya hiç ulaşmadı. '
        . 'Yazının son kayıtlı hâli yüklendi; daha küçük bir görsel seçip yeniden deneyin.';
    // $_POST tamamen boş geldiği için düzenlenen yazının kimliği yalnızca
    // URL'de kaldı. "new" varsayılsaydı gizli id alanı basılmaz, editörün
    // sonraki kaydı var olan yazıyı güncellemek yerine kopyasını oluştururdu.
    $view = ((string)($_GET['view'] ?? '')) === 'edit' && ($_GET['id'] ?? '') !== '' ? 'edit' : 'new';
} elseif ($isPost) {
    $action = (string)($_POST['action'] ?? '');

    if (!panel_csrf_check()) {
        $_SESSION['flash'] = 'Oturum doğrulanamadı, işlem yapılmadı.';
        header('Location: index.php');
        exit;
    }

    if ($action === 'logout') {
        panel_logout();
        header('Location: index.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $removed = null;

        try {
            panel_store_update(function (array $data) use ($id, &$removed): array {
                $kept = [];
                foreach ($data['posts'] as $post) {
                    if ((string)($post['id'] ?? '') === $id) {
                        $removed = $post;
                        continue;
                    }
                    $kept[] = $post;
                }
                $data['posts'] = $kept;
                return $data;
            });

            if ($removed === null) {
                $_SESSION['flash'] = 'Yazı bulunamadı.';
            } else {
                // Görseller ancak kayıt gerçekten silindikten sonra atılır.
                panel_delete_image($removed['image'] ?? null);
                $_SESSION['flash'] = 'Yazı silindi.';
            }
        } catch (Throwable $e) {
            error_log('panel delete: ' . $e->getMessage());
            $_SESSION['flash'] = 'Yazı silinemedi: ' . $e->getMessage();
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'save') {
        $form['id'] = (string)($_POST['id'] ?? '');
        $form['title'] = panel_plain((string)($_POST['title'] ?? ''), 160);
        $form['category'] = panel_plain((string)($_POST['category'] ?? ''), 40);
        $form['date'] = trim((string)($_POST['date'] ?? ''));
        $form['readingMinutes'] = trim((string)($_POST['readingMinutes'] ?? ''));
        $form['excerpt'] = panel_plain((string)($_POST['excerpt'] ?? ''), 400);
        $form['content'] = (string)($_POST['content'] ?? '');
        $form['imageAlt'] = panel_plain((string)($_POST['imageAlt'] ?? ''), 160);
        $form['baseUpdatedAt'] = (string)($_POST['baseUpdatedAt'] ?? '');

        $isEdit = $form['id'] !== '';
        $current = null;

        if ($isEdit) {
            try {
                $current = panel_find_post(panel_store_read()['posts'], $form['id']);
            } catch (Throwable $e) {
                error_log('panel save (read): ' . $e->getMessage());
                $errors['title'] = 'Blog verisi okunamadı: ' . $e->getMessage();
            }

            if ($current === null && !$errors) {
                $_SESSION['flash'] = 'Yazı bulunamadı.';
                header('Location: index.php');
                exit;
            }

            // Alt metin POST'tan ne geldiyse odur: eskisini geri doldurmak, yeni
            // yüklenen bir görsele önceki görselin açıklamasını yapıştırırdı.
            $existingImage = is_array($current['image'] ?? null) ? $current['image'] : null;
        }

        // Geçersiz UTF-8 en başta yakalanır: sonraki adımlarda ya sessizce
        // silinir ya da json_encode'u düşürüp anlamsız bir hata üretirdi.
        foreach ([
            'title' => 'Başlık',
            'category' => 'Kategori',
            'excerpt' => 'Özet',
            'content' => 'İçerik',
            'imageAlt' => 'Görsel alt metni',
        ] as $alan => $etiket) {
            if (!panel_is_valid_utf8((string)($_POST[$alan] ?? ''))) {
                $errors[$alan] = $etiket . ' alanındaki metin geçerli UTF-8 değil.'
                    . ' Başka bir programdan yapıştırdıysanız önce düz metne çevirin.';
            }
        }

        if (!isset($errors['title']) && panel_length($form['title']) < 3) {
            $errors['title'] = 'Başlık en az 3 karakter olmalı.';
        }
        if (!isset($errors['category']) && $form['category'] === '') {
            $errors['category'] = 'Kategori boş bırakılamaz.';
        }
        if (!panel_valid_date($form['date'])) {
            $errors['date'] = 'Tarih geçerli bir gün olmalı ve ' . panel_date_min()
                . ' ile ' . panel_date_max() . ' arasında kalmalı.';
        }
        if (!isset($errors['excerpt']) && $form['excerpt'] === '') {
            $errors['excerpt'] = 'Özet boş bırakılamaz.';
        }
        if (!isset($errors['content']) && trim(strip_tags($form['content'])) === '') {
            $errors['content'] = 'İçerik boş bırakılamaz.';
        }
        if (!isset($errors['content']) && panel_length($form['content']) > PANEL_MAX_CONTENT_CHARS) {
            $errors['content'] = 'İçerik en fazla ' . number_format(PANEL_MAX_CONTENT_CHARS, 0, ',', '.')
                . ' karakter olabilir.';
        }
        if ($form['readingMinutes'] !== ''
            && (!ctype_digit($form['readingMinutes'])
                || (int)$form['readingMinutes'] < 1
                || (int)$form['readingMinutes'] > 60)) {
            $errors['readingMinutes'] = 'Okuma süresi 1 ile 60 arasında bir sayı olmalı (ya da boş).';
        }

        $cleanContent = '';
        if (!isset($errors['content'])) {
            try {
                $cleanContent = panel_sanitize_html($form['content']);
                if ($cleanContent === '') {
                    $errors['content'] = 'İçerik temizlendikten sonra boş kaldı.';
                }
            } catch (Throwable $e) {
                error_log('panel sanitize: ' . $e->getMessage());
                $errors['content'] = $e->getMessage();
            }
        }

        // Görsel: yenisi yüklendiyse o, "kaldır" işaretliyse yok, aksi hâlde mevcut.
        $image = $existingImage;
        $imageToDelete = null;
        $hasUpload = isset($_FILES['image'])
            && is_array($_FILES['image'])
            && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $removeImage = !empty($_POST['removeImage']);

        // Alt metin kontrolü yüklemeden ÖNCE yapılır. Sonra yapılsaydı reddedilen
        // bir kaydın görseli diske yazılmış olur ve orada sahipsiz kalırdı.
        $willHaveImage = $hasUpload || ($existingImage !== null && !$removeImage);
        if ($willHaveImage && $form['imageAlt'] === '') {
            $errors['imageAlt'] = 'Görsel varsa alt metni zorunludur (ekran okuyucular için).';
        }

        if (!$errors && $hasUpload) {
            try {
                $image = panel_handle_upload(panel_slug($form['title']));
                $imageToDelete = $existingImage;
            } catch (Throwable $e) {
                $errors['image'] = $e->getMessage();
            }
        } elseif (!$errors && $removeImage) {
            $imageToDelete = $existingImage;
            $image = null;
        }

        if (!$errors) {
            if (is_array($image)) {
                $image['alt'] = $form['imageAlt'];
            }

            $minutes = $form['readingMinutes'] !== ''
                ? (int)$form['readingMinutes']
                : panel_reading_minutes($cleanContent);

            $now = gmdate('c');
            $record = [
                'id' => $isEdit ? $form['id'] : '',
                'slug' => panel_slug($form['title']),
                'title' => $form['title'],
                'category' => $form['category'],
                'date' => $form['date'],
                'readingMinutes' => $minutes,
                'excerpt' => $form['excerpt'],
                'content' => $cleanContent,
                'image' => $image,
                'createdAt' => $isEdit ? (string)($current['createdAt'] ?? $now) : $now,
                'updatedAt' => $now,
            ];

            $baseUpdatedAt = (string)($_POST['baseUpdatedAt'] ?? '');

            try {
                panel_store_update(function (array $data) use ($record, $isEdit, $baseUpdatedAt): array {
                    if ($isEdit) {
                        foreach ($data['posts'] as $index => $post) {
                            if ((string)($post['id'] ?? '') === $record['id']) {
                                // Form açıldıktan sonra aynı yazı başka bir yerden
                                // kaydedildiyse buradaki yazma onu sessizce silerdi.
                                if ($baseUpdatedAt !== '' && (string)($post['updatedAt'] ?? '') !== $baseUpdatedAt) {
                                    throw new RuntimeException(
                                        'Bu yazı siz düzenlerken başka bir yerden değiştirilmiş.'
                                        . ' Yazdıklarınızı kopyalayın, sayfayı yenileyip tekrar uygulayın.'
                                    );
                                }

                                // createdAt kilit altındaki kayıttan alınır.
                                $record['createdAt'] = (string)($post['createdAt'] ?? $record['createdAt']);
                                $data['posts'][$index] = $record;
                                return $data;
                            }
                        }

                        throw new RuntimeException('Yazı bulunamadı; siz düzenlerken silinmiş olabilir.');
                    }

                    // Kimlik çakışması pratikte imkânsız ama kontrol ucuz.
                    do {
                        $record['id'] = bin2hex(random_bytes(6));
                    } while (panel_find_post($data['posts'], $record['id']) !== null);

                    $data['posts'][] = $record;
                    return $data;
                });

                if ($imageToDelete !== null) {
                    panel_delete_image($imageToDelete);
                }

                $_SESSION['flash'] = $isEdit ? 'Yazı güncellendi.' : 'Yazı eklendi.';
                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                error_log('panel save: ' . $e->getMessage());
                // Kayıt yazılamadıysa bu isteğin yüklediği görsel sahipsiz kalır.
                if ($hasUpload && is_array($image)) {
                    panel_delete_image($image);
                }
                $errors['title'] = 'Kaydedilemedi: ' . $e->getMessage();
            }
        }

        // Hata varsa form girilen değerlerle yeniden gösterilir.
        $view = $isEdit ? 'edit' : 'new';
    }
}

/* -------------------------------------------------------------------- Görünümler */

try {
    $store = panel_store_read();
    $posts = panel_sort_posts($store['posts']);
    $storeSource = (string)($store['source'] ?? 'live');
    $storeError = '';
} catch (Throwable $e) {
    error_log('panel list: ' . $e->getMessage());
    $posts = [];
    $storeSource = 'error';
    $storeError = $e->getMessage();
}

$categories = [];
foreach ($posts as $post) {
    $name = trim((string)($post['category'] ?? ''));
    if ($name !== '' && !in_array($name, $categories, true)) {
        $categories[] = $name;
    }
}
sort($categories);

// Düzenleme/silme için kayıt, POST hatasından sonra formu ezmeyecek şekilde
// yüklenir. Tek istisna $postTooLarge: orada $_POST hiç gelmediği için formda
// gösterilecek tek doğru içerik kayıtlı hâlin kendisidir.
if (($view === 'edit' || $view === 'delete') && (!$isPost || $postTooLarge)) {
    $target = panel_find_post($posts, (string)($_GET['id'] ?? ''));

    if ($target === null) {
        $_SESSION['flash'] = 'Yazı bulunamadı.';
        header('Location: index.php');
        exit;
    }

    $form = [
        'id' => (string)$target['id'],
        'title' => (string)($target['title'] ?? ''),
        'category' => (string)($target['category'] ?? ''),
        'date' => (string)($target['date'] ?? ''),
        'readingMinutes' => (string)($target['readingMinutes'] ?? ''),
        'excerpt' => (string)($target['excerpt'] ?? ''),
        'content' => (string)($target['content'] ?? ''),
        'imageAlt' => is_array($target['image'] ?? null) ? (string)($target['image']['alt'] ?? '') : '',
        // Kaydetmede karşılaştırılır: bu yazı form açıkken değiştiyse yazma durur.
        'baseUpdatedAt' => (string)($target['updatedAt'] ?? ''),
    ];
    $existingImage = is_array($target['image'] ?? null) ? $target['image'] : null;
}

if ($view === 'delete') {
    panel_head('Yazıyı sil', true);
    ?>
<div class="card">
  <p class="lead">“<?= panel_e($form['title']) ?>” kalıcı olarak silinecek.</p>
  <p class="help">Bu işlem geri alınamaz. Yazıya ait yüklenmiş görseller de silinir.</p>
  <form method="post" class="row-actions">
    <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= panel_e($form['id']) ?>">
    <button type="submit" class="btn btn-danger">Evet, sil</button>
    <a class="btn btn-ghost" href="index.php">Vazgeç</a>
  </form>
</div>
<?php
    panel_foot();
    exit;
}

if ($view === 'new' || $view === 'edit') {
    $isEdit = $view === 'edit';
    panel_head($isEdit ? 'Yazıyı düzenle' : 'Yeni yazı', true);
    panel_error_summary($errors);
    ?>
<form method="post" enctype="multipart/form-data" class="card">
  <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
<?php if ($isEdit): ?>
  <input type="hidden" name="id" value="<?= panel_e($form['id']) ?>">
  <input type="hidden" name="baseUpdatedAt" value="<?= panel_e($form['baseUpdatedAt']) ?>">
<?php endif; ?>

  <div class="field">
    <label for="alan-title">Başlık</label>
    <input type="text" id="alan-title" name="title" required maxlength="160"
           value="<?= panel_e($form['title']) ?>"<?= panel_invalid($errors, 'title') ?>>
  </div>

  <div class="grid-2">
    <div class="field">
      <label for="alan-category">Kategori</label>
      <input type="text" id="alan-category" name="category" required maxlength="40"
             list="kategoriler" value="<?= panel_e($form['category']) ?>"<?= panel_invalid($errors, 'category') ?>>
      <datalist id="kategoriler">
<?php foreach ($categories as $name): ?>
        <option value="<?= panel_e($name) ?>"></option>
<?php endforeach; ?>
      </datalist>
    </div>

    <div class="field">
      <label for="alan-date">Tarih</label>
      <input type="date" id="alan-date" name="date" required value="<?= panel_e($form['date']) ?>"
             min="<?= panel_e(panel_date_min()) ?>" max="<?= panel_e(panel_date_max()) ?>"<?= panel_invalid($errors, 'date') ?>>
    </div>
  </div>

  <div class="field">
    <label for="alan-readingMinutes">Okuma süresi (dakika)</label>
    <input type="number" id="alan-readingMinutes" name="readingMinutes" min="1" max="60"
           value="<?= panel_e($form['readingMinutes']) ?>" aria-describedby="reading-help"<?= panel_invalid($errors, 'readingMinutes') ?>>
    <p class="help" id="reading-help">Boş bırakılırsa içerik uzunluğundan otomatik hesaplanır.</p>
  </div>

  <div class="field">
    <label for="alan-excerpt">Özet</label>
    <textarea id="alan-excerpt" name="excerpt" rows="3" required maxlength="400"
              aria-describedby="excerpt-help"<?= panel_invalid($errors, 'excerpt') ?>><?= panel_e($form['excerpt']) ?></textarea>
    <p class="help" id="excerpt-help">Kart üzerinde görünen kısa tanıtım yazısı. En fazla 400 karakter.</p>
  </div>

  <div class="field">
    <label for="alan-content">İçerik</label>
    <textarea id="alan-content" name="content" rows="16" required
              aria-describedby="content-help"<?= panel_invalid($errors, 'content') ?>><?= panel_e($form['content']) ?></textarea>
    <p class="help" id="content-help">
      HTML yazabilirsiniz. İzinli etiketler: <code>&lt;p&gt; &lt;br&gt; &lt;strong&gt; &lt;em&gt;
      &lt;ul&gt; &lt;ol&gt; &lt;li&gt; &lt;h4&gt; &lt;a href&gt;</code>.
      Diğer etiketler kaydedilirken kaldırılır, metinleri korunur.
    </p>
  </div>

  <fieldset class="field">
    <legend>Görsel</legend>
<?php if ($existingImage !== null): ?>
    <p class="thumb-row">
      <img class="thumb" src="../<?= panel_e($existingImage['src']) ?>" alt="" width="96" height="64">
      <span class="help"><?= panel_e($existingImage['src']) ?></span>
    </p>
    <p class="check">
      <input type="checkbox" id="alan-removeImage" name="removeImage" value="1">
      <label for="alan-removeImage">Bu görseli kaldır</label>
    </p>
<?php endif; ?>
    <label for="alan-image">Görsel yükle</label>
    <input type="file" id="alan-image" name="image" accept="image/jpeg,image/png,image/webp"
           aria-describedby="image-help"<?= panel_invalid($errors, 'image') ?>>
    <p class="help" id="image-help">JPG, PNG veya WEBP. En fazla 6 MB, kenarları 200-<?= PANEL_MAX_IMAGE_EDGE ?> piksel.
      Yeni dosya seçilirse mevcut görselin yerini alır.</p>

    <label for="alan-imageAlt">Görsel alt metni</label>
    <input type="text" id="alan-imageAlt" name="imageAlt" maxlength="160"
           value="<?= panel_e($form['imageAlt']) ?>" aria-describedby="alt-help"<?= panel_invalid($errors, 'imageAlt') ?>>
    <p class="help" id="alt-help">Görseli göremeyenler için kısa açıklama. Görsel varsa zorunludur.</p>
  </fieldset>

  <div class="row-actions">
    <button type="submit" class="btn">Kaydet</button>
    <a class="btn btn-ghost" href="index.php">Vazgeç</a>
  </div>
</form>
<?php
    panel_foot(true);
    exit;
}

/* --------------------------------------------------------------------- Liste */

panel_head('Blog yazıları', true);

if ($flash !== '') {
    echo '<p class="flash" role="status">' . panel_e($flash) . '</p>';
}

if ($storeError !== '') {
    echo '<div class="alert" role="alert"><p><strong>Liste gösterilemiyor.</strong> '
        . panel_e($storeError) . ' Bu ekrandan yeni yazı eklemeyin: veri dosyası'
        . ' okunamadığı sürece kayıt yapılamaz.</p></div>';
}

// data/blog.json kaybolduğunda liste sessizce eski bir kaynağa döner. Uyarı
// olmadan editör bunu fark etmez ve ilk kaydetmede aradaki yazılar silinir.
if ($storeSource === 'backup' || $storeSource === 'seed') {
    $kaynakMesaji = $storeSource === 'backup'
        ? 'Asıl veri dosyası (data/blog.json) sunucuda bulunamadı; liste son yedekten (blog.json.bak) gösteriliyor.'
        : 'Asıl veri dosyası (data/blog.json) sunucuda yok; liste depoyla gelen başlangıç verisinden gösteriliyor.';
    echo '<div class="alert" role="alert"><p><strong>Dikkat:</strong> ' . panel_e($kaynakMesaji)
        . ' Buradan yapacağınız ilk kayıt bu listeyi kalıcı hâle getirir. Eksik yazı görüyorsanız'
        . ' kaydetmeden önce sunucudaki data/ klasörünü kontrol edin.</p></div>';
}

if ($storeError !== '') {
    // Veri okunamıyorken "hiç yazı yok" demek yanıltıcı olur: yazılar duruyor
    // olabilir, görülemiyor. Ekleme çağrısı da bilerek gösterilmiyor.
} elseif (!$posts) {
    ?>
<div class="card empty">
  <p>Henüz yazı yok.</p>
  <a class="btn" href="index.php?view=new">İlk yazıyı ekle</a>
</div>
<?php
} else {
    ?>
<ul class="post-list">
<?php foreach ($posts as $post):
    $image = is_array($post['image'] ?? null) ? $post['image'] : null; ?>
  <li class="post-row">
<?php if ($image !== null): ?>
    <img class="thumb" src="../<?= panel_e($image['src']) ?>" alt="" width="72" height="48" loading="lazy">
<?php else: ?>
    <span class="thumb thumb-empty" aria-hidden="true">—</span>
<?php endif; ?>
    <div class="post-main">
      <h2><?= panel_e($post['title'] ?? '') ?></h2>
      <p class="post-meta">
        <span class="badge"><?= panel_e($post['category'] ?? '') ?></span>
        <time datetime="<?= panel_e($post['date'] ?? '') ?>"><?= panel_e(panel_date_display((string)($post['date'] ?? ''))) ?></time>
        <span><?= (int)($post['readingMinutes'] ?? 1) ?> dk</span>
      </p>
    </div>
    <div class="row-actions">
      <a class="btn btn-ghost" href="index.php?view=edit&amp;id=<?= panel_e($post['id']) ?>">Düzenle</a>
      <a class="btn btn-ghost btn-danger-link" href="index.php?view=delete&amp;id=<?= panel_e($post['id']) ?>">Sil</a>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php
}

panel_foot(true);
