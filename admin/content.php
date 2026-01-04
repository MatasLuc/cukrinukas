<?php
// admin/content.php

// ---------------------------------------------------------
// 1. Kategorijų LOGIKA (Create / Update / Delete)
// ---------------------------------------------------------

// Užtikriname lenteles
if (function_exists('ensureNewsCategoriesTable')) ensureNewsCategoriesTable($pdo);
if (function_exists('ensureRecipeCategoriesTable')) ensureRecipeCategoriesTable($pdo);

// Apdorojame veiksmus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- BENDRA FUNKCIJA SLUG GENERAVIMUI ---
    $generateSlug = function($name, $inputSlug) {
        if (empty($inputSlug)) {
            return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $inputSlug)));
    };

    // --- NAUJIENŲ KATEGORIJOS ---
    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $slug = $generateSlug($name, $_POST['slug'] ?? '');
            $pdo->prepare("INSERT INTO news_categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
        }
        header('Location: /admin.php?view=content'); exit;
    }
    if ($action === 'update_category') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $slug = $generateSlug($name, $_POST['slug'] ?? '');
            $pdo->prepare("UPDATE news_categories SET name = ?, slug = ? WHERE id = ?")->execute([$name, $slug, $id]);
        }
        header('Location: /admin.php?view=content'); exit;
    }
    if ($action === 'delete_category') {
        $id = (int)$_POST['id'];
        if ($id) $pdo->prepare("DELETE FROM news_categories WHERE id = ?")->execute([$id]);
        header('Location: /admin.php?view=content'); exit;
    }

    // --- RECEPTŲ KATEGORIJOS (NAUJA) ---
    if ($action === 'create_recipe_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $slug = $generateSlug($name, $_POST['slug'] ?? '');
            $pdo->prepare("INSERT INTO recipe_categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
        }
        header('Location: /admin.php?view=content'); exit;
    }
    if ($action === 'update_recipe_category') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $slug = $generateSlug($name, $_POST['slug'] ?? '');
            $pdo->prepare("UPDATE recipe_categories SET name = ?, slug = ? WHERE id = ?")->execute([$name, $slug, $id]);
        }
        header('Location: /admin.php?view=content'); exit;
    }
    if ($action === 'delete_recipe_category') {
        $id = (int)$_POST['id'];
        if ($id) $pdo->prepare("DELETE FROM recipe_categories WHERE id = ?")->execute([$id]);
        header('Location: /admin.php?view=content'); exit;
    }

    // --- ĮRAŠŲ TRYNIMAS ---
    if ($action === 'delete_news') {
        $id = (int)$_POST['id'];
        if ($id) $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([$id]);
        header('Location: /admin.php?view=content'); exit;
    }
    if ($action === 'delete_recipe') {
        $id = (int)$_POST['id'];
        if ($id) $pdo->prepare("DELETE FROM recipes WHERE id = ?")->execute([$id]);
        header('Location: /admin.php?view=content'); exit;
    }
}

// ---------------------------------------------------------
// 2. Duomenų gavimas
// ---------------------------------------------------------

$newsList = $pdo->query('SELECT id, title, created_at FROM news ORDER BY created_at DESC')->fetchAll();
$recipeList = $pdo->query('SELECT id, title, created_at FROM recipes ORDER BY created_at DESC')->fetchAll();
$categoryList = $pdo->query('SELECT * FROM news_categories ORDER BY name ASC')->fetchAll();
$recipeCategoryList = $pdo->query('SELECT * FROM recipe_categories ORDER BY name ASC')->fetchAll();

// Redagavimo būsenos
$editCategory = null;
if (isset($_GET['edit_cat'])) {
    $editId = (int)$_GET['edit_cat'];
    foreach ($categoryList as $cat) { if ($cat['id'] === $editId) { $editCategory = $cat; break; } }
}
$editRecipeCategory = null;
if (isset($_GET['edit_recipe_cat'])) {
    $editId = (int)$_GET['edit_recipe_cat'];
    foreach ($recipeCategoryList as $cat) { if ($cat['id'] === $editId) { $editRecipeCategory = $cat; break; } }
}
?>

