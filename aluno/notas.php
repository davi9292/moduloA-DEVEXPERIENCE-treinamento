<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();
$alunoId = idPerfilLogado();

$stmt = $pdo->prepare("
    SELECT d.id AS disciplina_id, d.nome AS disciplina_nome, n.valor, n.peso, n.data_lancamento
    FROM notas n
    JOIN disciplinas d ON d.id = n.disciplina_id
    WHERE n.aluno_id = ?
    ORDER BY d.nome, n.data_lancamento
");
$stmt->execute([$alunoId]);
$notas = $stmt->fetchAll();

// Agrupa por disciplina
$porDisciplina = [];
foreach ($notas as $n) {
    $porDisciplina[$n['disciplina_nome']][] = $n;
}

$mediaGeral = calcularMediaPonderada($notas);

$tituloPagina = 'Notas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ceon-card mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h6 fw-bold mb-0">Média Geral</h2>
        <span class="badge fs-6 <?= $mediaGeral >= 6 ? 'text-bg-success' : 'text-bg-danger' ?>">
            <?= number_format($mediaGeral, 2, ',', '.') ?>
        </span>
    </div>
</div>

<?php if (empty($porDisciplina)): ?>
    <div class="ceon-card"><p class="text-muted mb-0">Nenhuma nota lançada até o momento.</p></div>
<?php endif; ?>

<?php foreach ($porDisciplina as $disciplina => $listaNotas): ?>
    <?php $mediaDisciplina = calcularMediaPonderada($listaNotas); ?>
    <div class="ceon-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 class="h6 fw-bold mb-0"><?= e($disciplina) ?></h3>
            <span class="badge <?= $mediaDisciplina >= 6 ? 'text-bg-success' : 'text-bg-danger' ?>">
                Média: <?= number_format($mediaDisciplina, 2, ',', '.') ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table ceon-table table-sm align-middle mb-0">
                <thead><tr><th>Nota</th><th>Peso</th><th>Data de lançamento</th></tr></thead>
                <tbody>
                <?php foreach ($listaNotas as $n): ?>
                    <tr>
                        <td><?= number_format((float) $n['valor'], 1, ',', '.') ?></td>
                        <td><?= number_format((float) $n['peso'], 1, ',', '.') ?></td>
                        <td><?= formatarData($n['data_lancamento']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
