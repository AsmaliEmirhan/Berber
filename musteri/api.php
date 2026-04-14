<?php
// ============================================================
//  Müşteri API — AJAX Handler (JSON)
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'musteri') {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Yalnızca POST.']));
}

require_once __DIR__ . '/../config/db.php';

$pdo    = getPDO();
$userId = (int)$_SESSION['user_id'];
$action = trim($_POST['action'] ?? '');

match ($action) {
    'get_employees'      => getEmployees($pdo),
    'get_slots'          => getSlots($pdo),
    'book_appointment'   => bookAppointment($pdo, $userId),
    'cancel_appointment' => cancelAppointment($pdo, $userId),
    'submit_review'      => submitReview($pdo, $userId),
    default              => respond(false, 'Geçersiz işlem.')
};

// ============================================================

function getEmployees(PDO $pdo): void {
    $shopId = (int)($_POST['shop_id'] ?? 0);
    if (!$shopId) respond(false, 'Shop ID eksik.');

    // Dükkan sahibi + çalışanlar
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name
        FROM users u
        JOIN shops s ON s.owner_id = u.id
        WHERE s.id = ?
        UNION
        SELECT u.id, u.full_name
        FROM users u
        JOIN shop_employees se ON se.employee_id = u.id
        WHERE se.shop_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$shopId, $shopId]);
    $employees = $stmt->fetchAll();

    respond(true, 'OK', ['employees' => $employees]);
}

