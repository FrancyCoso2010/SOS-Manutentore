<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireReady();

$user = Auth::user();
$role = (string) $user['role'];
$pdo = Database::pdo();
$today = date('Y-m-d');
$isOperator = $role === 'operatore';

if ($isOperator) {
    $statement = $pdo->prepare(
        "SELECT r.*, c.name AS category_name, a.name AS asset_name,
                tu.full_name AS taken_full_name, tu.username AS taken_username
         FROM reports r
         LEFT JOIN categories c ON c.id = r.category_id
         LEFT JOIN assets a ON a.id = r.asset_id
         LEFT JOIN users tu ON tu.id = r.taken_by
         WHERE r.created_by = :user
         ORDER BY r.created_at DESC
         LIMIT 10"
    );
    $statement->execute(['user' => (int) $user['id']]);
    $myReports = $statement->fetchAll();

    $statement = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE created_by = :user AND status = 'aperto'");
    $statement->execute(['user' => (int) $user['id']]);
    $myOpen = (int) $statement->fetchColumn();

    $statement = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE created_by = :user AND status = 'risolto'");
    $statement->execute(['user' => (int) $user['id']]);
    $myResolved = (int) $statement->fetchColumn();
} else {
    $openReports = $pdo->query(
        "SELECT r.*, c.name AS category_name, a.name AS asset_name,
                cu.full_name AS created_full_name, cu.username AS created_username,
                tu.full_name AS taken_full_name, tu.username AS taken_username
         FROM reports r
         LEFT JOIN categories c ON c.id = r.category_id
         LEFT JOIN assets a ON a.id = r.asset_id
         LEFT JOIN users cu ON cu.id = r.created_by
         LEFT JOIN users tu ON tu.id = r.taken_by
         WHERE r.status = 'aperto'
         ORDER BY r.created_at ASC"
    )->fetchAll();

    $statement = $pdo->prepare(
        "SELECT t.*, u.full_name AS last_full_name, u.username AS last_username
         FROM tasks t
         LEFT JOIN users u ON u.id = t.last_done_by
         WHERE t.is_active = 1 AND t.next_due_at <= :today
         ORDER BY t.next_due_at ASC"
    );
    $statement->execute(['today' => $today]);
    $dueTasks = $statement->fetchAll();

    $statement = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = 'aperto' AND taken_by = :me");
    $statement->execute(['me' => (int) $user['id']]);
    $takenByMe = (int) $statement->fetchColumn();

    $statement = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = 'risolto' AND resolved_at >= :start");
    $statement->execute(['start' => $today . ' 00:00:00']);
    $resolvedToday = (int) $statement->fetchColumn();

    $fermoCount = 0;
    $takenCount = 0;

    foreach ($openReports as $report) {
        if ($report['urgency'] === 'fermo') {
            $fermoCount++;
        }

        if ($report['taken_by'] !== null) {
            $takenCount++;
        }
    }

    $queue = [];

    foreach ($openReports as $report) {
        $queue[] = [
            'rank' => urgency_rank((string) $report['urgency']) + ($report['taken_by'] === null ? 0 : 0.4),
            'sort' => (int) strtotime((string) $report['created_at']),
            'type' => 'report',
            'data' => $report,
        ];
    }

    foreach ($dueTasks as $task) {
        $queue[] = [
            'rank' => is_overdue((string) $task['next_due_at']) ? -0.5 : 2.5,
            'sort' => (int) strtotime((string) $task['next_due_at']),
            'type' => 'task',
            'data' => $task,
        ];
    }

    usort($queue, static fn (array $a, array $b): int => [$a['rank'], $a['sort']] <=> [$b['rank'], $b['sort']]);
    $queue = array_slice($queue, 0, 40);

    $statement = $pdo->prepare(
        "SELECT * FROM tasks
         WHERE is_active = 1 AND next_due_at > :today AND next_due_at <= :limit
         ORDER BY next_due_at ASC
         LIMIT 5"
    );
    $statement->execute(['today' => $today, 'limit' => date('Y-m-d', strtotime('+7 days'))]);
    $upcomingTasks = $statement->fetchAll();
}

