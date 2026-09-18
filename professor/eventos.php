<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/professor_helpers.php';
exigirTipoUsuario('professor');

$pdo = getConexao();
$professorId = idPerfilLogado();
$usuarioId = (int) $_SESSION['usuario_id'];
$minhasTurmas = turmasDoProfessor($pdo, $professorId);

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM eventos WHERE id = ? AND criado_por = ?');
    $stmt->execute([(int) $_POST['id'], $usuarioId]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Evento excluído com sucesso!' : 'Você só pode excluir eventos criados por você.');
    header('Location: eventos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao        = $_POST['acao'];
    $id          = (int) ($_POST['id'] ?? 0);
    $titulo      = trim($_POST['titulo'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $data        = $_POST['data'] ?? '';
    $hora        = $_POST['hora'] ?? '';
    $local       = trim($_POST['local'] ?? '');
    $publicoAlvo = $_POST['publico_alvo'] ?? 'turma';
    $turmaId     = (int) ($_POST['turma_id'] ?? 0);

    if ($titulo === '') { $erros[] = 'O título é obrigatório.'; }
    if (!DateTime::createFromFormat('Y-m-d', $data)) { $erros[] = 'Informe uma data válida.'; }
    if (!in_array($publicoAlvo, ['todos', 'alunos', 'turma'], true)) { $erros[] = 'Público-alvo inválido.'; }
    if ($publicoAlvo === 'turma' && $turmaId <= 0) { $erros[] = 'Selecione a turma do evento.'; }

    $turmaFinal = $publicoAlvo === 'turma' ? $turmaId : null;
    $horaFinal = $hora !== '' ? $hora : null;

    if (empty($erros)) {
        if ($acao === 'criar') {
            $stmt = $pdo->prepare('INSERT INTO eventos (titulo, descricao, data, hora, local, publico_alvo, turma_id, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$titulo, $descricao, $data, $horaFinal, $local, $publicoAlvo, $turmaFinal, $usuarioId]);
            definirMensagem('sucesso', 'Evento cadastrado com sucesso!');
        } else {
            $stmt = $pdo->prepare('UPDATE eventos SET titulo = ?, descricao = ?, data = ?, hora = ?, local = ?, publico_alvo = ?, turma_id = ? WHERE id = ? AND criado_por = ?');
            $stmt->execute([$titulo, $descricao, $data, $horaFinal, $local, $publicoAlvo, $turmaFinal, $id, $usuarioId]);
            definirMensagem('sucesso', 'Evento atualizado com sucesso!');
        }
        header('Location: eventos.php');
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT ev.*, t.nome AS turma_nome
    FROM eventos ev
    LEFT JOIN turmas t ON t.id = ev.turma_id
    WHERE ev.criado_por = ?
    ORDER BY ev.data DESC
");
$stmt->execute([$usuarioId]);
$meusEventos = $stmt->fetchAll();

$eventoEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM eventos WHERE id = ? AND criado_por = ?');
    $stmt->execute([(int) $_GET['editar'], $usuarioId]);
    $eventoEdicao = $stmt->fetch();
}

$tituloPagina = 'Eventos';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $eventoEdicao ? 'Editar evento' : 'Novo evento' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $eventoEdicao ? 'editar' : 'criar' ?>">
                <?php if ($eventoEdicao): ?><input type="hidden" name="id" value="<?= (int) $eventoEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required value="<?= e($eventoEdicao['titulo'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="2"><?= e($eventoEdicao['descricao'] ?? '') ?></textarea>
                </div>
                <div class="row">
                    <div class="col-7 mb-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" id="data" name="data" class="form-control" required value="<?= e($eventoEdicao['data'] ?? '') ?>">
                    </div>
                    <div class="col-5 mb-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="time" id="hora" name="hora" class="form-control" value="<?= e(substr($eventoEdicao['hora'] ?? '', 0, 5)) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="local" class="form-label">Local</label>
                    <input type="text" id="local" name="local" class="form-control" value="<?= e($eventoEdicao['local'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="publico_alvo" class="form-label">Público-alvo</label>
                    <select id="publico_alvo" name="publico_alvo" class="form-select" required>
                        <?php foreach (['turma' => 'Turma específica', 'alunos' => 'Alunos', 'todos' => 'Todos'] as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= (isset($eventoEdicao) && $eventoEdicao['publico_alvo'] === $valor) ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="turma_id" class="form-label">Turma (se aplicável)</label>
                    <select id="turma_id" name="turma_id" class="form-select">
                        <option value="0">—</option>
                        <?php foreach ($minhasTurmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (isset($eventoEdicao) && $eventoEdicao['turma_id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $eventoEdicao ? 'Salvar alterações' : 'Cadastrar evento' ?></button>
                <?php if ($eventoEdicao): ?><a href="eventos.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3">Eventos criados por mim</h2>
            <?php if (empty($meusEventos)): ?>
                <p class="text-muted mb-0">Você ainda não criou nenhum evento.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Título</th><th>Data</th><th>Local</th><th>Público</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($meusEventos as $ev): ?>
                            <tr>
                                <td><?= e($ev['titulo']) ?></td>
                                <td><?= formatarData($ev['data']) ?><?= $ev['hora'] ? '<br><small class="text-muted">' . formatarHora($ev['hora']) . '</small>' : '' ?></td>
                                <td><?= e($ev['local']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e(rotuloPublicoAlvo($ev['publico_alvo'])) ?><?= $ev['turma_nome'] ? ': ' . e($ev['turma_nome']) : '' ?></span></td>
                                <td class="text-nowrap">
                                    <a href="eventos.php?editar=<?= (int) $ev['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este evento?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
