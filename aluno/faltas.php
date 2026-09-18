<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();
$alunoId = idPerfilLogado();

$stmt = $pdo->prepare("
    SELECT d.nome AS disciplina_nome, SUM(f.quantidade) AS total_faltas
    FROM faltas f
    JOIN disciplinas d ON d.id = f.disciplina_id
    WHERE f.aluno_id = ?
    GROUP BY d.id, d.nome
    ORDER BY d.nome
");
$stmt->execute([$alunoId]);
$faltasPorDisciplina = $stmt->fetchAll();

// Estimativa de total de aulas por disciplina (para cálculo de presença), com base na tabela "aulas" da turma
$stmt = $pdo->prepare('SELECT turma_id FROM alunos WHERE id = ?');
$stmt->execute([$alunoId]);
$turmaId = $stmt->fetch()['turma_id'];

$stmt = $pdo->prepare("
    SELECT d.nome AS disciplina_nome, COUNT(*) AS total_aulas
    FROM aulas a JOIN disciplinas d ON d.id = a.disciplina_id
    WHERE a.turma_id = ?
    GROUP BY d.id, d.nome
");
$stmt->execute([$turmaId]);
$aulasPorDisciplina = [];
foreach ($stmt->fetchAll() as $row) {
    $aulasPorDisciplina[$row['disciplina_nome']] = (int) $row['total_aulas'];
}

$tituloPagina = 'Faltas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ceon-card">
    <?php if (empty($faltasPorDisciplina)): ?>
        <p class="text-muted mb-0">Nenhuma falta registrada. Parabéns pela assiduidade!</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table ceon-table align-middle">
                <thead><tr><th>Disciplina</th><th>Quantidade de faltas</th><th>Percentual de presença</th></tr></thead>
                <tbody>
                <?php foreach ($faltasPorDisciplina as $f): ?>
                    <?php
                        $totalAulas = $aulasPorDisciplina[$f['disciplina_nome']] ?? 0;
                        $percentualPresenca = $totalAulas > 0
                            ? max(0, round((($totalAulas - (int) $f['total_faltas']) / $totalAulas) * 100, 1))
                            : null;
                    ?>
                    <tr>
                        <td><?= e($f['disciplina_nome']) ?></td>
                        <td><span class="badge text-bg-warning"><?= (int) $f['total_faltas'] ?></span></td>
                        <td>
                            <?php if ($percentualPresenca !== null): ?>
                                <span class="badge <?= $percentualPresenca >= 75 ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= number_format($percentualPresenca, 1, ',', '.') ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Dados insuficientes</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
