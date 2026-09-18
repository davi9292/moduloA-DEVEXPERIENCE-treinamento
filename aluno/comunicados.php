<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();
$alunoId = idPerfilLogado();

$stmt = $pdo->prepare('SELECT turma_id FROM alunos WHERE id = ?');
$stmt->execute([$alunoId]);
$turmaId = $stmt->fetch()['turma_id'];

$stmt = $pdo->prepare("
    SELECT c.*, u.nome AS autor_nome, t.nome AS turma_nome
    FROM comunicados c
    JOIN usuarios u ON u.id = c.autor_id
    LEFT JOIN turmas t ON t.id = c.turma_id
    WHERE c.publico_alvo = 'todos' OR c.publico_alvo = 'alunos' OR (c.publico_alvo = 'turma' AND c.turma_id = ?)
    ORDER BY c.data_publicacao DESC
");
$stmt->execute([$turmaId]);
$comunicados = $stmt->fetchAll();

$tituloPagina = 'Comunicados';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (empty($comunicados)): ?>
    <div class="ceon-card"><p class="text-muted mb-0">Nenhum comunicado publicado até o momento.</p></div>
<?php endif; ?>

<?php foreach ($comunicados as $c): ?>
    <div class="ceon-card mb-3">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <h3 class="h6 fw-bold mb-0"><?= e($c['titulo']) ?></h3>
            <span class="badge text-bg-secondary"><?= e(rotuloPublicoAlvo($c['publico_alvo'])) ?><?= $c['turma_nome'] ? ' — ' . e($c['turma_nome']) : '' ?></span>
        </div>
        <p class="mb-2"><?= nl2br(e($c['mensagem'])) ?></p>
        <p class="small text-muted mb-0">Por <?= e($c['autor_nome']) ?> em <?= formatarDataHora($c['data_publicacao']) ?></p>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
