<?php
declare(strict_types=1);

// Bu dosya panel/index.php tarafindan require edilir; dogrudan istenirse hicbir
// sey yapmaz. panel/sections/.htaccess de ayni isi yapar, o kural
// desteklenmezse bu devrededir.
if (!defined('PANEL_BOOT')) {
    http_response_code(404);
    exit;
}

$store = panel_store('galeri');
$geri = 'index.php?bolum=galeri';

$errors = [];
$form = [
    'id' => '',
    'title' => '',
    'caption' => '',
    'category' => 'bakim',
    'order' => '',
    'imageAlt' => '',
    'baseUpdatedAt' => '',
];
$existingImage = null;
$view = (string)($_GET['view'] ?? 'list');

if ($postTooLarge) {
    $errors['image'] = 'Gönderilen dosya sunucunun izin verdiği boyutu aştı, form sunucuya hiç ulaşmadı. '
        . 'Karenin son kayıtlı hâli yüklendi; daha küçük bir görsel seçip yeniden deneyin.';
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
            error_log('panel galeri reorder: ' . $e->getMessage());
            $_SESSION['flash'] = 'Sıralama kaydedilemedi: ' . $e->getMessage();
        }

        header('Location: ' . $geri);
        exit;
    }

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
                $_SESSION['flash'] = 'Kare bulunamadı.';
            } else {
                panel_delete_image($store, $removed['image'] ?? null);
                $_SESSION['flash'] = 'Kare silindi.';
            }
        } catch (Throwable $e) {
            error_log('panel galeri delete: ' . $e->getMessage());
            $_SESSION['flash'] = 'Kare silinemedi: ' . $e->getMessage();
        }

        header('Location: ' . $geri);
        exit;
    }

    if ($action === 'save') {
        $form['id'] = (string)($_POST['id'] ?? '');
        $form['title'] = panel_plain((string)($_POST['title'] ?? ''), 120);
        $form['caption'] = panel_plain((string)($_POST['caption'] ?? ''), 200);
        $form['category'] = trim((string)($_POST['category'] ?? ''));
        $form['order'] = trim((string)($_POST['order'] ?? ''));
        $form['imageAlt'] = panel_plain((string)($_POST['imageAlt'] ?? ''), 160);
        $form['baseUpdatedAt'] = (string)($_POST['baseUpdatedAt'] ?? '');

        $isEdit = $form['id'] !== '';
        $current = null;

        if ($isEdit) {
            try {
                $current = panel_find_record(panel_store_read($store)['records'], $form['id']);
            } catch (Throwable $e) {
                error_log('panel galeri save (read): ' . $e->getMessage());
                $errors['title'] = 'Galeri verisi okunamadı: ' . $e->getMessage();
            }

            if ($current === null && !$errors) {
                $_SESSION['flash'] = 'Kare bulunamadı.';
                header('Location: ' . $geri);
                exit;
            }

            $existingImage = is_array($current['image'] ?? null) ? $current['image'] : null;
        }

        foreach (['title' => 'Başlık', 'caption' => 'Açıklama', 'imageAlt' => 'Alt metni'] as $alan => $etiket) {
            if (!panel_is_valid_utf8((string)($_POST[$alan] ?? ''))) {
                $errors[$alan] = $etiket . ' alanındaki metin geçerli UTF-8 değil.';
            }
        }

        if (!isset($errors['title']) && panel_length($form['title']) < 2) {
            $errors['title'] = 'Başlık en az 2 karakter olmalı.';
        }
        if (!isset(PANEL_GALLERY_CATEGORIES[$form['category']])) {
            $errors['category'] = 'Geçersiz kategori seçildi.';
        }
        if ($form['order'] !== '' && !ctype_digit($form['order'])) {
            $errors['order'] = 'Sıra yalnızca sayı olabilir.';
        }

        $yeniDosyalar = panel_upload_files('image');
        $hasUpload = count($yeniDosyalar) > 0;
        $removeImage = !empty($_POST['removeImage']);

        // Bir galeri karesi görselsiz anlamsız; ayrıca alt metni kontrolü
        // yüklemeden ÖNCE yapılır ki reddedilen kayıt diskte dosya bırakmasın.
        $sonundaGorselOlacak = $hasUpload || ($existingImage !== null && !$removeImage);
        if (!$sonundaGorselOlacak) {
            $errors['image'] = 'Galeri karesi görselsiz olamaz.';
        } elseif ($form['imageAlt'] === '') {
            $errors['imageAlt'] = 'Görsel alt metni zorunludur (ekran okuyucular için).';
        }

        $image = $existingImage;
        $silinecek = null;

        if (!$errors && $hasUpload) {
            try {
                $image = panel_handle_upload($store, $yeniDosyalar[0], panel_slug($form['title']));
                $silinecek = $existingImage;
            } catch (Throwable $e) {
                $errors['image'] = $e->getMessage();
            }
        }

        if (!$errors) {
            if (is_array($image)) {
                $image['alt'] = $form['imageAlt'];
            }

            $now = gmdate('c');
            $record = [
                'id' => $isEdit ? $form['id'] : '',
                'title' => $form['title'],
                'caption' => $form['caption'],
                'category' => $form['category'],
                'order' => $form['order'] !== '' ? (int)$form['order'] : 0,
                'image' => $image,
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
                                        'Bu kare siz düzenlerken başka bir yerden değiştirilmiş.'
                                        . ' Sayfayı yenileyip tekrar deneyin.'
                                    );
                                }
                                $record['createdAt'] = (string)($kayit['createdAt'] ?? $record['createdAt']);
                                $data['records'][$index] = $record;
                                return $data;
                            }
                        }
                        throw new RuntimeException('Kare bulunamadı; siz düzenlerken silinmiş olabilir.');
                    }

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

                if ($silinecek !== null) {
                    panel_delete_image($store, $silinecek);
                }

                $_SESSION['flash'] = $isEdit ? 'Kare güncellendi.' : 'Kare eklendi.';
                header('Location: ' . $geri);
                exit;
            } catch (Throwable $e) {
                error_log('panel galeri save: ' . $e->getMessage());
                if ($hasUpload && is_array($image)) {
                    panel_delete_image($store, $image);
                }
                $errors['title'] = 'Kaydedilemedi: ' . $e->getMessage();
            }
        }

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
    error_log('panel galeri list: ' . $e->getMessage());
    $kayitlar = [];
    $storeSource = 'error';
    $storeError = $e->getMessage();
}

