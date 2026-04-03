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

    // Hizmet süresi
    $stmt = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    $duration = (int)($stmt->fetchColumn() ?: 30);

    // O günkü mevcut randevular (bu çalışan için, iptal hariç)
    $stmt = $pdo->prepare("
        SELECT a.appointment_time, sv.duration_minutes
        FROM appointments a
        JOIN services sv ON a.service_id = sv.id
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

    // Hizmet fiyatını çek
    $stmt = $pdo->prepare('SELECT price FROM services WHERE id = ? AND shop_id = ?');
    $stmt->execute([$serviceId, $shopId]);
    $svc = $stmt->fetch();
    if (!$svc) respond(false, 'Hizmet bulunamadı.');

    // Çalışan bu dükkanla ilişkili mi?
    $stmt = $pdo->prepare("
        SELECT 1 FROM shops WHERE id = ? AND owner_id = ?
        UNION
        SELECT 1 FROM shop_employees WHERE shop_id = ? AND employee_id = ?
    ");
    $stmt->execute([$shopId, $employeeId, $shopId, $employeeId]);
    if (!$stmt->fetch()) respond(false, 'Çalışan bu dükkana ait değil.');

    // Çakışma kontrolü (son kontrol)
    $duration = (int)$pdo->prepare('SELECT duration_minutes FROM services WHERE id = ?')
        ->execute([$serviceId]) ? 30 : 30;

    $stmt2 = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = ?');
    $stmt2->execute([$serviceId]);
    $duration = (int)$stmt2->fetchColumn();

    $slotEnd = date('Y-m-d H:i:s', $ts + $duration * 60);

    $stmt = $pdo->prepare("
        SELECT a.id FROM appointments a
        JOIN services sv ON a.service_id = sv.id
        WHERE a.employee_id = ? AND a.status != 'iptal'
          AND a.appointment_time < ?
          AND DATE_ADD(a.appointment_time, INTERVAL sv.duration_minutes MINUTE) > ?
    ");
    $stmt->execute([$employeeId, $slotEnd, $datetime]);
    if ($stmt->fetch()) respond(false, 'Bu saat dolu, lütfen başka bir saat seçin.');

    // Randevuyu kaydet
    $stmt = $pdo->prepare("
        INSERT INTO appointments (customer_id, shop_id, employee_id, service_id, appointment_time, price_at_that_time)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->execute([$userId, $shopId, $employeeId, $serviceId, $datetime, $svc['price']]);

    respond(true, 'Randevunuz başarıyla alındı!', ['id' => (int)$pdo->lastInsertId()]);
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

function respond(bool $success, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
