<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    try {
        $stmt = $pdo->prepare('DELETE FROM turmas WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        definirMensagem('sucesso', 'Turma excluída com sucesso!');
    } catch (PDOException $ex) {
        definirMensagem('erro', 'Não foi possível excluir a turma: existem alunos vinculados a ela.');
    }
    header('Location: turmas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao      = $_POST['acao'];
    $id        = (int) ($_POST['id'] ?? 0);
    $nome      = trim($_POST['nome'] ?? '');
    $anoLetivo = (int) ($_POST['ano_letivo'] ?? 0);
    $turno     = $_POST['turno'] ?? '';

    if ($nome === '') { $erros[] = 'O nome da turma é obrigatório.'; }
    if ($anoLetivo < 2000 || $anoLetivo > 2100) { $erros[] = 'Informe um ano letivo válido.'; }
    if (!in_array($turno, ['manha', 'tarde', 'noite'], true)) { $erros[] = 'Selecione um turno válido.'; }

    if (empty($erros)) {
        try {
            if ($acao === 'criar') {
                $stmt = $pdo->prepare('INSERT INTO turmas (nome, ano_letivo, turno) VALUES (?, ?, ?)');
                $stmt->execute([$nome, $anoLetivo, $turno]);
                definirMensagem('sucesso', 'Turma cadastrada com sucesso!');
            } else {
                $stmt = $pdo->prepare('UPDATE turmas SET nome = ?, ano_letivo = ?, turno = ? WHERE id = ?');
                $stmt->execute([$nome, $anoLetivo, $turno, $id]);
                definirMensagem('sucesso', 'Turma atualizada com sucesso!');
            }
            header('Location: turmas.php');
            exit;
        } catch (PDOException $ex) {
            $erros[] = 'Já existe uma turma com este nome neste ano letivo.';
        }
    }
}

$busca = trim($_GET['busca'] ?? '');
$filtroTurno = $_GET['turno'] ?? '';

$sql = "
    SELECT t.*, (SELECT COUNT(*) FROM alunos a WHERE a.turma_id = t.id) AS total_alunos
    FROM turmas t WHERE 1 = 1
";
$parametros = [];
if ($busca !== '') { $sql .= ' AND t.nome LIKE ?'; $parametros[] = '%' . $busca . '%'; }
if (in_array($filtroTurno, ['manha', 'tarde', 'noite'], true)) { $sql .= ' AND t.turno = ?'; $parametros[] = $filtroTurno; }
$sql .= ' ORDER BY t.ano_letivo DESC, t.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$turmas = $stmt->fetchAll();

$turmaEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM turmas WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $turmaEdicao = $stmt->fetch();
}

$tituloPagina = 'Turmas';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $turmaEdicao ? 'Editar turma' : 'Nova turma' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $turmaEdicao ? 'editar' : 'criar' ?>">
                <?php if ($turmaEdicao): ?><input type="hidden" name="id" value="<?= (int) $turmaEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome da turma</label>
                    <input type="text" id="nome" name="nome" class="form-control" required value="<?= e($turmaEdicao['nome'] ?? '') ?>" placeholder="Ex.: 1º Ano A - Ensino Médio">
                </div>
                <div class="mb-3">
                    <label for="ano_letivo" class="form-label">Ano letivo</label>
                    <input type="number" id="ano_letivo" name="ano_letivo" class="form-control" required min="2000" max="2100" value="<?= e($turmaEdicao['ano_letivo'] ?? date('Y')) ?>">
                </div>
                <div class="mb-3">
                    <label for="turno" class="form-label">Turno</label>
                    <select id="turno" name="turno" class="form-select" required>
                        <?php foreach (['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= (isset($turmaEdicao) && $turmaEdicao['turno'] === $v) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $turmaEdicao ? 'Salvar alterações' : 'Cadastrar turma' ?></button>
                <?php if ($turmaEdicao): ?><a href="turmas.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Nome da turma">
                </div>
                <div class="col-sm-4">
                    <label for="turnoFiltro" class="form-label small">Turno</label>
                    <select id="turnoFiltro" name="turno" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= $filtroTurno === $v ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($turmas)): ?>
                <p class="text-muted mb-0">Nenhuma turma encontrada.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Turma</th><th>Ano letivo</th><th>Turno</th><th>Alunos</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($turmas as $t): ?>
                            <tr>
                                <td><?= e($t['nome']) ?></td>
                                <td><?= e($t['ano_letivo']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e(ucfirst($t['turno'])) ?></span></td>
                                <td><?= (int) $t['total_alunos'] ?></td>
                                <td class="text-nowrap">
                                    <a href="turmas.php?editar=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
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
