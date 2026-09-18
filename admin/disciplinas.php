<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    try {
        $stmt = $pdo->prepare('DELETE FROM disciplinas WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        definirMensagem('sucesso', 'Disciplina excluída com sucesso!');
    } catch (PDOException $ex) {
        definirMensagem('erro', 'Não foi possível excluir a disciplina (existem registros vinculados).');
    }
    header('Location: disciplinas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao      = $_POST['acao'];
    $id        = (int) ($_POST['id'] ?? 0);
    $nome      = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if ($nome === '') { $erros[] = 'O nome da disciplina é obrigatório.'; }

    if (empty($erros)) {
        if ($acao === 'criar') {
            $stmt = $pdo->prepare('INSERT INTO disciplinas (nome, descricao) VALUES (?, ?)');
            $stmt->execute([$nome, $descricao]);
            definirMensagem('sucesso', 'Disciplina cadastrada com sucesso!');
        } else {
            $stmt = $pdo->prepare('UPDATE disciplinas SET nome = ?, descricao = ? WHERE id = ?');
            $stmt->execute([$nome, $descricao, $id]);
            definirMensagem('sucesso', 'Disciplina atualizada com sucesso!');
        }
        header('Location: disciplinas.php');
        exit;
    }
}

$busca = trim($_GET['busca'] ?? '');
$sql = "
    SELECT d.*,
           (SELECT COUNT(*) FROM professor_disciplinas pd WHERE pd.disciplina_id = d.id) AS total_professores
    FROM disciplinas d WHERE 1 = 1
";
$parametros = [];
if ($busca !== '') { $sql .= ' AND d.nome LIKE ?'; $parametros[] = '%' . $busca . '%'; }
$sql .= ' ORDER BY d.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$disciplinas = $stmt->fetchAll();

$disciplinaEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM disciplinas WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $disciplinaEdicao = $stmt->fetch();
}

$tituloPagina = 'Disciplinas';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $disciplinaEdicao ? 'Editar disciplina' : 'Nova disciplina' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $disciplinaEdicao ? 'editar' : 'criar' ?>">
                <?php if ($disciplinaEdicao): ?><input type="hidden" name="id" value="<?= (int) $disciplinaEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" id="nome" name="nome" class="form-control" required value="<?= e($disciplinaEdicao['nome'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= e($disciplinaEdicao['descricao'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $disciplinaEdicao ? 'Salvar alterações' : 'Cadastrar disciplina' ?></button>
                <?php if ($disciplinaEdicao): ?><a href="disciplinas.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-9">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Nome da disciplina">
                </div>
                <div class="col-sm-3 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($disciplinas)): ?>
                <p class="text-muted mb-0">Nenhuma disciplina encontrada.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Nome</th><th>Descrição</th><th>Professores</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($disciplinas as $d): ?>
                            <tr>
                                <td><?= e($d['nome']) ?></td>
                                <td class="small text-muted"><?= e($d['descricao']) ?></td>
                                <td><?= (int) $d['total_professores'] ?></td>
                                <td class="text-nowrap">
                                    <a href="disciplinas.php?editar=<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
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
