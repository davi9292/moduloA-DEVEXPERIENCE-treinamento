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

// ---------- Exclusão ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('DELETE FROM tarefas WHERE id = ? AND professor_id = ?');
    $stmt->execute([$id, $professorId]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Tarefa excluída com sucesso!' : 'Não foi possível excluir a tarefa.');
    header('Location: tarefas.php');
    exit;
}

// ---------- Criação / Edição ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao          = $_POST['acao'];
    $id            = (int) ($_POST['id'] ?? 0);
    $titulo        = trim($_POST['titulo'] ?? '');
    $descricao     = trim($_POST['descricao'] ?? '');
    $disciplinaId  = (int) ($_POST['disciplina_id'] ?? 0);
    $turmaId       = (int) ($_POST['turma_id'] ?? 0);
    $dataEntrega   = $_POST['data_entrega'] ?? '';

    if ($titulo === '')                    { $erros[] = 'O título é obrigatório.'; }
    if ($disciplinaId <= 0)                { $erros[] = 'Selecione uma disciplina.'; }
    if ($turmaId <= 0)                     { $erros[] = 'Selecione uma turma.'; }
    if (!DateTime::createFromFormat('Y-m-d', $dataEntrega)) { $erros[] = 'Informe uma data de entrega válida.'; }
    if ($disciplinaId > 0 && !professorLecionaDisciplina($pdo, $professorId, $disciplinaId)) {
        $erros[] = 'Você não leciona a disciplina selecionada.';
    }

    if (empty($erros)) {
        if ($acao === 'criar') {
            $stmt = $pdo->prepare('INSERT INTO tarefas (disciplina_id, turma_id, professor_id, titulo, descricao, data_entrega) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$disciplinaId, $turmaId, $professorId, $titulo, $descricao, $dataEntrega]);
            definirMensagem('sucesso', 'Tarefa cadastrada com sucesso!');
        } else {
            $stmt = $pdo->prepare('UPDATE tarefas SET disciplina_id = ?, turma_id = ?, titulo = ?, descricao = ?, data_entrega = ? WHERE id = ? AND professor_id = ?');
            $stmt->execute([$disciplinaId, $turmaId, $titulo, $descricao, $dataEntrega, $id, $professorId]);
            definirMensagem('sucesso', 'Tarefa atualizada com sucesso!');
        }
        header('Location: tarefas.php');
        exit;
    }
}

// ---------- Filtros da listagem ----------
$filtroTurma = (int) ($_GET['turma'] ?? 0);
$busca = trim($_GET['busca'] ?? '');

$sql = "
    SELECT t.*, d.nome AS disciplina_nome, tu.nome AS turma_nome
    FROM tarefas t
    JOIN disciplinas d ON d.id = t.disciplina_id
    JOIN turmas tu ON tu.id = t.turma_id
    WHERE t.professor_id = ?
";
$parametros = [$professorId];

if ($filtroTurma > 0) {
    $sql .= ' AND t.turma_id = ?';
    $parametros[] = $filtroTurma;
}
if ($busca !== '') {
    $sql .= ' AND t.titulo LIKE ?';
    $parametros[] = '%' . $busca . '%';
}
$sql .= ' ORDER BY t.data_entrega DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$tarefas = $stmt->fetchAll();

// Tarefa em edição
$tarefaEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM tarefas WHERE id = ? AND professor_id = ?');
    $stmt->execute([(int) $_GET['editar'], $professorId]);
    $tarefaEdicao = $stmt->fetch();
}

$tituloPagina = 'Tarefas';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $tarefaEdicao ? 'Editar tarefa' : 'Nova tarefa' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $tarefaEdicao ? 'editar' : 'criar' ?>">
                <?php if ($tarefaEdicao): ?>
                    <input type="hidden" name="id" value="<?= (int) $tarefaEdicao['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required
                           value="<?= e($tarefaEdicao['titulo'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= e($tarefaEdicao['descricao'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="disciplina_id" class="form-label">Disciplina</label>
                    <select id="disciplina_id" name="disciplina_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($minhasDisciplinas as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (isset($tarefaEdicao) && $tarefaEdicao['disciplina_id'] == $d['id']) ? 'selected' : '' ?>>
                                <?= e($d['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="turma_id" class="form-label">Turma</label>
                    <select id="turma_id" name="turma_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($minhasTurmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (isset($tarefaEdicao) && $tarefaEdicao['turma_id'] == $t['id']) ? 'selected' : '' ?>>
                                <?= e($t['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="data_entrega" class="form-label">Data de entrega</label>
                    <input type="date" id="data_entrega" name="data_entrega" class="form-control" required
                           value="<?= e($tarefaEdicao['data_entrega'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $tarefaEdicao ? 'Salvar alterações' : 'Cadastrar tarefa' ?></button>
                <?php if ($tarefaEdicao): ?>
                    <a href="tarefas.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-5">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Título da tarefa">
                </div>
                <div class="col-sm-5">
                    <label for="turma" class="form-label small">Turma</label>
                    <select id="turma" name="turma" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($minhasTurmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $filtroTurma === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                </div>
            </form>

            <?php if (empty($tarefas)): ?>
                <p class="text-muted mb-0">Nenhuma tarefa encontrada.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Título</th><th>Disciplina</th><th>Turma</th><th>Entrega</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($tarefas as $t): ?>
                            <tr>
                                <td><?= e($t['titulo']) ?></td>
                                <td><?= e($t['disciplina_nome']) ?></td>
                                <td><?= e($t['turma_nome']) ?></td>
                                <td><?= formatarData($t['data_entrega']) ?></td>
                                <td class="text-nowrap">
                                    <a href="tarefas.php?editar=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir esta tarefa?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
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
