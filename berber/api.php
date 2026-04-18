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

function getActiveShopContext(PDO $pdo, int $userId, bool $mustBeOwner = false): array|false {
    $shopId = $_SESSION['active_shop_id'] ?? 0;
    if (!$shopId) return false;

    $stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ?');
    $stmt->execute([$shopId]);
    $shop = $stmt->fetch();
    
    if (!$shop) return false;

    if ($shop['owner_id'] == $userId) return $shop;

    if ($mustBeOwner) return false; // Patron değil yetkisiz

    $stmt = $pdo->prepare('SELECT 1 FROM shop_employees WHERE shop_id = ? AND employee_id = ?');
    $stmt->execute([$shopId, $userId]);
    if ($stmt->fetch()) return $shop;

    return false;
}

function switchShop(): void {
    $shopId = (int)($_POST['shop_id'] ?? 0);
    if ($shopId) {
        $_SESSION['active_shop_id'] = $shopId;
    }
    echo json_encode(['success' => true]);
    exit;
}

// Kullanıcı bilgisi
function getUser(PDO $pdo, int $userId): array|false {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

match ($action) {
    'switch_shop'             => switchShop(),
    'save_shop'               => saveShop($pdo, $userId),
    'add_service'             => addService($pdo, $userId),
    'edit_service'            => editService($pdo, $userId),
    'delete_service'          => deleteService($pdo, $userId),
    'search_employee'         => searchEmployee($pdo, $userId),
    'add_employee'            => addEmployee($pdo, $userId),
    'remove_employee'         => removeEmployee($pdo, $userId),
    'update_appointment'      => updateAppointment($pdo, $userId),
    'add_walkin'              => addWalkin($pdo, $userId),
    'activate_plus'           => activatePlus($pdo, $userId),
    default                   => respond(false, 'Geçersiz işlem.')
};

// ============================================================

function saveShop(PDO $pdo, int $userId): void {
    $name       = trim($_POST['shop_name'] ?? '');
    $cityId     = !empty($_POST['city_id']) ? (int)$_POST['city_id'] : null;
    $districtId = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
    $address    = trim($_POST['address'] ?? '');
    $isNew      = !empty($_POST['is_new']);

    if (!$name) respond(false, 'Dükkan adı zorunludur.');

    // Kullanıcının hiç aktif dükkanı yoksa bu ilk dükkanıdır — is_new olarak işle
    $hasNoActiveShop = empty($_SESSION['active_shop_id']);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM shops WHERE owner_id = ?');
    $stmt->execute([$userId]);
    $ownedShopCount = (int)$stmt->fetchColumn();

    if ($isNew || ($hasNoActiveShop && $ownedShopCount === 0)) {
        // Yeni dükkan oluştur ve kullanıcıyı Patron yap
        $stmt = $pdo->prepare('INSERT INTO shops (owner_id, shop_name, city_id, district_id, address) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $name, $cityId, $districtId, $address]);
        $newShopId = (int)$pdo->lastInsertId();
        $_SESSION['active_shop_id'] = $newShopId;
        respond(true, 'Dükkan başarıyla oluşturuldu! Artık patronsunuz.', ['shop_id' => $newShopId]);
    } else {
        $shop = getActiveShopContext($pdo, $userId, true);
        if ($shop) {
            $stmt = $pdo->prepare('UPDATE shops SET shop_name=?, city_id=?, district_id=?, address=? WHERE id=? AND owner_id=?');
            $stmt->execute([$name, $cityId, $districtId, $address, $shop['id'], $userId]);
            respond(true, 'Dükkan bilgileri güncellendi.');
        } else {
            respond(false, 'İşlem yetkisiz. Sadece patronlar bu dükkanı düzenleyebilir.');
        }
    }
}

function addService(PDO $pdo, int $userId): void {
    $shop = getActiveShopContext($pdo, $userId, true);
    if (!$shop) respond(false, 'Yetkisiz erişim. Bu işlem sadece dükkan sahibi tarafından yapılabilir.');

    $name     = trim($_POST['service_name'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_minutes'] ?? 30);

    if (!$name)          respond(false, 'Hizmet adı zorunludur.');
    if (mb_strlen($name) > 100) respond(false, 'Hizmet adı en fazla 100 karakter olabilir.');
    if ($price < 0)      respond(false, 'Fiyat negatif olamaz.');
    if ($price > 10000)  respond(false, 'Fiyat 10.000 TL üzerinde olamaz.');
    if ($duration < 5)   respond(false, 'Süre en az 5 dakika olmalıdır.');
    if ($duration > 480) respond(false, 'Süre 8 saati (480 dk) geçemez.');

    $stmt = $pdo->prepare('INSERT INTO services (shop_id, service_name, price, duration_minutes) VALUES (?,?,?,?)');
    $stmt->execute([$shop['id'], $name, $price, $duration]);
    respond(true, 'Hizmet eklendi.', ['id' => (int)$pdo->lastInsertId()]);
}

function editService(PDO $pdo, int $userId): void {
    $shop = getActiveShopContext($pdo, $userId, true);
    if (!$shop) respond(false, 'Yetkisiz erişim.');

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
    $shop = getActiveShopContext($pdo, $userId, true);
    if (!$shop) respond(false, 'Yetkisiz erişim.');

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

    // Çalışanın bu dükkanla ilişkisi var mı?
    $stmt = $pdo->prepare('SELECT id FROM shop_employees WHERE shop_id = ? AND employee_id = ?');
    $stmt->execute([$shop['id'], $employeeId]);
    if (!$stmt->fetch()) respond(false, 'Bu çalışan dükkanınızda kayıtlı değil.');

    $stmt = $pdo->prepare('DELETE FROM shop_employees WHERE shop_id = ? AND employee_id = ?');
    $stmt->execute([$shop['id'], $employeeId]);
    respond(true, 'Çalışan kaldırıldı.');
}

function updateAppointment(PDO $pdo, int $userId): void {
    // Hem patron hem çalışan kendi bulunduğu dükkandaki randevuyu güncelleyebilir (eğer kendisine aitse veya patronsa)
    $shop = getActiveShopContext($pdo, $userId, false);
    if (!$shop) respond(false, 'Dükkan bulunamadı.');

    $id     = (int)($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$id) respond(false, 'Randevu ID eksik.');
    if (!in_array($status, ['bekliyor','onaylandi','tamamlandi','iptal'], true)) respond(false, 'Geçersiz durum.');

    // Sahiplik kontrolü + randevu zamanı al
    $stmt = $pdo->prepare('SELECT id, appointment_time FROM appointments WHERE id = ? AND shop_id = ?');
    $stmt->execute([$id, $shop['id']]);
    $appt = $stmt->fetch();
    if (!$appt) respond(false, 'Randevu bulunamadı.');

    // İptal işlemi için patron/çalışan serbesttir (Müşteri paneli kısıtlamaya tabidir).

    $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    respond(true, 'Randevu güncellendi.');
}

function addWalkin(PDO $pdo, int $userId): void {
    $user = getUser($pdo, $userId);
    if (!$user['is_plus']) respond(false, 'Bu özellik Plus üyelere özeldir.');

    $shop = getOwnShop($pdo, $userId);
    if (!$shop) respond(false, 'Önce bir dükkan oluşturun.');

    $firstName  = trim($_POST['first_name'] ?? '');
    $lastName   = trim($_POST['last_name']  ?? '');
    $serviceId  = (int)($_POST['service_id']  ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $date       = trim($_POST['date'] ?? '');
    $time       = trim($_POST['time'] ?? '');

    if (!$firstName || !$lastName) respond(false, 'İsim ve soyisim zorunludur.');
    if (!$serviceId)  respond(false, 'Hizmet seçiniz.');
    if (!$employeeId) respond(false, 'Personel seçiniz.');
    if (!$date || !$time) respond(false, 'Tarih ve saat zorunludur.');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) respond(false, 'Geçersiz tarih formatı.');
    if (!preg_match('/^\d{2}:\d{2}$/', $time))        respond(false, 'Geçersiz saat formatı.');

    $datetime = $date . ' ' . $time . ':00';

    // Hizmet bu dükkana mı ait?
    $stmt = $pdo->prepare('SELECT id, price, duration_minutes FROM services WHERE id = ? AND shop_id = ?');
    $stmt->execute([$serviceId, $shop['id']]);
    $service = $stmt->fetch();
    if (!$service) respond(false, 'Hizmet bulunamadı.');

    // Personel bu dükkanda mı çalışıyor? (sahip dahil)
    $stmt = $pdo->prepare("
        SELECT u.id FROM users u
        WHERE u.id = ? AND (
            u.id = ? OR
            EXISTS (SELECT 1 FROM shop_employees se WHERE se.shop_id = ? AND se.employee_id = u.id)
        )
    ");
    $stmt->execute([$employeeId, $userId, $shop['id']]);
    if (!$stmt->fetch()) respond(false, 'Personel bulunamadı.');

    $walkinName = $firstName . ' ' . $lastName;

    $stmt = $pdo->prepare("
        INSERT INTO appointments
            (customer_id, walkin_name, shop_id, employee_id, service_id, appointment_time, price_at_that_time, total_duration, status)
        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'tamamlandi')
    ");
    $stmt->execute([
        $walkinName,
        $shop['id'],
        $employeeId,
        $serviceId,
        $datetime,
        $service['price'],
        $service['duration_minutes'],
    ]);
    respond(true, 'Yüz yüze randevu oluşturuldu.', ['id' => (int)$pdo->lastInsertId()]);
}

function activatePlus(PDO $pdo, int $userId): void {
    $user = getUser($pdo, $userId);
    if ($user['is_plus']) respond(false, 'Zaten Plus üyesiniz.');
    $pdo->prepare('UPDATE users SET is_plus = 1 WHERE id = ?')->execute([$userId]);
    respond(true, 'Plus üyeliğiniz aktifleştirildi.');
}

// ---- Helper ----
function respond(bool $success, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
