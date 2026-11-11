<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireReady();

$user = Auth::user();
$role = (string) $user['role'];
$isStaff = Auth::isStaff();
$pdo = Database::pdo();
$myId = (int) $user['id'];
$today = date('Y-m-d');
$start = $today . ' 00:00:00';
$end = $today . ' 23:59:59';

$sql = "SELECT r.*, c.name AS category_name, a.name AS asset_name,
               cu.full_name AS created_full_name, cu.username AS created_username,
               tu.full_name AS taken_full_name, tu.username AS taken_username
        FROM reports r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN assets a ON a.id = r.asset_id
        LEFT JOIN users cu ON cu.id = r.created_by
        LEFT JOIN users tu ON tu.id = r.taken_by
        WHERE r.created_at >= :start AND r.created_at <= :end";

$params = ['start' => $start, 'end' => $end];

if (!$isStaff) {
    $sql .= ' AND r.created_by = :me';
    $params['me'] = $myId;
}

$sql .= " ORDER BY CASE r.urgency
            WHEN 'fermo' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            ELSE 4
          END ASC, r.created_at DESC";

$statement = $pdo->prepare($sql);
$statement->execute($params);
$todayReports = $statement->fetchAll();

$openToday = 0;

foreach ($todayReports as $report) {
    if ((string) $report['status'] === 'aperto') {
        $openToday++;
    }
}

$resolvedSql = 'SELECT COUNT(*) FROM reports WHERE resolved_at >= :start AND resolved_at <= :end';
$resolvedParams = ['start' => $start, 'end' => $end];

if (!$isStaff) {
    $resolvedSql .= ' AND created_by = :me';
    $resolvedParams['me'] = $myId;
}

$statement = $pdo->prepare($resolvedSql);
$statement->execute($resolvedParams);
$resolvedToday = (int) $statement->fetchColumn();

$takenToday = 0;
$openMine = 0;

if ($isStaff) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM reports WHERE taken_at >= :start AND taken_at <= :end'
    );
    $statement->execute(['start' => $start, 'end' => $end]);
    $takenToday = (int) $statement->fetchColumn();

    $statement = $pdo->prepare(
        "SELECT t.* FROM tasks t
         WHERE t.is_active = 1 AND t.next_due_at <= :today
         ORDER BY t.next_due_at ASC, t.title ASC"
    );
    $statement->execute(['today' => $today]);
    $tasks = $statement->fetchAll();
} else {
    $tasks = [];

    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM reports WHERE created_by = :me AND status = 'aperto'"
    );
    $statement->execute(['me' => $myId]);
    $openMine = (int) $statement->fetchColumn();
}

$workStart = Settings::get('work_start');
$workEnd = Settings::get('work_end');
$hours = ($workStart !== '' && $workEnd !== '') ? $workStart . '–' . $workEnd : '';

layout_start('Turno di oggi', 'turno');
?>

<div class="print-head">
    <div>
        <strong><?= e(app_name()) ?></strong>
        <small><?= e(product_name()) ?> · turno del <?= e(date('d/m/Y')) ?></small>
    </div>
    <span class="print-id"><?= e(date('d/m/Y')) ?></span>
</div>

<p class="lead">
    <?= e(date('d/m/Y')) ?><?= $hours !== '' ? ' · orario di lavoro ' . e($hours) : '' ?>.
    <?= $isStaff
        ? 'Le segnalazioni arrivate oggi e le attività da eseguire.'
        : 'Le segnalazioni che hai inviato oggi e il loro stato.' ?>
</p>

<div class="toolbar">
    <a href="nuova-segnalazione.php" class="btn btn-primary">Nuova segnalazione</a>
    <?php if ($isStaff): ?>
        <a href="attivita.php" class="btn">Attività</a>
    <?php endif; ?>
    <button type="button" class="btn btn-ghost" onclick="window.print()">Stampa turno</button>
</div>

