<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    redirect(Auth::check() ? 'dashboard.php' : 'login.php');
}

Auth::logout();

redirect('login.php');
