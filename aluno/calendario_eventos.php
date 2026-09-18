<?php
require_once __DIR__ . '/../includes/auth.php';
exigirTipoUsuario('aluno');

header('Content-Type: application/json; charset=utf-8');

$pdo = getConexao();
$alunoId = idPerfilLogado();

$stmt = $pdo->prepare('SELECT turma_id FROM alunos WHERE id = ?');
$stmt->execute([$alunoId]);
$turmaId = $stmt->fetch()['turma_id'];

$itens = [];

// Aulas da turma
$stmt = $pdo->prepare("
    SELECT a.data, a.hora_inicio, a.hora_fim, d.nome AS disciplina, a.sala
    FROM aulas a JOIN disciplinas d ON d.id = a.disciplina_id
    WHERE a.turma_id = ?
");
$stmt->execute([$turmaId]);
foreach ($stmt->fetchAll() as $aula) {
    $itens[] = [
        'title' => 'Aula: ' . $aula['disciplina'] . ($aula['sala'] ? ' (' . $aula['sala'] . ')' : ''),
        'start' => $aula['data'] . 'T' . $aula['hora_inicio'],
        'end'   => $aula['data'] . 'T' . $aula['hora_fim'],
        'color' => '#1e3a8a',
    ];
}

// Eventos visíveis para o aluno
$stmt = $pdo->prepare("
    SELECT titulo, data, hora FROM eventos
    WHERE publico_alvo = 'todos' OR publico_alvo = 'alunos' OR (publico_alvo = 'turma' AND turma_id = ?)
");
$stmt->execute([$turmaId]);
foreach ($stmt->fetchAll() as $evento) {
    $itens[] = [
        'title' => 'Evento: ' . $evento['titulo'],
        'start' => $evento['data'] . ($evento['hora'] ? 'T' . $evento['hora'] : ''),
        'allDay' => empty($evento['hora']),
        'color' => '#0ea5a4',
    ];
}

// Tarefas com entrega
$stmt = $pdo->prepare("
    SELECT t.titulo, t.data_entrega, d.nome AS disciplina
    FROM tarefas t JOIN disciplinas d ON d.id = t.disciplina_id
    WHERE t.turma_id = ?
");
$stmt->execute([$turmaId]);
foreach ($stmt->fetchAll() as $tarefa) {
    $itens[] = [
        'title' => 'Entrega: ' . $tarefa['titulo'] . ' (' . $tarefa['disciplina'] . ')',
        'start' => $tarefa['data_entrega'],
        'allDay' => true,
        'color' => '#d97706',
    ];
}

echo json_encode($itens);