$pageTitle = 'Ciao, ' . person((string) $user['full_name'], (string) $user['username']);
$previousLogin = $_SESSION['previous_login_at'] ?? null;

layout_start($pageTitle, 'dashboard');
?>

<?php if ($isOperator): ?>
    <p class="lead">Se qualcosa non funziona, segnalalo qui: ci pensa il manutentore.</p>

    <a class="cta-big" href="nuova-segnalazione.php">
        <span class="cta-icon">!</span>
        <span class="cta-text">
            <strong>Segnala un guasto</strong>
            <small>Bastano tre tocchi</small>
        </span>
    </a>

    <ol class="steps">
        <li>
            <span class="step-n">1</span>
            <span class="step-text">
                <strong>Descrivi il problema</strong>
                <small>Scegli macchina e urgenza.</small>
            </span>
        </li>
        <li>
            <span class="step-n">2</span>
            <span class="step-text">
                <strong>Il manutentore prende in carico</strong>
                <small>Vedi chi se ne sta occupando.</small>
            </span>
        </li>
        <li>
            <span class="step-n">3</span>
            <span class="step-text">
                <strong>Ricevi la chiusura</strong>
                <small>Con la nota dell'intervento.</small>
            </span>
        </li>
    </ol>

    <section class="cards cards-3">
        <article class="card">
            <div class="card-top">
                <span class="card-label">Segnalazioni aperte</span>
                <span class="card-icon"><?= nav_icon('segnalazioni') ?></span>
            </div>
            <strong><?= e((string) $myOpen) ?></strong>
            <span class="card-meta">In attesa o in gestione</span>
        </article>

        <article class="card card-ok">
            <div class="card-top">
                <span class="card-label">Risolte</span>
                <span class="card-icon"><?= nav_icon('check') ?></span>
            </div>
            <strong><?= e((string) $myResolved) ?></strong>
            <span class="card-meta">Chiuse dai manutentori</span>
        </article>

        <article class="card">
            <div class="card-top">
                <span class="card-label">Ultimo accesso</span>
                <span class="card-icon"><?= nav_icon('log') ?></span>
            </div>
            <strong class="card-strong-sm"><?= e(fmt_dt(is_string($previousLogin) ? $previousLogin : null)) ?></strong>
            <span class="card-meta">Ora: <?= e(date('d/m/Y H:i')) ?></span>
        </article>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Le tue segnalazioni</h2>
            <a href="mie-segnalazioni.php" class="btn btn-ghost btn-sm">Vedi tutte</a>
        </div>

        <?php if ($myReports === []): ?>
            <p class="empty">Non hai ancora inviato segnalazioni.</p>
        <?php else: ?>
            <ul class="report-list">
                <?php foreach ($myReports as $report): ?>
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
                            </span>
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
    </section>
