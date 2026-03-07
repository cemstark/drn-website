<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ekin@drnekinotoizmit.com';
    $mail->Password   = 'Qwe123xyzww*';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('ekin@drnekinotoizmit.com', 'DRN.EKİN OTO');
    $mail->addAddress('ekin@drnekinotoizmit.com');
    $mail->isHTML(false);
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'Bu bir test mailidir.';

    $mail->send();
    echo '<p style="color:green; font-size:20px;">✓ Mail başarıyla gönderildi!</p>';
} catch (Exception $e) {
    echo '<p style="color:red; font-size:18px;">Hata: ' . htmlspecialchars($mail->ErrorInfo) . '</p>';
}
?>
