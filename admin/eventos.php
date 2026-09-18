<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$usuarioId = (int) $_SESSION['usuario_id'];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM eventos WHERE id = ?');
    $stmt->execute([(int) $_POST['id']]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Evento excluído com sucesso!' : 'Não foi possível excluir o evento.');
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
    $publicoAlvo = $_POST['publico_alvo'] ?? 'todos';
    $turmaId     = (int) ($_POST['turma_id'] ?? 0);

    if ($titulo === '') { $erros[] = 'O título é obrigatório.'; }
    if (!DateTime::createFromFormat('Y-m-d', $data)) { $erros[] = 'Informe uma data válida.'; }
    if (!in_array($publicoAlvo, ['todos', 'alunos', 'professores', 'turma'], true)) { $erros[] = 'Público-alvo inválido.'; }
    if ($publicoAlvo === 'turma' && $turmaId <= 0) { $erros[] = 'Selecione a turma do evento.'; }

    $turmaFinal = $publicoAlvo === 'turma' ? $turmaId : null;
    $horaFinal = $hora !== '' ? $hora : null;

    if (empty($erros)) {
        if ($acao === 'criar') {
            $stmt = $pdo->prepare('INSERT INTO eventos (titulo, descricao, data, hora, local, publico_alvo, turma_id, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$titulo, $descricao, $data, $horaFinal, $local, $publicoAlvo, $turmaFinal, $usuarioId]);
            definirMensagem('sucesso', 'Evento cadastrado com sucesso!');
        } else {
            $stmt = $pdo->prepare('UPDATE eventos SET titulo = ?, descricao = ?, data = ?, hora = ?, local = ?, publico_alvo = ?, turma_id = ? WHERE id = ?');
            $stmt->execute([$titulo, $descricao, $data, $horaFinal, $local, $publicoAlvo, $turmaFinal, $id]);
            definirMensagem('sucesso', 'Evento atualizado com sucesso!');
        }
        header('Location: eventos.php');
        exit;
    }
}

$busca = trim($_GET['busca'] ?? '');
$filtroPublico = $_GET['publico'] ?? '';

$sql = "
    SELECT ev.*, t.nome AS turma_nome, u.nome AS autor_nome
    FROM eventos ev
    LEFT JOIN turmas t ON t.id = ev.turma_id
    JOIN usuarios u ON u.id = ev.criado_por
    WHERE 1 = 1
";
$parametros = [];
if ($busca !== '') { $sql .= ' AND ev.titulo LIKE ?'; $parametros[] = '%' . $busca . '%'; }
if (in_array($filtroPublico, ['todos', 'alunos', 'professores', 'turma'], true)) {
    $sql .= ' AND ev.publico_alvo = ?';
    $parametros[] = $filtroPublico;
}
$sql .= ' ORDER BY ev.data DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$eventos = $stmt->fetchAll();

$turmas = $pdo->query('SELECT id, nome FROM turmas ORDER BY nome')->fetchAll();

$eventoEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM eventos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
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
                        <?php foreach (['todos' => 'Todos', 'alunos' => 'Alunos', 'professores' => 'Professores', 'turma' => 'Turma específica'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= (isset($eventoEdicao) && $eventoEdicao['publico_alvo'] === $v) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="turma_id" class="form-label">Turma (se aplicável)</label>
                    <select id="turma_id" name="turma_id" class="form-select">
                        <option value="0">—</option>
                        <?php foreach ($turmas as $t): ?>
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
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="busca" class="form-label small">Pesquisar</label>
                    <input type="text" id="busca" name="busca" class="form-control form-control-sm" value="<?= e($busca) ?>" placeholder="Título do evento">
                </div>
                <div class="col-sm-4">
                    <label for="publico" class="form-label small">Público-alvo</label>
                    <select id="publico" name="publico" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (['todos' => 'Todos', 'alunos' => 'Alunos', 'professores' => 'Professores', 'turma' => 'Turma'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= $filtroPublico === $v ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($eventos)): ?>
                <p class="text-muted mb-0">Nenhum evento encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Título</th><th>Data</th><th>Local</th><th>Público</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($eventos as $ev): ?>
                            <tr>
                                <td><?= e($ev['titulo']) ?><br><small class="text-muted">por <?= e($ev['autor_nome']) ?></small></td>
                                <td><?= formatarData($ev['data']) ?><?= $ev['hora'] ? '<br><small class="text-muted">' . formatarHora($ev['hora']) . '</small>' : '' ?></td>
                                <td class="small"><?= e($ev['local']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e(rotuloPublicoAlvo($ev['publico_alvo'])) ?><?= $ev['turma_nome'] ? ': ' . e($ev['turma_nome']) : '' ?></span></td>
                                <td class="text-nowrap">
                                    <a href="eventos.php?editar=<?= (int) $ev['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
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
