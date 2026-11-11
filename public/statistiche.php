<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireStaff();

$pdo = Database::pdo();

$reports = $pdo->query(
    'SELECT r.id, r.urgency, r.status, r.created_at, r.resolved_at, r.created_by,
            c.name AS category_name, a.name AS asset_name,
            u.full_name AS created_full_name, u.username AS created_username
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     LEFT JOIN assets a ON a.id = r.asset_id
     LEFT JOIN users u ON u.id = r.created_by
     ORDER BY r.id DESC'
)->fetchAll();

$total = count($reports);
$open = 0;
$resolved = 0;
$resolutionTotal = 0;
$resolutionCount = 0;
$openByUrgency = ['fermo' => 0, 'alta' => 0, 'media' => 0, 'bassa' => 0];
$byCategory = [];
$byAsset = [];
$byUser = [];

$monthKeys = [];
$monthLabels = [];
$monthNames = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('first day of -' . $i . ' month'));
    $monthKeys[$key] = 0;
    $timestamp = strtotime($key . '-01');
    $monthLabels[$key] = $monthNames[(int) date('n', $timestamp) - 1] . ' ' . date('y', $timestamp);
}

foreach ($reports as $report) {
    $status = (string) $report['status'];

    if ($status === 'aperto') {
        $open++;
        $urgency = (string) $report['urgency'];

        if (isset($openByUrgency[$urgency])) {
            $openByUrgency[$urgency]++;
        }
    } else {
        $resolved++;

        if ($report['resolved_at'] !== null && $report['created_at'] !== null) {
            $resolutionTotal += max(0, strtotime((string) $report['resolved_at']) - strtotime((string) $report['created_at']));
            $resolutionCount++;
        }
    }

    $categoryName = (string) ($report['category_name'] ?? 'Senza categoria');
    $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0) + 1;

    $assetName = (string) ($report['asset_name'] ?? 'Macchina non indicata');
    $byAsset[$assetName] = ($byAsset[$assetName] ?? 0) + 1;

    $userName = person($report['created_full_name'], $report['created_username']);
    $byUser[$userName] = ($byUser[$userName] ?? 0) + 1;

    $month = substr((string) $report['created_at'], 0, 7);

    if (array_key_exists($month, $monthKeys)) {
        $monthKeys[$month]++;
    }
}

$averageHours = $resolutionCount > 0 ? ($resolutionTotal / $resolutionCount) / 3600 : null;

$sortDesc = static function (array $values): array {
    arsort($values);

    return array_slice($values, 0, 8, true);
};

$byCategory = $sortDesc($byCategory);
$byAsset = $sortDesc($byAsset);
$byUser = $sortDesc($byUser);

$maxCategory = $byCategory === [] ? 1 : max($byCategory);
$maxAsset = $byAsset === [] ? 1 : max($byAsset);
$maxMonth = $monthKeys === [] ? 1 : max(1, max($monthKeys));
$maxUser = $byUser === [] ? 1 : max($byUser);

layout_start('Statistiche', 'statistiche');
?>

<section class="cards">
    <article class="card">
        <span class="card-label">Segnalazioni totali</span>
        <strong><?= e((string) $total) ?></strong>
        <span class="card-meta">Dall'inizio dello storico</span>
    </article>

    <article class="card">
        <span class="card-label">Aperte</span>
        <strong><?= e((string) $open) ?></strong>
        <span class="card-meta">Di cui fermo impianto: <?= e((string) $openByUrgency['fermo']) ?></span>
    </article>

    <article class="card">
        <span class="card-label">Risolte</span>
        <strong><?= e((string) $resolved) ?></strong>
        <span class="card-meta">Registrate con nota di chiusura</span>
    </article>

    <article class="card">
        <span class="card-label">Tempo medio di chiusura</span>
        <strong>
            <?= $averageHours === null ? '—' : e(number_format($averageHours, 1, ',', '.')) ?>
            <?php if ($averageHours !== null): ?><span class="unit">h</span><?php endif; ?>
        </strong>
        <span class="card-meta">
            <?= $resolutionCount === 0 ? 'Nessun dato sufficiente' : 'Su ' . e((string) $resolutionCount) . ' segnalazioni' ?>
        </span>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Segnalazioni aperte per urgenza</h2>
    </div>

    <ul class="bars">
        <?php foreach (urgencies() as $key => $label): ?>
            <?php $value = $openByUrgency[$key]; $percent = $open > 0 ? (int) round($value / $open * 100) : 0; ?>
            <li>
                <span class="bar-label">
                    <span class="badge badge-<?= e(urgency_class($key)) ?>"><?= e($label) ?></span>
                </span>
                <span class="bar-track">
                    <span class="bar-fill fill-<?= e(urgency_class($key)) ?>" style="width: <?= $percent ?>%"></span>
                </span>
                <span class="bar-value"><?= e((string) $value) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Cosa si rompe di più</h2>
        <span class="muted">Per categoria</span>
    </div>

    <?php if ($byCategory === []): ?>
        <p class="empty">Nessuna segnalazione registrata.</p>
    <?php else: ?>
        <ul class="bars">
            <?php foreach ($byCategory as $name => $value): ?>
                <li>
                    <span class="bar-label"><?= e((string) $name) ?></span>
                    <span class="bar-track">
                        <span class="bar-fill" style="width: <?= (int) round($value / $maxCategory * 100) ?>%"></span>
                    </span>
                    <span class="bar-value"><?= e((string) $value) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="split">
    <section class="panel">
        <div class="panel-head">
            <h2>Macchine più critiche</h2>
        </div>

        <?php if ($byAsset === []): ?>
            <p class="empty">Nessun dato.</p>
        <?php else: ?>
            <ul class="bars">
                <?php foreach ($byAsset as $name => $value): ?>
                    <li>
                        <span class="bar-label"><?= e((string) $name) ?></span>
                        <span class="bar-track">
                            <span class="bar-fill fill-info" style="width: <?= (int) round($value / $maxAsset * 100) ?>%"></span>
                        </span>
                        <span class="bar-value"><?= e((string) $value) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Chi segnala di più</h2>
        </div>

        <?php if ($byUser === []): ?>
            <p class="empty">Nessun dato.</p>
        <?php else: ?>
            <ul class="bars">
                <?php foreach ($byUser as $name => $value): ?>
                    <li>
                        <span class="bar-label"><?= e((string) $name) ?></span>
                        <span class="bar-track">
                            <span class="bar-fill fill-muted" style="width: <?= (int) round($value / $maxUser * 100) ?>%"></span>
                        </span>
                        <span class="bar-value"><?= e((string) $value) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Andamento ultimi 6 mesi</h2>
    </div>

    <ul class="columns">
        <?php foreach ($monthKeys as $key => $value): ?>
            <li>
                <span class="column-bar" style="height: <?= (int) round($value / $maxMonth * 100) ?>%"></span>
                <span class="column-value"><?= e((string) $value) ?></span>
                <span class="column-label"><?= e($monthLabels[$key]) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php layout_end(); ?>
