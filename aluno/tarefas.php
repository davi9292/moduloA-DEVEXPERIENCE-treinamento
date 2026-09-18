<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();
$alunoId = idPerfilLogado();

$stmt = $pdo->prepare('SELECT turma_id FROM alunos WHERE id = ?');
$stmt->execute([$alunoId]);
$turmaId = $stmt->fetch()['turma_id'];

// Marcar tarefa como concluída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tarefa_id'])) {
    $tarefaId = (int) $_POST['tarefa_id'];
    $acao = $_POST['acao'] ?? 'concluir';

    if ($acao === 'concluir') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO tarefas_concluidas (tarefa_id, aluno_id) VALUES (?, ?)');
        $stmt->execute([$tarefaId, $alunoId]);
        definirMensagem('sucesso', 'Tarefa marcada como concluída!');
    } else {
        $stmt = $pdo->prepare('DELETE FROM tarefas_concluidas WHERE tarefa_id = ? AND aluno_id = ?');
        $stmt->execute([$tarefaId, $alunoId]);
        definirMensagem('sucesso', 'Tarefa reaberta como pendente.');
    }
    header('Location: tarefas.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT tar.*, d.nome AS disciplina_nome, u.nome AS professor_nome,
           (tc.id IS NOT NULL) AS concluida
    FROM tarefas tar
    JOIN disciplinas d ON d.id = tar.disciplina_id
    JOIN professores p ON p.id = tar.professor_id
    JOIN usuarios u ON u.id = p.usuario_id
    LEFT JOIN tarefas_concluidas tc ON tc.tarefa_id = tar.id AND tc.aluno_id = ?
    WHERE tar.turma_id = ?
    ORDER BY tar.data_entrega ASC
");
$stmt->execute([$alunoId, $turmaId]);
$todasTarefas = $stmt->fetchAll();

$hoje = date('Y-m-d');
$pendentes = $atrasadas = $concluidas = [];
foreach ($todasTarefas as $tarefa) {
    if ($tarefa['concluida']) {
        $concluidas[] = $tarefa;
    } elseif ($tarefa['data_entrega'] < $hoje) {
        $atrasadas[] = $tarefa;
    } else {
        $pendentes[] = $tarefa;
    }
}

$tituloPagina = 'Tarefas';
require_once __DIR__ . '/../includes/header.php';

function renderTarefasTabela(array $lista, string $statusBadge, string $badgeClasse): void
{
    if (empty($lista)) {
        echo '<p class="text-muted small">Nenhuma tarefa nesta categoria.</p>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table ceon-table align-middle">
            <thead><tr><th>Disciplina</th><th>Título</th><th>Descrição</th><th>Professor</th><th>Entrega</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $t): ?>
                <tr>
                    <td><?= e($t['disciplina_nome']) ?></td>
                    <td><?= e($t['titulo']) ?></td>
                    <td class="small text-muted"><?= e($t['descricao']) ?></td>
                    <td><?= e($t['professor_nome']) ?></td>
                    <td><?= formatarData($t['data_entrega']) ?></td>
                    <td><span class="badge <?= $badgeClasse ?>"><?= $statusBadge ?></span></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="tarefa_id" value="<?= (int) $t['id'] ?>">
                            <?php if ($t['concluida']): ?>
                                <input type="hidden" name="acao" value="reabrir">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Reabrir</button>
                            <?php else: ?>
                                <input type="hidden" name="acao" value="concluir">
                                <button type="submit" class="btn btn-sm btn-success">Concluir</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-pendentes" type="button">Pendentes (<?= count($pendentes) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-atrasadas" type="button">Atrasadas (<?= count($atrasadas) ?>)</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-concluidas" type="button">Concluídas (<?= count($concluidas) ?>)</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active ceon-card" id="tab-pendentes">
        <?php renderTarefasTabela($pendentes, 'Pendente', 'badge-status-pendente'); ?>
    </div>
    <div class="tab-pane fade ceon-card" id="tab-atrasadas">
        <?php renderTarefasTabela($atrasadas, 'Atrasada', 'badge-status-atrasada'); ?>
    </div>
    <div class="tab-pane fade ceon-card" id="tab-concluidas">
        <?php renderTarefasTabela($concluidas, 'Concluída', 'badge-status-concluida'); ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
