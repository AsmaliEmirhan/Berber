<?php
// ============================================================
//  Çalışanlarım — Sadece Plus Üyeler
// ============================================================

// Plus kontrolü (berber_paneli.php'de nav'dan gizlenmiş ama
// URL ile erişim denemesine karşı ekstra kontrol)
if (!$user['is_plus']):
?>
<div class="empty-state">
    <div class="empty-icon">⭐</div>
    <h2>Bu özellik Plus üyelere özeldir</h2>
    <p>Plus üyelik ile sınırsız çalışan ekleyebilir, dükkanınızı büyütebilirsiniz.</p>
    <button class="btn btn-primary">Plus'a Geç</button>
</div>
<?php elseif (!$shop): ?>
<div class="empty-state">
    <div class="empty-icon">🏪</div>
    <h2>Önce bir dükkan oluşturun</h2>
    <a href="berber_paneli.php?page=dukkan" class="btn btn-primary">Dükkan Oluştur</a>
</div>
<?php else:
    // Mevcut çalışanlar
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.created_at
        FROM shop_employees se
        JOIN users u ON se.employee_id = u.id
        WHERE se.shop_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$shop['id']]);
    $employees = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Çalışanlarım <span class="plus-badge-inline">⭐ Plus</span></h2>
        <p class="page-sub">Dükkanınızda çalışan berberleri yönetin.</p>
    </div>
    <button class="btn btn-primary" onclick="openAddEmployee()">+ Çalışan Ekle</button>
</div>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <div class="empty-icon">👥</div>
    <h2>Henüz çalışan eklemediniz</h2>
    <p>Sisteme kayıtlı berberleri e-posta ile arayın ve dükkanınıza ekleyin.</p>
    <button class="btn btn-primary" onclick="openAddEmployee()">Çalışan Ekle</button>
</div>
<?php else: ?>

<div class="card">
    <div class="card-body p0">
        <table class="data-table">
            <thead>
                <tr><th>Ad Soyad</th><th>E-posta</th><th>Katılım</th><th style="width:100px">İşlem</th></tr>
            </thead>
            <tbody id="employeesTable">
                <?php foreach ($employees as $emp): ?>
                <tr id="emp-<?= $emp['id'] ?>">
                    <td>
                        <div class="emp-avatar-row">
                            <div class="mini-avatar"><?= mb_strtoupper(mb_substr($emp['full_name'],0,1)) ?></div>
                            <?= htmlspecialchars($emp['full_name']) ?>
                        </div>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($emp['email']) ?></td>
                    <td><?= date('d M Y', strtotime($emp['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-danger"
                            onclick="removeEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')">
                            Çıkar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; endif; ?>

<!-- Çalışan Arama Modal Template -->
<template id="addEmployeeTemplate">
    <div class="panel-form">
        <p class="text-muted" style="margin-bottom:16px">Sisteme kayıtlı berberin e-posta adresini girin.</p>

        <div class="field search-row">
            <label>E-posta</label>
            <div style="display:flex;gap:8px">
                <input type="email" id="empEmailInput" placeholder="berber@mail.com" style="flex:1">
                <button type="button" class="btn btn-primary" onclick="searchEmployee()">Ara</button>
            </div>
        </div>

        <div id="searchResult" class="search-result hidden"></div>
    </div>
</template>

<script>
function openAddEmployee() {
    const tpl = document.getElementById('addEmployeeTemplate').content.cloneNode(true);
    openModal('Çalışan Ekle', tpl);
}

async function searchEmployee() {
    const email = document.getElementById('empEmailInput').value.trim();
    const result = document.getElementById('searchResult');

    if (!email) { showToast('error', 'E-posta girin.'); return; }

    result.className = 'search-result';
    result.innerHTML = '<span class="text-muted">Aranıyor…</span>';

    const fd = new FormData();
    fd.set('action', 'search_employee');
    fd.set('email', email);

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.success) {
        result.innerHTML = `<div class="search-error">❌ ${data.message}</div>`;
        return;
    }

    const emp = data.employee;
    result.innerHTML = `
        <div class="search-found">
            <div class="mini-avatar large">${emp.full_name[0].toUpperCase()}</div>
            <div>
                <div class="found-name">${emp.full_name}</div>
                <div class="found-email">${emp.email}</div>
            </div>
            <button class="btn btn-primary" onclick="confirmAddEmployee(${emp.id})">Ekle</button>
        </div>`;
}

async function confirmAddEmployee(id) {
    const fd = new FormData();
    fd.set('action', 'add_employee');
    fd.set('employee_id', id);

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);
    if (data.success) { closeModal(); setTimeout(() => location.reload(), 800); }
}

async function removeEmployee(id, name) {
    if (!confirm(`${name} adlı çalışanı çıkarmak istiyor musunuz?`)) return;

    const fd = new FormData();
    fd.set('action', 'remove_employee');
    fd.set('employee_id', id);

    const res  = await fetch('berber/api.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'success' : 'error', data.message);
    if (data.success) document.getElementById('emp-' + id)?.remove();
}
</script>
