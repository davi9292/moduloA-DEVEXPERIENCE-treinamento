<?php
require_once __DIR__ . '/../includes/auth.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();
$alunoId = idPerfilLogado();
$materialId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT turma_id FROM alunos WHERE id = ?');
$stmt->execute([$alunoId]);
$turmaId = $stmt->fetch()['turma_id'];

// Garante que o material pertence à turma do aluno logado (evita acesso indevido)
$stmt = $pdo->prepare('SELECT arquivo, titulo FROM materiais WHERE id = ? AND turma_id = ?');
$stmt->execute([$materialId, $turmaId]);
$material = $stmt->fetch();

if (!$material) {
    http_response_code(404);
    die('Material não encontrado ou você não tem permissão para acessá-lo.');
}

$caminhoArquivo = __DIR__ . '/../uploads/materiais/' . $material['arquivo'];

if (!file_exists($caminhoArquivo)) {
    http_response_code(404);
    die('Arquivo não encontrado no servidor.');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($material['arquivo']) . '"');
header('Content-Length: ' . filesize($caminhoArquivo));
readfile($caminhoArquivo);
exit;
