<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('administrador');

$pdo = getConexao();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM cardapio WHERE id = ?');
    $stmt->execute([(int) $_POST['id']]);
    definirMensagem($stmt->rowCount() ? 'sucesso' : 'erro',
        $stmt->rowCount() ? 'Refeição excluída com sucesso!' : 'Não foi possível excluir a refeição.');
    header('Location: cardapio.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['criar', 'editar'], true)) {
    $acao      = $_POST['acao'];
    $id        = (int) ($_POST['id'] ?? 0);
    $data      = $_POST['data'] ?? '';
    $refeicao  = $_POST['refeicao'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');

    if (!DateTime::createFromFormat('Y-m-d', $data)) { $erros[] = 'Informe uma data válida.'; }
    if (!in_array($refeicao, ['cafe_da_manha', 'almoco', 'lanche_da_tarde'], true)) { $erros[] = 'Selecione uma refeição válida.'; }
    if ($descricao === '') { $erros[] = 'A descrição da refeição é obrigatória.'; }

    if (empty($erros)) {
        try {
            if ($acao === 'criar') {
                $stmt = $pdo->prepare('INSERT INTO cardapio (data, refeicao, descricao) VALUES (?, ?, ?)');
                $stmt->execute([$data, $refeicao, $descricao]);
                definirMensagem('sucesso', 'Refeição cadastrada com sucesso!');
            } else {
                $stmt = $pdo->prepare('UPDATE cardapio SET data = ?, refeicao = ?, descricao = ? WHERE id = ?');
                $stmt->execute([$data, $refeicao, $descricao, $id]);
                definirMensagem('sucesso', 'Refeição atualizada com sucesso!');
            }
            header('Location: cardapio.php');
            exit;
        } catch (PDOException $ex) {
            $erros[] = 'Já existe um cardápio cadastrado para esta data e refeição.';
        }
    }
}

$filtroData = $_GET['data'] ?? '';
$sql = "SELECT * FROM cardapio WHERE 1 = 1";
$parametros = [];
if (DateTime::createFromFormat('Y-m-d', $filtroData)) {
    $sql .= ' AND data = ?';
    $parametros[] = $filtroData;
}
$sql .= " ORDER BY data DESC, FIELD(refeicao, 'cafe_da_manha','almoco','lanche_da_tarde')";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$refeicoes = $stmt->fetchAll();

$refeicaoEdicao = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM cardapio WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $refeicaoEdicao = $stmt->fetch();
}

$tituloPagina = 'Cardápio';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ceon-card">
            <h2 class="h6 fw-bold mb-3"><?= $refeicaoEdicao ? 'Editar refeição' : 'Nova refeição' ?></h2>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="acao" value="<?= $refeicaoEdicao ? 'editar' : 'criar' ?>">
                <?php if ($refeicaoEdicao): ?><input type="hidden" name="id" value="<?= (int) $refeicaoEdicao['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label for="data" class="form-label">Data</label>
                    <input type="date" id="data" name="data" class="form-control" required value="<?= e($refeicaoEdicao['data'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="mb-3">
                    <label for="refeicao" class="form-label">Refeição</label>
                    <select id="refeicao" name="refeicao" class="form-select" required>
                        <?php foreach (['cafe_da_manha' => 'Café da Manhã', 'almoco' => 'Almoço', 'lanche_da_tarde' => 'Lanche da Tarde'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= (isset($refeicaoEdicao) && $refeicaoEdicao['refeicao'] === $v) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="4" required placeholder="Ex.: Arroz, feijão, frango grelhado, salada e fruta"><?= e($refeicaoEdicao['descricao'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?= $refeicaoEdicao ? 'Salvar alterações' : 'Cadastrar refeição' ?></button>
                <?php if ($refeicaoEdicao): ?><a href="cardapio.php" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="ceon-card">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-9">
                    <label for="dataFiltro" class="form-label small">Filtrar por data</label>
                    <input type="date" id="dataFiltro" name="data" class="form-control form-control-sm" value="<?= e($filtroData) ?>">
                </div>
                <div class="col-sm-3 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button></div>
            </form>

            <?php if (empty($refeicoes)): ?>
                <p class="text-muted mb-0">Nenhuma refeição cadastrada.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table ceon-table align-middle">
                        <thead><tr><th>Data</th><th>Dia</th><th>Refeição</th><th>Descrição</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($refeicoes as $r): ?>
                            <tr>
                                <td><?= formatarData($r['data']) ?></td>
                                <td class="small text-muted"><?= e(diaSemanaPt($r['data'])) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e(rotuloRefeicao($r['refeicao'])) ?></span></td>
                                <td class="small"><?= e($r['descricao']) ?></td>
                                <td class="text-nowrap">
                                    <a href="cardapio.php?editar=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline ceon-confirm-delete" data-confirm-message="Tem certeza que deseja excluir este registro?">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
