<?php
// ============================================================
//  Berber Paneli — Ana Layout & Router
// ============================================================
session_start();

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'berber') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$pdo = getPDO();

// Kullanıcı bilgisi
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Dükkan bilgisi
$stmt = $pdo->prepare('SELECT s.*, d.name as district_name FROM shops s LEFT JOIN districts d ON s.district_id = d.id WHERE s.owner_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$shop = $stmt->fetch();

// Sayfa yönlendirme
$allowedPages = ['dashboard', 'dukkan', 'hizmetler', 'calisanlar', 'randevular'];
$page = in_array($_GET['page'] ?? '', $allowedPages) ? $_GET['page'] : 'dashboard';

// Bekleyen randevu sayısı (badge için)
$pendingBadge = 0;
if ($shop) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE shop_id = ? AND status = 'bekliyor'");
    $stmt->execute([$shop['id']]);
    $pendingBadge = (int)$stmt->fetchColumn();
}

$navItems = [
    'dashboard'  => ['icon' => '📊', 'label' => 'Dashboard'],
    'dukkan'     => ['icon' => '🏪', 'label' => 'Dükkan Ayarları'],
    'hizmetler'  => ['icon' => '✂️',  'label' => 'Hizmetler'],
    'calisanlar' => ['icon' => '👥', 'label' => 'Çalışanlarım'],
    'randevular' => ['icon' => '📅', 'label' => 'Randevularım'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berber Paneli — BerberBook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/panel.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="panel-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-logo">
            <div class="logo-mark">✂️</div>
            <div>
                <div class="logo-name">BerberBook</div>
                <div class="logo-sub">Berber Paneli</div>
            </div>
        </div>

        <!-- Shop info -->
        <?php if ($shop): ?>
        <div class="sidebar-shop">
            <div class="shop-avatar"><?= mb_strtoupper(mb_substr($shop['shop_name'], 0, 1)) ?></div>
            <div>
                <div class="shop-name"><?= htmlspecialchars($shop['shop_name']) ?></div>
                <div class="shop-district"><?= htmlspecialchars($shop['district_name'] ?? 'İlçe belirtilmemiş') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <nav class="sidebar-nav">
            <?php foreach ($navItems as $key => $item): ?>
                <?php
                    $isActive = ($page === $key);
                    // Çalışanlar sadece plus üyelere
                    if ($key === 'calisanlar' && !$user['is_plus']) continue;
                ?>
                <a href="berber_paneli.php?page=<?= $key ?>"
                   class="nav-item <?= $isActive ? 'active' : '' ?>">
                    <span class="nav-icon"><?= $item['icon'] ?></span>
                    <span class="nav-label"><?= $item['label'] ?></span>
                    <?php if ($key === 'randevular' && $pendingBadge > 0): ?>
                        <span class="nav-badge"><?= $pendingBadge ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <?php if (!$user['is_plus']): ?>
            <div class="plus-promo">
                <div class="plus-icon">⭐</div>
                <div>
                    <div class="plus-title">Plus'a Geç</div>
                    <div class="plus-desc">Çalışan ekle, daha fazla kazan</div>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="user-role"><?= $user['is_plus'] ? '⭐ Plus Üye' : 'Standart Üye' ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn-logout" title="Çıkış Yap">⏏</a>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <div class="panel-main">

        <!-- Top Bar -->
        <header class="panel-header">
            <button class="hamburger" id="hamburger" aria-label="Menü">
                <span></span><span></span><span></span>
            </button>
            <div class="header-title">
                <span><?= $navItems[$page]['icon'] ?></span>
                <?= $navItems[$page]['label'] ?>
            </div>
            <div class="header-right">
                <div class="header-date"><?= date('d M Y, l') ?></div>
            </div>
        </header>

        <!-- Content -->
        <main class="panel-content">
            <?php include __DIR__ . "/berber/{$page}.php"; ?>
        </main>

    </div><!-- /.panel-main -->

</div><!-- /.panel-layout -->

<!-- Global Modal -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" id="modal">
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
</body>
</html>
