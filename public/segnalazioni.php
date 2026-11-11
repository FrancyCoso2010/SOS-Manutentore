<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireStaff();

$pdo = Database::pdo();

$status = get_str('stato', 'aperto');

if (!in_array($status, ['aperto', 'risolto', 'tutti'], true)) {
    $status = 'aperto';
}

$urgency = get_str('urgenza');

if (!array_key_exists($urgency, urgencies())) {
    $urgency = '';
}

$categoryId = get_int('categoria');
$search = get_str('q');

$where = [];
$params = [];

if ($status !== 'tutti') {
    $where[] = 'r.status = :status';
    $params['status'] = $status;
}

if ($urgency !== '') {
    $where[] = 'r.urgency = :urgency';
    $params['urgency'] = $urgency;
}

if ($categoryId > 0) {
    $where[] = 'r.category_id = :category';
    $params['category'] = $categoryId;
}

if ($search !== '') {
    $where[] = '(r.title LIKE :search OR r.description LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$sql = "SELECT r.*, c.name AS category_name, a.name AS asset_name,
               cu.full_name AS created_full_name, cu.username AS created_username,
               tu.full_name AS taken_full_name, tu.username AS taken_username
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN assets a ON a.id = r.asset_id
        LEFT JOIN users cu ON cu.id = r.created_by
        LEFT JOIN users tu ON tu.id = r.taken_by";

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY CASE r.urgency
            WHEN 'fermo' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            ELSE 4
          END ASC, r.created_at DESC";

if (get_str('export') === 'csv') {
    $exportStatement = $pdo->prepare($sql . ' LIMIT 5000');
    $exportStatement->execute($params);
    $exportRows = $exportStatement->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="segnalazioni-' . date('Ymd-Hi') . '.csv"');
    header('Cache-Control: no-store');

    $handle = fopen('php://output', 'w');

    if ($handle !== false) {
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'ID', 'Titolo', 'Descrizione', 'Macchina', 'Categoria', 'Urgenza', 'Stato',
            'Segnalato da', 'In carico', 'Creata il', 'Risolta il', 'Nota di chiusura',
        ], ';', '"', '');

        foreach ($exportRows as $row) {
            fputcsv($handle, [
                (int) $row['id'],
                $row['title'],
                $row['description'],
                $row['asset_name'] ?? '',
                $row['category_name'] ?? '',
                urgency_label((string) $row['urgency']),
                status_label((string) $row['status']),
                person($row['created_full_name'], $row['created_username']),
                $row['taken_by'] !== null ? person($row['taken_full_name'], $row['taken_username']) : '',
                $row['created_at'],
                $row['resolved_at'] ?? '',
                $row['resolution_note'] ?? '',
            ], ';', '"', '');
        }

        fclose($handle);
    }

    exit;
}

$statement = $pdo->prepare($sql . ' LIMIT 100');
$statement->execute($params);
$reports = $statement->fetchAll();

$categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();

$exportParams = ['export' => 'csv', 'stato' => $status];

if ($urgency !== '') {
    $exportParams['urgenza'] = $urgency;
}

if ($categoryId > 0) {
    $exportParams['categoria'] = $categoryId;
}

if ($search !== '') {
    $exportParams['q'] = $search;
}

$exportUrl = 'segnalazioni.php?' . http_build_query($exportParams);

layout_start('Segnalazioni', 'segnalazioni');
?>

<div class="toolbar">
    <a href="nuova-segnalazione.php" class="btn btn-primary">Nuova segnalazione</a>
    <a href="<?= e($exportUrl) ?>" class="btn">Esporta CSV</a>
    <a href="statistiche.php" class="btn btn-ghost">Statistiche</a>
</div>

<form method="get" action="segnalazioni.php" class="filters">
    <label class="field field-inline">
        <span>Stato</span>
        <select name="stato">
            <option value="aperto" <?= $status === 'aperto' ? 'selected' : '' ?>>Aperte</option>
            <option value="risolto" <?= $status === 'risolto' ? 'selected' : '' ?>>Risolte</option>
            <option value="tutti" <?= $status === 'tutti' ? 'selected' : '' ?>>Tutte</option>
        </select>
    </label>

    <label class="field field-inline">
        <span>Urgenza</span>
        <select name="urgenza">
            <option value="">Tutte</option>
            <?php foreach (urgencies() as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $urgency === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="field field-inline">
        <span>Categoria</span>
        <select name="categoria">
            <option value="0">Tutte</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="field field-inline field-grow">
        <span>Cerca</span>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Titolo o descrizione">
    </label>

    <div class="filters-actions">
        <button type="submit" class="btn">Filtra</button>
        <a href="segnalazioni.php" class="btn btn-ghost">Azzera</a>
    </div>
</form>

<section class="panel">
    <div class="panel-head">
        <h2><?= e((string) count($reports)) ?> segnalazioni</h2>
        <span class="muted">Massimo 100 risultati</span>
    </div>

    <?php if ($reports === []): ?>
        <p class="empty">Nessuna segnalazione con questi filtri.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Segnalazione</th>
                        <th>Macchina</th>
                        <th>Categoria</th>
                        <th>Urgenza</th>
                        <th>Stato</th>
                        <th>In carico</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td class="mono"><?= (int) $report['id'] ?></td>
                            <td>
                                <a href="segnalazione.php?id=<?= (int) $report['id'] ?>" class="table-title">
                                    <?= e($report['title']) ?>
                                </a>
                                <span class="table-sub">
                                    da <?= e(person($report['created_full_name'], $report['created_username'])) ?>
                                </span>
                            </td>
                            <td><?= e($report['asset_name'] ?? '—') ?></td>
                            <td><?= e($report['category_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge badge-<?= e(urgency_class((string) $report['urgency'])) ?>">
                                    <?= e(urgency_label((string) $report['urgency'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($report['status'] === 'risolto'): ?>
                                    <span class="pill pill-ok">Risolto</span>
                                <?php else: ?>
                                    <span class="pill pill-wait">Aperto</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($report['taken_by'] !== null): ?>
                                    <?= e(person($report['taken_full_name'], $report['taken_username'])) ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap"><?= e(fmt_dt((string) $report['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php layout_end(); ?>
