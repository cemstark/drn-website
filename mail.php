<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
    exit;
}

function clean($val) {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$type = isset($_POST['type']) ? trim($_POST['type']) : '';

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
    $body = "
    <h2 style='color:#c0392b;'>Yeni Randevu Talebi</h2>
    <table style='border-collapse:collapse; width:100%; font-family:Arial,sans-serif;'>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Ad Soyad</strong></td><td style='padding:8px; border:1px solid #ddd;'>$ad_soyad</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Telefon</strong></td><td style='padding:8px; border:1px solid #ddd;'>$telefon</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>E-Posta</strong></td><td style='padding:8px; border:1px solid #ddd;'>$eposta</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Araç Marka</strong></td><td style='padding:8px; border:1px solid #ddd;'>$marka</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Araç Model</strong></td><td style='padding:8px; border:1px solid #ddd;'>$model</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Model Yılı</strong></td><td style='padding:8px; border:1px solid #ddd;'>$yil</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Plaka</strong></td><td style='padding:8px; border:1px solid #ddd;'>$plaka</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>İstenen Hizmet</strong></td><td style='padding:8px; border:1px solid #ddd;'>$hizmet</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Tercih Tarih</strong></td><td style='padding:8px; border:1px solid #ddd;'>$tarih</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Tercih Saat</strong></td><td style='padding:8px; border:1px solid #ddd;'>$saat</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Ek Notlar</strong></td><td style='padding:8px; border:1px solid #ddd;'>$notlar</td></tr>
    </table>
    ";

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
    $body = "
    <h2 style='color:#c0392b;'>Yeni İletişim Mesajı</h2>
    <table style='border-collapse:collapse; width:100%; font-family:Arial,sans-serif;'>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Ad Soyad</strong></td><td style='padding:8px; border:1px solid #ddd;'>$ad_soyad</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Telefon</strong></td><td style='padding:8px; border:1px solid #ddd;'>$telefon</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>E-Posta</strong></td><td style='padding:8px; border:1px solid #ddd;'>$eposta</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Konu</strong></td><td style='padding:8px; border:1px solid #ddd;'>$konu</td></tr>
      <tr><td style='padding:8px; border:1px solid #ddd; background:#f9f9f9;'><strong>Mesaj</strong></td><td style='padding:8px; border:1px solid #ddd;'>$mesaj</td></tr>
    </table>
    ";

} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz form tipi.']);
    exit;
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ekin@drnekinotoizmit.com';
    $mail->Password   = 'Qwe123xyzww*';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('ekin@drnekinotoizmit.com', 'DRN.EKİN OTO');
    $mail->addAddress('ekin@drnekinotoizmit.com', 'DRN.EKİN OTO');
    if (!empty($eposta)) $mail->addReplyTo($eposta, $ad_soyad);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mail gönderilemedi: ' . $mail->ErrorInfo]);
}
?>
