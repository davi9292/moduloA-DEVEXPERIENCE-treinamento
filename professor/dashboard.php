<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/professor_helpers.php';
exigirTipoUsuario('professor');

$pdo = getConexao();
$professorId = idPerfilLogado();

$minhasDisciplinas = disciplinasDoProfessor($pdo, $professorId);
$minhasTurmas = turmasDoProfessor($pdo, $professorId);

$stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tarefas WHERE professor_id = ?');
$stmt->execute([$professorId]);
$totalTarefas = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c FROM eventos
    WHERE data >= CURDATE() AND (publico_alvo IN ('todos','professores') OR criado_por = ?)
");
$stmt->execute([(int) $_SESSION['usuario_id']]);
$totalEventos = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("
    SELECT * FROM eventos
    WHERE data >= CURDATE() AND (publico_alvo IN ('todos','professores') OR criado_por = ?)
    ORDER BY data ASC, hora ASC LIMIT 5
");
$stmt->execute([(int) $_SESSION['usuario_id']]);
$proximosEventos = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT t.*, d.nome AS disciplina_nome, tu.nome AS turma_nome
    FROM tarefas t
    JOIN disciplinas d ON d.id = t.disciplina_id
    JOIN turmas tu ON tu.id = t.turma_id
    WHERE t.professor_id = ?
    ORDER BY t.data_entrega DESC LIMIT 5
");
$stmt->execute([$professorId]);
$tarefasRecentes = $stmt->fetchAll();

$tituloPagina = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#1e3a8a;"><i class="bi bi-people"></i></div>
            <div>
                <div class="ceon-stat-value"><?= count($minhasTurmas) ?></div>
                <div class="ceon-stat-label">Minhas turmas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#0ea5a4;"><i class="bi bi-book"></i></div>
            <div>
                <div class="ceon-stat-value"><?= count($minhasDisciplinas) ?></div>
                <div class="ceon-stat-label">Minhas disciplinas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#d97706;"><i class="bi bi-list-check"></i></div>
            <div>
                <div class="ceon-stat-value"><?= $totalTarefas ?></div>
                <div class="ceon-stat-label">Tarefas criadas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#16a34a;"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="ceon-stat-value"><?= $totalEventos ?></div>
                <div class="ceon-stat-label">Próximos eventos</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-people text-primary"></i> Minhas turmas</h2>
            <?php if (empty($minhasTurmas)): ?>
                <p class="text-muted small mb-0">Nenhuma turma vinculada.</p>
            <?php else: ?>
                <?php foreach ($minhasTurmas as $t): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($t['nome']) ?></div>
                        <div class="small text-muted"><?= e($t['ano_letivo']) ?> &middot; <?= e(ucfirst($t['turno'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="turmas.php" class="btn btn-sm btn-outline-primary mt-2">Ver detalhes</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-list-check text-warning"></i> Tarefas criadas</h2>
            <?php if (empty($tarefasRecentes)): ?>
                <p class="text-muted small mb-0">Nenhuma tarefa cadastrada.</p>
            <?php else: ?>
                <?php foreach ($tarefasRecentes as $t): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($t['titulo']) ?></div>
                        <div class="small text-muted"><?= e($t['disciplina_nome']) ?> &middot; <?= e($t['turma_nome']) ?></div>
                        <div class="small text-muted">Entrega: <?= formatarData($t['data_entrega']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="tarefas.php" class="btn btn-sm btn-outline-primary mt-2">Gerenciar</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-calendar-event text-info"></i> Próximos eventos</h2>
            <?php if (empty($proximosEventos)): ?>
                <p class="text-muted small mb-0">Nenhum evento agendado.</p>
            <?php else: ?>
                <?php foreach ($proximosEventos as $ev): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($ev['titulo']) ?></div>
                        <div class="small text-muted"><?= formatarData($ev['data']) ?> <?= $ev['hora'] ? 'às ' . formatarHora($ev['hora']) : '' ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="eventos.php" class="btn btn-sm btn-outline-primary mt-2">Gerenciar</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
