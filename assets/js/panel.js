/* ============================================================
   Berber Paneli — Panel JS
   ============================================================ */
(function () {
    'use strict';

    /* ---- Sidebar Toggle (Mobile) ---- */
    const sidebar  = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay  = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    hamburger?.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);

    /* ---- Modal ---- */
    const backdrop  = document.getElementById('modalBackdrop');
    const modal     = document.getElementById('modal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody  = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');

    window.openModal = function (title, content) {
        modalTitle.textContent = title;
        modalBody.innerHTML = '';

        // content can be a string, Node, or DocumentFragment
        if (typeof content === 'string') {
            modalBody.innerHTML = content;
        } else {
            modalBody.appendChild(content);
        }

        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function () {
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => { modalBody.innerHTML = ''; }, 300);
    };

    modalClose?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', (e) => {
        if (e.target === backdrop) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
    });

    /* ---- Toast ---- */
    let toastTimer;
    const toast     = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMsg  = document.getElementById('toastMsg');

    const ICONS = { success: '✓', error: '✕', info: 'ℹ' };

    window.showToast = function (type, message) {
        clearTimeout(toastTimer);
        toast.className = `toast show ${type}`;
        toastIcon.textContent = ICONS[type] ?? 'ℹ';
        toastMsg.textContent  = message;

        toastTimer = setTimeout(() => {
            toast.classList.remove('show');
        }, 3500);
    };

    /* ---- Active nav highlight (URL based) ---- */
    const currentPage = new URLSearchParams(location.search).get('page') || 'dashboard';
    document.querySelectorAll('.nav-item').forEach(link => {
        const linkPage = new URLSearchParams(new URL(link.href, location.origin).search).get('page') || 'dashboard';
        if (linkPage === currentPage) link.classList.add('active');
        else link.classList.remove('active');
    });

})();
