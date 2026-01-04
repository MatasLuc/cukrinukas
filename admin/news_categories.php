<?php
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/../helpers.php';
require __DIR__ . '/header.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureNewsCategoriesTable($pdo);

// Pridėti kategoriją
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    validateCsrfToken();
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $stmt = $pdo->prepare("INSERT INTO news_categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
    }
    header('Location: /admin/news_categories.php');
    exit;
}

// Ištrinti kategoriją
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    validateCsrfToken();
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM news_categories WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: /admin/news_categories.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM news_categories ORDER BY name ASC")->fetchAll();
?>

<div class="page-content" style="max-width: 800px; margin: 30px auto; padding: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>Naujienų kategorijos</h2>
        <a href="/admin/content.php" class="btn">← Grįžti į turinį</a>
    </div>

    <div class="card" style="margin-bottom: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div style="flex-grow:1;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Nauja kategorija</label>
                <input type="text" name="name" required placeholder="pvz. Mityba" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <button type="submit" class="btn" style="background:#0b0b0b; color:white; padding:9px 15px; border:none; border-radius:4px; cursor:pointer;">Pridėti</button>
        </form>
    </div>

    <div class="card" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid #eee;">
                    <th style="padding:10px;">Pavadinimas</th>
                    <th style="padding:10px;">Slug</th>
                    <th style="padding:10px; text-align:right;">Veiksmai</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr style="border-bottom:1px solid #f9f9f9;">
                    <td style="padding:10px;"><?php echo htmlspecialchars($cat['name']); ?></td>
                    <td style="padding:10px; color:#666;"><?php echo htmlspecialchars($cat['slug']); ?></td>
                    <td style="padding:10px; text-align:right;">
                        <form method="post" onsubmit="return confirm('Ar tikrai trinti?');" style="display:inline;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <button type="submit" style="background:none; border:none; color:red; cursor:pointer; text-decoration:underline;">Ištrinti</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($categories)): ?>
                    <tr><td colspan="3" style="padding:20px; text-align:center; color:#777;">Kategorijų nėra.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
