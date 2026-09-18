<?php
/**
 * CEON - Plataforma Escolar
 * Controle de sessão e autenticação.
 * Este arquivo deve ser incluído no TOPO de toda página protegida.
 *
 * Uso:
 *   require_once __DIR__ . '/../includes/auth.php';
 *   exigirLogin();                         // qualquer usuário logado
 *   exigirTipoUsuario('administrador');    // apenas administradores
 *   exigirTipoUsuario(['professor','administrador']); // múltiplos tipos
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configurações de sessão mais seguras
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Verifica se existe um usuário autenticado na sessão.
 */
function estaLogado(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['tipo_usuario']);
}

/**
 * Redireciona para o login caso o usuário não esteja autenticado.
 */
function exigirLogin(): void
{
    if (!estaLogado()) {
        header('Location: ' . caminhoBase() . 'login.php');
        exit;
    }
}

/**
 * Garante que o usuário logado possui um dos tipos permitidos.
 * Caso contrário, exibe página de acesso negado.
 *
 * @param string|array $tiposPermitidos
 */
function exigirTipoUsuario($tiposPermitidos): void
{
    exigirLogin();

    if (is_string($tiposPermitidos)) {
        $tiposPermitidos = [$tiposPermitidos];
    }

    if (!in_array($_SESSION['tipo_usuario'], $tiposPermitidos, true)) {
        http_response_code(403);
        include __DIR__ . '/acesso_negado.php';
        exit;
    }
}

/**
 * Calcula o caminho relativo até a raiz do projeto (para uso em redirects e links),
 * baseado na profundidade da pasta atual (aluno/, professor/, admin/ ficam 1 nível abaixo).
 */
function caminhoBase(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (preg_match('#/(aluno|professor|admin)(/|$)#', $scriptDir)) {
        return '../';
    }
    return '';
}

/**
 * Retorna o ID interno (tabela alunos/professores) do usuário logado.
 * Para alunos, retorna alunos.id; para professores, professores.id.
 */
function idPerfilLogado(): ?int
{
    $pdo = getConexao();

    if ($_SESSION['tipo_usuario'] === 'aluno') {
        $stmt = $pdo->prepare('SELECT id FROM alunos WHERE usuario_id = ?');
        $stmt->execute([$_SESSION['usuario_id']]);
        $linha = $stmt->fetch();
        return $linha ? (int) $linha['id'] : null;
    }

    if ($_SESSION['tipo_usuario'] === 'professor') {
        $stmt = $pdo->prepare('SELECT id FROM professores WHERE usuario_id = ?');
        $stmt->execute([$_SESSION['usuario_id']]);
        $linha = $stmt->fetch();
        return $linha ? (int) $linha['id'] : null;
    }

    return null;
}
