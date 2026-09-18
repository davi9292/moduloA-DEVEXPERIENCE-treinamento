<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();
$alunoId = idPerfilLogado();

// Dados do aluno e turma
$stmt = $pdo->prepare('SELECT a.turma_id, t.nome AS turma_nome FROM alunos a JOIN turmas t ON t.id = a.turma_id WHERE a.id = ?');
$stmt->execute([$alunoId]);
$aluno = $stmt->fetch();
$turmaId = $aluno['turma_id'];

// Quantidade de disciplinas (todas cadastradas, já que não há vínculo direto turma-disciplina)
$totalDisciplinas = (int) $pdo->query('SELECT COUNT(*) AS c FROM disciplinas')->fetch()['c'];

// Tarefas pendentes (data de entrega >= hoje e não concluídas)
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c FROM tarefas t
    WHERE t.turma_id = ?
    AND t.data_entrega >= CURDATE()
    AND t.id NOT IN (SELECT tarefa_id FROM tarefas_concluidas WHERE aluno_id = ?)
");
$stmt->execute([$turmaId, $alunoId]);
$tarefasPendentes = (int) $stmt->fetch()['c'];

// Próximos eventos (todos + turma do aluno)
$stmt = $pdo->prepare("
    SELECT * FROM eventos
    WHERE data >= CURDATE()
    AND (publico_alvo = 'todos' OR publico_alvo = 'alunos' OR (publico_alvo = 'turma' AND turma_id = ?))
    ORDER BY data ASC, hora ASC
    LIMIT 5
");
$stmt->execute([$turmaId]);
$proximosEventos = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c FROM eventos
    WHERE data >= CURDATE()
    AND (publico_alvo = 'todos' OR publico_alvo = 'alunos' OR (publico_alvo = 'turma' AND turma_id = ?))
");
$stmt->execute([$turmaId]);
$totalEventosProximos = (int) $stmt->fetch()['c'];

// Comunicados recentes
$stmt = $pdo->prepare("
    SELECT * FROM comunicados
    WHERE (publico_alvo = 'todos' OR publico_alvo = 'alunos' OR (publico_alvo = 'turma' AND turma_id = ?))
    ORDER BY data_publicacao DESC
    LIMIT 5
");
$stmt->execute([$turmaId]);
$comunicadosRecentes = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c FROM comunicados
    WHERE (publico_alvo = 'todos' OR publico_alvo = 'alunos' OR (publico_alvo = 'turma' AND turma_id = ?))
");
$stmt->execute([$turmaId]);
$totalComunicados = (int) $stmt->fetch()['c'];

// Média geral do aluno
$stmt = $pdo->prepare('SELECT valor, peso FROM notas WHERE aluno_id = ?');
$stmt->execute([$alunoId]);
$todasNotas = $stmt->fetchAll();
$mediaGeral = calcularMediaPonderada($todasNotas);

// Próximas tarefas (detalhado)
$stmt = $pdo->prepare("
    SELECT tar.*, d.nome AS disciplina_nome, u.nome AS professor_nome,
           (tc.id IS NOT NULL) AS concluida
    FROM tarefas tar
    JOIN disciplinas d ON d.id = tar.disciplina_id
    JOIN professores p ON p.id = tar.professor_id
    JOIN usuarios u ON u.id = p.usuario_id
    LEFT JOIN tarefas_concluidas tc ON tc.tarefa_id = tar.id AND tc.aluno_id = ?
    WHERE tar.turma_id = ? AND tar.data_entrega >= CURDATE()
    ORDER BY tar.data_entrega ASC
    LIMIT 5
");
$stmt->execute([$alunoId, $turmaId]);
$proximasTarefas = $stmt->fetchAll();

$tituloPagina = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#1e3a8a;"><i class="bi bi-book"></i></div>
            <div>
                <div class="ceon-stat-value"><?= $totalDisciplinas ?></div>
                <div class="ceon-stat-label">Disciplinas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#d97706;"><i class="bi bi-list-check"></i></div>
            <div>
                <div class="ceon-stat-value"><?= $tarefasPendentes ?></div>
                <div class="ceon-stat-label">Tarefas pendentes</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#0ea5a4;"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="ceon-stat-value"><?= $totalEventosProximos ?></div>
                <div class="ceon-stat-label">Próximos eventos</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="ceon-card ceon-stat-card">
            <div class="ceon-stat-icon" style="background:#16a34a;"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="ceon-stat-value"><?= number_format($mediaGeral, 1, ',', '.') ?></div>
                <div class="ceon-stat-label">Média geral</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-list-check text-warning"></i> Próximas tarefas</h2>
            <?php if (empty($proximasTarefas)): ?>
                <p class="text-muted small mb-0">Nenhuma tarefa pendente no momento.</p>
            <?php else: ?>
                <?php foreach ($proximasTarefas as $tarefa): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($tarefa['disciplina_nome']) ?></div>
                        <div class="small"><?= e($tarefa['titulo']) ?></div>
                        <div class="small text-muted">Entrega: <?= formatarData($tarefa['data_entrega']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="tarefas.php" class="btn btn-sm btn-outline-primary mt-2">Ver todas</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-calendar-event text-info"></i> Próximos eventos</h2>
            <?php if (empty($proximosEventos)): ?>
                <p class="text-muted small mb-0">Nenhum evento agendado.</p>
            <?php else: ?>
                <?php foreach ($proximosEventos as $evento): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($evento['titulo']) ?></div>
                        <div class="small text-muted"><?= formatarData($evento['data']) ?> <?= $evento['hora'] ? 'às ' . formatarHora($evento['hora']) : '' ?></div>
                        <?php if ($evento['local']): ?><div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($evento['local']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="calendario.php" class="btn btn-sm btn-outline-primary mt-2">Ver calendário</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-megaphone text-primary"></i> Comunicados recentes</h2>
            <?php if (empty($comunicadosRecentes)): ?>
                <p class="text-muted small mb-0">Nenhum comunicado publicado.</p>
            <?php else: ?>
                <?php foreach ($comunicadosRecentes as $com): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold"><?= e($com['titulo']) ?></div>
                        <div class="small text-muted"><?= e(mb_strimwidth($com['mensagem'], 0, 80, '...')) ?></div>
                        <div class="small text-muted"><?= formatarData(substr($com['data_publicacao'], 0, 10)) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="comunicados.php" class="btn btn-sm btn-outline-primary mt-2">Ver todos</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
