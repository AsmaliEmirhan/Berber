<?php
// ============================================================
//  Dükkan Ayarları
//  $pdo, $shop, $user berber_paneli.php'den gelir
// ============================================================

$districts = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
?>

<div class="page-header">
    <h2 class="page-title">Dükkan Ayarları</h2>
    <p class="page-sub"><?= $shop ? 'Dükkan bilgilerinizi güncelleyin.' : 'Dükkanınızı oluşturarak randevu almaya başlayın.' ?></p>
</div>

<div class="card" style="max-width:600px">
    <div class="card-header">
        <h3 class="card-title"><?= $shop ? '🏪 Dükkanı Düzenle' : '🏪 Yeni Dükkan Oluştur' ?></h3>
    </div>
    <div class="card-body">
        <form id="shopForm" class="panel-form">

            <div class="field">
                <label>Dükkan Adı <span class="required">*</span></label>
                <input type="text" name="shop_name" required maxlength="200"
                       placeholder="ör. Ahmet Usta Berberi"
                       value="<?= htmlspecialchars($shop['shop_name'] ?? '') ?>">
            </div>

            <div class="field">
                <label>İlçe</label>
                <select name="district_id">
                    <option value="">İlçe seçin…</option>
                    <?php foreach ($districts as $d): ?>
                    <option value="<?= $d['id'] ?>"
                        <?= ($shop && $shop['district_id'] == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Adres</label>
                <textarea name="address" rows="3" placeholder="Sokak, mahalle, bina no…"><?= htmlspecialchars($shop['address'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="shopSaveBtn">
                    <span class="spinner-sm hidden"></span>
                    <?= $shop ? 'Güncelle' : 'Dükkanı Oluştur' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Danger Zone (sadece dükkan varsa) -->
<?php if ($shop): ?>
<div class="card danger-card" style="max-width:600px;margin-top:24px">
    <div class="card-header">
        <h3 class="card-title" style="color:var(--error)">⚠️ Dükkan Bilgileri</h3>
    </div>
    <div class="card-body">
        <div class="info-row"><span class="info-key">Dükkan ID</span><span class="info-val">#<?= $shop['id'] ?></span></div>
        <div class="info-row"><span class="info-key">Oluşturulma</span><span class="info-val"><?= date('d M Y', strtotime($shop['created_at'])) ?></span></div>
        <div class="info-row"><span class="info-key">İlçe</span><span class="info-val"><?= htmlspecialchars($shop['district_name'] ?? '—') ?></span></div>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('shopForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('shopSaveBtn');
    const fd  = new FormData(this);
    fd.set('action', 'save_shop');
    btn.disabled = true;

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);
    btn.disabled = false;

    if (data.success) setTimeout(() => location.reload(), 1200);
});
</script>
