<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireReady();

$user = Auth::user();
$role = (string) $user['role'];
$pdo = Database::pdo();

$id = get_int('id', post_int('id'));

if ($id <= 0) {
    redirect($role === 'operatore' ? 'mie-segnalazioni.php' : 'segnalazioni.php');
}

$statement = $pdo->prepare(
    "SELECT r.*, c.name AS category_name, a.name AS asset_name,
            cu.full_name AS created_full_name, cu.username AS created_username,
            tu.full_name AS taken_full_name, tu.username AS taken_username,
            su.full_name AS solved_full_name, su.username AS solved_username
     FROM reports r
     LEFT JOIN categories c ON c.id = r.category_id
     LEFT JOIN assets a ON a.id = r.asset_id
     LEFT JOIN users cu ON cu.id = r.created_by
     LEFT JOIN users tu ON tu.id = r.taken_by
     LEFT JOIN users su ON su.id = r.resolved_by
     WHERE r.id = :id
     LIMIT 1"
);
$statement->execute(['id' => $id]);
$report = $statement->fetch() ?: null;

if ($report === null) {
    flash('error', 'Segnalazione non trovata.');

    redirect($role === 'operatore' ? 'mie-segnalazioni.php' : 'segnalazioni.php');
}

if ($role === 'operatore' && (int) $report['created_by'] !== (int) $user['id']) {
    flash('error', 'Puoi vedere solo le segnalazioni che hai inviato.');

    redirect('mie-segnalazioni.php');
}

if (is_post()) {
    csrf_or_redirect('segnalazione.php?id=' . $id);

    if (!Auth::isStaff()) {
        flash('error', 'Solo i manutentori possono gestire le segnalazioni.');

        redirect('segnalazione.php?id=' . $id);
    }

    $action = post_str('action');
    $now = date('Y-m-d H:i:s');

    if ($action === 'take') {
        if ((string) $report['status'] !== 'aperto') {
            flash('error', 'La segnalazione è già stata risolta.');
        } else {
            $update = $pdo->prepare(
                "UPDATE reports SET taken_by = :me, taken_at = :now
                 WHERE id = :id AND status = 'aperto' AND (taken_by IS NULL OR taken_by = :me)"
            );
            $update->execute(['me' => (int) $user['id'], 'now' => $now, 'id' => $id]);

            if ($update->rowCount() > 0) {
                Activity::log('presa_in_carico', 'reports', $id, (string) $report['title']);
                flash('success', 'Hai preso in carico la segnalazione #' . $id . '.');
            } else {
                flash('error', 'Un altro manutentore l\'ha appena presa in carico.');
            }
        }
    } elseif ($action === 'release') {
        $update = $pdo->prepare(
            "UPDATE reports SET taken_by = NULL, taken_at = NULL
             WHERE id = :id AND taken_by = :me AND status = 'aperto'"
        );
        $update->execute(['id' => $id, 'me' => (int) $user['id']]);

        if ($update->rowCount() > 0) {
            Activity::log('presa_in_carico_annullata', 'reports', $id, (string) $report['title']);
            flash('success', 'Presa in carico annullata: la segnalazione è di nuovo libera.');
        } else {
            flash('error', 'Non puoi annullare la presa in carico di un\'altra persona.');
        }
    } elseif ($action === 'resolve') {
        $note = post_str('note');

        if ((string) $report['status'] === 'risolto') {
            flash('error', 'La segnalazione risulta già risolta.');
        } else {
            $update = $pdo->prepare(
                "UPDATE reports SET status = 'risolto', resolved_by = :me, resolved_at = :now, resolution_note = :note
                 WHERE id = :id AND status = 'aperto'"
            );
            $update->execute([
                'me'   => (int) $user['id'],
                'now'  => $now,
                'note' => $note !== '' ? $note : null,
                'id'   => $id,
            ]);

            if ($update->rowCount() > 0) {
                Activity::log('segnalazione_risolta', 'reports', $id, (string) $report['title'] . ($note !== '' ? ' · ' . $note : ''));
                flash('success', 'Segnalazione #' . $id . ' segnata come risolta.');
            } else {
                flash('error', 'Non è stato possibile chiudere la segnalazione.');
            }
        }
    }

    redirect(back_target('segnalazione.php?id=' . $id));
}

$history = $pdo->prepare(
    "SELECT * FROM activity_log WHERE entity_type = 'reports' AND entity_id = :id ORDER BY id DESC LIMIT 20"
);
$history->execute(['id' => $id]);
$events = $history->fetchAll();

$status = (string) $report['status'];
$isOpen = $status === 'aperto';
$takenByMe = $report['taken_by'] !== null && (int) $report['taken_by'] === (int) $user['id'];

layout_start('Segnalazione #' . $id, $role === 'operatore' ? 'miei' : 'segnalazioni');
?>

<div class="toolbar">
    <a href="<?= $role === 'operatore' ? 'mie-segnalazioni.php' : 'segnalazioni.php' ?>" class="btn btn-ghost">Torna all'elenco</a>
    <button type="button" class="btn" onclick="window.print()">Stampa</button>
