<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/professor_helpers.php';
exigirTipoUsuario('professor');

$pdo = getConexao();
$professorId = idPerfilLogado();
$minhasTurmas = turmasDoProfessor($pdo, $professorId);

$turmaSelecionada = (int) ($_GET['turma'] ?? ($minhasTurmas[0]['id'] ?? 0));
$alunos = $turmaSelecionada > 0 ? alunosDaTurma($pdo, $turmaSelecionada) : [];

// Média de cada aluno da turma selecionada
$mediasAlunos = [];
foreach ($alunos as $aluno) {
    $stmt = $pdo->prepare('SELECT valor, peso FROM notas WHERE aluno_id = ?');
    $stmt->execute([$aluno['id']]);
    $mediasAlunos[$aluno['id']] = calcularMediaPonderada($stmt->fetchAll());
}

$tituloPagina = 'Minhas Turmas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="ceon-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-8">
            <label for="turma" class="form-label small">Selecione a turma</label>
            <select id="turma" name="turma" class="form-select">
                <?php foreach ($minhasTurmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $turmaSelecionada === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= e($t['nome']) ?> — <?= e($t['ano_letivo']) ?> (<?= e(ucfirst($t['turno'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4">
            <button type="submit" class="btn btn-primary w-100">Ver alunos</button>
        </div>
    </form>
</div>

<div class="ceon-card">
    <h2 class="h6 fw-bold mb-3">Alunos da turma (<?= count($alunos) ?>)</h2>
    <?php if (empty($alunos)): ?>
        <p class="text-muted mb-0">Nenhum aluno matriculado nesta turma.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table ceon-table align-middle">
                <thead><tr><th>#</th><th>Nome do aluno</th><th>Média geral</th></tr></thead>
                <tbody>
                <?php foreach ($alunos as $i => $aluno): ?>
                    <?php $media = $mediasAlunos[$aluno['id']]; ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($aluno['nome']) ?></td>
                        <td>
                            <?php if ($media > 0): ?>
                                <span class="badge <?= $media >= 6 ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= number_format($media, 2, ',', '.') ?>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
