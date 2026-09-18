<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Se já estiver logado, redireciona direto para o dashboard correspondente
if (estaLogado()) {
    header('Location: ' . $_SESSION['tipo_usuario'] . '/dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha para continuar.';
    } else {
        $pdo = getConexao();
        $stmt = $pdo->prepare('SELECT id, nome, email, senha, tipo_usuario, status FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $erro = 'E-mail ou senha inválidos.';
        } elseif ($usuario['status'] !== 'ativo') {
            $erro = 'Este usuário está inativo. Procure a administração da escola.';
        } else {
            // Regenera o ID de sessão para evitar fixação de sessão
            session_regenerate_id(true);

            $_SESSION['usuario_id']   = (int) $usuario['id'];
            $_SESSION['nome']         = $usuario['nome'];
            $_SESSION['email']        = $usuario['email'];
            $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

            header('Location: ' . $usuario['tipo_usuario'] . '/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - CEON | Plataforma Escolar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="ceon-login-body">
<div class="ceon-login-wrapper">
    <div class="ceon-login-card">
        <div class="ceon-login-brand">
            <span class="ceon-login-logo">CEON</span>
            <span class="ceon-login-sub">Plataforma Escolar</span>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger" role="alert"><strong>✕</strong> <?= e($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" class="form-control form-control-lg" id="senha" name="senha"
                       required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">Entrar</button>
        </form>

        <div class="ceon-login-hint">
            <p class="text-muted small mb-1 mt-4">Contas de demonstração:</p>
            <ul class="small text-muted mb-0">
                <li>Admin: admin@ceon.com / admin123</li>
                <li>Professor: professor@ceon.com / professor123</li>
                <li>Aluno: aluno@ceon.com / aluno123</li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