</div>

<article class="detail">
    <div class="print-head">
        <div>
            <strong><?= e(app_name()) ?></strong>
            <small>
                <?= e(product_name()) ?> · segnalazione #<?= $id ?>
                · <?= e(fmt_dt((string) $report['created_at'])) ?>
            </small>
        </div>
        <span class="print-id">#<?= $id ?></span>
    </div>

    <header class="detail-head">
        <div>
            <div class="detail-badges">
                <span class="badge badge-<?= e(urgency_class((string) $report['urgency'])) ?>">
                    <?= e(urgency_label((string) $report['urgency'])) ?>
                </span>
                <?php if ($isOpen): ?>
                    <span class="pill pill-wait">Aperto</span>
                <?php else: ?>
                    <span class="pill pill-ok">Risolto</span>
                <?php endif; ?>
            </div>
            <h2><?= e($report['title']) ?></h2>
        </div>
        <span class="detail-id">#<?= $id ?></span>
    </header>

    <dl class="detail-grid">
        <div>
            <dt>Macchina</dt>
            <dd><?= e($report['asset_name'] ?? 'Non indicata') ?></dd>
        </div>
        <div>
            <dt>Categoria</dt>
            <dd><?= e($report['category_name'] ?? 'Non indicata') ?></dd>
        </div>
        <div>
            <dt>Segnalato da</dt>
            <dd><?= e(person($report['created_full_name'], $report['created_username'])) ?></dd>
        </div>
        <div>
            <dt>Data segnalazione</dt>
            <dd><?= e(fmt_dt((string) $report['created_at'])) ?></dd>
        </div>
        <div>
            <dt>Presa in carico</dt>
            <dd>
                <?php if ($report['taken_by'] !== null): ?>
                    <?= e(person($report['taken_full_name'], $report['taken_username'])) ?>
                    <span class="muted">· <?= e(fmt_dt((string) $report['taken_at'])) ?></span>
                <?php else: ?>
                    <span class="muted">Nessuno</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Risolto</dt>
            <dd>
                <?php if ($report['resolved_by'] !== null): ?>
                    <?= e(person($report['solved_full_name'], $report['solved_username'])) ?>
                    <span class="muted">· <?= e(fmt_dt((string) $report['resolved_at'])) ?></span>
                <?php else: ?>
                    <span class="muted">Non ancora</span>
                <?php endif; ?>
            </dd>
        </div>
    </dl>

    <?php if (trim((string) $report['description']) !== ''): ?>
        <section class="detail-block">
            <h3>Descrizione</h3>
            <p class="pre-wrap"><?= e((string) $report['description']) ?></p>
        </section>
    <?php endif; ?>

    <?php if (trim((string) ($report['resolution_note'] ?? '')) !== ''): ?>
        <section class="detail-block">
            <h3>Nota di chiusura</h3>
            <p class="pre-wrap"><?= e((string) $report['resolution_note']) ?></p>
        </section>
    <?php endif; ?>

    <?php if (Auth::isStaff() && $isOpen): ?>
        <section class="detail-block actions-block">
            <h3>Azioni</h3>

            <?php if ($report['taken_by'] === null): ?>
                <form method="post" action="segnalazione.php?id=<?= $id ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="take">
                    <button type="submit" class="btn btn-primary">Prendi in carico</button>
                </form>
            <?php elseif ($takenByMe): ?>
                <form method="post" action="segnalazione.php?id=<?= $id ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="release">
                    <button type="submit" class="btn">Lascia la segnalazione</button>
                </form>
            <?php else: ?>
                <p class="muted">
                    In carico a <?= e(person($report['taken_full_name'], $report['taken_username'])) ?>.
                    Puoi comunque chiuderla se l'intervento è stato fatto.
                </p>
            <?php endif; ?>

            <form method="post" action="segnalazione.php?id=<?= $id ?>" class="form form-tight">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resolve">

                <label class="field">
                    <span>Nota di chiusura (facoltativa)</span>
                    <textarea name="note" rows="2" maxlength="2000"
                              placeholder="Cosa è stato fatto"></textarea>
                </label>

                <button type="submit" class="btn btn-success">Segna come risolto</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($events !== []): ?>
        <section class="detail-block">
            <h3>Storico</h3>
            <ul class="timeline">
                <?php foreach ($events as $event): ?>
                    <li>
                        <span class="timeline-dot"></span>
                        <span class="timeline-text">
                            <strong><?= e($event['username'] !== '' ? $event['username'] : 'sistema') ?></strong>
                            · <?= e(str_replace('_', ' ', (string) $event['action'])) ?>
                            <span class="muted"><?= e(fmt_dt((string) $event['created_at'])) ?></span>
                            <?php if (trim((string) $event['details']) !== ''): ?>
                                <small><?= e((string) $event['details']) ?></small>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</article>

<?php layout_end(); ?>
