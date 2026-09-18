<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/professor_helpers.php';
exigirTipoUsuario('professor');

$pdo = getConexao();
$professorId = idPerfilLogado();
$minhasDisciplinas = disciplinasDoProfessor($pdo, $professorId);
$minhasTurmas = turmasDoProfessor($pdo, $professorId);
$pastaUploads = __DIR__ . '/../uploads/materiais';

$erros = [];

// ---------- Exclusão ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare('SELECT arquivo FROM materiais WHERE id = ? AND professor_id = ?');
    $stmt->execute([$id, $professorId]);
    $material = $stmt->fetch();

    if ($material) {
        $stmt = $pdo->prepare('DELETE FROM materiais WHERE id = ? AND professor_id = ?');
        $stmt->execute([$id, $professorId]);
        $caminho = $pastaUploads . '/' . $material['arquivo'];
        if (is_file($caminho)) {
            unlink($caminho);
        }
        definirMensagem('sucesso', 'Material excluído com sucesso!');
    } else {
        definirMensagem('erro', 'Você só pode excluir materiais enviados por você.');
    }
    header('Location: materiais.php');
    exit;
}

// ---------- Edição de metadados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar') {
    $id           = (int) $_POST['id'];
    $titulo       = trim($_POST['titulo'] ?? '');
    $descricao    = trim($_POST['descricao'] ?? '');
    $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
    $turmaId      = (int) ($_POST['turma_id'] ?? 0);

    if ($titulo === '')     { $erros[] = 'O título é obrigatório.'; }
    if ($disciplinaId <= 0) { $erros[] = 'Selecione uma disciplina.'; }
    if ($turmaId <= 0)      { $erros[] = 'Selecione uma turma.'; }

    if (empty($erros)) {
        $stmt = $pdo->prepare('UPDATE materiais SET titulo = ?, descricao = ?, disciplina_id = ?, turma_id = ? WHERE id = ? AND professor_id = ?');
        $stmt->execute([$titulo, $descricao, $disciplinaId, $turmaId, $id, $professorId]);
        definirMensagem('sucesso', 'Material atualizado com sucesso!');
        header('Location: materiais.php');
        exit;
    }
}

// ---------- Upload de novo material ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar') {
    $titulo       = trim($_POST['titulo'] ?? '');
    $descricao    = trim($_POST['descricao'] ?? '');
    $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
    $turmaId      = (int) ($_POST['turma_id'] ?? 0);

    if ($titulo === '')     { $erros[] = 'O título é obrigatório.'; }
    if ($disciplinaId <= 0) { $erros[] = 'Selecione uma disciplina.'; }
    if ($turmaId <= 0)      { $erros[] = 'Selecione uma turma.'; }
    if ($disciplinaId > 0 && !professorLecionaDisciplina($pdo, $professorId, $disciplinaId)) {
        $erros[] = 'Você não leciona a disciplina selecionada.';
    }

    $nomeArquivo = null;
    if (empty($erros)) {
        try {
            if (!is_dir($pastaUploads)) {
                mkdir($pastaUploads, 0775, true);
            }
            $nomeArquivo = processarUpload($_FILES['arquivo'] ?? [], $pastaUploads);
        } catch (Exception $ex) {
            $erros[] = $ex->getMessage();
        }
    }

    if (empty($erros) && $nomeArquivo) {
        $stmt = $pdo->prepare('INSERT INTO materiais (professor_id, turma_id, disciplina_id, titulo, descricao, arquivo) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$professorId, $turmaId, $disciplinaId, $titulo, $descricao, $nomeArquivo]);
        definirMensagem('sucesso', 'Material enviado com sucesso!');
        header('Location: materiais.php');
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT m.*, d.nome AS disciplina_nome, t.nome AS turma_nome
    FROM materiais m
    JOIN disciplinas d ON d.id = m.disciplina_id
    JOIN turmas t ON t.id = m.turma_id
    WHERE m.professor_id = ?
    ORDER BY m.data_upload DESC
");
$stmt->execute([$professorId]);
$meusMateriais = $stmt->fetchAll();

$materialEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM materiais WHERE id = ? AND professor_id = ?');
    $stmt->execute([(int) $_GET['editar'], $professorId]);
    $materialEdicao = $stmt->fetch();
}

$tituloPagina = 'Materiais';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $materialEdicao ? 'Editar material' : 'Enviar novo material' ?></h2>
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $materialEdicao ? 'editar' : 'criar' ?>">
                <?php if ($materialEdicao): ?><input type="hidden" name="id" value="<?= (int) $materialEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required value="<?= e($materialEdicao['titulo'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= e($materialEdicao['descricao'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="disciplina_id" class="form-label">Disciplina</label>
                    <select id="disciplina_id" name="disciplina_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($minhasDisciplinas as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (isset($materialEdicao) && $materialEdicao['disciplina_id'] == $d['id']) ? 'selected' : '' ?>><?= e($d['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="turma_id" class="form-label">Turma</label>
                    <select id="turma_id" name="turma_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($minhasTurmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (isset($materialEdicao) && $materialEdicao['turma_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$materialEdicao): ?>
                    <div class="mb-3">
                        <label for="arquivo" class="form-label">Arquivo</label>
                        <input type="file" id="arquivo" name="arquivo" class="form-control" required
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.txt,.zip">
                        <div class="form-text">Até 10 MB. Formatos aceitos: PDF, Word, PowerPoint, Excel, imagens, TXT e ZIP.</div>
                    </div>
                <?php else: ?>
                    <p class="small text-muted">Arquivo atual: <?= e($materialEdicao['arquivo']) ?> (para trocar o arquivo, exclua e envie novamente).</p>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary w-100"><?= $materialEdicao ? 'Salvar alterações' : 'Enviar material' ?></button>
                <?php if ($materialEdicao): ?><a href="materiais.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Meus materiais</h2>
            <?php if (empty($meusMateriais)): ?>
                <p class="text-muted mb-0">Nenhum material enviado ainda.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Título</th><th>Disciplina</th><th>Turma</th><th>Enviado em</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($meusMateriais as $m): ?>
                            <tr>
                                <td><?= e($m['titulo']) ?><br><small class="text-muted"><?= e($m['arquivo']) ?></small></td>
                                <td><?= e($m['disciplina_nome']) ?></td>
                                <td><?= e($m['turma_nome']) ?></td>
                                <td><?= formatarData(substr($m['data_upload'], 0, 10)) ?></td>
                                <td class="text-nowrap">
                                    <a href="materiais.php?editar=<?= (int) $m['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este material?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
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
