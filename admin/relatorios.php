<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();

// Desempenho por turma (média ponderada de todas as notas dos alunos da turma)
$desempenhoTurmas = $pdo->query("
    SELECT t.nome AS turma,
           COUNT(DISTINCT a.id) AS total_alunos,
           ROUND(SUM(n.valor * n.peso) / NULLIF(SUM(n.peso), 0), 2) AS media
    FROM turmas t
    LEFT JOIN alunos a ON a.turma_id = t.id
    LEFT JOIN notas n ON n.aluno_id = a.id
    GROUP BY t.id, t.nome
    ORDER BY t.nome
")->fetchAll();

// Faltas por disciplina
$faltasDisciplina = $pdo->query("
    SELECT d.nome AS disciplina, COALESCE(SUM(f.quantidade), 0) AS total_faltas
    FROM disciplinas d
    LEFT JOIN faltas f ON f.disciplina_id = d.id
    GROUP BY d.id, d.nome
    ORDER BY total_faltas DESC
")->fetchAll();

// Tarefas por disciplina
$tarefasDisciplina = $pdo->query("
    SELECT d.nome AS disciplina, COUNT(t.id) AS total
    FROM disciplinas d
    LEFT JOIN tarefas t ON t.disciplina_id = d.id
    GROUP BY d.id, d.nome
    ORDER BY d.nome
")->fetchAll();

// Ranking dos alunos com melhor média
$melhoresAlunos = $pdo->query("
    SELECT u.nome AS aluno, t.nome AS turma,
           ROUND(SUM(n.valor * n.peso) / NULLIF(SUM(n.peso), 0), 2) AS media
    FROM alunos a
    JOIN usuarios u ON u.id = a.usuario_id
    JOIN turmas t ON t.id = a.turma_id
    JOIN notas n ON n.aluno_id = a.id
    GROUP BY a.id, u.nome, t.nome
    HAVING media IS NOT NULL
    ORDER BY media DESC
    LIMIT 10
")->fetchAll();

// Totais gerais
$totais = [
    'usuarios'    => (int) $pdo->query('SELECT COUNT(*) AS c FROM usuarios')->fetch()['c'],
    'materiais'   => (int) $pdo->query('SELECT COUNT(*) AS c FROM materiais')->fetch()['c'],
    'tarefas'     => (int) $pdo->query('SELECT COUNT(*) AS c FROM tarefas')->fetch()['c'],
    'comunicados' => (int) $pdo->query('SELECT COUNT(*) AS c FROM comunicados')->fetch()['c'],
    'eventos'     => (int) $pdo->query('SELECT COUNT(*) AS c FROM eventos')->fetch()['c'],
    'aulas'       => (int) $pdo->query('SELECT COUNT(*) AS c FROM aulas')->fetch()['c'],
];

$tituloPagina = 'Relatórios';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <?php
    $cardsTotais = [
        ['Usuários cadastrados', $totais['usuarios'], 'bi-person-badge', '#1e3a8a'],
        ['Materiais publicados', $totais['materiais'], 'bi-folder2-open', '#0ea5a4'],
        ['Tarefas criadas', $totais['tarefas'], 'bi-list-check', '#d97706'],
        ['Comunicados', $totais['comunicados'], 'bi-megaphone', '#16a34a'],
        ['Eventos', $totais['eventos'], 'bi-calendar-event', '#7c3aed'],
        ['Aulas agendadas', $totais['aulas'], 'bi-clock-history', '#dc2626'],
    ];
    foreach ($cardsTotais as [$rotulo, $valor, $icone, $cor]):
    ?>
        <div class="col-6 col-lg-2">
            <div class="ceon-card text-center">
                <i class="bi <?= $icone ?> fs-3" style="color: <?= $cor ?>"></i>
                <div class="ceon-stat-value mt-2"><?= $valor ?></div>
                <div class="ceon-stat-label"><?= e($rotulo) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Desempenho médio por turma</h2>
            <canvas id="graficoDesempenhoTurmas" height="200"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Total de faltas por disciplina</h2>
            <canvas id="graficoFaltas" height="200"></canvas>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Resumo por turma</h2>
            <div class="table-responsive">
                <table class="table ceon-table table-sm align-middle mb-0">
                    <thead><tr><th>Turma</th><th>Alunos</th><th>Média</th></tr></thead>
                    <tbody>
                    <?php foreach ($desempenhoTurmas as $d): ?>
                        <tr>
                            <td><?= e($d['turma']) ?></td>
                            <td><?= (int) $d['total_alunos'] ?></td>
                            <td>
                                <?php if ($d['media'] !== null): ?>
                                    <span class="badge <?= $d['media'] >= 6 ? 'text-bg-success' : 'text-bg-danger' ?>">
                                        <?= number_format((float) $d['media'], 2, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Sem notas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Alunos com melhor desempenho</h2>
            <?php if (empty($melhoresAlunos)): ?>
                <p class="text-muted small mb-0">Nenhuma nota lançada ainda.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Aluno</th><th>Turma</th><th>Média</th></tr></thead>
                        <tbody>
                        <?php foreach ($melhoresAlunos as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($a['aluno']) ?></td>
                                <td class="small text-muted"><?= e($a['turma']) ?></td>
                                <td><span class="badge text-bg-success"><?= number_format((float) $a['media'], 2, ',', '.') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('graficoDesempenhoTurmas'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($desempenhoTurmas, 'turma')) ?>,
        datasets: [{
            label: 'Média da turma',
            data: <?= json_encode(array_map(fn($m) => $m === null ? 0 : (float) $m, array_column($desempenhoTurmas, 'media'))) ?>,
            backgroundColor: '#1e3a8a'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 10 } } }
});

new Chart(document.getElementById('graficoFaltas'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($faltasDisciplina, 'disciplina')) ?>,
        datasets: [{
            label: 'Faltas',
            data: <?= json_encode(array_map('intval', array_column($faltasDisciplina, 'total_faltas'))) ?>,
            backgroundColor: '#dc2626'
        }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
