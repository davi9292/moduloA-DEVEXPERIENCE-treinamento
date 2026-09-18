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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare("
        DELETE f FROM faltas f
        JOIN professor_disciplinas pd ON pd.disciplina_id = f.disciplina_id
        WHERE f.id = ? AND pd.professor_id = ?
    ");
    $stmt->execute([(int) $_POST['id'], $professorId]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Registro de falta excluído!' : 'Não foi possível excluir este registro.');
    header('Location: faltas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'registrar') {
    $alunoId      = (int) ($_POST['aluno_id'] ?? 0);
    $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
    $data         = $_POST['data'] ?? '';
    $quantidade   = (int) ($_POST['quantidade'] ?? 0);

    if ($alunoId <= 0)      { $erros[] = 'Selecione um aluno.'; }
    if ($disciplinaId <= 0) { $erros[] = 'Selecione uma disciplina.'; }
    if (!DateTime::createFromFormat('Y-m-d', $data)) { $erros[] = 'Informe uma data válida.'; }
    if ($quantidade <= 0)   { $erros[] = 'A quantidade de faltas deve ser maior que zero.'; }
    if ($disciplinaId > 0 && !professorLecionaDisciplina($pdo, $professorId, $disciplinaId)) {
        $erros[] = 'Você não leciona a disciplina selecionada.';
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare('INSERT INTO faltas (aluno_id, disciplina_id, data, quantidade) VALUES (?, ?, ?, ?)');
        $stmt->execute([$alunoId, $disciplinaId, $data, $quantidade]);
        definirMensagem('sucesso', 'Falta registrada com sucesso!');
        header('Location: faltas.php?turma=' . (int) ($_POST['turma_id'] ?? 0));
        exit;
    }
}

$turmaSelecionada = (int) ($_GET['turma'] ?? ($minhasTurmas[0]['id'] ?? 0));
$alunos = $turmaSelecionada > 0 ? alunosDaTurma($pdo, $turmaSelecionada) : [];

$faltasRegistradas = [];
if ($turmaSelecionada > 0 && !empty($minhasDisciplinas)) {
    $idsDisciplinas = array_column($minhasDisciplinas, 'id');
    $placeholders = implode(',', array_fill(0, count($idsDisciplinas), '?'));
    $sql = "
        SELECT f.*, u.nome AS aluno_nome, d.nome AS disciplina_nome
        FROM faltas f
        JOIN alunos a ON a.id = f.aluno_id
        JOIN usuarios u ON u.id = a.usuario_id
        JOIN disciplinas d ON d.id = f.disciplina_id
        WHERE a.turma_id = ? AND f.disciplina_id IN ($placeholders)
        ORDER BY f.data DESC, u.nome
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$turmaSelecionada], $idsDisciplinas));
    $faltasRegistradas = $stmt->fetchAll();
}

$tituloPagina = 'Faltas';
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
            <h2 class="h6 fw-bold mb-3">Registrar falta</h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="registrar">
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
                    <div class="col-7 mb-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" id="data" name="data" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-5 mb-3">
                        <label for="quantidade" class="form-label">Quantidade</label>
                        <input type="number" id="quantidade" name="quantidade" class="form-control" min="1" value="1" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Registrar falta</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Faltas registradas</h2>
            <?php if (empty($faltasRegistradas)): ?>
                <p class="text-muted mb-0">Nenhuma falta registrada para esta turma nas suas disciplinas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Aluno</th><th>Disciplina</th><th>Data</th><th>Qtd.</th><th>Ação</th></tr></thead>
                        <tbody>
                        <?php foreach ($faltasRegistradas as $f): ?>
                            <tr>
                                <td><?= e($f['aluno_nome']) ?></td>
                                <td><?= e($f['disciplina_nome']) ?></td>
                                <td><?= formatarData($f['data']) ?></td>
                                <td><span class="badge text-bg-warning"><?= (int) $f['quantidade'] ?></span></td>
                                <td>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro de falta?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
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
