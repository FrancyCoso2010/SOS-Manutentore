<?php

declare(strict_types=1);

$config = require __DIR__ . '/../src/bootstrap.php';

Auth::requireStaff();

$pdo = Database::pdo();
$errors = [];

if (is_post()) {
    csrf_or_redirect('categorie.php');

    $action = post_str('action');

    if ($action === 'add_category') {
        $name = post_str('name');

        if ($name === '') {
            $errors[] = 'Inserisci il nome della categoria.';
        } elseif (text_len($name) > 120) {
            $errors[] = 'Il nome della categoria è troppo lungo (massimo 120 caratteri).';
        } else {
            $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(:name)');
            $check->execute(['name' => $name]);

            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Esiste già una categoria con questo nome.';
            } else {
                $pdo->prepare('INSERT INTO categories (name, is_active, created_at) VALUES (:name, 1, :created_at)')
                    ->execute(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);

                Activity::log('categoria_creata', 'categories', (int) $pdo->lastInsertId(), $name);
                flash('success', 'Categoria aggiunta.');

                redirect('categorie.php');
            }
        }
    }

    if ($action === 'toggle_category') {
        $id = post_int('id');

        $pdo->prepare('UPDATE categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id')
            ->execute(['id' => $id]);

        Activity::log('categoria_modificata', 'categories', $id, 'Attivazione/disattivazione');
        flash('success', 'Categoria aggiornata.');

        redirect('categorie.php');
    }

    if ($action === 'add_asset') {
        $name = post_str('name');
        $categoryId = post_int('category_id');

        if ($name === '') {
            $errors[] = 'Inserisci il nome della macchina.';
        } elseif (text_len($name) > 120) {
            $errors[] = 'Il nome della macchina è troppo lungo (massimo 120 caratteri).';
        } else {
            if ($categoryId > 0) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = :id AND is_active = 1');
                $check->execute(['id' => $categoryId]);

                if ((int) $check->fetchColumn() === 0) {
                    $categoryId = 0;
                }
            }

            $pdo->prepare('INSERT INTO assets (name, category_id, is_active, created_at) VALUES (:name, :category, 1, :created_at)')
                ->execute([
                    'name'       => $name,
                    'category'   => $categoryId > 0 ? $categoryId : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

            Activity::log('macchina_creata', 'assets', (int) $pdo->lastInsertId(), $name);
            flash('success', 'Macchina aggiunta.');

            redirect('categorie.php');
        }
    }

    if ($action === 'toggle_asset') {
        $id = post_int('id');

        $pdo->prepare('UPDATE assets SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id')
            ->execute(['id' => $id]);

        Activity::log('macchina_modificata', 'assets', $id, 'Attivazione/disattivazione');
        flash('success', 'Macchina aggiornata.');

        redirect('categorie.php');
    }
}

$categories = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id) AS report_count,
            (SELECT COUNT(*) FROM assets a WHERE a.category_id = c.id AND a.is_active = 1) AS asset_count
     FROM categories c
     ORDER BY c.is_active DESC, c.name ASC'
)->fetchAll();

$assets = $pdo->query(
    'SELECT a.*, c.name AS category_name,
            (SELECT COUNT(*) FROM reports r WHERE r.asset_id = a.id) AS report_count
     FROM assets a
     LEFT JOIN categories c ON c.id = a.category_id
     ORDER BY a.is_active DESC, a.name ASC'
)->fetchAll();

layout_start('Categorie e macchine', 'categorie');
?>

<p class="lead">
    Le categorie indicano che <em>tipo</em> di cosa si è guastato (utensile, macchina, linea, utilità).
    L'urgenza invece è un livello fisso scelto dall'operatore, usato per ordinare la coda di lavoro.
</p>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="split">
    <section class="panel">
        <div class="panel-head">
            <h2>Categorie</h2>
        </div>

        <form method="post" action="categorie.php" class="form form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">

            <label class="field field-grow">
                <span>Nuova categoria</span>
                <input type="text" name="name" maxlength="120" placeholder="Es. Impianto elettrico" required>
            </label>

            <button type="submit" class="btn">Aggiungi</button>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Macchine</th>
                        <th>Segnalazioni</th>
                        <th>Stato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= e($category['name']) ?></td>
                            <td><?= (int) $category['asset_count'] ?></td>
                            <td><?= (int) $category['report_count'] ?></td>
                            <td>
                                <?= (int) $category['is_active'] === 1
                                    ? '<span class="pill pill-ok">Attiva</span>'
                                    : '<span class="pill pill-off">Disattivata</span>' ?>
                            </td>
                            <td class="cell-actions">
                                <form method="post" action="categorie.php" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_category">
                                    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                    <button type="submit" class="link-sm">
                                        <?= (int) $category['is_active'] === 1 ? 'Disattiva' : 'Riattiva' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint">Le categorie disattivate non compaiono più nel form dell'operatore, ma restano nello storico.</p>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Macchine e impianti</h2>
        </div>

        <form method="post" action="categorie.php" class="form form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_asset">

            <label class="field field-grow">
                <span>Nome della macchina</span>
                <input type="text" name="name" maxlength="120" placeholder="Es. Tornio 3" required>
            </label>

            <label class="field field-inline">
                <span>Categoria</span>
                <select name="category_id">
                    <option value="0">Nessuna</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ((int) $category['is_active'] === 1): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="btn">Aggiungi</button>
        </form>

        <?php if ($assets === []): ?>
            <p class="empty">Nessuna macchina registrata.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Macchina</th>
                            <th>Categoria</th>
                            <th>Segnalazioni</th>
                            <th>Stato</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><?= e($asset['name']) ?></td>
                                <td><?= e($asset['category_name'] ?? '—') ?></td>
                                <td><?= (int) $asset['report_count'] ?></td>
                                <td>
                                    <?= (int) $asset['is_active'] === 1
                                        ? '<span class="pill pill-ok">Attiva</span>'
                                        : '<span class="pill pill-off">Disattivata</span>' ?>
                                </td>
                                <td class="cell-actions">
                                    <form method="post" action="categorie.php" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_asset">
                                        <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
                                        <button type="submit" class="link-sm">
                                            <?= (int) $asset['is_active'] === 1 ? 'Disattiva' : 'Riattiva' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php layout_end(); ?>
