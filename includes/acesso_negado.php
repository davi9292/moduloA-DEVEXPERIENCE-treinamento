<?php
// Este arquivo é incluído por exigirTipoUsuario() quando o usuário não tem permissão.
$base = caminhoBase();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Acesso Negado - CEON</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $base ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
<div class="text-center p-5">
    <h1 class="display-1 fw-bold text-danger">403</h1>
    <p class="fs-3"><span class="text-danger">Ops!</span> Acesso negado.</p>
    <p class="lead text-muted">Você não tem permissão para acessar esta página.</p>
    <a href="<?= $base ?>index.php" class="btn btn-primary mt-3">Voltar ao início</a>
</div>
</body>
</html>
