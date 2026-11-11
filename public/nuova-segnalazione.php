<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireReady();

$user = Auth::user();
$role = (string) $user['role'];
$pdo = Database::pdo();

$categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
$assets = $pdo->query('SELECT id, name, category_id FROM assets WHERE is_active = 1 ORDER BY name')->fetchAll();

$errors = [];
$title = '';
$description = '';
$categoryId = 0;
$assetId = 0;
$urgency = 'media';

if (is_post()) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $title = post_str('title');
        $description = post_str('description');
        $categoryId = post_int('category_id');
        $assetId = post_int('asset_id');
        $urgency = post_str('urgency', 'media');

        if ($title === '') {
            $errors[] = 'Scrivi cosa non funziona.';
        } elseif (text_len($title) > 160) {
            $errors[] = 'Il titolo è troppo lungo (massimo 160 caratteri).';
        }

        if (text_len($description) > 2000) {
            $errors[] = 'La descrizione è troppo lunga (massimo 2000 caratteri).';
        }

        if (!array_key_exists($urgency, urgencies())) {
            $errors[] = 'Scegli un livello di urgenza.';
        }

        $validCategories = array_map(static fn (array $row): int => (int) $row['id'], $categories);
        $validAssets = array_map(static fn (array $row): int => (int) $row['id'], $assets);

        if ($categoryId > 0 && !in_array($categoryId, $validCategories, true)) {
            $errors[] = 'La categoria scelta non è valida.';
        }

        if ($assetId > 0 && !in_array($assetId, $validAssets, true)) {
            $errors[] = 'La macchina scelta non è valida.';
        }

        if ($errors === []) {
            $pdo->prepare(
                'INSERT INTO reports (title, description, category_id, asset_id, urgency, status, created_by, created_at)
                 VALUES (:title, :description, :category_id, :asset_id, :urgency, \'aperto\', :created_by, :created_at)'
            )->execute([
                'title'       => $title,
                'description' => $description,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'asset_id'    => $assetId > 0 ? $assetId : null,
                'urgency'     => $urgency,
                'created_by'  => (int) $user['id'],
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $newId = (int) $pdo->lastInsertId();

            Activity::log('segnalazione_creata', 'reports', $newId, $title . ' · ' . urgency_label($urgency));

            flash('success', 'Segnalazione #' . $newId . ' registrata. I manutentori la vedono subito.');

            redirect($role === 'operatore' ? 'mie-segnalazioni.php' : 'segnalazione.php?id=' . $newId);
        }
    }
}

layout_start('Nuova segnalazione', 'nuova');
?>

<p class="lead">Descrivi in poche parole il problema. Non serve essere tecnici.</p>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" action="nuova-segnalazione.php" class="form form-report">
    <?= csrf_field() ?>

    <label class="field field-big">
        <span>1. Cosa non funziona?</span>
        <input type="text" name="title" value="<?= e($title) ?>" maxlength="160"
               placeholder="Es. Perdita d'olio dal tornio 3" required autofocus>
    </label>

    <label class="field field-big">
        <span>2. Dove? (macchina)</span>
        <select name="asset_id">
            <option value="0">Non so / non indicata</option>
            <?php foreach ($assets as $asset): ?>
                <option value="<?= (int) $asset['id'] ?>" <?= $assetId === (int) $asset['id'] ? 'selected' : '' ?>>
                    <?= e($asset['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="field field-big">
        <span>Tipo di problema</span>
        <select name="category_id">
            <option value="0">Non so</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <fieldset class="field field-big">
        <legend>3. Quanto è urgente?</legend>

        <div class="radio-grid">
            <?php foreach (urgencies() as $key => $label): ?>
                <label class="radio-card radio-<?= e(urgency_class($key)) ?>">
                    <input type="radio" name="urgency" value="<?= e($key) ?>"
                        <?= $urgency === $key ? 'checked' : '' ?> required>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <label class="field field-big">
        <span>Vuoi aggiungere altro? (facoltativo)</span>
        <textarea name="description" rows="3" maxlength="2000"
                  placeholder="Es. succede solo a fine turno"><?= e($description) ?></textarea>
    </label>

    <button type="submit" class="btn btn-primary btn-block btn-lg">Invia segnalazione</button>
</form>

<?php layout_end(); ?>
