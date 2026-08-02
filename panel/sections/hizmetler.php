<?php
declare(strict_types=1);

// Bu dosya panel/index.php tarafindan require edilir; dogrudan istenirse hicbir
// sey yapmaz. panel/sections/.htaccess de ayni isi yapar, o kural
// desteklenmezse bu devrededir.
if (!defined('PANEL_BOOT')) {
    http_response_code(404);
    exit;
}

$store = panel_store('hizmetler');
$geri = 'index.php?bolum=hizmetler';

$errors = [];
$form = [
    'id' => '',
    'title' => '',
    'icon' => '',
    'summary' => '',
    'description' => '',
    'features' => '',
    'order' => '',
    'baseUpdatedAt' => '',
];
$existingImages = [];
// Hata sonrası formu POST'taki hâliyle geri basmak için.
$formAltlar = [];
$formSilinecekler = [];
$formYeniAltlar = [];
$view = is_string($_GET['view'] ?? '') ? (string)($_GET['view'] ?? 'list') : 'list';

if ($postTooLarge) {
    $errors['newImage'] = 'Gönderilen dosyalar sunucunun izin verdiği boyutu aştı, form sunucuya hiç ulaşmadı. '
        . 'Kartın son kayıtlı hâli yüklendi; daha küçük görseller seçip yeniden deneyin.';
    $view = ((string)($_GET['view'] ?? '')) === 'edit' && ($_GET['id'] ?? '') !== '' ? 'edit' : 'new';
} elseif ($isPost) {
    $action = (string)($_POST['action'] ?? '');

    if (!panel_csrf_check()) {
        $_SESSION['flash'] = 'Oturum doğrulanamadı, işlem yapılmadı.';
        header('Location: ' . $geri);
        exit;
    }

    if ($action === 'logout') {
        panel_logout();
        header('Location: index.php');
        exit;
    }

    /* ------------------------------------------------------------- Sıralama */

    if ($action === 'reorder') {
        try {
            $siralar = (array)($_POST['order'] ?? []);
            panel_store_update($store, function (array $data) use ($siralar): array {
                foreach ($data['records'] as $i => $kayit) {
                    $id = (string)($kayit['id'] ?? '');
                    if (isset($siralar[$id]) && ctype_digit((string)$siralar[$id])) {
                        $data['records'][$i]['order'] = (int)$siralar[$id];
                    }
                }
                return $data;
            });
            $_SESSION['flash'] = 'Sıralama kaydedildi.';
        } catch (Throwable $e) {
            error_log('panel hizmetler reorder: ' . $e->getMessage());
            $_SESSION['flash'] = 'Sıralama kaydedilemedi: ' . $e->getMessage();
        }

        header('Location: ' . $geri);
        exit;
    }

    /* ---------------------------------------------------------------- Silme */

    if ($action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $removed = null;

        try {
            panel_store_update($store, function (array $data) use ($id, &$removed): array {
                $kalan = [];
                foreach ($data['records'] as $kayit) {
                    if ((string)($kayit['id'] ?? '') === $id) {
                        $removed = $kayit;
                        continue;
                    }
                    $kalan[] = $kayit;
                }
                $data['records'] = $kalan;
                return $data;
            });

            if ($removed === null) {
                $_SESSION['flash'] = 'Hizmet bulunamadı.';
            } else {
                // Görseller ancak kayıt gerçekten silindikten sonra atılır.
                foreach ((array)($removed['images'] ?? []) as $img) {
                    panel_delete_image($store, $img);
                }
                $_SESSION['flash'] = 'Hizmet silindi.';
            }
        } catch (Throwable $e) {
            error_log('panel hizmetler delete: ' . $e->getMessage());
            $_SESSION['flash'] = 'Hizmet silinemedi: ' . $e->getMessage();
        }

        header('Location: ' . $geri);
        exit;
    }

    /* ------------------------------------------------------------ Kaydetme */

    if ($action === 'save') {
        $form['id'] = (string)($_POST['id'] ?? '');
        $form['title'] = panel_plain((string)($_POST['title'] ?? ''), 120);
        $form['icon'] = trim((string)($_POST['icon'] ?? ''));
        $form['summary'] = panel_plain((string)($_POST['summary'] ?? ''), 300);
        $form['description'] = panel_plain((string)($_POST['description'] ?? ''), 800);
        $form['features'] = (string)($_POST['features'] ?? '');
        $form['order'] = trim((string)($_POST['order'] ?? ''));
        $form['baseUpdatedAt'] = (string)($_POST['baseUpdatedAt'] ?? '');

        $isEdit = $form['id'] !== '';
        $current = null;

        if ($isEdit) {
            try {
                $current = panel_find_record(panel_store_read($store)['records'], $form['id']);
            } catch (Throwable $e) {
                error_log('panel hizmetler save (read): ' . $e->getMessage());
                $errors['title'] = 'Hizmet verisi okunamadı: ' . $e->getMessage();
            }

            if ($current === null && !$errors) {
                $_SESSION['flash'] = 'Hizmet bulunamadı.';
                header('Location: ' . $geri);
                exit;
            }
        }

        // Geçersiz UTF-8 en başta yakalanır: sonra ya sessizce silinir ya da
        // json_encode'u düşürüp anlamsız bir hata üretirdi.
        foreach (['title' => 'Başlık', 'summary' => 'Kısa açıklama', 'description' => 'Uzun açıklama', 'features' => 'Özellikler'] as $alan => $etiket) {
            if (!panel_is_valid_utf8((string)($_POST[$alan] ?? ''))) {
                $errors[$alan] = $etiket . ' alanındaki metin geçerli UTF-8 değil.';
            }
        }

        if (!isset($errors['title']) && panel_length($form['title']) < 2) {
            $errors['title'] = 'Başlık en az 2 karakter olmalı.';
        }
        if ($form['icon'] !== '' && !isset(PANEL_SERVICE_ICONS[$form['icon']])) {
            $errors['icon'] = 'Geçersiz ikon seçildi.';
        }
        if (!isset($errors['summary']) && $form['summary'] === '') {
            $errors['summary'] = 'Kısa açıklama boş bırakılamaz (anasayfa kartında görünür).';
        }
        if (!isset($errors['description']) && $form['description'] === '') {
            $errors['description'] = 'Uzun açıklama boş bırakılamaz (hizmetler sayfasında görünür).';
        }
        if ($form['order'] !== '' && !ctype_digit($form['order'])) {
            $errors['order'] = 'Sıra yalnızca sayı olabilir.';
        }

        $features = [];
        if (!isset($errors['features'])) {
            foreach (preg_split('/\R/u', $form['features']) as $satir) {
                $satir = panel_plain((string)$satir, 120);
                if ($satir !== '') {
                    $features[] = $satir;
                }
            }
            if (count($features) > PANEL_MAX_FEATURES) {
                $errors['features'] = 'En fazla ' . PANEL_MAX_FEATURES . ' özellik maddesi girebilirsiniz.';
            }
        }

        /* -- Görseller: önce mevcutlar, sonra yeni yüklenenler -- */

        $mevcut = is_array($current['images'] ?? null) ? $current['images'] : [];
        $altlar = (array)($_POST['existingAlt'] ?? []);
        $silinecekler = (array)($_POST['removeImage'] ?? []);

        // Anahtarlar korunur: hata durumunda form bu indekslerle yeniden basılıyor
        // ve kaydırılmış bir indeks alt metinlerini yanlış görsele yazardı.
        $tutulan = [];
        $silinenler = [];
        foreach ($mevcut as $i => $img) {
            if (!empty($silinecekler[$i])) {
                $silinenler[] = $img;
                continue;
            }
            $img['alt'] = panel_plain((string)($altlar[$i] ?? ($img['alt'] ?? '')), 160);
            $tutulan[$i] = $img;
        }

        $yeniDosyalar = panel_upload_files('newImage');
        $yeniAltlar = (array)($_POST['newAlt'] ?? []);

        if (count($tutulan) + count($yeniDosyalar) > PANEL_MAX_SERVICE_IMAGES) {
            $errors['newImage'] = 'Bir kartta en fazla ' . PANEL_MAX_SERVICE_IMAGES . ' görsel olabilir.';
        }

        // Alt metin kontrolü yüklemeden ÖNCE: sonra yapılsaydı reddedilen bir
        // kaydın görselleri diske yazılmış olur ve orada sahipsiz kalırdı.
        foreach ($tutulan as $img) {
            if (trim((string)($img['alt'] ?? '')) === '') {
                $errors['existingAlt'] = 'Her görselin alt metni dolu olmalı (ekran okuyucular için).';
                break;
            }
        }
        foreach (array_keys($yeniDosyalar) as $i) {
            if (panel_plain((string)($yeniAltlar[$i] ?? ''), 160) === '') {
                $errors['newAlt'] = 'Yüklediğiniz her görsel için alt metni girin.';
                break;
            }
        }

        $yuklenenler = [];
        if (!$errors) {
            foreach ($yeniDosyalar as $i => $dosya) {
                try {
                    $img = panel_handle_upload($store, $dosya, panel_slug($form['title']));
                    // $i slot indeksi; alt metni aynı slottan okunur.
                    $img['alt'] = panel_plain((string)($yeniAltlar[$i] ?? ''), 160);
                    $yuklenenler[] = $img;
                } catch (Throwable $e) {
                    $errors['newImage'] = $e->getMessage();
                    break;
                }
            }

            // İkinci dosya reddedilse bile ilki diske yazılmış olur; kayıt
            // yapılmayacağı için o dosyaları hiçbir kayıt işaret etmez.
            if ($errors && $yuklenenler) {
                foreach ($yuklenenler as $img) {
                    panel_delete_image($store, $img);
                }
                $yuklenenler = [];
            }
        }

        if (!$errors) {
            $now = gmdate('c');
            $record = [
                'id' => $isEdit ? $form['id'] : '',
                'slug' => panel_slug($form['title']),
                'title' => $form['title'],
                'icon' => $form['icon'],
                'summary' => $form['summary'],
                'description' => $form['description'],
                'features' => $features,
                'order' => $form['order'] !== '' ? (int)$form['order'] : 0,
                'images' => array_merge($tutulan, $yuklenenler),
                'createdAt' => $isEdit ? (string)($current['createdAt'] ?? $now) : $now,
                'updatedAt' => $now,
            ];

            $baseUpdatedAt = $form['baseUpdatedAt'];

            try {
                panel_store_update($store, function (array $data) use ($record, $isEdit, $baseUpdatedAt): array {
                    if ($isEdit) {
                        foreach ($data['records'] as $index => $kayit) {
                            if ((string)($kayit['id'] ?? '') === $record['id']) {
                                if ($baseUpdatedAt !== '' && (string)($kayit['updatedAt'] ?? '') !== $baseUpdatedAt) {
                                    throw new RuntimeException(
                                        'Bu hizmet siz düzenlerken başka bir yerden değiştirilmiş.'
                                        . ' Yazdıklarınızı kopyalayın, sayfayı yenileyip tekrar uygulayın.'
                                    );
                                }
                                $record['createdAt'] = (string)($kayit['createdAt'] ?? $record['createdAt']);
                                $data['records'][$index] = $record;
                                return $data;
                            }
                        }
                        throw new RuntimeException('Hizmet bulunamadı; siz düzenlerken silinmiş olabilir.');
                    }

                    // Yeni kayıt listenin sonuna: sıra verilmemişse en büyük + 10.
                    if ($record['order'] === 0) {
                        $enBuyuk = 0;
                        foreach ($data['records'] as $kayit) {
                            $enBuyuk = max($enBuyuk, (int)($kayit['order'] ?? 0));
                        }
                        $record['order'] = $enBuyuk + 10;
                    }

                    do {
                        $record['id'] = bin2hex(random_bytes(6));
                    } while (panel_find_record($data['records'], $record['id']) !== null);

                    $data['records'][] = $record;
                    return $data;
                });

                foreach ($silinenler as $img) {
                    panel_delete_image($store, $img);
                }

                $_SESSION['flash'] = $isEdit ? 'Hizmet güncellendi.' : 'Hizmet eklendi.';
                header('Location: ' . $geri);
                exit;
            } catch (Throwable $e) {
                error_log('panel hizmetler save: ' . $e->getMessage());
                // Kayıt yazılamadıysa bu isteğin yüklediği görseller sahipsiz kalır.
                foreach ($yuklenenler as $img) {
                    panel_delete_image($store, $img);
                }
                $errors['title'] = 'Kaydedilemedi: ' . $e->getMessage();
            }
        }

        // Form kayıtlı görsellerin TAMAMINI yeniden basar; "kaldır" işaretleri ve
        // girilen alt metinleri aşağıda POST'tan geri yazılıyor. $tutulan basılsaydı
        // kaldırılmak istenen görsel listeden düşer, editör onu kaldırılmış sanır
        // ve bir sonraki kayıtta geri gelirdi.
        $existingImages = $mevcut;
        $formAltlar = $altlar;
        $formSilinecekler = $silinecekler;
        $formYeniAltlar = $yeniAltlar;
        $view = $isEdit ? 'edit' : 'new';
    }
}

