<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireAdmin();

$pdo = Database::pdo();

$userId = get_int('utente');
$action = get_str('azione');

$entries = Activity::recent(300, $userId > 0 ? $userId : null, $action);
$users = $pdo->query('SELECT id, username, full_name FROM users ORDER BY username ASC')->fetchAll();

layout_start('Registro attività', 'log');
?>

<p class="lead">
    Storico di ciò che accade nel pannello: accessi, creazione e chiusura segnalazioni, attività completate,
    utenti e categorie modificati.
</p>

<form method="get" action="log.php" class="filters">
    <label class="field field-inline">
        <span>Utente</span>
        <select name="utente">
            <option value="0">Tutti</option>
            <?php foreach ($users as $row): ?>
                <option value="<?= (int) $row['id'] ?>" <?= $userId === (int) $row['id'] ? 'selected' : '' ?>>
                    <?= e(person((string) $row['full_name'], (string) $row['username'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="field field-inline field-grow">
        <span>Azione</span>
        <input type="search" name="azione" value="<?= e($action) ?>"
               placeholder="Es. segnalazione, utente, password">
    </label>

    <div class="filters-actions">
        <button type="submit" class="btn">Filtra</button>
        <a href="log.php" class="btn btn-ghost">Azzera</a>
    </div>
</form>

<section class="panel">
    <div class="panel-head">
        <h2>Ultime <?= e((string) count($entries)) ?> azioni</h2>
        <span class="muted">Massimo 300 righe</span>
    </div>

    <?php if ($entries === []): ?>
        <p class="empty">Nessuna azione registrata con questi filtri.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data e ora</th>
                        <th>Utente</th>
                        <th>Azione</th>
                        <th>Oggetto</th>
                        <th>Dettagli</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td class="nowrap"><?= e(fmt_dt((string) $entry['created_at'])) ?></td>
                            <td>
                                <?php if ((string) $entry['username'] !== ''): ?>
                                    <span class="mono"><?= e((string) $entry['username']) ?></span>
                                <?php else: ?>
                                    <span class="muted">sistema</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill"><?= e(str_replace('_', ' ', (string) $entry['action'])) ?></span></td>
                            <td class="mono">
                                <?php if ((string) $entry['entity_type'] !== ''): ?>
                                    <?= e((string) $entry['entity_type']) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) $entry['details']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php layout_end(); ?>
