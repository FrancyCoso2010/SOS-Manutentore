<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireLogin();

$user = Auth::user();
$needsPassword = (int) $user['must_change_password'] === 1;
$needsCompany = !Settings::isSetupComplete();
$isAdmin = (string) $user['role'] === 'admin';

if (!$needsPassword && !$needsCompany) {
    redirect('dashboard.php');
}

$canSetupCompany = $needsCompany && $isAdmin;

$errors = [];
$companyName = Settings::get('company_name');
$companyLocation = Settings::get('company_location');
$workStart = Settings::get('work_start', '08:00');
$workEnd = Settings::get('work_end', '17:00');
$timezone = Settings::get('timezone', date_default_timezone_get());

if (is_post()) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $currentPassword = post_str('current_password');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($canSetupCompany) {
            $companyName = post_str('company_name');
            $companyLocation = post_str('company_location');
            $workStart = post_str('work_start');
            $workEnd = post_str('work_end');
            $timezone = post_str('timezone');

            if ($companyName === '') {
                $errors[] = 'Inserisci il nome dell\'azienda.';
            } elseif (text_len($companyName) > 150) {
                $errors[] = 'Il nome dell\'azienda è troppo lungo (massimo 150 caratteri).';
            }

            if (text_len($companyLocation) > 150) {
                $errors[] = 'Il luogo è troppo lungo (massimo 150 caratteri).';
            }

            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $workStart) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $workEnd)) {
                $errors[] = 'Inserisci orari validi nel formato HH:MM.';
            } elseif ($workStart >= $workEnd) {
                $errors[] = 'L\'orario di fine deve essere successivo a quello di inizio.';
            }

            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                $errors[] = 'Scegli un fuso orario valido.';
            }
        }

        if ($needsPassword) {
            if ($currentPassword === '') {
                $errors[] = 'Inserisci la password attuale.';
            } elseif (!Auth::verifyCurrentPassword($currentPassword)) {
                $errors[] = 'La password attuale non è corretta.';
            }

            if (text_len($newPassword) < 8) {
                $errors[] = 'La nuova password deve avere almeno 8 caratteri.';
            } elseif (strtolower($newPassword) === 'admin') {
                $errors[] = 'Scegli una password diversa da quella di default.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Le due password non coincidono.';
            }
        }

        if ($errors === []) {
            if ($needsPassword) {
                Auth::updatePassword((int) $user['id'], $newPassword);
                Activity::log('password_cambiata', 'users', (int) $user['id'], 'Password aggiornata al primo accesso');
            }

            if ($canSetupCompany) {
                Settings::setMany([
                    'company_name'       => $companyName,
                    'company_location'   => $companyLocation,
                    'work_start'         => $workStart,
                    'work_end'           => $workEnd,
                    'timezone'           => $timezone,
                    'setup_completed'    => '1',
                    'setup_completed_at' => date('Y-m-d H:i:s'),
                ]);

                Activity::log('setup_completato', 'settings', null, $companyName . ' · ' . $companyLocation);
            }

            flash('success', 'Configurazione completata. Benvenuto in ' . app_name() . '.');
            redirect('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a1424">
    <title>Configurazione iniziale · <?= e(product_name()) ?></title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="auth-page setup-page">
    <div class="auth-shell">
        <aside class="auth-aside" aria-hidden="true">
            <div class="auth-aside-top"><?= brand_lockup('logo-lockup-light') ?></div>

            <div class="auth-aside-body">
                <h2>Partiamo<br>con il piede giusto.</h2>
                <p>
                    Due minuti per impostare password e dati dell'azienda: da qui in poi
                    il pannello è pronto per il reparto.
                </p>

                <ul class="auth-points">
                    <li>
                        <span class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg></span>
                        Password personale, mai condivisa
                    </li>
                    <li>
                        <span class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg></span>
                        Fuso orario allineato alla sede
                    </li>
                    <li>
                        <span class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg></span>
                        Ogni azione finisce nel registro
                    </li>
                </ul>
            </div>

            <p class="auth-aside-foot">Configurazione guidata · una volta sola</p>
        </aside>

        <main class="auth-main auth-main-top">
            <div class="setup-card">
                <header class="auth-header">
                    <?= brand_lockup() ?>
                    <h1>Configurazione iniziale</h1>
                    <p>
                        <?php if ($canSetupCompany): ?>
                            Prima di usare il pannello imposta la tua password e i dati dell'azienda.
                        <?php else: ?>
                            Prima di continuare devi cambiare la password assegnata dall'amministratore.
                        <?php endif; ?>
                    </p>
                </header>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <?php if ($needsCompany && !$isAdmin): ?>
                    <div class="alert alert-warning" role="alert">
                        La configurazione dell'azienda non è ancora stata completata dall'amministratore.
                    </div>
                <?php endif; ?>

                <form method="post" action="setup.php" class="form" autocomplete="off">
                    <?= csrf_field() ?>

                    <?php if ($canSetupCompany): ?>
                        <section class="form-section">
                            <h2>Dati azienda</h2>

                            <label class="field">
                                <span>Nome dell'azienda</span>
                                <input type="text" name="company_name" value="<?= e($companyName) ?>"
                                       maxlength="150" required autofocus>
                            </label>

                            <label class="field">
                                <span>Luogo / sede</span>
                                <input type="text" name="company_location" value="<?= e($companyLocation) ?>"
                                       maxlength="150" placeholder="Es. Stabilimento di Modena">
                            </label>

                            <div class="field-row">
                                <label class="field">
                                    <span>Inizio orario di lavoro</span>
                                    <input type="time" name="work_start" value="<?= e($workStart) ?>" required>
                                </label>

                                <label class="field">
                                    <span>Fine orario di lavoro</span>
                                    <input type="time" name="work_end" value="<?= e($workEnd) ?>" required>
                                </label>
                            </div>

                            <label class="field">
                                <span>Fuso orario</span>
                                <input type="text" name="timezone" value="<?= e($timezone) ?>"
                                       list="timezone-list" required>
                                <datalist id="timezone-list">
                                    <option value="Europe/Rome"></option>
                                    <option value="Europe/Zurich"></option>
                                    <option value="Europe/Paris"></option>
                                    <option value="Europe/Berlin"></option>
                                    <option value="Europe/Madrid"></option>
                                    <option value="Europe/London"></option>
                                </datalist>
                            </label>

                            <p class="hint">
                                Data e ora rilevate da questa macchina:
                                <strong><?= e(date('d/m/Y H:i')) ?></strong>
                                (<?= e(date_default_timezone_get()) ?>)
                            </p>
                        </section>
                    <?php endif; ?>

                    <?php if ($needsPassword): ?>
                        <section class="form-section">
                            <h2>Sicurezza accesso</h2>

                            <label class="field">
                                <span>Password attuale</span>
                                <input type="password" name="current_password" autocomplete="current-password"
                                       <?= $canSetupCompany ? '' : 'autofocus' ?> required>
                                <?php if ($isAdmin && $needsCompany): ?>
                                    <small class="hint">La password iniziale è quella di default (admin).</small>
                                <?php endif; ?>
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
                        </section>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?= $canSetupCompany ? 'Salva e inizia' : 'Cambia password' ?>
                    </button>
                </form>

                <form method="post" action="logout.php" class="setup-logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Esci</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