/* ---------------------------------------------------------------- Görünümler */

try {
    $depo = panel_store_read($store);
    $kayitlar = panel_sort_by_order($depo['records']);
    $storeSource = (string)($depo['source'] ?? 'live');
    $storeError = '';
} catch (Throwable $e) {
    error_log('panel hizmetler list: ' . $e->getMessage());
    $kayitlar = [];
    $storeSource = 'error';
    $storeError = $e->getMessage();
}

if (($view === 'edit' || $view === 'delete') && (!$isPost || $postTooLarge)) {
    // id dizi gelirse (string) donusumu "Array to string conversion" uyarisi basardi.
    $istenenId = $_GET['id'] ?? '';
    $hedef = is_string($istenenId) ? panel_find_record($kayitlar, $istenenId) : null;

    if ($hedef === null) {
        $_SESSION['flash'] = 'Hizmet bulunamadı.';
        header('Location: ' . $geri);
        exit;
    }

    $form = [
        'id' => (string)$hedef['id'],
        'title' => (string)($hedef['title'] ?? ''),
        'icon' => (string)($hedef['icon'] ?? ''),
        'summary' => (string)($hedef['summary'] ?? ''),
        'description' => (string)($hedef['description'] ?? ''),
        'features' => implode("\n", (array)($hedef['features'] ?? [])),
        'order' => (string)($hedef['order'] ?? ''),
        'baseUpdatedAt' => (string)($hedef['updatedAt'] ?? ''),
    ];
    $existingImages = is_array($hedef['images'] ?? null) ? $hedef['images'] : [];
}

