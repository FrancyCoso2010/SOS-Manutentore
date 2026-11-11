<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireReady();

$user = Auth::user();

if ((string) $user['role'] !== 'operatore') {
    redirect('segnalazioni.php');
}

$pdo = Database::pdo();

$status = get_str('stato', 'tutti');

if (!in_array($status, ['aperto', 'risolto', 'tutti'], true)) {
    $status = 'tutti';
}

$sql = "SELECT r.*, c.name AS category_name, a.name AS asset_name,
               tu.full_name AS taken_full_name, tu.username AS taken_username
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN assets a ON a.id = r.asset_id
        LEFT JOIN users tu ON tu.id = r.taken_by
        WHERE r.created_by = :user";

$params = ['user' => (int) $user['id']];

if ($status !== 'tutti') {
    $sql .= ' AND r.status = :status';
    $params['status'] = $status;
}

$sql .= ' ORDER BY r.created_at DESC LIMIT 100';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$reports = $statement->fetchAll();

layout_start('Le mie segnalazioni', 'miei');
?>

<div class="toolbar">
    <a href="nuova-segnalazione.php" class="btn btn-primary">Nuova segnalazione</a>
</div>

<nav class="tabs" aria-label="Filtro stato">
    <a href="mie-segnalazioni.php?stato=tutti" class="<?= $status === 'tutti' ? 'is-active' : '' ?>">Tutte</a>
    <a href="mie-segnalazioni.php?stato=aperto" class="<?= $status === 'aperto' ? 'is-active' : '' ?>">Aperte</a>
    <a href="mie-segnalazioni.php?stato=risolto" class="<?= $status === 'risolto' ? 'is-active' : '' ?>">Risolte</a>
</nav>

<?php if ($reports === []): ?>
    <section class="panel">
        <p class="empty">Nessuna segnalazione da mostrare.</p>
    </section>
<?php else: ?>
    <ul class="report-list">
        <?php foreach ($reports as $report): ?>
            <li class="report-item">
                <div class="report-main">
                    <a href="segnalazione.php?id=<?= (int) $report['id'] ?>" class="report-title">
                        #<?= (int) $report['id'] ?> · <?= e($report['title']) ?>
                    </a>
                    <span class="report-meta">
                        <?= e(fmt_dt((string) $report['created_at'])) ?>
                        <?php if ($report['asset_name'] !== null): ?>
                            · <?= e($report['asset_name']) ?>
                        <?php endif; ?>
                        <?php if ($report['category_name'] !== null): ?>
                            · <?= e($report['category_name']) ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($report['status'] === 'risolto'): ?>
                        <span class="report-meta">
                            Risolto il <?= e(fmt_dt((string) $report['resolved_at'])) ?>
                        </span>
                    <?php elseif ($report['taken_by'] !== null): ?>
                        <span class="report-meta">
                            In gestione da <?= e(person($report['taken_full_name'], $report['taken_username'])) ?>
                        </span>
                    <?php else: ?>
                        <span class="report-meta">In attesa di presa in carico</span>
                    <?php endif; ?>
                </div>
                <div class="report-side">
                    <span class="badge badge-<?= e(urgency_class((string) $report['urgency'])) ?>">
                        <?= e(urgency_label((string) $report['urgency'])) ?>
                    </span>
                    <?php if ($report['status'] === 'risolto'): ?>
                        <span class="pill pill-ok">Risolto</span>
                    <?php elseif ($report['taken_by'] !== null): ?>
                        <span class="pill">In gestione</span>
                    <?php else: ?>
                        <span class="pill pill-wait">In attesa</span>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php layout_end(); ?>
