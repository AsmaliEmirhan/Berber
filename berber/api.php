<?php
// ============================================================
//  Berber API — AJAX Handler (JSON)
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'berber') {
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

// Dükkan sahibi mi kontrolü (çoğu işlem için)
function getOwnShop(PDO $pdo, int $userId): array|false {
    $stmt = $pdo->prepare('SELECT * FROM shops WHERE owner_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Kullanıcı bilgisi
function getUser(PDO $pdo, int $userId): array|false {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

match ($action) {
    'save_shop'               => saveShop($pdo, $userId),
    'add_service'             => addService($pdo, $userId),
    'edit_service'            => editService($pdo, $userId),
    'delete_service'          => deleteService($pdo, $userId),
    'search_employee'         => searchEmployee($pdo, $userId),
    'add_employee'            => addEmployee($pdo, $userId),
    'remove_employee'         => removeEmployee($pdo, $userId),
    'update_appointment'      => updateAppointment($pdo, $userId),
    default                   => respond(false, 'Geçersiz işlem.')
};

// ============================================================

function saveShop(PDO $pdo, int $userId): void {
    $name       = trim($_POST['shop_name'] ?? '');
    $districtId = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
    $address    = trim($_POST['address'] ?? '');

    if (!$name) respond(false, 'Dükkan adı zorunludur.');

    $shop = getOwnShop($pdo, $userId);

    if ($shop) {
        $stmt = $pdo->prepare('UPDATE shops SET shop_name=?, district_id=?, address=? WHERE owner_id=?');
        $stmt->execute([$name, $districtId, $address, $userId]);
        respond(true, 'Dükkan bilgileri güncellendi.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO shops (owner_id, shop_name, district_id, address) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $name, $districtId, $address]);
        respond(true, 'Dükkan başarıyla oluşturuldu!', ['shop_id' => (int)$pdo->lastInsertId()]);
    }
}

function addService(PDO $pdo, int $userId): void {
    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Önce bir dükkan oluşturun.');

    $name     = trim($_POST['service_name'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_minutes'] ?? 30);

    if (!$name)       respond(false, 'Hizmet adı zorunludur.');
    if ($price < 0)   respond(false, 'Fiyat negatif olamaz.');
    if ($duration < 5) respond(false, 'Süre en az 5 dakika olmalıdır.');

    $stmt = $pdo->prepare('INSERT INTO services (shop_id, service_name, price, duration_minutes) VALUES (?,?,?,?)');
    $stmt->execute([$shop['id'], $name, $price, $duration]);
    respond(true, 'Hizmet eklendi.', ['id' => (int)$pdo->lastInsertId()]);
}

function editService(PDO $pdo, int $userId): void {
    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Dükkan bulunamadı.');

    $id       = (int)($_POST['service_id'] ?? 0);
    $name     = trim($_POST['service_name'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_minutes'] ?? 30);

    if (!$id || !$name) respond(false, 'Eksik bilgi.');

    // Sahiplik kontrolü
    $stmt = $pdo->prepare('SELECT id FROM services WHERE id = ? AND shop_id = ?');
    $stmt->execute([$id, $shop['id']]);
    if (!$stmt->fetch()) respond(false, 'Hizmet bulunamadı.');

    $stmt = $pdo->prepare('UPDATE services SET service_name=?, price=?, duration_minutes=? WHERE id=?');
    $stmt->execute([$name, $price, $duration, $id]);
    respond(true, 'Hizmet güncellendi.');
}

function deleteService(PDO $pdo, int $userId): void {
    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Dükkan bulunamadı.');

    $id = (int)($_POST['service_id'] ?? 0);
    if (!$id) respond(false, 'Hizmet ID eksik.');

    $stmt = $pdo->prepare('SELECT id FROM services WHERE id = ? AND shop_id = ?');
    $stmt->execute([$id, $shop['id']]);
    if (!$stmt->fetch()) respond(false, 'Hizmet bulunamadı.');

    $stmt = $pdo->prepare('DELETE FROM services WHERE id = ?');
    $stmt->execute([$id]);
    respond(true, 'Hizmet silindi.');
}

function searchEmployee(PDO $pdo, int $userId): void {
    $user = getUser($pdo, $userId);
    if (!$user['is_plus']) respond(false, 'Bu özellik Plus üyelere özeldir.');

    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Önce bir dükkan oluşturun.');

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) respond(false, 'Geçerli bir e-posta girin.');
    if ($email === $user['email']) respond(false, 'Kendinizi ekleyemezsiniz.');

    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND role = 'berber'");
    $stmt->execute([$email]);
    $found = $stmt->fetch();
    if (!$found) respond(false, 'Bu e-postada kayıtlı berber bulunamadı.');

    // Zaten ekli mi?
    $stmt = $pdo->prepare('SELECT id FROM shop_employees WHERE shop_id = ? AND employee_id = ?');
    $stmt->execute([$shop['id'], $found['id']]);
    if ($stmt->fetch()) respond(false, 'Bu berber zaten çalışanlarınız arasında.');

    respond(true, 'Berber bulundu.', ['employee' => $found]);
}

function addEmployee(PDO $pdo, int $userId): void {
    $user = getUser($pdo, $userId);
    if (!$user['is_plus']) respond(false, 'Bu özellik Plus üyelere özeldir.');

    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Dükkan bulunamadı.');

    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if (!$employeeId) respond(false, 'Çalışan ID eksik.');
    if ($employeeId === $userId) respond(false, 'Kendinizi ekleyemezsiniz.');

    // Var mı?
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'berber'");
    $stmt->execute([$employeeId]);
    if (!$stmt->fetch()) respond(false, 'Berber bulunamadı.');

    // Duplicate check
    $stmt = $pdo->prepare('SELECT id FROM shop_employees WHERE shop_id = ? AND employee_id = ?');
    $stmt->execute([$shop['id'], $employeeId]);
    if ($stmt->fetch()) respond(false, 'Zaten ekli.');

    $stmt = $pdo->prepare('INSERT INTO shop_employees (shop_id, employee_id) VALUES (?,?)');
    $stmt->execute([$shop['id'], $employeeId]);
    respond(true, 'Çalışan eklendi.');
}

function removeEmployee(PDO $pdo, int $userId): void {
    $user = getUser($pdo, $userId);
    if (!$user['is_plus']) respond(false, 'Bu özellik Plus üyelere özeldir.');

    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Dükkan bulunamadı.');

    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if (!$employeeId) respond(false, 'Çalışan ID eksik.');

    $stmt = $pdo->prepare('DELETE FROM shop_employees WHERE shop_id = ? AND employee_id = ?');
    $stmt->execute([$shop['id'], $employeeId]);
    respond(true, 'Çalışan kaldırıldı.');
}

function updateAppointment(PDO $pdo, int $userId): void {
    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Dükkan bulunamadı.');

    $id     = (int)($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$id) respond(false, 'Randevu ID eksik.');
    if (!in_array($status, ['bekliyor','tamamlandi','iptal'], true)) respond(false, 'Geçersiz durum.');

    // Sahiplik kontrolü
    $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id = ? AND shop_id = ?');
    $stmt->execute([$id, $shop['id']]);
    if (!$stmt->fetch()) respond(false, 'Randevu bulunamadı.');

    $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    respond(true, 'Randevu güncellendi.');
}

// ---- Helper ----
function respond(bool $success, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
