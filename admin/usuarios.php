<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$erros = [];

// ---------- Exclusão ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int) $_POST['id'];

    if ($id === (int) $_SESSION['usuario_id']) {
        definirMensagem('erro', 'Você não pode excluir o próprio usuário que está logado.');
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            definirMensagem('sucesso', 'Usuário excluído com sucesso!');
        } catch (PDOException $ex) {
            definirMensagem('erro', 'Não foi possível excluir o usuário (existem registros vinculados).');
        }
    }
    header('Location: usuarios.php');
    exit;
}

// ---------- Criação / Edição ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao   = $_POST['acao'];
    $id     = (int) ($_POST['id'] ?? 0);
    $nome   = trim($_POST['nome'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $senha  = $_POST['senha'] ?? '';
    $tipo   = $_POST['tipo_usuario'] ?? '';
    $status = $_POST['status'] ?? 'ativo';

    if ($nome === '')  { $erros[] = 'O nome é obrigatório.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erros[] = 'Informe um e-mail válido.'; }
    if (!in_array($tipo, ['aluno', 'professor', 'administrador'], true)) { $erros[] = 'Tipo de usuário inválido.'; }
    if (!in_array($status, ['ativo', 'inativo'], true)) { $erros[] = 'Status inválido.'; }
    if ($acao === 'criar' && strlen($senha) < 6) { $erros[] = 'A senha deve ter pelo menos 6 caracteres.'; }
    if ($acao === 'editar' && $senha !== '' && strlen($senha) < 6) { $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.'; }

    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) { $erros[] = 'Este e-mail já está cadastrado.'; }
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            if ($acao === 'criar') {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo_usuario, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$nome, $email, $hash, $tipo, $status]);
                $novoId = (int) $pdo->lastInsertId();

                // Cria registro na tabela de extensão correspondente
                if ($tipo === 'professor') {
                    $pdo->prepare('INSERT INTO professores (usuario_id) VALUES (?)')->execute([$novoId]);
                } elseif ($tipo === 'aluno') {
                    $turmaId = (int) ($_POST['turma_id'] ?? 0);
                    $dataNascimento = $_POST['data_nascimento'] ?? '';
                    if ($turmaId <= 0 || !DateTime::createFromFormat('Y-m-d', $dataNascimento)) {
                        throw new Exception('Para alunos, informe a turma e a data de nascimento.');
                    }
                    $pdo->prepare('INSERT INTO alunos (usuario_id, turma_id, data_nascimento) VALUES (?, ?, ?)')
                        ->execute([$novoId, $turmaId, $dataNascimento]);
                }

                $pdo->commit();
                definirMensagem('sucesso', 'Usuário cadastrado com sucesso!');
            } else {
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, senha = ?, tipo_usuario = ?, status = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $hash, $tipo, $status, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, tipo_usuario = ?, status = ? WHERE id = ?');
                    $stmt->execute([$nome, $email, $tipo, $status, $id]);
                }
                $pdo->commit();
                definirMensagem('sucesso', 'Usuário atualizado com sucesso!');
            }

            header('Location: usuarios.php');
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $erros[] = $ex->getMessage();
        }
    }
}

// ---------- Listagem com filtros ----------
$busca        = trim($_GET['busca'] ?? '');
$filtroTipo   = $_GET['tipo'] ?? '';
$filtroStatus = $_GET['status'] ?? '';

$sql = 'SELECT * FROM usuarios WHERE 1 = 1';
$parametros = [];

if ($busca !== '') {
    $sql .= ' AND (nome LIKE ? OR email LIKE ?)';
    $parametros[] = '%' . $busca . '%';
    $parametros[] = '%' . $busca . '%';
}
if (in_array($filtroTipo, ['aluno', 'professor', 'administrador'], true)) {
    $sql .= ' AND tipo_usuario = ?';
    $parametros[] = $filtroTipo;
}
if (in_array($filtroStatus, ['ativo', 'inativo'], true)) {
    $sql .= ' AND status = ?';
    $parametros[] = $filtroStatus;
}
$sql .= ' ORDER BY nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$usuarios = $stmt->fetchAll();

$turmas = $pdo->query('SELECT id, nome FROM turmas ORDER BY nome')->fetchAll();

$usuarioEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $usuarioEdicao = $stmt->fetch();
}

$tituloPagina = 'Usuários';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $usuarioEdicao ? 'Editar usuário' : 'Novo usuário' ?></h2>
            <form method="POST" class="needs-validation" novalidate id="formUsuario">
                <input type="hidden" name="acao" value="<?= $usuarioEdicao ? 'editar' : 'criar' ?>">
                <?php if ($usuarioEdicao): ?><input type="hidden" name="id" value="<?= (int) $usuarioEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" id="nome" name="nome" class="form-control" required value="<?= e($usuarioEdicao['nome'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?= e($usuarioEdicao['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha <?= $usuarioEdicao ? '(deixe em branco para manter)' : '' ?></label>
                    <input type="password" id="senha" name="senha" class="form-control" minlength="6" <?= $usuarioEdicao ? '' : 'required' ?>>
                </div>
                <div class="mb-3">
                    <label for="tipo_usuario" class="form-label">Tipo de usuário</label>
                    <select id="tipo_usuario" name="tipo_usuario" class="form-select" required>
                        <?php foreach (['aluno' => 'Aluno', 'professor' => 'Professor', 'administrador' => 'Administrador'] as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= (isset($usuarioEdicao) && $usuarioEdicao['tipo_usuario'] === $valor) ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$usuarioEdicao): ?>
                    <div id="camposAluno" class="d-none">
                        <div class="mb-3">
                            <label for="turma_id" class="form-label">Turma (obrigatório para aluno)</label>
                            <select id="turma_id" name="turma_id" class="form-select">
                                <option value="0">Selecione...</option>
                                <?php foreach ($turmas as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>"><?= e($t['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="data_nascimento" class="form-label">Data de nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" class="form-control">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="ativo" <?= (isset($usuarioEdicao) && $usuarioEdicao['status'] === 'ativo') ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= (isset($usuarioEdicao) && $usuarioEdicao['status'] === 'inativo') ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100"><?= $usuarioEdicao ? 'Salvar alterações' : 'Cadastrar usuário' ?></button>
                <?php if ($usuarioEdicao): ?><a href="usuarios.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-5">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Nome ou e-mail">
                </div>
                <div class="col-sm-3">
                    <label for="tipo" class="form-label small">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['aluno' => 'Aluno', 'professor' => 'Professor', 'administrador' => 'Administrador'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= $filtroTipo === $v ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2">
                    <label for="status" class="form-label small">Status</label>
                    <select id="statusFiltro" name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                </div>
            </form>

            <?php if (empty($usuarios)): ?>
                <p class="text-muted mb-0">Nenhum usuário encontrado com os filtros aplicados.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?= e($u['nome']) ?></td>
                                <td class="small"><?= e($u['email']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e(rotuloTipoUsuario($u['tipo_usuario'])) ?></span></td>
                                <td><span class="badge <?= $u['status'] === 'ativo' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                                <td class="text-nowrap">
                                    <a href="usuarios.php?editar=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
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

<script>
// Exibe os campos extras somente quando o tipo selecionado for "aluno"
(function () {
    const selectTipo = document.getElementById('tipo_usuario');
    const camposAluno = document.getElementById('camposAluno');
    if (!selectTipo || !camposAluno) return;

    function alternarCampos() {
        camposAluno.classList.toggle('d-none', selectTipo.value !== 'aluno');
    }
    selectTipo.addEventListener('change', alternarCampos);
    alternarCampos();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
