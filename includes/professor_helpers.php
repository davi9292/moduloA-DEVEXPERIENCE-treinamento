<?php
/**
 * CEON - Plataforma Escolar
 * Funções auxiliares específicas do módulo do professor.
 */

/**
 * Retorna as disciplinas vinculadas ao professor informado.
 */
function disciplinasDoProfessor(PDO $pdo, int $professorId): array
{
    $stmt = $pdo->prepare("
        SELECT d.id, d.nome
        FROM professor_disciplinas pd
        JOIN disciplinas d ON d.id = pd.disciplina_id
        WHERE pd.professor_id = ?
        ORDER BY d.nome
    ");
    $stmt->execute([$professorId]);
    return $stmt->fetchAll();
}

/**
 * Retorna as turmas nas quais o professor possui aulas cadastradas.
 * Caso não haja aulas, devolve todas as turmas (para permitir o primeiro cadastro).
 */
function turmasDoProfessor(PDO $pdo, int $professorId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.id, t.nome, t.ano_letivo, t.turno
        FROM aulas a
        JOIN turmas t ON t.id = a.turma_id
        WHERE a.professor_id = ?
        ORDER BY t.nome
    ");
    $stmt->execute([$professorId]);
    $turmas = $stmt->fetchAll();

    if (empty($turmas)) {
        $turmas = $pdo->query('SELECT id, nome, ano_letivo, turno FROM turmas ORDER BY nome')->fetchAll();
    }

    return $turmas;
}

/**
 * Verifica se o professor possui vínculo com a disciplina informada.
 */
function professorLecionaDisciplina(PDO $pdo, int $professorId, int $disciplinaId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM professor_disciplinas WHERE professor_id = ? AND disciplina_id = ?');
    $stmt->execute([$professorId, $disciplinaId]);
    return (bool) $stmt->fetch();
}

/**
 * Retorna os alunos de uma turma específica.
 */
function alunosDaTurma(PDO $pdo, int $turmaId): array
{
    $stmt = $pdo->prepare("
        SELECT a.id, u.nome
        FROM alunos a
        JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.turma_id = ?
        ORDER BY u.nome
    ");
    $stmt->execute([$turmaId]);
    return $stmt->fetchAll();
}
