<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireReady();

$user = Auth::user();
$errors = [];
$themes = themes();
$activeTheme = current_theme();

if (is_post()) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } elseif (post_str('form') === 'theme') {
        $theme = post_str('theme');

        if (!array_key_exists($theme, $themes)) {
            $errors[] = 'Scegli un tema valido.';
        } else {
            Database::pdo()
                ->prepare('UPDATE users SET theme = :theme WHERE id = :id')
                ->execute(['theme' => $theme, 'id' => (int) $user['id']]);

            Activity::log('tema_cambiato', 'users', (int) $user['id'], 'Tema: ' . $themes[$theme]);

            flash('success', 'Tema aggiornato: ' . $themes[$theme] . '.');

            redirect('profilo.php');
        }
    } else {
        $current = post_str('current_password');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($current === '' || !Auth::verifyCurrentPassword($current)) {
            $errors[] = 'La password attuale non è corretta.';
        }

        if (text_len($newPassword) < 8) {
            $errors[] = 'La nuova password deve avere almeno 8 caratteri.';
        } elseif ($newPassword !== $confirm) {
            $errors[] = 'Le due password non coincidono.';
        } elseif ($newPassword === $current) {
            $errors[] = 'La nuova password deve essere diversa da quella attuale.';
        }

        if ($errors === []) {
            Auth::updatePassword((int) $user['id'], $newPassword);
            Activity::log('password_cambiata', 'users', (int) $user['id'], 'Cambio password dal profilo');

            flash('success', 'Password aggiornata.');
            redirect('profilo.php');
        }
    }
}

$previousLogin = $_SESSION['previous_login_at'] ?? null;

layout_start('Profilo', 'profilo');
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<p class="lead">Il tuo account, l'aspetto del pannello e la password di accesso.</p>

<div class="split">
    <section class="panel">
        <div class="panel-head">
            <h2>I tuoi dati</h2>
        </div>

        <dl class="detail-grid detail-grid-single">
            <div>
                <dt>Nome utente</dt>
                <dd class="mono"><?= e((string) $user['username']) ?></dd>
            </div>
            <div>
                <dt>Nome completo</dt>
                <dd><?= e(person((string) $user['full_name'], (string) $user['username'])) ?></dd>
            </div>
            <div>
                <dt>Ruolo</dt>
                <dd><?= e(role_label((string) $user['role'])) ?></dd>
            </div>
            <div>
                <dt>Account creato il</dt>
                <dd><?= e(fmt_dt((string) $user['created_at'])) ?></dd>
            </div>
            <div>
                <dt>Ultimo accesso precedente</dt>
                <dd><?= e(fmt_dt(is_string($previousLogin) ? $previousLogin : null)) ?></dd>
            </div>
            <div>
                <dt>Azienda</dt>
                <dd>
                    <?= e(app_name()) ?>
                    <?= Settings::get('company_location') !== '' ? ' · ' . e(Settings::get('company_location')) : '' ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Tema dell'interfaccia</h2>
            <span class="muted">Salvato sul tuo account</span>
        </div>

        <form method="post" action="profilo.php" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="theme">

            <div class="theme-grid">
                <?php foreach ($themes as $key => $label): ?>
                    <label class="theme-card theme-<?= e($key) ?>">
                        <input type="radio" name="theme" value="<?= e($key) ?>"
                            <?= $activeTheme === $key ? 'checked' : '' ?>>
                        <span class="theme-preview" aria-hidden="true">
                            <span class="tp-side"></span>
                            <span class="tp-body">
                                <span class="tp-line"></span>
                                <span class="tp-line short"></span>
                                <span class="tp-dot"></span>
                            </span>
                        </span>
                        <span class="theme-name"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p class="hint">
                L'anteprima cambia appena scegli un tema. Dopo il salvataggio resta legato
                al tuo utente, anche se entri da un altro dispositivo.
            </p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salva tema</button>
            </div>
        </form>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Cambia password</h2>
    </div>

    <form method="post" action="profilo.php" class="form form-grid" autocomplete="off">
        <?= csrf_field() ?>

        <label class="field field-span">
            <span>Password attuale</span>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>

        <label class="field">
            <span>Nuova password</span>
            <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            <small class="hint">Almeno 8 caratteri.</small>
        </label>

        <label class="field">
            <span>Ripeti la nuova password</span>
            <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
        </label>

        <div class="form-actions field-span">
            <button type="submit" class="btn btn-primary">Aggiorna password</button>
        </div>
    </form>
</section>

<script>
    (function () {
        var inputs = document.querySelectorAll('input[name="theme"]');

        Array.prototype.forEach.call(inputs, function (input) {
            input.addEventListener('change', function () {
                document.documentElement.setAttribute('data-theme', input.value);
            });
        });
    })();
</script>

<?php layout_end(); ?>
