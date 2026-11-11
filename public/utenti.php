<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireAdmin();

$user = Auth::user();
$pdo = Database::pdo();

$errors = [];
$new = ['username' => '', 'full_name' => '', 'role' => 'operatore'];

if (is_post()) {
    csrf_or_redirect('utenti.php');

    $action = post_str('action');

    if ($action === 'create') {
        $new['username'] = strtolower(post_str('username'));
        $new['full_name'] = post_str('full_name');
        $new['role'] = post_str('role');
        $password = (string) ($_POST['password'] ?? '');

        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $new['username'])) {
            $errors[] = 'Il nome utente deve avere 3-50 caratteri: lettere, numeri, punto, trattino o underscore.';
        }

        if (text_len($new['full_name']) > 120) {
            $errors[] = 'Il nome completo è troppo lungo (massimo 120 caratteri).';
        }

        if (!array_key_exists($new['role'], roles())) {
            $errors[] = 'Scegli un ruolo valido.';
        }

        if (text_len($password) < 8) {
            $errors[] = 'La password temporanea deve avere almeno 8 caratteri.';
        }

        if ($errors === []) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username) = :username');
            $check->execute(['username' => $new['username']]);

            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Questo nome utente esiste già.';
            } else {
                $pdo->prepare(
                    'INSERT INTO users (username, password_hash, full_name, role, is_active, must_change_password, created_at)
                     VALUES (:username, :hash, :full_name, :role, 1, 1, :created_at)'
                )->execute([
                    'username'   => $new['username'],
                    'hash'       => password_hash($password, PASSWORD_DEFAULT),
                    'full_name'  => $new['full_name'],
                    'role'       => $new['role'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $newId = (int) $pdo->lastInsertId();

                Activity::log('utente_creato', 'users', $newId, $new['username'] . ' · ' . role_label($new['role']));

                flash('success', 'Utente ' . $new['username'] . ' creato. Al primo accesso dovrà cambiare la password.');

                redirect('utenti.php');
            }
        }
    }

    if ($action === 'update') {
        $id = post_int('id');
        $role = post_str('role');
        $active = post_int('is_active') === 1 ? 1 : 0;

        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $target = $statement->fetch() ?: null;

        if ($target === null) {
            $errors[] = 'Utente non trovato.';
        } elseif ($id === (int) $user['id']) {
            $errors[] = 'Non puoi cambiare ruolo o stato del tuo stesso account.';
        } elseif (!array_key_exists($role, roles())) {
            $errors[] = 'Ruolo non valido.';
        } else {
            $removesAdmin = $target['role'] === 'admin' && ($role !== 'admin' || $active === 0);

            if ($removesAdmin) {
                $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();

                if ($admins <= 1) {
                    $errors[] = 'Deve rimanere almeno un amministratore attivo.';
                }
            }

            if ($errors === []) {
                $pdo->prepare('UPDATE users SET role = :role, is_active = :active WHERE id = :id')
                    ->execute(['role' => $role, 'active' => $active, 'id' => $id]);

                Activity::log('utente_modificato', 'users', $id, $target['username'] . ' · ' . role_label($role) . ' · ' . ($active === 1 ? 'attivo' : 'disattivato'));

                flash('success', 'Utente aggiornato.');

                redirect('utenti.php');
            }
        }
    }

    if ($action === 'reset_password') {
        $id = post_int('id');
        $password = (string) ($_POST['new_password'] ?? '');

        if ($id === (int) $user['id']) {
            $errors[] = 'Usa la pagina Profilo per cambiare la tua password.';
        } elseif (text_len($password) < 8) {
            $errors[] = 'La password temporanea deve avere almeno 8 caratteri.';
        } else {
            $statement = $pdo->prepare('SELECT username FROM users WHERE id = :id');
            $statement->execute(['id' => $id]);
            $username = $statement->fetchColumn();

            if ($username === false) {
                $errors[] = 'Utente non trovato.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id')
                    ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);

                Activity::log('password_reimpostata', 'users', $id, (string) $username);

                flash('success', 'Password temporanea impostata per ' . $username . '.');

                redirect('utenti.php');
            }
        }
    }
}

