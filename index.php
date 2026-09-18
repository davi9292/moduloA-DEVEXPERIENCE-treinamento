<?php
require_once __DIR__ . '/includes/auth.php';

if (estaLogado()) {
    header('Location: ' . $_SESSION['tipo_usuario'] . '/dashboard.php');
} else {
    header('Location: login.php');
}
exit;
