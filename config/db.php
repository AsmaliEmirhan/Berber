<?php
// ============================================================
//  Veritabanı Bağlantısı - PDO
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'berber_db');
define('DB_USER', 'root');   // Kendi kullanıcı adınızla değiştirin
define('DB_PASS', '');       // Kendi şifrenizle değiştirin
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Üretimde bu hatayı loglayın, kullanıcıya göstermeyin
            http_response_code(500);
            exit(json_encode(['success' => false, 'message' => 'Veritabanı bağlantı hatası.']));
        }
    }

    return $pdo;
}
