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
    SELECT m.*, d.nome AS disciplina_nome, u.nome AS professor_nome
    FROM materiais m
    JOIN disciplinas d ON d.id = m.disciplina_id
    JOIN professores p ON p.id = m.professor_id
    JOIN usuarios u ON u.id = p.usuario_id
    WHERE m.turma_id = ?
    ORDER BY m.data_upload DESC
");
$stmt->execute([$turmaId]);
$materiais = $stmt->fetchAll();

$tituloPagina = 'Materiais';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <?php if (empty($materiais)): ?>
        <div class="col-12"><div class="ceon-card"><p class="text-muted mb-0">Nenhum material disponível para sua turma.</p></div></div>
    <?php endif; ?>

    <?php foreach ($materiais as $m): ?>
        <div class="col-md-6 col-lg-4">
            <div class="ceon-card h-100 d-flex flex-column">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi bi-file-earmark-arrow-down fs-3 text-primary"></i>
                    <div>
                        <h3 class="h6 fw-bold mb-0"><?= e($m['titulo']) ?></h3>
                        <span class="badge text-bg-secondary"><?= e($m['disciplina_nome']) ?></span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1"><?= e($m['descricao']) ?></p>
                <p class="small text-muted mb-2">Professor: <?= e($m['professor_nome']) ?> &middot; <?= formatarData(substr($m['data_upload'], 0, 10)) ?></p>
                <a href="download_material.php?id=<?= (int) $m['id'] ?>" class="btn btn-sm btn-primary mt-auto">
                    <i class="bi bi-download"></i> Baixar
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