/* --------------------------------------------------------------- Silme onayı */

if ($view === 'delete') {
    panel_head('Hizmeti sil', 'hizmetler');
    ?>
<div class="card">
  <p class="lead">“<?= panel_e($form['title']) ?>” kalıcı olarak silinecek.</p>
  <p class="help">Bu işlem geri alınamaz. Kart hem anasayfadan hem hizmetler sayfasından kalkar;
     panelden yüklenmiş görselleri de silinir.</p>
  <form method="post" class="row-actions">
    <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= panel_e($form['id']) ?>">
    <button type="submit" class="btn btn-danger">Evet, sil</button>
    <a class="btn btn-ghost" href="<?= panel_e($geri) ?>">Vazgeç</a>
  </form>
</div>
<?php
    panel_foot();
    exit;
}

/* ---------------------------------------------------------------------- Form */

if ($view === 'new' || $view === 'edit') {
    $isEdit = $view === 'edit';
    panel_head($isEdit ? 'Hizmeti düzenle' : 'Yeni hizmet', 'hizmetler');
    // Tekrarlanan alanların id'si indeks taşıyor; özet ilk satıra/slota gitsin.
    panel_error_summary($errors, [
        'existingAlt' => 'alan-existingAlt-0',
        'newImage' => 'alan-newImage-0',
        'newAlt' => 'alan-newAlt-0',
    ]);
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
    <input type="text" id="alan-title" name="title" required maxlength="120"
           value="<?= panel_e($form['title']) ?>"<?= panel_invalid($errors, 'title') ?>>
  </div>

  <div class="grid-2">
    <div class="field">
      <label for="alan-icon">İkon</label>
      <select id="alan-icon" name="icon"<?= panel_invalid($errors, 'icon') ?>>
        <option value="">(ikon yok)</option>
<?php foreach (PANEL_SERVICE_ICONS as $sinif => $etiket): ?>
        <option value="<?= panel_e($sinif) ?>"<?= $form['icon'] === $sinif ? ' selected' : '' ?>><?= panel_e($etiket) ?></option>
<?php endforeach; ?>
      </select>
      <p class="help">Yalnızca hizmetler sayfasındaki kartta görünür.</p>
    </div>

    <div class="field">
      <label for="alan-order">Sıra</label>
      <input type="number" id="alan-order" name="order" min="0" max="9999"
             value="<?= panel_e($form['order']) ?>" aria-describedby="order-help"<?= panel_invalid($errors, 'order') ?>>
      <p class="help" id="order-help">Küçük sayı önce gelir. Boş bırakılırsa listenin sonuna eklenir.</p>
    </div>
  </div>

  <div class="field">
    <label for="alan-summary">Kısa açıklama</label>
    <textarea id="alan-summary" name="summary" rows="3" required maxlength="300"
              aria-describedby="summary-help"<?= panel_invalid($errors, 'summary') ?>><?= panel_e($form['summary']) ?></textarea>
    <p class="help" id="summary-help">Anasayfadaki kartta görünür. En fazla 300 karakter.</p>
  </div>

  <div class="field">
    <label for="alan-description">Uzun açıklama</label>
    <textarea id="alan-description" name="description" rows="5" required maxlength="800"
              aria-describedby="description-help"<?= panel_invalid($errors, 'description') ?>><?= panel_e($form['description']) ?></textarea>
    <p class="help" id="description-help">Hizmetler sayfasındaki kartta görünür. En fazla 800 karakter.</p>
  </div>

  <div class="field">
    <label for="alan-features">Özellikler</label>
    <textarea id="alan-features" name="features" rows="5"
              aria-describedby="features-help"<?= panel_invalid($errors, 'features') ?>><?= panel_e($form['features']) ?></textarea>
    <p class="help" id="features-help">Her satır bir madde olur; hizmetler sayfasında tikli liste
      olarak görünür. En fazla <?= PANEL_MAX_FEATURES ?> madde. Boş bırakabilirsiniz.</p>
  </div>

  <fieldset class="field">
    <legend>Görseller</legend>
    <p class="help">Kartta en fazla <?= PANEL_MAX_SERVICE_IMAGES ?> görsel olabilir.
      Birden fazla görsel varsa kart otomatik olarak kaydırmalı gösterime geçer.</p>

<?php if ($existingImages): ?>
    <ul class="image-list">
<?php foreach ($existingImages as $i => $img):
      if (!is_array($img)) { continue; }
      $altDeger = array_key_exists($i, $formAltlar) ? (string)$formAltlar[$i] : (string)($img['alt'] ?? '');
      $isaretli = !empty($formSilinecekler[$i]); ?>
      <li class="image-row">
        <img class="thumb" src="../<?= panel_e($img['src'] ?? '') ?>" alt="" width="96" height="64" loading="lazy">
        <div class="image-fields">
          <label for="alan-existingAlt-<?= (int)$i ?>"><?= panel_e($img['src'] ?? 'Görsel') ?> — alt metni</label>
          <input type="text" id="alan-existingAlt-<?= (int)$i ?>" name="existingAlt[<?= (int)$i ?>]"
                 maxlength="160" value="<?= panel_e($altDeger) ?>"<?= isset($errors['existingAlt']) && trim($altDeger) === '' ? ' aria-invalid="true"' : '' ?>>
          <p class="check">
            <input type="checkbox" id="alan-removeImage-<?= (int)$i ?>" name="removeImage[<?= (int)$i ?>]" value="1"<?= $isaretli ? ' checked' : '' ?>>
            <label for="alan-removeImage-<?= (int)$i ?>">Bu görseli kaldır</label>
          </p>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="help">Bu kartta henüz görsel yok.</p>
<?php endif; ?>

<?php
    // Kalan kapasite kadar slot: 5 görselli bir kartta dört slot göstermek,
    // editöre ancak reddedildikten sonra öğreneceği bir seçenek sunardı.
    $kalanKapasite = max(0, PANEL_MAX_SERVICE_IMAGES - count($existingImages));
    $slotSayisi = min(PANEL_UPLOAD_SLOTS, $kalanKapasite);
    for ($slot = 0; $slot < $slotSayisi; $slot++): ?>
    <div class="upload-slot">
      <label for="alan-newImage-<?= $slot ?>">Yeni görsel <?= $slot + 1 ?></label>
      <input type="file" id="alan-newImage-<?= $slot ?>" name="newImage[<?= $slot ?>]"
             accept="image/jpeg,image/png,image/webp"<?= $slot === 0 ? panel_invalid($errors, 'newImage') : '' ?>>
      <label for="alan-newAlt-<?= $slot ?>">Yeni görsel <?= $slot + 1 ?> alt metni</label>
      <input type="text" id="alan-newAlt-<?= $slot ?>" name="newAlt[<?= $slot ?>]" maxlength="160"
             value="<?= panel_e($formYeniAltlar[$slot] ?? '') ?>"<?= $slot === 0 ? panel_invalid($errors, 'newAlt') : '' ?>>
    </div>
<?php endfor; ?>
<?php if ($slotSayisi === 0): ?>
    <p class="help">Bu kart <?= PANEL_MAX_SERVICE_IMAGES ?> görsele ulaştı. Yeni görsel eklemek için
       önce mevcutlardan birini kaldırıp kaydedin.</p>
<?php endif; ?>
    <p class="help">JPG, PNG veya WEBP. En fazla 6 MB, kenarları 200-<?= PANEL_MAX_IMAGE_EDGE ?> piksel.
      Görsel yüklerseniz alt metni zorunludur.</p>
  </fieldset>

  <div class="row-actions">
    <button type="submit" class="btn">Kaydet</button>
    <a class="btn btn-ghost" href="<?= panel_e($geri) ?>">Vazgeç</a>
  </div>
</form>
<?php
    panel_foot(true);
    exit;
}

