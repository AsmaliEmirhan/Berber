<?php
// ============================================================
//  index.php — Auth Sayfası (Giriş / Kayıt)
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

// Zaten giriş yapmışsa yönlendir
if (!empty($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'berber' ? 'berber_paneli.php' : 'musteri_paneli.php'));
    exit;
}

// İlçe listesini çek
try {
    $districts = getPDO()->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
} catch (Exception) {
    $districts = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berber — Giriş & Kayıt</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-wrapper">

    <!-- Logo -->
    <div class="auth-logo">
        <div class="logo-icon">✂️</div>
        <h1>BerberBook</h1>
        <p>Randevunu kolayca yönet</p>
    </div>

    <!-- Card -->
    <div class="auth-card">

        <!-- Role Tabs -->
        <div class="role-tabs" role="tablist">
            <div class="tabs-slider" id="tabsSlider"></div>
            <button class="role-tab active" id="tabMusteri" role="tab" aria-selected="true">
                <span class="tab-icon">👤</span> Müşteri
            </button>
            <button class="role-tab" id="tabBerber" role="tab" aria-selected="false">
                <span class="tab-icon">✂️</span> Berber
            </button>
        </div>

        <!-- Form Title -->
        <div style="margin-bottom:20px;">
            <h2 id="formTitle" style="font-size:1.25rem;font-weight:700;letter-spacing:-0.3px;"></h2>
            <p id="formSubtitle" style="font-size:0.83rem;color:var(--text-muted);margin-top:4px;"></p>
        </div>

        <!-- Mode Switch -->
        <div class="mode-switch">
            <button class="mode-btn" id="modeLogin">Giriş Yap</button>
            <button class="mode-btn" id="modeRegister">Kayıt Ol</button>
        </div>

        <!-- Alert -->
        <div class="alert" id="alertBox" role="alert">
            <span class="alert-icon"></span>
            <span class="alert-msg"></span>
        </div>

        <!-- Form -->
        <form id="authForm" method="POST" action="auth_handler.php" novalidate>

            <!-- Ad / Soyad (sadece kayıtta) -->
            <div class="form-row hidden" id="rowName">
                <div class="field">
                    <label for="firstName">Ad</label>
                    <div class="input-wrap">
                        <input type="text" id="firstName" name="first_name"
                               placeholder="Adınız" autocomplete="given-name">
                        <span class="input-icon">👤</span>
                    </div>
                </div>
                <div class="field">
                    <label for="lastName">Soyad</label>
                    <div class="input-wrap">
                        <input type="text" id="lastName" name="last_name"
                               placeholder="Soyadınız" autocomplete="family-name">
                        <span class="input-icon">👤</span>
                    </div>
                </div>
            </div>

            <!-- E-posta -->
            <div class="field">
                <label for="email">E-posta</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email" required
                           placeholder="ornek@mail.com" autocomplete="email">
                    <span class="input-icon">✉️</span>
                </div>
            </div>

            <!-- Şifre -->
            <div class="field">
                <label for="password">Şifre</label>
                <div class="input-wrap has-toggle">
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••" autocomplete="current-password" minlength="6">
                    <button type="button" class="toggle-pass" aria-label="Şifreyi göster/gizle">
                        <!-- eye icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- İlçe (sadece müşteri kaydında) -->
            <div class="field hidden" id="fieldDistrict">
                <label for="districtId">İlçe <span style="color:var(--text-muted);font-weight:400;font-size:0.72rem;">(opsiyonel)</span></label>
                <div class="input-wrap">
                    <select id="districtId" name="district_id">
                        <option value="">İlçe seçin…</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= htmlspecialchars($d['id']) ?>">
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="input-icon">📍</span>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-submit" id="submitBtn">
                <span class="spinner"></span>
                <span class="btn-text" id="btnText">Giriş Yap</span>
            </button>

        </form>

        <!-- Footer link -->
        <p class="form-footer" style="margin-top:20px;">
            Hesabın yok mu?
            <a href="#" id="switchToRegister">Kayıt ol</a>
        </p>

    </div><!-- /.auth-card -->
</div><!-- /.auth-wrapper -->

<script src="assets/js/auth.js"></script>
<script>
    // Quick switch link
    document.getElementById('switchToRegister').addEventListener('click', function(e){
        e.preventDefault();
        document.getElementById('modeRegister').click();
        this.closest('.form-footer').innerHTML =
            'Zaten hesabın var mı? <a href="#" id="switchToLogin">Giriş yap</a>';
        document.getElementById('switchToLogin').addEventListener('click', function(e){
            e.preventDefault();
            document.getElementById('modeLogin').click();
            document.querySelector('.form-footer').innerHTML =
                'Hesabın yok mu? <a href="#" id="switchToRegister">Kayıt ol</a>';
            // re-bind (simple reload logic for demo)
            location.reload();
        });
    });
</script>
</body>
</html>
