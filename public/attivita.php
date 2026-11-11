<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireStaff();

$user = Auth::user();
$pdo = Database::pdo();

$errors = [];
$form = [
    'id'             => 0,
    'title'          => '',
    'description'    => '',
    'frequency_days' => 14,
    'next_due_at'    => date('Y-m-d'),
];

if (is_post()) {
    csrf_or_redirect('attivita.php');

    $action = post_str('action');

    if ($action === 'complete') {
        $id = post_int('id');
        $note = post_str('note');

        $statement = $pdo->prepare('SELECT * FROM tasks WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $id]);
        $task = $statement->fetch() ?: null;

        if ($task === null) {
            flash('error', 'Attività non trovata.');

            redirect(back_target('attivita.php'));
        }

        $now = date('Y-m-d H:i:s');
        $frequency = max(1, (int) $task['frequency_days']);
        $next = date('Y-m-d', strtotime('+' . $frequency . ' days'));

        $pdo->prepare('UPDATE tasks SET last_done_at = :now, last_done_by = :by, next_due_at = :next WHERE id = :id')
            ->execute(['now' => $now, 'by' => (int) $user['id'], 'next' => $next, 'id' => $id]);

        $pdo->prepare('INSERT INTO task_logs (task_id, done_by, done_at, note) VALUES (:task, :by, :now, :note)')
            ->execute(['task' => $id, 'by' => (int) $user['id'], 'now' => $now, 'note' => $note]);

        Activity::log('attivita_completata', 'tasks', $id, $task['title'] . ($note !== '' ? ' · ' . $note : ''));

        flash('success', 'Attività completata. Prossima scadenza: ' . fmt_date($next) . '.');

        redirect(back_target('attivita.php'));
    }

    if ($action === 'deactivate' || $action === 'reactivate') {
        $id = post_int('id');

        $statement = $pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $statement->execute(['id' => $id]);
        $task = $statement->fetch() ?: null;

        if ($task === null) {
            flash('error', 'Attività non trovata.');

            redirect('attivita.php');
        }

        $active = $action === 'reactivate' ? 1 : 0;

        $pdo->prepare('UPDATE tasks SET is_active = :active WHERE id = :id')
            ->execute(['active' => $active, 'id' => $id]);

        Activity::log($active === 1 ? 'attivita_riattivata' : 'attivita_disattivata', 'tasks', $id, (string) $task['title']);

        flash('success', $active === 1 ? 'Attività riattivata.' : 'Attività disattivata.');

        redirect('attivita.php');
    }

    if ($action === 'save') {
        $form['id'] = post_int('id');
        $form['title'] = post_str('title');
        $form['description'] = post_str('description');
        $form['frequency_days'] = post_int('frequency_days', 14);
        $form['next_due_at'] = post_str('next_due_at');

        if ($form['title'] === '') {
            $errors[] = 'Dai un nome all\'attività.';
        } elseif (text_len($form['title']) > 160) {
            $errors[] = 'Il nome è troppo lungo (massimo 160 caratteri).';
        }

        if ($form['frequency_days'] < 1 || $form['frequency_days'] > 365) {
            $errors[] = 'La frequenza deve essere tra 1 e 365 giorni.';
        }

        $date = DateTime::createFromFormat('Y-m-d', $form['next_due_at']);

        if ($date === false || $date->format('Y-m-d') !== $form['next_due_at']) {
            $errors[] = 'Inserisci una data di scadenza valida.';
        }

        if ($errors === []) {
            if ($form['id'] > 0) {
                $pdo->prepare(
                    'UPDATE tasks SET title = :title, description = :description, frequency_days = :frequency, next_due_at = :next
                     WHERE id = :id'
                )->execute([
                    'title'       => $form['title'],
                    'description' => $form['description'],
                    'frequency'   => $form['frequency_days'],
                    'next'        => $form['next_due_at'],
                    'id'          => $form['id'],
                ]);

                Activity::log('attivita_modificata', 'tasks', $form['id'], $form['title']);
                flash('success', 'Attività aggiornata.');
            } else {
                $pdo->prepare(
                    'INSERT INTO tasks (title, description, frequency_days, next_due_at, created_by, is_active, created_at)
                     VALUES (:title, :description, :frequency, :next, :created_by, 1, :created_at)'
                )->execute([
                    'title'       => $form['title'],
                    'description' => $form['description'],
                    'frequency'   => $form['frequency_days'],
                    'next'        => $form['next_due_at'],
                    'created_by'  => (int) $user['id'],
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);

                $newId = (int) $pdo->lastInsertId();
                Activity::log('attivita_creata', 'tasks', $newId, $form['title']);
                flash('success', 'Attività creata.');
            }

            redirect('attivita.php');
        }
    }
}

$editing = get_int('edit');

if ($editing > 0 && !is_post()) {
    $statement = $pdo->prepare('SELECT * FROM tasks WHERE id = :id');
    $statement->execute(['id' => $editing]);
    $task = $statement->fetch() ?: null;

    if ($task !== null) {
        $form = [
            'id'             => (int) $task['id'],
            'title'          => (string) $task['title'],
            'description'    => (string) $task['description'],
            'frequency_days' => (int) $task['frequency_days'],
            'next_due_at'    => (string) $task['next_due_at'],
        ];
    }
}

$statement = $pdo->prepare(
    'SELECT t.*, u.full_name AS last_full_name, u.username AS last_username
     FROM tasks t
     LEFT JOIN users u ON u.id = t.last_done_by
     WHERE t.is_active = 1
     ORDER BY t.next_due_at ASC, t.title ASC'
);
$statement->execute();
$tasks = $statement->fetchAll();

$inactiveTasks = $pdo->query(
    'SELECT id, title, frequency_days, next_due_at FROM tasks WHERE is_active = 0 ORDER BY title ASC'
)->fetchAll();

$completions = $pdo->query(
    'SELECT l.*, t.title AS task_title, u.full_name AS user_full_name, u.username AS user_username
     FROM task_logs l
     LEFT JOIN tasks t ON t.id = l.task_id
     LEFT JOIN users u ON u.id = l.done_by
     ORDER BY l.id DESC
     LIMIT 15'
)->fetchAll();

layout_start('Attività ricorrenti', 'attivita');
?>

<p class="lead">
    Manutenzione programmata: quando segni un'attività come fatta, la prossima scadenza si sposta in avanti
    della frequenza indicata. Se salta la scadenza, resta in cima alla coda come "in ritardo".
</p>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="panel">
    <div class="panel-head">
        <h2><?= $form['id'] > 0 ? 'Modifica attività' : 'Nuova attività' ?></h2>
    </div>

    <form method="post" action="attivita.php" class="form form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

        <label class="field field-span">
            <span>Cosa va fatto</span>
            <input type="text" name="title" value="<?= e($form['title']) ?>" maxlength="160"
                   placeholder="Es. Pulire i filtri dell'impianto di aspirazione" required>
        </label>

        <label class="field">
            <span>Ogni quanti giorni?</span>
            <input type="number" name="frequency_days" value="<?= (int) $form['frequency_days'] ?>"
                   min="1" max="365" required>
            <small class="hint">7 = settimanale · 14 = ogni due settimane · 30 = mensile</small>
        </label>

        <label class="field">
            <span>Prima scadenza / prossima scadenza</span>
            <input type="date" name="next_due_at" value="<?= e($form['next_due_at']) ?>" required>
        </label>

        <label class="field field-span">
            <span>Note (facoltative)</span>
            <textarea name="description" rows="2" maxlength="2000"
                      placeholder="Es. usare guanti e controllare la pressione"><?= e($form['description']) ?></textarea>
        </label>

        <div class="form-actions field-span">
            <button type="submit" class="btn btn-primary"><?= $form['id'] > 0 ? 'Salva modifiche' : 'Crea attività' ?></button>
            <?php if ($form['id'] > 0): ?>
                <a href="attivita.php" class="btn btn-ghost">Annulla</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Attività attive</h2>
        <span class="muted"><?= e((string) count($tasks)) ?> attività</span>
    </div>

    <?php if ($tasks === []): ?>
        <p class="empty">Nessuna attività programmata. Creane una qui sopra.</p>
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
                            · prossima scadenza <?= e(fmt_date((string) $task['next_due_at'])) ?>
                            <?php if ($task['last_done_at'] !== null): ?>
                                · ultima volta <?= e(fmt_dt((string) $task['last_done_at'])) ?>
                                da <?= e(person($task['last_full_name'], $task['last_username'])) ?>
                            <?php else: ?>
                                · mai eseguita
                            <?php endif; ?>
                        </span>
                        <?php if (trim((string) $task['description']) !== ''): ?>
                            <span class="task-note"><?= e((string) $task['description']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="task-actions">
                        <form method="post" action="attivita.php" class="task-complete">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                            <input type="text" name="note" placeholder="Nota (facoltativa)" maxlength="500" class="input-sm">
                            <button type="submit" class="btn btn-sm btn-success">Fatta</button>
                        </form>

                        <div class="task-links">
                            <a href="attivita.php?edit=<?= (int) $task['id'] ?>" class="link-sm">Modifica</a>

                            <form method="post" action="attivita.php" class="inline"
                                  onsubmit="return confirm('Disattivare questa attività?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                <button type="submit" class="link-sm link-danger">Disattiva</button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($inactiveTasks !== []): ?>
    <section class="panel panel-muted">
        <div class="panel-head">
            <h2>Attività disattivate</h2>
        </div>
        <ul class="simple-list">
            <?php foreach ($inactiveTasks as $task): ?>
                <li>
                    <span><?= e($task['title']) ?> <span class="muted">· <?= e(frequency_text((int) $task['frequency_days'])) ?></span></span>
                    <form method="post" action="attivita.php" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reactivate">
                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                        <button type="submit" class="link-sm">Riattiva</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2>Ultimi completamenti</h2>
        <span class="muted">Registro attività</span>
    </div>

    <?php if ($completions === []): ?>
        <p class="empty">Nessun completamento registrato.</p>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($completions as $completion): ?>
                <li>
                    <span class="timeline-dot"></span>
                    <span class="timeline-text">
                        <strong><?= e($completion['task_title'] ?? 'Attività eliminata') ?></strong>
                        · <?= e(person($completion['user_full_name'], $completion['user_username'])) ?>
                        <span class="muted"><?= e(fmt_dt((string) $completion['done_at'])) ?></span>
                        <?php if (trim((string) $completion['note']) !== ''): ?>
                            <small><?= e((string) $completion['note']) ?></small>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php layout_end(); ?>
