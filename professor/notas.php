<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/professor_helpers.php';
exigirTipoUsuario('professor');

$pdo = getConexao();
$professorId = idPerfilLogado();
$minhasDisciplinas = disciplinasDoProfessor($pdo, $professorId);
$minhasTurmas = turmasDoProfessor($pdo, $professorId);

$erros = [];

// ---------- Exclusão de nota ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $notaId = (int) $_POST['id'];
    // Só permite excluir notas de disciplinas que o professor leciona
    $stmt = $pdo->prepare("
        DELETE n FROM notas n
        JOIN professor_disciplinas pd ON pd.disciplina_id = n.disciplina_id
        WHERE n.id = ? AND pd.professor_id = ?
    ");
    $stmt->execute([$notaId, $professorId]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Nota excluída com sucesso!' : 'Não foi possível excluir esta nota.');
    header('Location: notas.php');
    exit;
}

// ---------- Lançamento de nota ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'lancar') {
    $alunoId      = (int) ($_POST['aluno_id'] ?? 0);
    $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
    $valor        = $_POST['valor'] ?? '';
    $peso         = $_POST['peso'] ?? '';

    if ($alunoId <= 0)      { $erros[] = 'Selecione um aluno.'; }
    if ($disciplinaId <= 0) { $erros[] = 'Selecione uma disciplina.'; }
    if (!is_numeric($valor) || $valor < 0 || $valor > 10) { $erros[] = 'A nota deve ser um número entre 0 e 10.'; }
    if (!is_numeric($peso) || $peso <= 0)                 { $erros[] = 'O peso deve ser um número maior que zero.'; }
    if ($disciplinaId > 0 && !professorLecionaDisciplina($pdo, $professorId, $disciplinaId)) {
        $erros[] = 'Você não leciona a disciplina selecionada.';
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare('INSERT INTO notas (aluno_id, disciplina_id, valor, peso, data_lancamento) VALUES (?, ?, ?, ?, CURDATE())');
        $stmt->execute([$alunoId, $disciplinaId, $valor, $peso]);
        definirMensagem('sucesso', 'Nota lançada com sucesso!');
        header('Location: notas.php?turma=' . (int) ($_POST['turma_id'] ?? 0));
        exit;
    }
}

$turmaSelecionada = (int) ($_GET['turma'] ?? ($minhasTurmas[0]['id'] ?? 0));
$alunos = $turmaSelecionada > 0 ? alunosDaTurma($pdo, $turmaSelecionada) : [];

// Notas já lançadas pelas disciplinas do professor na turma selecionada
$notasLancadas = [];
if ($turmaSelecionada > 0 && !empty($minhasDisciplinas)) {
    $idsDisciplinas = array_column($minhasDisciplinas, 'id');
    $placeholders = implode(',', array_fill(0, count($idsDisciplinas), '?'));
    $sql = "
        SELECT n.*, u.nome AS aluno_nome, d.nome AS disciplina_nome
        FROM notas n
        JOIN alunos a ON a.id = n.aluno_id
        JOIN usuarios u ON u.id = a.usuario_id
        JOIN disciplinas d ON d.id = n.disciplina_id
        WHERE a.turma_id = ? AND n.disciplina_id IN ($placeholders)
        ORDER BY u.nome, d.nome, n.data_lancamento DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$turmaSelecionada], $idsDisciplinas));
    $notasLancadas = $stmt->fetchAll();
}

$tituloPagina = 'Notas';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="ceon-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-8">
            <label for="turma" class="form-label small">Turma</label>
            <select id="turma" name="turma" class="form-select">
                <?php foreach ($minhasTurmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $turmaSelecionada === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4"><button type="submit" class="btn btn-primary w-100">Carregar turma</button></div>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Lançar nota</h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="lancar">
                <input type="hidden" name="turma_id" value="<?= $turmaSelecionada ?>">

                <div class="mb-3">
                    <label for="aluno_id" class="form-label">Aluno</label>
                    <select id="aluno_id" name="aluno_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($alunos as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"><?= e($a['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="disciplina_id" class="form-label">Disciplina</label>
                    <select id="disciplina_id" name="disciplina_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($minhasDisciplinas as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"><?= e($d['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label for="valor" class="form-label">Nota (0 a 10)</label>
                        <input type="number" id="valor" name="valor" class="form-control" step="0.1" min="0" max="10" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label for="peso" class="form-label">Peso</label>
                        <input type="number" id="peso" name="peso" class="form-control" step="0.5" min="0.5" value="1" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Lançar nota</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Notas lançadas</h2>
            <?php if (empty($notasLancadas)): ?>
                <p class="text-muted mb-0">Nenhuma nota lançada para esta turma nas suas disciplinas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Aluno</th><th>Disciplina</th><th>Nota</th><th>Peso</th><th>Data</th><th>Ação</th></tr></thead>
                        <tbody>
                        <?php foreach ($notasLancadas as $n): ?>
                            <tr>
                                <td><?= e($n['aluno_nome']) ?></td>
                                <td><?= e($n['disciplina_nome']) ?></td>
                                <td><?= number_format((float) $n['valor'], 1, ',', '.') ?></td>
                                <td><?= number_format((float) $n['peso'], 1, ',', '.') ?></td>
                                <td><?= formatarData($n['data_lancamento']) ?></td>
                                <td>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir esta nota?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