/* --------------------------------------------------------------------- Liste */

panel_head('Hizmetler', 'hizmetler', 'Yeni Hizmet');

if ($flash !== '') {
    echo '<p class="flash" role="status">' . panel_e($flash) . '</p>';
}

if ($storeError !== '') {
    echo '<div class="alert" role="alert"><p><strong>Liste gösterilemiyor.</strong> '
        . panel_e($storeError) . ' Bu ekrandan yeni hizmet eklemeyin: veri dosyası'
        . ' okunamadığı sürece kayıt yapılamaz.</p></div>';
} elseif ($storeSource === 'backup' || $storeSource === 'seed') {
    $kaynakMesaji = $storeSource === 'backup'
        ? 'Asıl veri dosyası (data/hizmetler.json) sunucuda bulunamadı; liste son yedekten gösteriliyor.'
        : 'Henüz panelden kayıt yapılmadı; liste depoyla gelen başlangıç verisinden gösteriliyor.';
    echo '<div class="alert" role="alert"><p>' . panel_e($kaynakMesaji)
        . ' Buradan yapacağınız ilk kayıt bu listeyi kalıcı hâle getirir.</p></div>';
}

if ($storeError !== '') {
    // Veri okunamıyorken "hiç kayıt yok" demek yanıltıcı olurdu.
} elseif (!$kayitlar) {
    ?>
<div class="card empty">
  <p>Henüz hizmet yok.</p>
  <a class="btn" href="<?= panel_e($geri) ?>&amp;view=new">İlk hizmeti ekle</a>
</div>
<?php
} else {
    ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
  <input type="hidden" name="action" value="reorder">
  <ul class="post-list">
<?php foreach ($kayitlar as $kayit):
    $ilk = is_array($kayit['images'][0] ?? null) ? $kayit['images'][0] : null; ?>
    <li class="post-row">
<?php if ($ilk !== null): ?>
      <img class="thumb" src="../<?= panel_e($ilk['src']) ?>" alt="" width="72" height="48" loading="lazy">
<?php else: ?>
      <span class="thumb thumb-empty" aria-hidden="true">—</span>
<?php endif; ?>
      <div class="post-main">
        <h2><?= panel_e($kayit['title'] ?? '') ?></h2>
        <p class="post-meta">
          <span><?= count((array)($kayit['images'] ?? [])) ?> görsel</span>
          <span><?= count((array)($kayit['features'] ?? [])) ?> özellik</span>
<?php if (!empty($kayit['icon'])): ?>
          <span class="badge"><?= panel_e(PANEL_SERVICE_ICONS[$kayit['icon']] ?? $kayit['icon']) ?></span>
<?php endif; ?>
        </p>
      </div>
      <div class="order-cell">
        <label for="sira-<?= panel_e($kayit['id']) ?>">Sıra</label>
        <!-- aria-label görünür etiketi ezer: on satırın hepsinde "Sıra" demek
             yerine ekran okuyucu hangi kayda ait olduğunu söyler. -->
        <input type="number" id="sira-<?= panel_e($kayit['id']) ?>" name="order[<?= panel_e($kayit['id']) ?>]"
               min="0" max="9999" value="<?= (int)($kayit['order'] ?? 0) ?>"
               aria-label="<?= panel_e($kayit['title'] ?? '') ?> hizmeti sırası">
      </div>
      <div class="row-actions">
        <a class="btn btn-ghost" href="<?= panel_e($geri) ?>&amp;view=edit&amp;id=<?= panel_e($kayit['id']) ?>">Düzenle</a>
        <a class="btn btn-ghost btn-danger-link" href="<?= panel_e($geri) ?>&amp;view=delete&amp;id=<?= panel_e($kayit['id']) ?>">Sil</a>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
  <button type="submit" class="btn">Sıralamayı kaydet</button>
</form>
<?php
}

panel_foot(true);