function getSlots(PDO $pdo): void {
    $shopId     = (int)($_POST['shop_id']     ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $serviceId  = (int)($_POST['service_id']  ?? 0);
    $date       = $_POST['date'] ?? '';

    if (!$shopId || !$employeeId || !$serviceId || !$date) {
        respond(false, 'Eksik parametre.');
    }

    // Tarih doğrulama
    $dateTs = strtotime($date);
    if (!$dateTs || $dateTs < strtotime('today')) {
        respond(false, 'Geçersiz tarih.');
    }
    $dateStr = date('Y-m-d', $dateTs);

    // Hizmet süresi (Ana hizmet) — shop_id kontrolü ile
    $stmt = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = ? AND shop_id = ?');
    $stmt->execute([$serviceId, $shopId]);
    $svcRow = $stmt->fetch();
    if (!$svcRow) respond(false, 'Hizmet bulunamadı.');
    $duration = (int)$svcRow['duration_minutes'];

    // Ekstra Hizmetler — shop_id kontrolü ile
    $extraIds = json_decode($_POST['extra_services'] ?? '[]', true);
    if (!empty($extraIds) && is_array($extraIds)) {
        $extraIds = array_map('intval', $extraIds);
        $inQuery  = implode(',', array_fill(0, count($extraIds), '?'));
        $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE shop_id = ? AND id IN ($inQuery)");
        $stmt->execute(array_merge([$shopId], $extraIds));
        foreach ($stmt->fetchAll() as $row) {
            $duration += (int)$row['duration_minutes'];
        }
    }

    // O günkü mevcut randevular (bu çalışan için, iptal hariç)
    // Artık 'total_duration' var, onu kullanıyoruz.
    $stmt = $pdo->prepare("
        SELECT a.appointment_time, a.total_duration as duration_minutes
        FROM appointments a
        WHERE a.employee_id = ? AND DATE(a.appointment_time) = ? AND a.status != 'iptal'
    ");
    $stmt->execute([$employeeId, $dateStr]);
    $booked = $stmt->fetchAll();

    // Slot üretimi: 09:00 – 19:00, her 30 dakika
    $slots    = [];
    $dayStart = strtotime($dateStr . ' 09:00:00');
    $dayEnd   = strtotime($dateStr . ' 19:00:00');
    $step     = 30 * 60; // 30 dk
    $now      = time();

    for ($t = $dayStart; $t + $duration * 60 <= $dayEnd; $t += $step) {
        $slotEnd   = $t + $duration * 60;
        $available = true;

        // Geçmiş slot kontrolü
        if ($t < $now) { $available = false; }

        // Çakışma kontrolü
        foreach ($booked as $b) {
            $bStart = strtotime($b['appointment_time']);
            $bEnd   = $bStart + (int)$b['duration_minutes'] * 60;
            if ($t < $bEnd && $slotEnd > $bStart) {
                $available = false;
                break;
            }
        }

        $slots[] = [
            'time'      => date('H:i', $t),
            'available' => $available,
        ];
    }

    respond(true, 'OK', ['slots' => $slots, 'duration' => $duration]);
}

function bookAppointment(PDO $pdo, int $userId): void {
    $shopId     = (int)($_POST['shop_id']     ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $serviceId  = (int)($_POST['service_id']  ?? 0);
    $datetime   = trim($_POST['appointment_time'] ?? '');

    if (!$shopId || !$employeeId || !$serviceId || !$datetime) {
        respond(false, 'Eksik bilgi.');
    }

    // Datetime doğrulama
    $ts = strtotime($datetime);
    if (!$ts || $ts < time()) respond(false, 'Geçersiz randevu zamanı.');

    $price = 0.0;
    $duration = 0;
    $notesArr = [];

    // Hizmet bilgisi (Ana)
    $stmt = $pdo->prepare('SELECT service_name, price, duration_minutes FROM services WHERE id = ? AND shop_id = ?');
    $stmt->execute([$serviceId, $shopId]);
    $mainSvc = $stmt->fetch();
    if (!$mainSvc) respond(false, 'Hizmet bulunamadı.');

    $price += (float)$mainSvc['price'];
    $duration += (int)$mainSvc['duration_minutes'];

    // Ekstra Hizmetler
    $extraIds = json_decode($_POST['extra_services'] ?? '[]', true);
    $extraIdsList = is_array($extraIds) ? array_map('intval', $extraIds) : [];
    
    if (!empty($extraIdsList)) {
        $inQuery = implode(',', array_fill(0, count($extraIdsList), '?'));
        $stmt = $pdo->prepare("SELECT service_name, price, duration_minutes FROM services WHERE shop_id = ? AND id IN ($inQuery)");
        $params = array_merge([$shopId], $extraIdsList);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $price += (float)$row['price'];
            $duration += (int)$row['duration_minutes'];
            $notesArr[] = '+ ' . $row['service_name'];
        }
    }

    $notes = empty($notesArr) ? null : implode("\n", $notesArr);

    // Çalışan bu dükkanla ilişkili mi?
    $stmt = $pdo->prepare("
        SELECT 1 FROM shops WHERE id = ? AND owner_id = ?
        UNION
        SELECT 1 FROM shop_employees WHERE shop_id = ? AND employee_id = ?
    ");
    $stmt->execute([$shopId, $employeeId, $shopId, $employeeId]);
    if (!$stmt->fetch()) respond(false, 'Çalışan bu dükkana ait değil.');

    // Çakışma kontrolü + kayıt — transaction ile race condition koruması
    $slotEnd = date('Y-m-d H:i:s', $ts + $duration * 60);

    try {
        $pdo->beginTransaction();

        // Kilitle: aynı çalışanın çakışan randevularını FOR UPDATE ile kilitle
        $stmt = $pdo->prepare("
            SELECT a.id FROM appointments a
            WHERE a.employee_id = ? AND a.status != 'iptal'
              AND a.appointment_time < ?
              AND DATE_ADD(a.appointment_time, INTERVAL a.total_duration MINUTE) > ?
            FOR UPDATE
        ");
        $stmt->execute([$employeeId, $slotEnd, $datetime]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            respond(false, 'Bu saat dolu veya seçilen işlemler toplam süreye sığmıyor. Lütfen başka bir saat seçin.');
        }

        // Randevuyu kaydet
        $stmt = $pdo->prepare("
            INSERT INTO appointments (customer_id, shop_id, employee_id, service_id, appointment_time, price_at_that_time, total_duration, notes)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$userId, $shopId, $employeeId, $serviceId, $datetime, $price, $duration, $notes]);
        $newId = (int)$pdo->lastInsertId();

        $pdo->commit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Randevu kaydedilemedi, lütfen tekrar deneyin.');
    }

    respond(true, 'Randevunuz başarıyla alındı!', ['id' => $newId]);
}

function cancelAppointment(PDO $pdo, int $userId): void {
    $id = (int)($_POST['appointment_id'] ?? 0);
    if (!$id) respond(false, 'ID eksik.');

    // Sahiplik + durum kontrolü
    $stmt = $pdo->prepare("SELECT status FROM appointments WHERE id = ? AND customer_id = ?");
    $stmt->execute([$id, $userId]);
    $app = $stmt->fetch();

    if (!$app)                       respond(false, 'Randevu bulunamadı.');
    if ($app['status'] !== 'bekliyor') respond(false, 'Sadece bekleyen randevular iptal edilebilir.');

    $stmt = $pdo->prepare("UPDATE appointments SET status = 'iptal' WHERE id = ?");
    $stmt->execute([$id]);
    respond(true, 'Randevunuz iptal edildi.');
}


function submitReview(PDO $pdo, int $userId): void {
    $appId = (int)($_POST['appointment_id'] ?? 0);
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (!$appId || !$shopId || $rating < 1 || $rating > 5) {
        respond(false, 'Hatalı puanlama veya eksik bilgi.');
    }

    // Verify ownership and status
    $stmt = $pdo->prepare("SELECT status FROM appointments WHERE id = ? AND customer_id = ? AND shop_id = ?");
    $stmt->execute([$appId, $userId, $shopId]);
    $app = $stmt->fetch();

    if (!$app) respond(false, 'Randevu bulunamadı.');
    if ($app['status'] !== 'tamamlandi') respond(false, 'Sadece tamamlanan hizmetlere yorum yapılabilir.');

    // Duplicate check
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE appointment_id = ?");
    $stmt->execute([$appId]);
    if ($stmt->fetch()) respond(false, 'Bu randevu için zaten yorum yapılmış.');

    // Insert
    $stmt = $pdo->prepare("INSERT INTO reviews (appointment_id, shop_id, customer_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$appId, $shopId, $userId, $rating, $comment])) {
        respond(true, 'Değerlendirmeniz başarıyla kaydedilmiştir. Teşekkür ederiz!');
    } else {
        respond(false, 'Sistemsel bir hata oluştu.');
    }
}

function respond(bool $success, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