if (($view === 'edit' || $view === 'delete') && (!$isPost || $postTooLarge)) {
    $hedef = panel_find_record($kayitlar, (string)($_GET['id'] ?? ''));

    if ($hedef === null) {
        $_SESSION['flash'] = 'Kare bulunamadı.';
        header('Location: ' . $geri);
        exit;
    }

    $form = [
        'id' => (string)$hedef['id'],
        'title' => (string)($hedef['title'] ?? ''),
        'caption' => (string)($hedef['caption'] ?? ''),
        'category' => (string)($hedef['category'] ?? 'bakim'),
        'order' => (string)($hedef['order'] ?? ''),
        'imageAlt' => is_array($hedef['image'] ?? null) ? (string)($hedef['image']['alt'] ?? '') : '',
        'baseUpdatedAt' => (string)($hedef['updatedAt'] ?? ''),
    ];
    $existingImage = is_array($hedef['image'] ?? null) ? $hedef['image'] : null;
}

if ($view === 'delete') {
    panel_head('Kareyi sil', 'galeri');
    ?>
<div class="card">
  <p class="lead">“<?= panel_e($form['title']) ?>” kalıcı olarak silinecek.</p>
  <p class="help">Bu işlem geri alınamaz. Panelden yüklenmiş görseli de silinir.</p>
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

if ($view === 'new' || $view === 'edit') {
    $isEdit = $view === 'edit';
    panel_head($isEdit ? 'Kareyi düzenle' : 'Yeni kare', 'galeri');
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
    <input type="text" id="alan-title" name="title" required maxlength="120"
           value="<?= panel_e($form['title']) ?>" aria-describedby="title-help"<?= panel_invalid($errors, 'title') ?>>
    <p class="help" id="title-help">Karenin üzerinde görünen etiket.</p>
  </div>

  <div class="grid-2">
    <div class="field">
      <label for="alan-category">Kategori</label>
      <select id="alan-category" name="category" required<?= panel_invalid($errors, 'category') ?>>
<?php foreach (PANEL_GALLERY_CATEGORIES as $anahtar => $etiket): ?>
        <option value="<?= panel_e($anahtar) ?>"<?= $form['category'] === $anahtar ? ' selected' : '' ?>><?= panel_e($etiket) ?></option>
<?php endforeach; ?>
      </select>
      <p class="help">Galeri sayfasındaki filtre butonlarını belirler.</p>
    </div>

    <div class="field">
      <label for="alan-order">Sıra</label>
      <input type="number" id="alan-order" name="order" min="0" max="9999"
             value="<?= panel_e($form['order']) ?>" aria-describedby="order-help"<?= panel_invalid($errors, 'order') ?>>
      <p class="help" id="order-help">Küçük sayı önce gelir. Boş bırakılırsa sona eklenir.</p>
    </div>
  </div>

  <div class="field">
    <label for="alan-caption">Açıklama</label>
    <input type="text" id="alan-caption" name="caption" maxlength="200"
           value="<?= panel_e($form['caption']) ?>" aria-describedby="caption-help"<?= panel_invalid($errors, 'caption') ?>>
    <p class="help" id="caption-help">Görsel büyütüldüğünde altında görünür. Boş bırakılırsa başlık kullanılır.</p>
  </div>

  <fieldset class="field">
    <legend>Görsel</legend>
<?php if ($existingImage !== null): ?>
    <p class="thumb-row">
      <img class="thumb" src="../<?= panel_e($existingImage['src']) ?>" alt="" width="96" height="64">
      <span class="help"><?= panel_e($existingImage['src']) ?></span>
    </p>
<?php endif; ?>
    <label for="alan-image"><?= $existingImage !== null ? 'Görseli değiştir' : 'Görsel yükle' ?></label>
    <input type="file" id="alan-image" name="image" accept="image/jpeg,image/png,image/webp"
           <?= $existingImage === null ? 'required ' : '' ?>aria-describedby="image-help"<?= panel_invalid($errors, 'image') ?>>
    <p class="help" id="image-help">JPG, PNG veya WEBP. En fazla 6 MB, kenarları 200-<?= PANEL_MAX_IMAGE_EDGE ?> piksel.
      Kareler 4:3 oranında kırpılarak gösterilir.</p>

    <label for="alan-imageAlt">Alt metni</label>
    <input type="text" id="alan-imageAlt" name="imageAlt" required maxlength="160"
           value="<?= panel_e($form['imageAlt']) ?>" aria-describedby="alt-help"<?= panel_invalid($errors, 'imageAlt') ?>>
    <p class="help" id="alt-help">Görseli göremeyenler için kısa açıklama. Zorunludur.</p>
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

panel_head('Galeri', 'galeri', 'Yeni Kare');

if ($flash !== '') {
    echo '<p class="flash" role="status">' . panel_e($flash) . '</p>';
}

if ($storeError !== '') {
    echo '<div class="alert" role="alert"><p><strong>Liste gösterilemiyor.</strong> '
        . panel_e($storeError) . ' Bu ekrandan yeni kare eklemeyin: veri dosyası'
        . ' okunamadığı sürece kayıt yapılamaz.</p></div>';
} elseif ($storeSource === 'backup' || $storeSource === 'seed') {
    $kaynakMesaji = $storeSource === 'backup'
        ? 'Asıl veri dosyası (data/galeri.json) sunucuda bulunamadı; liste son yedekten gösteriliyor.'
        : 'Henüz panelden kayıt yapılmadı; liste depoyla gelen başlangıç verisinden gösteriliyor.';
    echo '<div class="alert" role="alert"><p>' . panel_e($kaynakMesaji)
        . ' Buradan yapacağınız ilk kayıt bu listeyi kalıcı hâle getirir.</p></div>';
}

if ($storeError !== '') {
    // Veri okunamıyorken "hiç kare yok" demek yanıltıcı olurdu.
} elseif (!$kayitlar) {
    ?>
<div class="card empty">
  <p>Henüz kare yok.</p>
  <a class="btn" href="<?= panel_e($geri) ?>&amp;view=new">İlk kareyi ekle</a>
</div>
<?php
} else {
    ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= panel_e(panel_csrf_token()) ?>">
  <input type="hidden" name="action" value="reorder">
  <ul class="post-list">
<?php foreach ($kayitlar as $kayit):
    $img = is_array($kayit['image'] ?? null) ? $kayit['image'] : null; ?>
    <li class="post-row">
<?php if ($img !== null): ?>
      <img class="thumb" src="../<?= panel_e($img['src']) ?>" alt="" width="72" height="48" loading="lazy">
<?php else: ?>
      <span class="thumb thumb-empty" aria-hidden="true">—</span>
<?php endif; ?>
      <div class="post-main">
        <h2><?= panel_e($kayit['title'] ?? '') ?></h2>
        <p class="post-meta">
          <span class="badge"><?= panel_e(PANEL_GALLERY_CATEGORIES[$kayit['category'] ?? ''] ?? $kayit['category'] ?? '') ?></span>
<?php if (!empty($kayit['caption'])): ?>
          <span><?= panel_e(mb_substr((string)$kayit['caption'], 0, 40)) ?></span>
<?php endif; ?>
        </p>
      </div>
      <div class="order-cell">
        <label for="sira-<?= panel_e($kayit['id']) ?>">Sıra</label>
        <input type="number" id="sira-<?= panel_e($kayit['id']) ?>" name="order[<?= panel_e($kayit['id']) ?>]"
               min="0" max="9999" value="<?= (int)($kayit['order'] ?? 0) ?>">
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
