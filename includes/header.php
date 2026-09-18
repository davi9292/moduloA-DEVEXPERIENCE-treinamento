<?php
/**
 * CEON - Plataforma Escolar
 * Cabeçalho HTML. Espera que $tituloPagina esteja definido antes da inclusão.
 * Espera também que auth.php e functions.php já tenham sido incluídos.
 */
$base = caminhoBase();
$tituloPagina = $tituloPagina ?? 'CEON';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($tituloPagina) ?> - CEON | Plataforma Escolar</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="<?= $base ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="ceon-wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="ceon-content">
    <header class="ceon-topbar">
        <button class="btn ceon-menu-toggle d-lg-none" id="btnToggleSidebar" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">
            <i class="bi bi-list fs-3"></i>
        </button>
        <h1 class="ceon-page-title"><?= e($tituloPagina) ?></h1>
        <div class="ceon-topbar-user">
            <span class="d-none d-sm-inline text-muted small me-2"><?= e($_SESSION['nome'] ?? '') ?></span>
            <span class="badge text-bg-primary"><?= e(rotuloTipoUsuario($_SESSION['tipo_usuario'] ?? '')) ?></span>
        </div>
    </header>

    <main class="ceon-main">
        <?php exibirMensagem(); ?>
