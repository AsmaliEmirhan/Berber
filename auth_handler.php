<?php
// ============================================================
//  Auth Handler — Kayıt & Giriş (JSON API)
// ============================================================

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';

// Yalnızca POST kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Yalnızca POST istekleri kabul edilir.']));
}

$action = trim($_POST['action'] ?? '');

match ($action) {
    'register' => handleRegister(),
    'login'    => handleLogin(),
    default    => badRequest('Geçersiz işlem.')
};


// ------------------------------------------------------------
//  Kayıt
// ------------------------------------------------------------
function handleRegister(): void {
    $pdo  = getPDO();

    $role      = in_array($_POST['role'] ?? '', ['musteri','berber'], true) ? $_POST['role'] : null;
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password  = $_POST['password'] ?? '';
    $districtId = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;

    // Doğrulama
    if (!$role)                    respond(false, 'Geçersiz kullanıcı tipi.');
    if (!$firstName || !$lastName) respond(false, 'Ad ve soyad zorunludur.');
    if (!$email)                   respond(false, 'Geçerli bir e-posta adresi girin.');
    if (strlen($password) < 6)     respond(false, 'Şifre en az 6 karakter olmalıdır.');

    // E-posta benzersizlik kontrolü
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respond(false, 'Bu e-posta adresi zaten kayıtlı.');
    }

    $fullName     = $firstName . ' ' . $lastName;
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password, role, district_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$fullName, $email, $passwordHash, $role, $districtId]);
    $userId = (int)$pdo->lastInsertId();

    // Oturum başlat
    startSession($userId, $fullName, $email, $role);

    respond(true, 'Kayıt başarılı! Yönlendiriliyorsunuz…', [
        'redirect' => redirectUrl($role)
    ]);
}


// ------------------------------------------------------------
//  Giriş
// ------------------------------------------------------------
function handleLogin(): void {
    $pdo  = getPDO();

    $role     = in_array($_POST['role'] ?? '', ['musteri','berber'], true) ? $_POST['role'] : null;
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$role)    respond(false, 'Geçersiz kullanıcı tipi.');
    if (!$email)   respond(false, 'Geçerli bir e-posta adresi girin.');
    if (!$password) respond(false, 'Şifre boş bırakılamaz.');

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = ?');
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        respond(false, 'E-posta, şifre veya kullanıcı tipi hatalı.');
    }

    startSession($user['id'], $user['full_name'], $user['email'], $user['role']);

    respond(true, 'Giriş başarılı! Yönlendiriliyorsunuz…', [
        'redirect' => redirectUrl($user['role'])
    ]);
}


// ------------------------------------------------------------
//  Yardımcı fonksiyonlar
// ------------------------------------------------------------
function startSession(int $id, string $name, string $email, string $role): void {
    $_SESSION['user_id']   = $id;
    $_SESSION['full_name'] = $name;
    $_SESSION['email']     = $email;
    $_SESSION['role']      = $role;
}

function redirectUrl(string $role): string {
    return $role === 'berber' ? 'berber_paneli.php' : 'musteri_paneli.php';
}

function respond(bool $success, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function badRequest(string $message): never {
    http_response_code(400);
    respond(false, $message);
}
