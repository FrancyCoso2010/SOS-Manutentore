<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

if (Auth::check()) {
    redirect('dashboard.php');
}

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Inserisci nome utente e password.';
    } elseif (Auth::attempt($username, $password)) {
        redirect('dashboard.php');
    } else {
        $errors[] = 'Credenziali non valide.';
    }
}

$company = Settings::get('company_name');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a1424">
    <title>Accesso · <?= e(product_name()) ?></title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <aside class="auth-aside" aria-hidden="true">
            <div class="auth-aside-top"><?= brand_lockup('logo-lockup-light') ?></div>

            <div class="auth-aside-body">
                <h2>Il guasto non aspetta.<br>Nemmeno il pannello.</h2>
                <p>
                    Segnala in tre tocchi, prendi in carico dalla coda condivisa,
                    chiudi con una nota. Tutto tracciato, niente telefonate a vuoto.
                </p>

                <ul class="auth-points">
                    <li>
                        <span class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg></span>
                        Coda unica per tutti i manutentori
                    </li>
                    <li>
                        <span class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg></span>
                        Attività programmate con scadenze
                    </li>
                    <li>
                        <span class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg></span>
                        Statistiche e registro sempre aggiornati
                    </li>
                </ul>
            </div>

            <p class="auth-aside-foot">Installazione interna · i dati restano nella tua azienda</p>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <header class="auth-header">
                    <?= brand_lockup() ?>
                    <h1><?= e($company !== '' ? $company : 'Accesso al pannello') ?></h1>
                    <p>Inserisci le credenziali del tuo account aziendale.</p>
                </header>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" action="login.php" class="form" autocomplete="on">
                    <?= csrf_field() ?>

                    <label class="field">
                        <span>Nome utente</span>
                        <input type="text" name="username" value="<?= e($username) ?>"
                               autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required>
                    </label>

                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">Accedi</button>
                </form>

                <p class="auth-footer">Accesso riservato al personale autorizzato.</p>
            </div>
        </main>
    </div>
</body>
</html>
