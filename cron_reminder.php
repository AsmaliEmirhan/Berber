<?php
// ============================================================
//  cron_reminder.php — Randevu Hatırlatma Maili (CRON)
//
//  Her 5 dakikada bir çalıştırılmalı:
//  Linux/cPanel:
//      */5 * * * * php /var/www/html/berber/cron_reminder.php >> /dev/null 2>&1
//
//  Windows Task Scheduler:
//      Program : php.exe
//      Argüman : C:\xampp\htdocs\berber\cron_reminder.php
//      Tetikle : Her 5 dakikada bir
// ============================================================

declare(strict_types=1);

// CLI dışından çalıştırılmasını engelle (güvenlik)
if (PHP_SAPI !== 'cli' && !isset($_GET['cron_secret'])) {
    http_response_code(403);
    exit('Forbidden');
}

// Geliştirme sırasında URL üzerinden test için:
// http://localhost/berber/cron_reminder.php?cron_secret=BURAYA_GIZLI_KOD_YAZ
define('CRON_SECRET', 'gizli-cron-anahtar-2024'); // Değiştirin!
if (PHP_SAPI !== 'cli' && ($_GET['cron_secret'] ?? '') !== CRON_SECRET) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ---- Log fonksiyonu ----
function cronLog(string $message): void {
    $logDir = dirname(CRON_LOG_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(CRON_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
    echo $line; // CLI çıktısı
}

cronLog('=== CRON Başladı ===');

// ---- Veritabanı bağlantısı ----
$pdo = getPDO();

// ---- 10 dakika içindeki bekleyen randevuları bul ----
// Şu an ile şu an + 12 dk arası (5 dk CRON + 2 dk buffer)
$now          = new DateTimeImmutable('now');
$windowStart  = $now->format('Y-m-d H:i:s');
$windowEnd    = $now->modify('+12 minutes')->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.appointment_time,
        a.price_at_that_time,
        u.full_name       AS customer_name,
        u.email           AS customer_email,
        sh.shop_name,
        sh.address        AS shop_address,
        d.name            AS district_name,
        sv.service_name,
        sv.duration_minutes,
        e.full_name       AS employee_name
    FROM appointments a
    JOIN users     u  ON a.customer_id  = u.id
    JOIN shops     sh ON a.shop_id      = sh.id
    JOIN services  sv ON a.service_id   = sv.id
    JOIN users     e  ON a.employee_id  = e.id
    LEFT JOIN districts d ON sh.district_id = d.id
    WHERE a.status         = 'bekliyor'
      AND a.reminder_sent  = FALSE
      AND a.appointment_time BETWEEN ? AND ?
");
$stmt->execute([$windowStart, $windowEnd]);
$appointments = $stmt->fetchAll();

cronLog(count($appointments) . ' randevu hatırlatma gönderilecek.');

if (empty($appointments)) {
    cronLog('=== CRON Bitti (randevu yok) ===');
    exit(0);
}

// ---- Her randevu için mail gönder ----
$sentCount  = 0;
$errorCount = 0;

foreach ($appointments as $appt) {
    $success = sendReminderMail($appt);

    if ($success) {
        // reminder_sent = TRUE yap
        $upd = $pdo->prepare('UPDATE appointments SET reminder_sent = TRUE WHERE id = ?');
        $upd->execute([$appt['id']]);
        $sentCount++;
        cronLog("✓ Mail gönderildi → #{$appt['id']} - {$appt['customer_email']}");
    } else {
        $errorCount++;
        cronLog("✗ Mail BAŞARISIZ → #{$appt['id']} - {$appt['customer_email']}");
    }
}

cronLog("=== CRON Bitti | Gönderilen: {$sentCount} | Hata: {$errorCount} ===");
exit(0);


// ============================================================
//  Mail Gönderme Fonksiyonu
// ============================================================
function sendReminderMail(array $appt): bool {
    $mail = new PHPMailer(true);

    try {
        // SMTP Ayarları
        $mail->isSMTP();
        $mail->Host        = MAIL_HOST;
        $mail->SMTPAuth    = true;
        $mail->Username    = MAIL_USERNAME;
        $mail->Password    = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = MAIL_PORT;
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';

        // Gönderici & Alıcı
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($appt['customer_email'], $appt['customer_name']);

        // Konu & İçerik
        $mail->isHTML(true);
        $mail->Subject = '⏰ Randevunuz 10 Dakika Sonra! — ' . $appt['shop_name'];
        $mail->Body    = buildEmailHtml($appt);
        $mail->AltBody = buildEmailText($appt);

        $mail->send();
        return true;

    } catch (Exception $e) {
        cronLog('PHPMailer Hatası: ' . $mail->ErrorInfo);
        return false;
    }
}


// ============================================================
//  HTML E-posta Şablonu
// ============================================================
function buildEmailHtml(array $a): string {
    $appointmentTime = date('d M Y, H:i', strtotime($a['appointment_time']));
    $shopLocation    = trim(($a['district_name'] ?? '') . ' ' . ($a['shop_address'] ?? ''));

    return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Randevu Hatırlatma</title>
</head>
<body style="margin:0;padding:0;background:#0f0f17;font-family:'Segoe UI',Arial,sans-serif;color:#f1f1f5;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f17;padding:40px 16px;">
<tr><td align="center">

  <!-- Kart -->
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#16161f;border:1px solid rgba(255,255,255,0.1);border-radius:20px;overflow:hidden;">

    <!-- Header -->
    <tr>
      <td style="background:linear-gradient(135deg,#8b5cf6,#6366f1);padding:32px 32px 28px;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:8px;">✂️</div>
        <h1 style="margin:0;font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-0.5px;">BerberBook</h1>
        <p style="margin:6px 0 0;font-size:0.85rem;color:rgba(255,255,255,0.75);">Randevu Hatırlatma</p>
      </td>
    </tr>

    <!-- Alert Banner -->
    <tr>
      <td style="background:#f59e0b;padding:14px 32px;text-align:center;">
        <p style="margin:0;font-size:1rem;font-weight:700;color:#fff;">
          ⏰ Randevunuz 10 Dakika Sonra Başlıyor!
        </p>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:28px 32px;">

        <p style="margin:0 0 24px;font-size:0.95rem;color:#d1d5db;">
          Merhaba <strong style="color:#f1f1f5;">{$a['customer_name']}</strong>,<br>
          Aşağıdaki randevunuzun zamanı yaklaşıyor. Lütfen hazır olun!
        </p>

        <!-- Detay Kutusu -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#1e1e2e;border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;">

          <tr>
            <td style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07);">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#9ca3af;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:40%;">🏪 Dükkan</td>
                  <td style="color:#f1f1f5;font-size:0.88rem;font-weight:600;">{$a['shop_name']}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07);">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#9ca3af;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:40%;">✂️ Hizmet</td>
                  <td style="color:#f1f1f5;font-size:0.88rem;">{$a['service_name']} <span style="color:#6b7280;font-size:0.78rem;">({$a['duration_minutes']} dk)</span></td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07);">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#9ca3af;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:40%;">👤 Personel</td>
                  <td style="color:#f1f1f5;font-size:0.88rem;">{$a['employee_name']}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07);">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#9ca3af;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:40%;">📅 Tarih & Saat</td>
                  <td style="color:#f59e0b;font-size:0.92rem;font-weight:700;">{$appointmentTime}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07);">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#9ca3af;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:40%;">📍 Konum</td>
                  <td style="color:#f1f1f5;font-size:0.88rem;">{$shopLocation}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 18px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="color:#9ca3af;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:40%;">💰 Ücret</td>
                  <td style="color:#10b981;font-size:1rem;font-weight:700;">₺{$a['price_at_that_time']}</td>
                </tr>
              </table>
            </td>
          </tr>

        </table><!-- /detay kutusu -->

        <!-- Bilgi notu -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="margin-top:20px;background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:10px;">
          <tr>
            <td style="padding:14px 16px;font-size:0.82rem;color:#c4b5fd;line-height:1.5;">
              💡 Randevunuza geç kalacaksanız veya iptal etmek istiyorsanız lütfen dükkanı önceden bilgilendirin.
            </td>
          </tr>
        </table>

      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:20px 32px;border-top:1px solid rgba(255,255,255,0.07);text-align:center;">
        <p style="margin:0;font-size:0.75rem;color:#6b7280;">
          Bu mail <strong style="color:#9ca3af;">BerberBook</strong> tarafından otomatik gönderilmiştir.<br>
          Lütfen bu maili yanıtlamayın.
        </p>
      </td>
    </tr>

  </table><!-- /kart -->

</td></tr>
</table>

</body>
</html>
HTML;
}


// ============================================================
//  Düz Metin (Fallback) E-posta
// ============================================================
function buildEmailText(array $a): string {
    $appointmentTime = date('d M Y, H:i', strtotime($a['appointment_time']));
    return <<<TEXT
BerberBook — Randevu Hatırlatma
================================

Merhaba {$a['customer_name']},

Randevunuz 10 dakika sonra başlıyor!

Dükkan  : {$a['shop_name']}
Hizmet  : {$a['service_name']} ({$a['duration_minutes']} dk)
Personel: {$a['employee_name']}
Tarih   : {$appointmentTime}
Ücret   : ₺{$a['price_at_that_time']}

İyi günler dileriz,
BerberBook
TEXT;
}
