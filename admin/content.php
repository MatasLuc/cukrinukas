<?php
// admin/content.php

// ---------------------------------------------------------
// 1. Kategorijų LOGIKA (Create / Update / Delete)
// ---------------------------------------------------------

// Užtikriname, kad lentelė egzistuoja (jei netyčia dar nėra)
if (function_exists('ensureNewsCategoriesTable')) {
    ensureNewsCategoriesTable($pdo);
}

// Apdorojame veiksmus tik jei tai POST užklausa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Čia reikėtų validateCsrfToken(); jei turite tą funkciją pasiekiamą
    // validateCsrfToken(); 

    $action = $_POST['action'] ?? '';
    
    // --- KATEGORIJOS SUKŪRIMAS ---
    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        
        if ($name) {
            // Jei slug neįvestas, generuojame iš pavadinimo
            if (empty($slugInput)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slugInput)));
            }
            
            $stmt = $pdo->prepare("INSERT INTO news_categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
        }
        // Perkrauname puslapį, kad išvalytume POST duomenis
        header('Location: /admin.php?view=content');
        exit;
    }

    // --- KATEGORIJOS ATNAUJINIMAS ---
    if ($action === 'update_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        
        if ($id && $name) {
            if (empty($slugInput)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slugInput)));
            }

            $stmt = $pdo->prepare("UPDATE news_categories SET name = ?, slug = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $id]);
        }
        header('Location: /admin.php?view=content');
        exit;
    }

    // --- KATEGORIJOS TRYNIMAS ---
    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM news_categories WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: /admin.php?view=content');
        exit;
    }

    // --- NAUJIENOS TRYNIMAS (iš senos logikos) ---
    if ($action === 'delete_news') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: /admin.php?view=content');
        exit;
    }

    // --- RECEPTO TRYNIMAS (iš senos logikos) ---
    if ($action === 'delete_recipe') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: /admin.php?view=content');
        exit;
    }
}

// ---------------------------------------------------------
// 2. Duomenų gavimas
// ---------------------------------------------------------

$newsList = $pdo->query('SELECT id, title, created_at FROM news ORDER BY created_at DESC')->fetchAll();
$recipeList = $pdo->query('SELECT id, title, created_at FROM recipes ORDER BY created_at DESC')->fetchAll();
$categoryList = $pdo->query('SELECT * FROM news_categories ORDER BY name ASC')->fetchAll();

// Patikriname, ar redaguojame kategoriją
$editCategory = null;
if (isset($_GET['edit_cat'])) {
    $editId = (int)$_GET['edit_cat'];
    foreach ($categoryList as $cat) {
        if ($cat['id'] === $editId) {
            $editCategory = $cat;
            break;
        }
    }
}
?>

<div class="card">
  <h3>Naujienos</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Redaguokite diabeto naujienų įrašus.</p>
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
            <form method="POST" onsubmit="return confirm('Ar tikrai norite ištrinti šią naujieną?');" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="delete_news">
                <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                <button type="submit" class="btn" style="background-color: #d32f2f; color: white; border: none; cursor: pointer;">Ištrinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:18px;">
    <h3>Naujienų kategorijos</h3>
    <p style="margin:0 0 15px; color:#6b6b7a;">Kurkite ir redaguokite kategorijas naujienų rūšiavimui.</p>

    <div style="background: #f9f9ff; padding: 15px; border-radius: 8px; border: 1px solid #e4e7ec; margin-bottom: 20px;">
        <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            
            <?php if ($editCategory): ?>
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                
                <div style="flex-grow:1; min-width: 200px;">
                    <label style="font-size:12px; font-weight:bold; display:block; margin-bottom:4px;">Pavadinimas</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($editCategory['name']); ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                </div>
                <div style="flex-grow:1; min-width: 200px;">
                    <label style="font-size:12px; font-weight:bold; display:block; margin-bottom:4px;">Slug (URL dalis)</label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($editCategory['slug']); ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                </div>
                <div>
                    <button type="submit" class="btn" style="background:#0b0b0b; color:white;">Atnaujinti</button>
                    <a href="/admin.php?view=content" class="btn" style="background:#fff; color:#333; border:1px solid #ccc;">Atšaukti</a>
                </div>
            <?php else: ?>
                <input type="hidden" name="action" value="create_category">
                
                <div style="flex-grow:1; min-width: 200px;">
                    <label style="font-size:12px; font-weight:bold; display:block; margin-bottom:4px;">Naujos kategorijos pavadinimas</label>
                    <input type="text" name="name" required placeholder="pvz. Mityba" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                </div>
                <div style="flex-grow:1; min-width: 200px;">
                    <label style="font-size:12px; font-weight:bold; display:block; margin-bottom:4px;">Slug (neprivaloma)</label>
                    <input type="text" name="slug" placeholder="pvz. mityba" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                </div>
                <div>
                    <button type="submit" class="btn" style="background:#0b0b0b; color:white;">Pridėti</button>
                </div>
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
                    <a class="btn" style="padding: 6px 12px; font-size: 13px;" href="/admin.php?view=content&edit_cat=<?php echo $cat['id']; ?>">Redaguoti</a>
                    <form method="POST" onsubmit="return confirm('Ar tikrai norite ištrinti kategoriją \'<?php echo htmlspecialchars($cat['name']); ?>\'?');" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn" style="background-color: #d32f2f; color: white; border: none; cursor: pointer; padding: 6px 12px; font-size: 13px;">Ištrinti</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($categoryList)): ?>
            <tr><td colspan="3" style="text-align:center; padding:20px; color:#888;">Kategorijų kol kas nėra.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Receptai</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Prižiūrėkite receptus ir jų turinį.</p>
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
            <form method="POST" onsubmit="return confirm('Ar tikrai norite ištrinti šį receptą?');" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="delete_recipe">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button type="submit" class="btn" style="background-color: #d32f2f; color: white; border: none; cursor: pointer;">Ištrinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
