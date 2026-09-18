/**
 * CEON - Plataforma Escolar
 * Script global: menu lateral responsivo, confirmação de exclusão e validação de formulários.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ---------- Sidebar responsiva (mobile) ----------
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const btnToggle = document.getElementById('btnToggleSidebar');

    function abrirSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        btnToggle.setAttribute('aria-expanded', 'true');
    }
    function fecharSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        btnToggle.setAttribute('aria-expanded', 'false');
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? fecharSidebar() : abrirSidebar();
        });
    }
    if (overlay) {
        overlay.addEventListener('click', fecharSidebar);
    }

    // ---------- Confirmação de exclusão ----------
    document.querySelectorAll('.ceon-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const mensagem = form.dataset.confirmMessage || 'Tem certeza que deseja excluir este registro?';
            if (!window.confirm(mensagem)) {
                event.preventDefault();
            }
        });
    });

    // ---------- Validação de formulários (Bootstrap) ----------
    document.querySelectorAll('.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ---------- Auto-fechar alertas após alguns segundos ----------
    document.querySelectorAll('.alert-dismissible').forEach(function (alerta) {
        setTimeout(function () {
            const instancia = bootstrap.Alert.getOrCreateInstance(alerta);
            instancia.close();
        }, 6000);
    });
});
