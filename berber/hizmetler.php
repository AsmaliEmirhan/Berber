<?php
// ============================================================
//  Hizmetler Yönetimi
// ============================================================
if (!$shop):
?>
<div class="empty-state">
    <div class="empty-icon">✂️</div>
    <h2>Önce bir dükkan oluşturun</h2>
    <a href="berber_paneli.php?page=dukkan" class="btn btn-primary">Dükkan Oluştur</a>
</div>
<?php else:
    $stmt = $pdo->prepare('SELECT * FROM services WHERE shop_id = ? ORDER BY service_name');
    $stmt->execute([$shop['id']]);
    $services = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Hizmetler</h2>
        <p class="page-sub">Sunduğunuz hizmetleri, fiyatları ve süreleri yönetin.</p>
    </div>
    <button class="btn btn-primary" onclick="openAddService()">+ Hizmet Ekle</button>
</div>

<?php if (empty($services)): ?>
<div class="empty-state">
    <div class="empty-icon">✂️</div>
    <h2>Henüz hizmet eklemediniz</h2>
    <p>Saç kesimi, sakal tıraşı gibi hizmetleri ekleyin.</p>
    <button class="btn btn-primary" onclick="openAddService()">İlk Hizmeti Ekle</button>
</div>
<?php else: ?>

<div class="card">
    <div class="card-body p0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hizmet Adı</th>
                    <th>Fiyat</th>
                    <th>Süre</th>
                    <th style="width:120px">İşlemler</th>
                </tr>
            </thead>
            <tbody id="servicesTable">
                <?php foreach ($services as $s): ?>
                <tr id="svc-<?= $s['id'] ?>">
                    <td>
                        <span class="svc-icon">✂️</span>
                        <?= htmlspecialchars($s['service_name']) ?>
                    </td>
                    <td class="price-cell">₺<?= number_format($s['price'], 2) ?></td>
                    <td>
                        <span class="duration-badge"><?= $s['duration_minutes'] ?> dk</span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn btn-sm btn-ghost"
                                onclick="openEditService(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['service_name'])) ?>', <?= $s['price'] ?>, <?= $s['duration_minutes'] ?>)">
                                ✏️
                            </button>
                            <button class="btn btn-sm btn-danger"
                                onclick="deleteService(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['service_name'])) ?>')">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; endif; ?>

<!-- Hizmet Ekle/Düzenle Formu (Modal içine inject edilir) -->
<template id="serviceFormTemplate">
    <form id="serviceForm" class="panel-form">
        <input type="hidden" name="service_id" id="serviceIdInput">

        <div class="field">
            <label>Hizmet Adı <span class="required">*</span></label>
            <input type="text" name="service_name" id="serviceNameInput"
                   required maxlength="200" placeholder="ör. Saç Kesimi">
        </div>

        <div class="form-row-2">
            <div class="field">
                <label>Fiyat (₺) <span class="required">*</span></label>
                <input type="number" name="price" id="servicePriceInput"
                       min="0" step="0.50" placeholder="150.00">
            </div>
            <div class="field">
                <label>Süre (dakika) <span class="required">*</span></label>
                <input type="number" name="duration_minutes" id="serviceDurInput"
                       min="5" step="5" placeholder="30">
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">İptal</button>
            <button type="submit" class="btn btn-primary" id="serviceSaveBtn">Kaydet</button>
        </div>
    </form>
</template>

<script>
function openAddService() {
    const tpl = document.getElementById('serviceFormTemplate').content.cloneNode(true);
    openModal('Yeni Hizmet Ekle', tpl);
    document.getElementById('serviceForm').addEventListener('submit', submitServiceForm.bind(null, 'add_service'));
}

function openEditService(id, name, price, duration) {
    const tpl = document.getElementById('serviceFormTemplate').content.cloneNode(true);
    openModal('Hizmet Düzenle', tpl);
    document.getElementById('serviceIdInput').value   = id;
    document.getElementById('serviceNameInput').value = name;
    document.getElementById('servicePriceInput').value = price;
    document.getElementById('serviceDurInput').value   = duration;
    document.getElementById('serviceForm').addEventListener('submit', submitServiceForm.bind(null, 'edit_service'));
}

async function submitServiceForm(action, e) {
    e.preventDefault();
    const btn = document.getElementById('serviceSaveBtn');
    btn.disabled = true;

    const fd = new FormData(document.getElementById('serviceForm'));
    fd.set('action', action);

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);
    btn.disabled = false;
    if (data.success) { closeModal(); setTimeout(() => location.reload(), 800); }
}

async function deleteService(id, name) {
    if (!confirm(`"${name}" hizmetini silmek istediğinize emin misiniz?`)) return;
    const fd = new FormData();
    fd.set('action', 'delete_service');
    fd.set('service_id', id);
    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);
    if (data.success) document.getElementById('svc-' + id)?.remove();
}
</script>