$users = $pdo->query(
    'SELECT * FROM users ORDER BY
        CASE role WHEN \'admin\' THEN 1 WHEN \'manutentore\' THEN 2 ELSE 3 END,
        username ASC'
)->fetchAll();

layout_start('Utenti', 'utenti');
?>

<p class="lead">
    Non esiste registrazione: gli account li crei solo tu da qui. Ogni nuovo utente riceve una password
    temporanea e al primo accesso viene obbligato a cambiarla.
</p>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="panel">
    <div class="panel-head">
        <h2>Nuovo utente</h2>
    </div>

    <form method="post" action="utenti.php" class="form form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <label class="field">
            <span>Nome utente</span>
            <input type="text" name="username" value="<?= e($new['username']) ?>" maxlength="50"
                   placeholder="Es. mario.rossi" required autocomplete="off">
        </label>

        <label class="field">
            <span>Nome e cognome</span>
            <input type="text" name="full_name" value="<?= e($new['full_name']) ?>" maxlength="120"
                   placeholder="Es. Mario Rossi">
        </label>

        <label class="field">
            <span>Ruolo</span>
            <select name="role">
                <?php foreach (roles() as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $new['role'] === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Password temporanea</span>
            <input type="text" name="password" minlength="8" required autocomplete="new-password"
                   placeholder="Minimo 8 caratteri">
        </label>

        <div class="form-actions field-span">
            <button type="submit" class="btn btn-primary">Crea utente</button>
        </div>
    </form>

    <ul class="legend">
        <li><strong>Amministratore</strong>: gestisce utenti, categorie, macchine e vede tutto.</li>
        <li><strong>Manutentore</strong>: coda condivisa, attività ricorrenti, statistiche.</li>
        <li><strong>Operatore</strong>: invia segnalazioni e vede solo le proprie.</li>
    </ul>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Utenti registrati</h2>
        <span class="muted"><?= e((string) count($users)) ?> account</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Ruolo</th>
                    <th>Stato</th>
                    <th>Password</th>
                    <th>Ultimo accesso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e(person((string) $row['full_name'], (string) $row['username'])) ?></strong>
                            <span class="table-sub mono"><?= e($row['username']) ?></span>
                        </td>
                        <td><?= e(role_label((string) $row['role'])) ?></td>
                        <td>
                            <?= (int) $row['is_active'] === 1
                                ? '<span class="pill pill-ok">Attivo</span>'
                                : '<span class="pill pill-off">Disattivato</span>' ?>
                        </td>
                        <td>
                            <?php if ((int) $row['must_change_password'] === 1): ?>
                                <span class="pill pill-warn">Da cambiare</span>
                            <?php else: ?>
                                <span class="muted">OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap"><?= e(fmt_dt($row['last_login_at'] !== null ? (string) $row['last_login_at'] : null)) ?></td>
                        <td class="cell-actions">
                            <?php if ((int) $row['id'] === (int) $user['id']): ?>
                                <span class="muted">Tu</span>
                            <?php else: ?>
                                <details class="row-actions">
                                    <summary>Gestisci</summary>

                                    <form method="post" action="utenti.php" class="form form-tight">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                                        <label class="field">
                                            <span>Ruolo</span>
                                            <select name="role">
                                                <?php foreach (roles() as $key => $label): ?>
                                                    <option value="<?= e($key) ?>" <?= $row['role'] === $key ? 'selected' : '' ?>>
                                                        <?= e($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <label class="checkbox">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1"
                                                <?= (int) $row['is_active'] === 1 ? 'checked' : '' ?>>
                                            <span>Account attivo</span>
                                        </label>

                                        <button type="submit" class="btn btn-sm">Salva</button>
                                    </form>

                                    <form method="post" action="utenti.php" class="form form-tight"
                                          onsubmit="return confirm('Impostare una nuova password temporanea per questo utente?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                                        <label class="field">
                                            <span>Nuova password temporanea</span>
                                            <input type="text" name="new_password" minlength="8" required autocomplete="new-password">
                                        </label>

                                        <button type="submit" class="btn btn-sm">Reimposta password</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="hint">
        Gli utenti non si eliminano, si disattivano: così lo storico delle segnalazioni resta leggibile.
    </p>
</section>

<?php layout_end(); ?>
