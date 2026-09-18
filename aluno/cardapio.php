<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirTipoUsuario('aluno');

$pdo = getConexao();

$stmt = $pdo->prepare("SELECT * FROM cardapio WHERE data >= CURDATE() ORDER BY data ASC, FIELD(refeicao, 'cafe_da_manha','almoco','lanche_da_tarde') LIMIT 30");
$stmt->execute();
$itens = $stmt->fetchAll();

$porDia = [];
foreach ($itens as $item) {
    $porDia[$item['data']][] = $item;
}

$tituloPagina = 'Cardápio';
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="h5 fw-bold mb-3">Cardápio da Semana</h2>

<?php if (empty($porDia)): ?>
    <div class="ceon-card"><p class="text-muted mb-0">Nenhum cardápio cadastrado para os próximos dias.</p></div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($porDia as $data => $refeicoes): ?>
        <div class="col-md-6 col-lg-4">
            <div class="ceon-card h-100">
                <h3 class="h6 fw-bold text-uppercase text-primary"><?= e(diaSemanaPt($data)) ?></h3>
                <p class="small text-muted mb-3"><?= formatarData($data) ?></p>
                <?php foreach ($refeicoes as $r): ?>
                    <div class="ceon-list-item">
                        <div class="fw-semibold small"><?= e(rotuloRefeicao($r['refeicao'])) ?></div>
                        <div class="small text-muted"><?= nl2br(e($r['descricao'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
