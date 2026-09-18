<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();

$totalAlunos      = (int) $pdo->query('SELECT COUNT(*) AS c FROM alunos')->fetch()['c'];
$totalProfessores = (int) $pdo->query('SELECT COUNT(*) AS c FROM professores')->fetch()['c'];
$totalTurmas      = (int) $pdo->query('SELECT COUNT(*) AS c FROM turmas')->fetch()['c'];
$totalDisciplinas = (int) $pdo->query('SELECT COUNT(*) AS c FROM disciplinas')->fetch()['c'];

$proximosEventos = $pdo->query("
    SELECT ev.*, t.nome AS turma_nome
    FROM eventos ev LEFT JOIN turmas t ON t.id = ev.turma_id
    WHERE ev.data >= CURDATE()
    ORDER BY ev.data ASC, ev.hora ASC LIMIT 5
")->fetchAll();

$comunicadosRecentes = $pdo->query("
    SELECT c.*, u.nome AS autor_nome
    FROM comunicados c JOIN usuarios u ON u.id = c.autor_id
    ORDER BY c.data_publicacao DESC LIMIT 5
")->fetchAll();

// ---------- Dados para os gráficos ----------
$alunosPorTurma = $pdo->query("
    SELECT t.nome, COUNT(a.id) AS total
    FROM turmas t LEFT JOIN alunos a ON a.turma_id = t.id
    GROUP BY t.id, t.nome ORDER BY t.nome
")->fetchAll();

$mediaPorDisciplina = $pdo->query("
    SELECT d.nome, ROUND(SUM(n.valor * n.peso) / NULLIF(SUM(n.peso), 0), 2) AS media
    FROM disciplinas d LEFT JOIN notas n ON n.disciplina_id = d.id
    GROUP BY d.id, d.nome ORDER BY d.nome
")->fetchAll();

$distribuicaoUsuarios = $pdo->query("
    SELECT tipo_usuario, COUNT(*) AS total FROM usuarios GROUP BY tipo_usuario
")->fetchAll();

$eventosPorMes = $pdo->query("
    SELECT DATE_FORMAT(data, '%m/%Y') AS mes, COUNT(*) AS total
    FROM eventos GROUP BY DATE_FORMAT(data, '%Y-%m'), mes ORDER BY MIN(data)
")->fetchAll();

$tituloPagina = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#1e3a8a;"><i class="bi bi-mortarboard"></i></div>
            <div><div class="ceon-stat-value"><?= $totalAlunos ?></div><div class="ceon-stat-label">Total de alunos</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#0ea5a4;"><i class="bi bi-person-workspace"></i></div>
            <div><div class="ceon-stat-value"><?= $totalProfessores ?></div><div class="ceon-stat-label">Total de professores</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#d97706;"><i class="bi bi-people"></i></div>
            <div><div class="ceon-stat-value"><?= $totalTurmas ?></div><div class="ceon-stat-label">Total de turmas</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#16a34a;"><i class="bi bi-book"></i></div>
            <div><div class="ceon-stat-value"><?= $totalDisciplinas ?></div><div class="ceon-stat-label">Total de disciplinas</div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Alunos por turma</h2>
            <canvas id="graficoAlunosTurma" height="180"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Média por disciplina</h2>
            <canvas id="graficoMediaDisciplina" height="180"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Distribuição de usuários</h2>
            <canvas id="graficoUsuarios" height="180"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Eventos por mês</h2>
            <canvas id="graficoEventos" height="180"></canvas>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-calendar-event text-info"></i> Próximos eventos</h2>
            <?php if (empty($proximosEventos)): ?>
                <p class="text-muted small mb-0">Nenhum evento agendado.</p>
            <?php else: ?>
                <?php foreach ($proximosEventos as $ev): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($ev['titulo']) ?></div>
                        <div class="small text-muted"><?= formatarData($ev['data']) ?> <?= $ev['hora'] ? 'às ' . formatarHora($ev['hora']) : '' ?> &middot; <?= e($ev['local']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="eventos.php" class="btn btn-sm btn-outline-primary mt-2">Gerenciar eventos</a>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-megaphone text-primary"></i> Comunicados recentes</h2>
            <?php if (empty($comunicadosRecentes)): ?>
                <p class="text-muted small mb-0">Nenhum comunicado publicado.</p>
            <?php else: ?>
                <?php foreach ($comunicadosRecentes as $c): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($c['titulo']) ?></div>
                        <div class="small text-muted">Por <?= e($c['autor_nome']) ?> &middot; <?= formatarDataHora($c['data_publicacao']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="comunicados.php" class="btn btn-sm btn-outline-primary mt-2">Gerenciar comunicados</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const coresCeon = ['#1e3a8a', '#0ea5a4', '#d97706', '#16a34a', '#7c3aed', '#dc2626'];

new Chart(document.getElementById('graficoAlunosTurma'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($alunosPorTurma, 'nome')) ?>,
        datasets: [{
            label: 'Alunos',
            data: <?= json_encode(array_map('intval', array_column($alunosPorTurma, 'total'))) ?>,
            backgroundColor: '#1e3a8a'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('graficoMediaDisciplina'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($mediaPorDisciplina, 'nome')) ?>,
        datasets: [{
            label: 'Média',
            data: <?= json_encode(array_map(fn($m) => $m === null ? 0 : (float) $m, array_column($mediaPorDisciplina, 'media'))) ?>,
            backgroundColor: '#0ea5a4'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 10 } } }
});

new Chart(document.getElementById('graficoUsuarios'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map('ucfirst', array_column($distribuicaoUsuarios, 'tipo_usuario'))) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', array_column($distribuicaoUsuarios, 'total'))) ?>,
            backgroundColor: coresCeon
        }]
    },
    options: { responsive: true }
});

new Chart(document.getElementById('graficoEventos'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($eventosPorMes, 'mes')) ?>,
        datasets: [{
            label: 'Eventos',
            data: <?= json_encode(array_map('intval', array_column($eventosPorMes, 'total'))) ?>,
            borderColor: '#d97706',
            backgroundColor: 'rgba(217,119,6,.15)',
            fill: true,
            tension: .3
        }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
