<?php
// ============================================================
//  Müşteri Paneli — Ana Layout & Router
// ============================================================
session_start();

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'musteri') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

// Kullanıcı bilgisi
$stmt = $pdo->prepare('SELECT u.*, d.name AS district_name FROM users u LEFT JOIN districts d ON u.district_id = d.id WHERE u.id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Sayfa yönlendirme
$allowedPages = ['kesfet', 'berber_detay', 'randevularim'];
$page = in_array($_GET['page'] ?? '', $allowedPages) ? $_GET['page'] : 'kesfet';

// Bekleyen randevu sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id = ? AND status = 'bekliyor' AND appointment_time > NOW()");
$stmt->execute([$_SESSION['user_id']]);
$upcomingCount = (int)$stmt->fetchColumn();

$navItems = [
    'kesfet'      => ['icon' => '🔍', 'label' => 'Keşfet'],
    'randevularim' => ['icon' => '📅', 'label' => 'Randevularım'],
];

$activeNav = ($page === 'berber_detay') ? 'kesfet' : $page;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli — BerberBook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="stylesheet" href="assets/css/musteri.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="panel-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-logo">
            <div class="logo-mark">✂️</div>
            <div>
                <div class="logo-name">BerberBook</div>
                <div class="logo-sub">Müşteri Paneli</div>
            </div>
        </div>

        <!-- Kullanıcı kartı -->
        <div class="sidebar-shop" style="background:rgba(99,102,241,.08);border-color:rgba(99,102,241,.2)">
            <div class="shop-avatar" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div class="shop-name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="shop-district">📍 <?= htmlspecialchars($user['district_name'] ?? 'İlçe belirtilmemiş') ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($navItems as $key => $item): ?>
            <a href="musteri_paneli.php?page=<?= $key ?>"
               class="nav-item <?= $activeNav === $key ? 'active' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <span class="nav-label"><?= $item['label'] ?></span>
                <?php if ($key === 'randevularim' && $upcomingCount > 0): ?>
                    <span class="nav-badge"><?= $upcomingCount ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                    <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="user-role" style="color:#818cf8">Müşteri</div>
                </div>
            </div>
            <a href="logout.php" class="btn-logout" title="Çıkış Yap">⏏</a>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <div class="panel-main">

        <header class="panel-header">
            <button class="hamburger" id="hamburger" aria-label="Menü">
                <span></span><span></span><span></span>
            </button>
            <div class="header-title">
                <?php if ($page === 'berber_detay'): ?>
                    🏪 Berber Detayı
                <?php else: ?>
                    <?= $navItems[$activeNav]['icon'] ?> <?= $navItems[$activeNav]['label'] ?>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="header-date"><?= date('d M Y, l') ?></div>
            </div>
        </header>

        <main class="panel-content">
            <?php include __DIR__ . "/musteri/{$page}.php"; ?>
        </main>

    </div>
</div>

<!-- Global Modal -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" id="modal" style="max-width:520px">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle"></h3>
            <button class="modal-close" id="modalClose">✕</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon"></span>
    <span class="toast-msg"  id="toastMsg"></span>
</div>

<script src="assets/js/panel.js"></script>
<script src="assets/js/musteri.js"></script>
</body>
</html>
