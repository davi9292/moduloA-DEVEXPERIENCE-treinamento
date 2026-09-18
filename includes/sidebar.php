<?php
/**
 * CEON - Plataforma Escolar
 * Sidebar de navegação, com itens de menu variando pelo tipo de usuário logado.
 */
$tipo = $_SESSION['tipo_usuario'] ?? '';
$paginaAtual = basename($_SERVER['SCRIPT_NAME']);

$menus = [
    'aluno' => [
        ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
        ['calendario.php', 'bi-calendar3', 'Calendário'],
        ['tarefas.php', 'bi-list-check', 'Tarefas'],
        ['notas.php', 'bi-journal-text', 'Notas'],
        ['faltas.php', 'bi-person-x', 'Faltas'],
        ['materiais.php', 'bi-folder2-open', 'Materiais'],
        ['comunicados.php', 'bi-megaphone', 'Comunicados'],
        ['cardapio.php', 'bi-cup-hot', 'Cardápio'],
        ['perfil.php', 'bi-person-circle', 'Perfil'],
    ],
    'professor' => [
        ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
        ['calendario.php', 'bi-calendar3', 'Calendário'],
        ['turmas.php', 'bi-people', 'Minhas Turmas'],
        ['tarefas.php', 'bi-list-check', 'Tarefas'],
        ['eventos.php', 'bi-calendar-event', 'Eventos'],
        ['comunicados.php', 'bi-megaphone', 'Comunicados'],
        ['materiais.php', 'bi-folder2-open', 'Materiais'],
        ['notas.php', 'bi-journal-text', 'Notas'],
        ['faltas.php', 'bi-person-x', 'Faltas'],
        ['perfil.php', 'bi-person-circle', 'Perfil'],
    ],
    'administrador' => [
        ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
        ['usuarios.php', 'bi-person-badge', 'Usuários'],
        ['alunos.php', 'bi-mortarboard', 'Alunos'],
        ['professores.php', 'bi-person-workspace', 'Professores'],
        ['turmas.php', 'bi-people', 'Turmas'],
        ['disciplinas.php', 'bi-book', 'Disciplinas'],
        ['eventos.php', 'bi-calendar-event', 'Eventos'],
        ['comunicados.php', 'bi-megaphone', 'Comunicados'],
        ['cardapio.php', 'bi-cup-hot', 'Cardápio'],
        ['relatorios.php', 'bi-bar-chart', 'Relatórios'],
        ['perfil.php', 'bi-person-circle', 'Perfil'],
    ],
];

$itensMenu = $menus[$tipo] ?? [];
?>
<aside class="ceon-sidebar" id="sidebar">
    <div class="ceon-brand">
        <span class="ceon-brand-logo">CEON</span>
        <span class="ceon-brand-sub">Plataforma Escolar</span>
    </div>

    <nav class="ceon-nav">
        <?php foreach ($itensMenu as [$arquivo, $icone, $rotulo]): ?>
            <a href="<?= e($arquivo) ?>" class="ceon-nav-link <?= $paginaAtual === $arquivo ? 'active' : '' ?>">
                <i class="bi <?= e($icone) ?>"></i>
                <span><?= e($rotulo) ?></span>
            </a>
        <?php endforeach; ?>

        <a href="<?= caminhoBase() ?>logout.php" class="ceon-nav-link ceon-nav-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sair</span>
        </a>
    </nav>
</aside>
<div class="ceon-sidebar-overlay" id="sidebarOverlay"></div>
