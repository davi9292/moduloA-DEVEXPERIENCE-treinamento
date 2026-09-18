<?php
require_once __DIR__ . '/../includes/auth.php';
exigirTipoUsuario('professor');

header('Content-Type: application/json; charset=utf-8');

$pdo = getConexao();
$professorId = idPerfilLogado();
$usuarioId = (int) $_SESSION['usuario_id'];

$itens = [];

// Aulas ministradas pelo professor
$stmt = $pdo->prepare("
    SELECT a.data, a.hora_inicio, a.hora_fim, a.sala, d.nome AS disciplina, t.nome AS turma
    FROM aulas a
    JOIN disciplinas d ON d.id = a.disciplina_id
    JOIN turmas t ON t.id = a.turma_id
    WHERE a.professor_id = ?
");
$stmt->execute([$professorId]);
foreach ($stmt->fetchAll() as $aula) {
    $itens[] = [
        'title' => $aula['disciplina'] . ' - ' . $aula['turma'] . ($aula['sala'] ? ' (' . $aula['sala'] . ')' : ''),
        'start' => $aula['data'] . 'T' . $aula['hora_inicio'],
        'end'   => $aula['data'] . 'T' . $aula['hora_fim'],
        'color' => '#1e3a8a',
    ];
}

// Eventos visíveis ao professor
$stmt = $pdo->prepare("
    SELECT titulo, data, hora FROM eventos
    WHERE publico_alvo IN ('todos','professores') OR criado_por = ?
");
$stmt->execute([$usuarioId]);
foreach ($stmt->fetchAll() as $evento) {
    $itens[] = [
        'title'  => 'Evento: ' . $evento['titulo'],
        'start'  => $evento['data'] . ($evento['hora'] ? 'T' . $evento['hora'] : ''),
        'allDay' => empty($evento['hora']),
        'color'  => '#0ea5a4',
    ];
}

// Tarefas criadas pelo professor
$stmt = $pdo->prepare("
    SELECT t.titulo, t.data_entrega, d.nome AS disciplina, tu.nome AS turma
    FROM tarefas t
    JOIN disciplinas d ON d.id = t.disciplina_id
    JOIN turmas tu ON tu.id = t.turma_id
    WHERE t.professor_id = ?
");
$stmt->execute([$professorId]);
foreach ($stmt->fetchAll() as $tarefa) {
    $itens[] = [
        'title'  => 'Entrega: ' . $tarefa['titulo'] . ' (' . $tarefa['turma'] . ')',
        'start'  => $tarefa['data_entrega'],
        'allDay' => true,
        'color'  => '#d97706',
    ];
}

echo json_encode($itens);
