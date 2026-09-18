<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$usuarioId = (int) $_SESSION['usuario_id'];

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formulario = $_POST['formulario'] ?? '';

    if ($formulario === 'dados') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nome === '' || $email === '') {
            $erros[] = 'Nome e e-mail são obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail válido.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $usuarioId]);
            if ($stmt->fetch()) {
                $erros[] = 'Este e-mail já está em uso por outro usuário.';
            }
        }

        if (empty($erros)) {
            $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $usuarioId]);
            $_SESSION['nome'] = $nome;
            $_SESSION['email'] = $email;
            definirMensagem('sucesso', 'Dados atualizados com sucesso!');
            header('Location: perfil.php');
            exit;
        }
    }

    if ($formulario === 'senha') {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        $stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = ?');
        $stmt->execute([$usuarioId]);
        $hashAtual = $stmt->fetch()['senha'];

        if (!password_verify($senhaAtual, $hashAtual)) {
            $erros[] = 'Senha atual incorreta.';
        } elseif (strlen($novaSenha) < 6) {
            $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($novaSenha !== $confirmarSenha) {
            $erros[] = 'A confirmação de senha não confere.';
        }

        if (empty($erros)) {
            $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
            $stmt->execute([$novoHash, $usuarioId]);
            definirMensagem('sucesso', 'Senha alterada com sucesso!');
            header('Location: perfil.php');
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT nome, email, tipo_usuario FROM usuarios WHERE id = ?');
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

$tituloPagina = 'Perfil';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Meus Dados</h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="formulario" value="dados">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= e($usuario['nome']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= e($usuario['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo de usuário</label>
                    <input type="text" class="form-control" value="<?= e(rotuloTipoUsuario($usuario['tipo_usuario'])) ?>" disabled>
                </div>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Alterar Senha</h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="formulario" value="senha">
                <div class="mb-3">
                    <label class="form-label">Senha atual</label>
                    <input type="password" name="senha_atual" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="nova_senha" class="form-control" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="confirmar_senha" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary">Alterar senha</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