<section class="cards <?= $isStaff ? '' : 'cards-3' ?>">
    <?php if ($isStaff): ?>
        <article class="card">
            <div class="card-top">
                <span class="card-label">Aperte oggi</span>
                <span class="card-icon"><?= nav_icon('segnalazioni') ?></span>
            </div>
            <strong><?= e((string) $openToday) ?></strong>
            <span class="card-meta"><?= e((string) count($todayReports)) ?> segnalazioni oggi</span>
        </article>

        <article class="card">
            <div class="card-top">
                <span class="card-label">Prese in carico</span>
                <span class="card-icon"><?= nav_icon('profilo') ?></span>
            </div>
            <strong><?= e((string) $takenToday) ?></strong>
            <span class="card-meta">Durante il turno</span>
        </article>

        <article class="card card-ok">
            <div class="card-top">
                <span class="card-label">Risolte oggi</span>
                <span class="card-icon"><?= nav_icon('check') ?></span>
            </div>
            <strong><?= e((string) $resolvedToday) ?></strong>
            <span class="card-meta">Chiuse dai manutentori</span>
        </article>

        <article class="card <?= $tasks !== [] ? 'card-danger' : '' ?>">
            <div class="card-top">
                <span class="card-label">Attività da fare</span>
                <span class="card-icon"><?= nav_icon('attivita') ?></span>
            </div>
            <strong><?= e((string) count($tasks)) ?></strong>
            <span class="card-meta">Scadute o in scadenza oggi</span>
        </article>
    <?php else: ?>
        <article class="card">
            <div class="card-top">
                <span class="card-label">Tue segnalazioni oggi</span>
                <span class="card-icon"><?= nav_icon('segnalazioni') ?></span>
            </div>
            <strong><?= e((string) count($todayReports)) ?></strong>
            <span class="card-meta"><?= e((string) $openToday) ?> ancora aperte</span>
        </article>

        <article class="card">
            <div class="card-top">
                <span class="card-label">In gestione</span>
                <span class="card-icon"><?= nav_icon('profilo') ?></span>
            </div>
            <strong><?= e((string) $openMine) ?></strong>
            <span class="card-meta">Segnalazioni aperte in totale</span>
        </article>

        <article class="card card-ok">
            <div class="card-top">
                <span class="card-label">Risolte oggi</span>
                <span class="card-icon"><?= nav_icon('check') ?></span>
            </div>
            <strong><?= e((string) $resolvedToday) ?></strong>
            <span class="card-meta">Chiuse dai manutentori</span>
        </article>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Segnalazioni di oggi</h2>
        <span class="muted"><?= e((string) count($todayReports)) ?> in totale</span>
    </div>

    <?php if ($todayReports === []): ?>
        <p class="empty">
            <?= $isStaff
                ? 'Nessuna segnalazione arrivata oggi.'
                : 'Non hai segnalato guasti oggi.' ?>
        </p>
    <?php else: ?>
        <ul class="queue">
            <?php foreach ($todayReports as $report): ?>
                <?php
                $status = (string) $report['status'];
                $open = $status === 'aperto';
                ?>
                <li class="queue-item urgent-<?= e(urgency_class((string) $report['urgency'])) ?>">
                    <div class="queue-body">
                        <div class="queue-line">
                            <span class="badge badge-<?= e(urgency_class((string) $report['urgency'])) ?>">
                                <?= e(urgency_label((string) $report['urgency'])) ?>
                            </span>
                            <a href="segnalazione.php?id=<?= (int) $report['id'] ?>" class="queue-title">
                                <?= e($report['title']) ?>
                            </a>
                            <span class="queue-id">#<?= (int) $report['id'] ?></span>
                        </div>
                        <span class="queue-meta">
                            <?= e(date('H:i', (int) strtotime((string) $report['created_at']))) ?>
                            <?php if ($report['asset_name'] !== null): ?>
                                · <?= e($report['asset_name']) ?>
                            <?php endif; ?>
                            <?php if ($report['category_name'] !== null): ?>
                                · <?= e($report['category_name']) ?>
                            <?php endif; ?>
                            · da <?= e(person($report['created_full_name'], $report['created_username'])) ?>
                            <?php if ($report['taken_by'] !== null): ?>
                                · in carico a <?= e(person($report['taken_full_name'], $report['taken_username'])) ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="queue-actions">
                        <?php if (!$open): ?>
                            <span class="pill pill-ok">Risolto</span>
                        <?php elseif ($report['taken_by'] !== null): ?>
                            <span class="pill">In gestione</span>
                        <?php else: ?>
                            <span class="pill pill-wait">In attesa</span>
                        <?php endif; ?>

                        <a href="segnalazione.php?id=<?= (int) $report['id'] ?>" class="btn btn-sm">Apri</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($isStaff): ?>
    <section class="panel">
        <div class="panel-head">
            <h2>Attività da fare</h2>
            <span class="muted">Scadute o con scadenza oggi</span>
        </div>

        <?php if ($tasks === []): ?>
            <p class="empty">Nessuna attività in scadenza per oggi. Tutto in ordine.</p>
        <?php else: ?>
            <ul class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <?php $late = is_overdue((string) $task['next_due_at']); ?>
                    <li class="task-item <?= $late ? 'is-late' : '' ?>">
                        <div class="task-main">
                            <div class="task-title-row">
                                <strong><?= e($task['title']) ?></strong>
                                <span class="pill <?= $late ? 'pill-late' : 'pill-ok' ?>">
                                    <?= e(due_text((string) $task['next_due_at'])) ?>
                                </span>
                            </div>
                            <span class="task-meta">
                                <?= e(frequency_text((int) $task['frequency_days'])) ?>
                                · scadenza <?= e(fmt_date((string) $task['next_due_at'])) ?>
                            </span>
                            <?php if (trim((string) $task['description']) !== ''): ?>
                                <span class="task-note"><?= e((string) $task['description']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="task-actions">
                            <form method="post" action="attivita.php" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                <input type="hidden" name="back" value="turno.php">
                                <button type="submit" class="btn btn-sm btn-success">Segna come fatta</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php layout_end(); ?>
