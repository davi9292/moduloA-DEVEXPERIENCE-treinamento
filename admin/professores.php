<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$erros = [];

// ---------- Exclusão ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $professorId = (int) $_POST['id'];
    $stmt = $pdo->prepare('SELECT usuario_id FROM professores WHERE id = ?');
    $stmt->execute([$professorId]);
    $registro = $stmt->fetch();

    if ($registro) {
        try {
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([(int) $registro['usuario_id']]);
            definirMensagem('sucesso', 'Professor excluído com sucesso!');
        } catch (PDOException $ex) {
            definirMensagem('erro', 'Não foi possível excluir o professor (existem registros vinculados).');
        }
    } else {
        definirMensagem('erro', 'Professor não encontrado.');
    }
    header('Location: professores.php');
    exit;
}

// ---------- Criação / Edição ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao        = $_POST['acao'];
    $professorId = (int) ($_POST['id'] ?? 0);
    $nome        = trim($_POST['nome'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $senha       = $_POST['senha'] ?? '';
    $disciplinas = $_POST['disciplinas'] ?? [];

    if (!is_array($disciplinas)) { $disciplinas = []; }
    $disciplinas = array_map('intval', $disciplinas);

    if ($nome === '') { $erros[] = 'O nome é obrigatório.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros[] = 'Informe um e-mail válido.'; }
    if ($acao === 'criar' && strlen($senha) < 6) { $erros[] = 'A senha deve ter pelo menos 6 caracteres.'; }
    if ($acao === 'editar' && $senha !== '' && strlen($senha) < 6) { $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.'; }

    $usuarioIdAtual = 0;
    if ($acao === 'editar') {
        $stmt = $pdo->prepare('SELECT usuario_id FROM professores WHERE id = ?');
        $stmt->execute([$professorId]);
        $usuarioIdAtual = (int) ($stmt->fetch()['usuario_id'] ?? 0);
    }
    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $usuarioIdAtual]);
        if ($stmt->fetch()) { $erros[] = 'Este e-mail já está cadastrado.'; }
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            if ($acao === 'criar') {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_usuario, status) VALUES (?, ?, ?, 'professor', 'ativo')");
                $stmt->execute([$nome, $email, $hash]);
                $novoUsuarioId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO professores (usuario_id) VALUES (?)')->execute([$novoUsuarioId]);
                $professorId = (int) $pdo->lastInsertId();

                definirMensagem('sucesso', 'Professor cadastrado com sucesso!');
            } else {
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, senha = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $hash, $usuarioIdAtual]);
                } else {
                    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $usuarioIdAtual]);
                }
                definirMensagem('sucesso', 'Professor atualizado com sucesso!');
            }

            // Atualiza os vínculos com disciplinas
            $pdo->prepare('DELETE FROM professor_disciplinas WHERE professor_id = ?')->execute([$professorId]);
            $stmtVinculo = $pdo->prepare('INSERT INTO professor_disciplinas (professor_id, disciplina_id) VALUES (?, ?)');
            foreach ($disciplinas as $disciplinaId) {
                if ($disciplinaId > 0) {
                    $stmtVinculo->execute([$professorId, $disciplinaId]);
                }
            }

            $pdo->commit();
            header('Location: professores.php');
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar o professor: ' . $ex->getMessage();
        }
    }
}

// ---------- Listagem ----------
$busca = trim($_GET['busca'] ?? '');

$sql = "
    SELECT p.id, u.nome, u.email, u.status,
           GROUP_CONCAT(d.nome ORDER BY d.nome SEPARATOR ', ') AS disciplinas
    FROM professores p
    JOIN usuarios u ON u.id = p.usuario_id
    LEFT JOIN professor_disciplinas pd ON pd.professor_id = p.id
    LEFT JOIN disciplinas d ON d.id = pd.disciplina_id
    WHERE 1 = 1
";
$parametros = [];
if ($busca !== '') {
    $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ?)';
    $parametros[] = '%' . $busca . '%';
    $parametros[] = '%' . $busca . '%';
}
$sql .= ' GROUP BY p.id, u.nome, u.email, u.status ORDER BY u.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$professores = $stmt->fetchAll();

$todasDisciplinas = $pdo->query('SELECT id, nome FROM disciplinas ORDER BY nome')->fetchAll();

$professorEdicao = null;
$disciplinasVinculadas = [];
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("
        SELECT p.id, u.nome, u.email
        FROM professores p JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.id = ?
    ");
    $stmt->execute([(int) $_GET['editar']]);
    $professorEdicao = $stmt->fetch();

    if ($professorEdicao) {
        $stmt = $pdo->prepare('SELECT disciplina_id FROM professor_disciplinas WHERE professor_id = ?');
        $stmt->execute([(int) $professorEdicao['id']]);
        $disciplinasVinculadas = array_column($stmt->fetchAll(), 'disciplina_id');
    }
}

$tituloPagina = 'Professores';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $professorEdicao ? 'Editar professor' : 'Novo professor' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $professorEdicao ? 'editar' : 'criar' ?>">
                <?php if ($professorEdicao): ?><input type="hidden" name="id" value="<?= (int) $professorEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" required value="<?= e($professorEdicao['nome'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?= e($professorEdicao['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha <?= $professorEdicao ? '(deixe em branco para manter)' : '' ?></label>
                    <input type="password" id="senha" name="senha" class="form-control" minlength="6" <?= $professorEdicao ? '' : 'required' ?>>
                </div>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Disciplinas lecionadas</legend>
                    <?php foreach ($todasDisciplinas as $d): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="disciplinas[]"
                                   value="<?= (int) $d['id'] ?>" id="disc<?= (int) $d['id'] ?>"
                                   <?= in_array($d['id'], $disciplinasVinculadas) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="disc<?= (int) $d['id'] ?>"><?= e($d['nome']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit" class="btn btn-primary w-100"><?= $professorEdicao ? 'Salvar alterações' : 'Cadastrar professor' ?></button>
                <?php if ($professorEdicao): ?><a href="professores.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-9">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Nome ou e-mail">
                </div>
                <div class="col-sm-3 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($professores)): ?>
                <p class="text-muted mb-0">Nenhum professor encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Disciplinas</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($professores as $p): ?>
                            <tr>
                                <td><?= e($p['nome']) ?></td>
                                <td class="small"><?= e($p['email']) ?></td>
                                <td class="small text-muted"><?= e($p['disciplinas'] ?? 'Nenhuma') ?></td>
                                <td class="text-nowrap">
                                    <a href="professores.php?editar=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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