<?php else: ?>
    <p class="lead">
        Coda condivisa dei manutentori: segnalazioni aperte e attività in scadenza, ordinate per priorità.
    </p>

    <section class="cards">
        <article class="card">
            <div class="card-top">
                <span class="card-label">Segnalazioni aperte</span>
                <span class="card-icon"><?= nav_icon('segnalazioni') ?></span>
            </div>
            <strong><?= e((string) count($openReports)) ?></strong>
            <span class="card-meta"><?= e((string) $takenCount) ?> già prese in carico</span>
        </article>

        <article class="card card-danger">
            <div class="card-top">
                <span class="card-label">Fermo impianto</span>
                <span class="card-icon"><?= nav_icon('allarme') ?></span>
            </div>
            <strong><?= e((string) $fermoCount) ?></strong>
            <span class="card-meta">Priorità massima</span>
        </article>

        <article class="card card-warn">
            <div class="card-top">
                <span class="card-label">Attività in scadenza</span>
                <span class="card-icon"><?= nav_icon('attivita') ?></span>
            </div>
            <strong><?= e((string) count($dueTasks)) ?></strong>
            <span class="card-meta">Oggi o in ritardo</span>
        </article>

        <article class="card card-ok">
            <div class="card-top">
                <span class="card-label">In carico a te</span>
                <span class="card-icon"><?= nav_icon('profilo') ?></span>
            </div>
            <strong><?= e((string) $takenByMe) ?></strong>
            <span class="card-meta">Risolte oggi: <?= e((string) $resolvedToday) ?></span>
        </article>
    </section>

    <div class="toolbar">
        <a href="nuova-segnalazione.php" class="btn btn-primary">Nuova segnalazione</a>
        <a href="attivita.php" class="btn">Nuova attività</a>
        <a href="segnalazioni.php" class="btn btn-ghost">Tutte le segnalazioni</a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2>Coda di lavoro</h2>
            <span class="muted"><?= e((string) count($queue)) ?> elementi</span>
        </div>

        <?php if ($queue === []): ?>
            <p class="empty">Nessuna segnalazione aperta e nessuna attività in scadenza. Tutto sotto controllo.</p>
        <?php else: ?>
            <ul class="queue">
                <?php foreach ($queue as $item): ?>
                    <?php if ($item['type'] === 'report'): ?>
                        <?php $report = $item['data']; ?>
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
                                    <?php if ($report['asset_name'] !== null): ?>
                                        <?= e($report['asset_name']) ?> ·
                                    <?php endif; ?>
                                    <?php if ($report['category_name'] !== null): ?>
                                        <?= e($report['category_name']) ?> ·
                                    <?php endif; ?>
                                    segnalato da <?= e(person($report['created_full_name'], $report['created_username'])) ?>
                                    il <?= e(fmt_dt((string) $report['created_at'])) ?>
                                </span>
                            </div>

                            <div class="queue-actions">
                                <?php if ($report['taken_by'] !== null): ?>
                                    <span class="pill pill-taken">
                                        Preso in carico da <?= e(person($report['taken_full_name'], $report['taken_username'])) ?>
                                    </span>
                                <?php elseif (Auth::isStaff()): ?>
                                    <form method="post" action="segnalazione.php?id=<?= (int) $report['id'] ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="take">
                                        <input type="hidden" name="back" value="dashboard.php">
                                        <button type="submit" class="btn btn-sm btn-primary">Prendi in carico</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php $task = $item['data']; ?>
                        <li class="queue-item urgent-task">
                            <div class="queue-body">
                                <div class="queue-line">
                                    <span class="badge badge-<?= is_overdue((string) $task['next_due_at']) ? 'danger' : 'info' ?>">
                                        Attività
                                    </span>
                                    <span class="queue-title"><?= e($task['title']) ?></span>
                                    <span class="queue-id"><?= e(frequency_text((int) $task['frequency_days'])) ?></span>
                                </div>
                                <span class="queue-meta">
                                    Scadenza <?= e(fmt_date((string) $task['next_due_at'])) ?> ·
                                    <?= e(due_text((string) $task['next_due_at'])) ?>
                                    <?php if ($task['last_done_at'] !== null): ?>
                                        · ultima esecuzione <?= e(fmt_dt((string) $task['last_done_at'])) ?>
                                        (<?= e(person($task['last_full_name'], $task['last_username'])) ?>)
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="queue-actions">
                                <form method="post" action="attivita.php" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="back" value="dashboard.php">
                                    <button type="submit" class="btn btn-sm">Segna come fatta</button>
                                </form>
                            </div>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($upcomingTasks !== []): ?>
        <section class="panel">
            <div class="panel-head">
                <h2>Prossime attività</h2>
                <a href="attivita.php" class="link-sm">Tutte</a>
            </div>

            <ul class="simple-list">
                <?php foreach ($upcomingTasks as $task): ?>
                    <li>
                        <span>
                            <strong><?= e($task['title']) ?></strong>
                            <span class="table-sub"><?= e(frequency_text((int) $task['frequency_days'])) ?></span>
                        </span>
                        <span class="pill">
                            <?= e(due_text((string) $task['next_due_at'])) ?> · <?= e(fmt_date((string) $task['next_due_at'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php layout_end(); ?>
