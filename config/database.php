<?php
/**
 * CEON - Plataforma Escolar
 * Configuração de conexão com o banco de dados MySQL via PDO
 */

// Ajuste estes dados conforme sua instalação do XAMPP (padrão: usuário root, sem senha)
define('DB_HOST', 'localhost');
define('DB_NAME', 'ceon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna uma instância PDO conectada ao banco ceon.
 * Utiliza padrão Singleton para reaproveitar a conexão durante a requisição.
 */
function getConexao(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opcoes = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);
        } catch (PDOException $e) {
            die('Erro ao conectar ao banco de dados. Verifique se o MySQL está ativo no XAMPP. Detalhes: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