<div class="card">
  <h3>Naujienos</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Valdykite naujienų įrašus.</p>
    <a class="btn" href="/news_create.php">+ Nauja naujiena</a>
  </div>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Data</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($newsList as $n): ?>
        <tr>
          <td><?php echo htmlspecialchars($n['title']); ?></td>
          <td><?php echo date('Y-m-d', strtotime($n['created_at'])); ?></td>
          <td style="display:flex; gap: 8px;">
            <a class="btn" href="/news_edit.php?id=<?php echo (int)$n['id']; ?>">Redaguoti</a>
            <form method="POST" onsubmit="return confirm('Trinti naujieną?');" style="margin:0;">
                <input type="hidden" name="action" value="delete_news"><input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                <button type="submit" class="btn" style="background:#d32f2f; color:white; border:none;">Ištrinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:18px;">
    <h3>Naujienų kategorijos</h3>
    <div style="background: #f9f9ff; padding: 15px; border-radius: 8px; border: 1px solid #e4e7ec; margin-bottom: 20px;">
        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <?php if ($editCategory): ?>
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                <div style="flex-grow:1;"><label>Pavadinimas</label><input type="text" name="name" required value="<?php echo htmlspecialchars($editCategory['name']); ?>" style="width:100%; padding:8px;"></div>
                <div style="flex-grow:1;"><label>Slug</label><input type="text" name="slug" value="<?php echo htmlspecialchars($editCategory['slug']); ?>" style="width:100%; padding:8px;"></div>
                <div><button type="submit" class="btn">Atnaujinti</button> <a href="/admin.php?view=content" class="btn secondary">Atšaukti</a></div>
            <?php else: ?>
                <input type="hidden" name="action" value="create_category">
                <div style="flex-grow:1;"><label>Nauja kategorija</label><input type="text" name="name" required placeholder="pvz. Mityba" style="width:100%; padding:8px;"></div>
                <div style="flex-grow:1;"><label>Slug</label><input type="text" name="slug" placeholder="neprivaloma" style="width:100%; padding:8px;"></div>
                <div><button type="submit" class="btn">Pridėti</button></div>
            <?php endif; ?>
        </form>
    </div>
    <table>
        <thead><tr><th>Pavadinimas</th><th>Slug</th><th>Veiksmai</th></tr></thead>
        <tbody>
        <?php foreach ($categoryList as $cat): ?>
            <tr style="<?php echo ($editCategory && $editCategory['id'] == $cat['id']) ? 'background:#f0f9ff;' : ''; ?>">
                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                <td style="color:#666;"><?php echo htmlspecialchars($cat['slug']); ?></td>
                <td style="display:flex; gap: 8px;">
                    <a class="btn" href="/admin.php?view=content&edit_cat=<?php echo $cat['id']; ?>">Redaguoti</a>
                    <form method="POST" onsubmit="return confirm('Trinti kategoriją?');" style="margin:0;">
                        <input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn" style="background:#d32f2f; color:white; border:none;">Ištrinti</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Receptai</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Valdykite receptus.</p>
    <a class="btn" href="/recipe_create.php">+ Naujas receptas</a>
  </div>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Data</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($recipeList as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['title']); ?></td>
          <td><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
          <td style="display:flex; gap: 8px;">
            <a class="btn" href="/recipe_edit.php?id=<?php echo (int)$r['id']; ?>">Redaguoti</a>
            <form method="POST" onsubmit="return confirm('Trinti receptą?');" style="margin:0;">
                <input type="hidden" name="action" value="delete_recipe"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button type="submit" class="btn" style="background:#d32f2f; color:white; border:none;">Ištrinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:18px;">
    <h3>Receptų kategorijos</h3>
    <div style="background: #fff5f5; padding: 15px; border-radius: 8px; border: 1px solid #fed7d7; margin-bottom: 20px;">
        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <?php if ($editRecipeCategory): ?>
                <input type="hidden" name="action" value="update_recipe_category">
                <input type="hidden" name="id" value="<?php echo $editRecipeCategory['id']; ?>">
                <div style="flex-grow:1;"><label>Pavadinimas</label><input type="text" name="name" required value="<?php echo htmlspecialchars($editRecipeCategory['name']); ?>" style="width:100%; padding:8px;"></div>
                <div style="flex-grow:1;"><label>Slug</label><input type="text" name="slug" value="<?php echo htmlspecialchars($editRecipeCategory['slug']); ?>" style="width:100%; padding:8px;"></div>
                <div><button type="submit" class="btn">Atnaujinti</button> <a href="/admin.php?view=content" class="btn secondary">Atšaukti</a></div>
            <?php else: ?>
                <input type="hidden" name="action" value="create_recipe_category">
                <div style="flex-grow:1;"><label>Nauja receptų kategorija</label><input type="text" name="name" required placeholder="pvz. Desertai" style="width:100%; padding:8px;"></div>
                <div style="flex-grow:1;"><label>Slug</label><input type="text" name="slug" placeholder="neprivaloma" style="width:100%; padding:8px;"></div>
                <div><button type="submit" class="btn">Pridėti</button></div>
            <?php endif; ?>
        </form>
    </div>
    <table>
        <thead><tr><th>Pavadinimas</th><th>Slug</th><th>Veiksmai</th></tr></thead>
        <tbody>
        <?php foreach ($recipeCategoryList as $cat): ?>
            <tr style="<?php echo ($editRecipeCategory && $editRecipeCategory['id'] == $cat['id']) ? 'background:#fff0f0;' : ''; ?>">
                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                <td style="color:#666;"><?php echo htmlspecialchars($cat['slug']); ?></td>
                <td style="display:flex; gap: 8px;">
                    <a class="btn" href="/admin.php?view=content&edit_recipe_cat=<?php echo $cat['id']; ?>">Redaguoti</a>
                    <form method="POST" onsubmit="return confirm('Trinti receptų kategoriją?');" style="margin:0;">
                        <input type="hidden" name="action" value="delete_recipe_category"><input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn" style="background:#d32f2f; color:white; border:none;">Ištrinti</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
