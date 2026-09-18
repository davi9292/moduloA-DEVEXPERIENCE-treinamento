<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/professor_helpers.php';
exigirTipoUsuario('professor');

$pdo = getConexao();
$professorId = idPerfilLogado();
$usuarioId = (int) $_SESSION['usuario_id'];
$minhasTurmas = turmasDoProfessor($pdo, $professorId);

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM comunicados WHERE id = ? AND autor_id = ?');
    $stmt->execute([(int) $_POST['id'], $usuarioId]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Comunicado excluído com sucesso!' : 'Você só pode excluir comunicados criados por você.');
    header('Location: comunicados.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao     = $_POST['acao'];
    $id       = (int) ($_POST['id'] ?? 0);
    $titulo   = trim($_POST['titulo'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $turmaId  = (int) ($_POST['turma_id'] ?? 0);

    if ($titulo === '')   { $erros[] = 'O título é obrigatório.'; }
    if ($mensagem === '') { $erros[] = 'A mensagem é obrigatória.'; }
    if ($turmaId <= 0)    { $erros[] = 'Selecione a turma destinatária.'; }

    if (empty($erros)) {
        // Professores publicam comunicados sempre direcionados a uma de suas turmas
        if ($acao === 'criar') {
            $stmt = $pdo->prepare("INSERT INTO comunicados (titulo, mensagem, publico_alvo, turma_id, autor_id) VALUES (?, ?, 'turma', ?, ?)");
            $stmt->execute([$titulo, $mensagem, $turmaId, $usuarioId]);
            definirMensagem('sucesso', 'Comunicado publicado com sucesso!');
        } else {
            $stmt = $pdo->prepare('UPDATE comunicados SET titulo = ?, mensagem = ?, turma_id = ? WHERE id = ? AND autor_id = ?');
            $stmt->execute([$titulo, $mensagem, $turmaId, $id, $usuarioId]);
            definirMensagem('sucesso', 'Comunicado atualizado com sucesso!');
        }
        header('Location: comunicados.php');
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT c.*, t.nome AS turma_nome
    FROM comunicados c
    LEFT JOIN turmas t ON t.id = c.turma_id
    WHERE c.autor_id = ?
    ORDER BY c.data_publicacao DESC
");
$stmt->execute([$usuarioId]);
$meusComunicados = $stmt->fetchAll();

$comunicadoEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM comunicados WHERE id = ? AND autor_id = ?');
    $stmt->execute([(int) $_GET['editar'], $usuarioId]);
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
                    <label for="turma_id" class="form-label">Turma destinatária</label>
                    <select id="turma_id" name="turma_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($minhasTurmas as $t): ?>
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
            <h2 class="h6 fw-bold mb-3">Meus comunicados</h2>
            <?php if (empty($meusComunicados)): ?>
                <p class="text-muted mb-0">Você ainda não publicou nenhum comunicado.</p>
            <?php else: ?>
                <?php foreach ($meusComunicados as $c): ?>
                    <div class="ceon-list-item">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold"><?= e($c['titulo']) ?></div>
                                <div class="small text-muted"><?= e(mb_strimwidth($c['mensagem'], 0, 120, '...')) ?></div>
                                <div class="small text-muted">
                                    <span class="badge text-bg-secondary"><?= e($c['turma_nome'] ?? 'Geral') ?></span>
                                    <?= formatarDataHora($c['data_publicacao']) ?>
                                </div>
                            </div>
                            <div class="text-nowrap">
                                <a href="comunicados.php?editar=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este comunicado?">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Excluir"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
