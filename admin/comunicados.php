<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$usuarioId = (int) $_SESSION['usuario_id'];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM comunicados WHERE id = ?');
    $stmt->execute([(int) $_POST['id']]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Comunicado excluído com sucesso!' : 'Não foi possível excluir o comunicado.');
    header('Location: comunicados.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao        = $_POST['acao'];
    $id          = (int) ($_POST['id'] ?? 0);
    $titulo      = trim($_POST['titulo'] ?? '');
    $mensagem    = trim($_POST['mensagem'] ?? '');
    $publicoAlvo = $_POST['publico_alvo'] ?? 'todos';
    $turmaId     = (int) ($_POST['turma_id'] ?? 0);

    if ($titulo === '')   { $erros[] = 'O título é obrigatório.'; }
    if ($mensagem === '') { $erros[] = 'A mensagem é obrigatória.'; }
    if (!in_array($publicoAlvo, ['todos', 'alunos', 'professores', 'turma'], true)) { $erros[] = 'Público-alvo inválido.'; }
    if ($publicoAlvo === 'turma' && $turmaId <= 0) { $erros[] = 'Selecione a turma destinatária.'; }

    $turmaFinal = $publicoAlvo === 'turma' ? $turmaId : null;

    if (empty($erros)) {
        if ($acao === 'criar') {
            $stmt = $pdo->prepare('INSERT INTO comunicados (titulo, mensagem, publico_alvo, turma_id, autor_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$titulo, $mensagem, $publicoAlvo, $turmaFinal, $usuarioId]);
            definirMensagem('sucesso', 'Comunicado publicado com sucesso!');
        } else {
            $stmt = $pdo->prepare('UPDATE comunicados SET titulo = ?, mensagem = ?, publico_alvo = ?, turma_id = ? WHERE id = ?');
            $stmt->execute([$titulo, $mensagem, $publicoAlvo, $turmaFinal, $id]);
            definirMensagem('sucesso', 'Comunicado atualizado com sucesso!');
        }
        header('Location: comunicados.php');
        exit;
    }
}

$busca = trim($_GET['busca'] ?? '');
$filtroPublico = $_GET['publico'] ?? '';

$sql = "
    SELECT c.*, t.nome AS turma_nome, u.nome AS autor_nome
    FROM comunicados c
    LEFT JOIN turmas t ON t.id = c.turma_id
    JOIN usuarios u ON u.id = c.autor_id
    WHERE 1 = 1
";
$parametros = [];
if ($busca !== '') { $sql .= ' AND c.titulo LIKE ?'; $parametros[] = '%' . $busca . '%'; }
if (in_array($filtroPublico, ['todos', 'alunos', 'professores', 'turma'], true)) {
    $sql .= ' AND c.publico_alvo = ?';
    $parametros[] = $filtroPublico;
}
$sql .= ' ORDER BY c.data_publicacao DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$comunicados = $stmt->fetchAll();

$turmas = $pdo->query('SELECT id, nome FROM turmas ORDER BY nome')->fetchAll();

$comunicadoEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM comunicados WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $comunicadoEdicao = $stmt->fetch();
}

$tituloPagina = 'Comunicados';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $comunicadoEdicao ? 'Editar comunicado' : 'Novo comunicado' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $comunicadoEdicao ? 'editar' : 'criar' ?>">
                <?php if ($comunicadoEdicao): ?><input type="hidden" name="id" value="<?= (int) $comunicadoEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required value="<?= e($comunicadoEdicao['titulo'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="mensagem" class="form-label">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" class="form-control" rows="5" required><?= e($comunicadoEdicao['mensagem'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="publico_alvo" class="form-label">Destinatários</label>
                    <select id="publico_alvo" name="publico_alvo" class="form-select" required>
                        <?php foreach (['todos' => 'Todos', 'alunos' => 'Alunos', 'professores' => 'Professores', 'turma' => 'Turma específica'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= (isset($comunicadoEdicao) && $comunicadoEdicao['publico_alvo'] === $v) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="turma_id" class="form-label">Turma (se aplicável)</label>
                    <select id="turma_id" name="turma_id" class="form-select">
                        <option value="0">—</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (isset($comunicadoEdicao) && $comunicadoEdicao['turma_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $comunicadoEdicao ? 'Salvar alterações' : 'Publicar comunicado' ?></button>
                <?php if ($comunicadoEdicao): ?><a href="comunicados.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Título do comunicado">
                </div>
                <div class="col-sm-4">
                    <label for="publico" class="form-label small">Destinatários</label>
                    <select id="publico" name="publico" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['todos' => 'Todos', 'alunos' => 'Alunos', 'professores' => 'Professores', 'turma' => 'Turma'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= $filtroPublico === $v ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($comunicados)): ?>
                <p class="text-muted mb-0">Nenhum comunicado encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Título</th><th>Destinatários</th><th>Autor</th><th>Publicado em</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($comunicados as $c): ?>
                            <tr>
                                <td><?= e($c['titulo']) ?><br><small class="text-muted"><?= e(mb_strimwidth($c['mensagem'], 0, 60, '...')) ?></small></td>
                                <td><span class="badge text-bg-secondary"><?= e(rotuloPublicoAlvo($c['publico_alvo'])) ?><?= $c['turma_nome'] ? ': ' . e($c['turma_nome']) : '' ?></span></td>
                                <td class="small"><?= e($c['autor_nome']) ?></td>
                                <td class="small"><?= formatarDataHora($c['data_publicacao']) ?></td>
                                <td class="text-nowrap">
                                    <a href="comunicados.php?editar=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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
