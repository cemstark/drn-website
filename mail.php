<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
    exit;
}

$to      = 'ekin@drnekinotoizmit.com';
$type    = isset($_POST['type']) ? trim($_POST['type']) : '';

function clean($val) {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

if ($type === 'randevu') {

    $ad_soyad = clean($_POST['ad_soyad'] ?? '');
    $telefon  = clean($_POST['telefon'] ?? '');
    $eposta   = clean($_POST['eposta'] ?? '');
    $marka    = clean($_POST['marka'] ?? '');
    $model    = clean($_POST['model'] ?? '');
    $yil      = clean($_POST['yil'] ?? '');
    $plaka    = clean($_POST['plaka'] ?? '');
    $hizmet   = clean($_POST['hizmet'] ?? '');
    $tarih    = clean($_POST['tarih'] ?? '');
    $saat     = clean($_POST['saat'] ?? '');
    $notlar   = clean($_POST['notlar'] ?? '');

    if (!$ad_soyad || !$telefon || !$marka || !$model || !$hizmet || !$tarih) {
        echo json_encode(['success' => false, 'message' => 'Zorunlu alanlar eksik.']);
        exit;
    }

    $subject = "Yeni Randevu Talebi – $ad_soyad";
    $body    = "YENİ RANDEVU TALEBİ\n";
    $body   .= str_repeat("=", 40) . "\n\n";
    $body   .= "Ad Soyad   : $ad_soyad\n";
    $body   .= "Telefon    : $telefon\n";
    $body   .= "E-Posta    : $eposta\n\n";
    $body   .= "Araç Marka : $marka\n";
    $body   .= "Araç Model : $model\n";
    $body   .= "Model Yılı : $yil\n";
    $body   .= "Plaka      : $plaka\n\n";
    $body   .= "İstenen Hizmet : $hizmet\n";
    $body   .= "Tercih Tarih   : $tarih\n";
    $body   .= "Tercih Saat    : $saat\n\n";
    $body   .= "Ek Notlar  :\n$notlar\n";

} elseif ($type === 'iletisim') {

    $ad_soyad = clean($_POST['ad_soyad'] ?? '');
    $telefon  = clean($_POST['telefon'] ?? '');
    $eposta   = clean($_POST['eposta'] ?? '');
    $konu     = clean($_POST['konu'] ?? '');
    $mesaj    = clean($_POST['mesaj'] ?? '');

    if (!$ad_soyad || !$telefon || !$konu || !$mesaj) {
        echo json_encode(['success' => false, 'message' => 'Zorunlu alanlar eksik.']);
        exit;
    }

    $subject = "Yeni Mesaj – $konu ($ad_soyad)";
    $body    = "YENİ İLETİŞİM MESAJI\n";
    $body   .= str_repeat("=", 40) . "\n\n";
    $body   .= "Ad Soyad : $ad_soyad\n";
    $body   .= "Telefon  : $telefon\n";
    $body   .= "E-Posta  : $eposta\n";
    $body   .= "Konu     : $konu\n\n";
    $body   .= "Mesaj    :\n$mesaj\n";

} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz form tipi.']);
    exit;
}

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "From: noreply@drnekinotoizmit.com\r\n";
$headers .= "Reply-To: $eposta\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mail gönderilemedi. Lütfen tekrar deneyin.']);
}
?>
