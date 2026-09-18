<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$erros = [];

// ---------- Exclusão (remove usuário; alunos cai em cascata) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $alunoId = (int) $_POST['id'];
    $stmt = $pdo->prepare('SELECT usuario_id FROM alunos WHERE id = ?');
    $stmt->execute([$alunoId]);
    $registro = $stmt->fetch();

    if ($registro) {
        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->execute([(int) $registro['usuario_id']]);
        definirMensagem('sucesso', 'Aluno excluído com sucesso!');
    } else {
        definirMensagem('erro', 'Aluno não encontrado.');
    }
    header('Location: alunos.php');
    exit;
}

// ---------- Criação / Edição ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao           = $_POST['acao'];
    $alunoId        = (int) ($_POST['id'] ?? 0);
    $nome           = trim($_POST['nome'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $senha          = $_POST['senha'] ?? '';
    $turmaId        = (int) ($_POST['turma_id'] ?? 0);
    $dataNascimento = $_POST['data_nascimento'] ?? '';

    if ($nome === '') { $erros[] = 'O nome é obrigatório.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros[] = 'Informe um e-mail válido.'; }
    if ($turmaId <= 0) { $erros[] = 'Selecione a turma do aluno.'; }
    if (!DateTime::createFromFormat('Y-m-d', $dataNascimento)) { $erros[] = 'Informe uma data de nascimento válida.'; }
    if ($acao === 'criar' && strlen($senha) < 6) { $erros[] = 'A senha deve ter pelo menos 6 caracteres.'; }
    if ($acao === 'editar' && $senha !== '' && strlen($senha) < 6) { $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.'; }

    // Verifica e-mail duplicado
    $usuarioIdAtual = 0;
    if ($acao === 'editar') {
        $stmt = $pdo->prepare('SELECT usuario_id FROM alunos WHERE id = ?');
        $stmt->execute([$alunoId]);
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
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo_usuario, status) VALUES (?, ?, ?, 'aluno', 'ativo')");
                $stmt->execute([$nome, $email, $hash]);
                $novoUsuarioId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO alunos (usuario_id, turma_id, data_nascimento) VALUES (?, ?, ?)');
                $stmt->execute([$novoUsuarioId, $turmaId, $dataNascimento]);

                definirMensagem('sucesso', 'Aluno cadastrado com sucesso!');
            } else {
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, senha = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $hash, $usuarioIdAtual]);
                } else {
                    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $usuarioIdAtual]);
                }
                $stmt = $pdo->prepare('UPDATE alunos SET turma_id = ?, data_nascimento = ? WHERE id = ?');
                $stmt->execute([$turmaId, $dataNascimento, $alunoId]);

                definirMensagem('sucesso', 'Aluno atualizado com sucesso!');
            }

            $pdo->commit();
            header('Location: alunos.php');
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar o aluno: ' . $ex->getMessage();
        }
    }
}

// ---------- Listagem ----------
$busca = trim($_GET['busca'] ?? '');
$filtroTurma = (int) ($_GET['turma'] ?? 0);

$sql = "
    SELECT a.id, a.data_nascimento, u.nome, u.email, u.status, t.nome AS turma_nome, t.id AS turma_id
    FROM alunos a
    JOIN usuarios u ON u.id = a.usuario_id
    JOIN turmas t ON t.id = a.turma_id
    WHERE 1 = 1
";
$parametros = [];
if ($busca !== '') {
    $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ?)';
    $parametros[] = '%' . $busca . '%';
    $parametros[] = '%' . $busca . '%';
}
if ($filtroTurma > 0) { $sql .= ' AND a.turma_id = ?'; $parametros[] = $filtroTurma; }
$sql .= ' ORDER BY u.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$alunos = $stmt->fetchAll();

$turmas = $pdo->query('SELECT id, nome FROM turmas ORDER BY nome')->fetchAll();

$alunoEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.turma_id, a.data_nascimento, u.nome, u.email
        FROM alunos a JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.id = ?
    ");
    $stmt->execute([(int) $_GET['editar']]);
    $alunoEdicao = $stmt->fetch();
}

$tituloPagina = 'Alunos';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $alunoEdicao ? 'Editar aluno' : 'Novo aluno' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $alunoEdicao ? 'editar' : 'criar' ?>">
                <?php if ($alunoEdicao): ?><input type="hidden" name="id" value="<?= (int) $alunoEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" required value="<?= e($alunoEdicao['nome'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?= e($alunoEdicao['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha <?= $alunoEdicao ? '(deixe em branco para manter)' : '' ?></label>
                    <input type="password" id="senha" name="senha" class="form-control" minlength="6" <?= $alunoEdicao ? '' : 'required' ?>>
                </div>
                <div class="mb-3">
                    <label for="turma_id" class="form-label">Turma</label>
                    <select id="turma_id" name="turma_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (isset($alunoEdicao) && $alunoEdicao['turma_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="data_nascimento" class="form-label">Data de nascimento</label>
                    <input type="date" id="data_nascimento" name="data_nascimento" class="form-control" required value="<?= e($alunoEdicao['data_nascimento'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $alunoEdicao ? 'Salvar alterações' : 'Cadastrar aluno' ?></button>
                <?php if ($alunoEdicao): ?><a href="alunos.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Nome ou e-mail">
                </div>
                <div class="col-sm-4">
                    <label for="turmaFiltro" class="form-label small">Turma</label>
                    <select id="turmaFiltro" name="turma" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $filtroTurma === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($alunos)): ?>
                <p class="text-muted mb-0">Nenhum aluno encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Turma</th><th>Nascimento</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($alunos as $a): ?>
                            <tr>
                                <td><?= e($a['nome']) ?></td>
                                <td class="small"><?= e($a['email']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e($a['turma_nome']) ?></span></td>
                                <td><?= formatarData($a['data_nascimento']) ?></td>
                                <td class="text-nowrap">
                                    <a href="alunos.php?editar=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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
