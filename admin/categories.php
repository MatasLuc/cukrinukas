<?php
// admin/categories.php

// 1. DB Migracija: Užtikriname, kad categories lentelė turi parent_id
try {
    $pdo->query("SELECT parent_id FROM categories LIMIT 1");
} catch (Exception $e) {
    // Jei stulpelio nėra, sukuriame
    $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id");
    $pdo->exec("ALTER TABLE categories ADD INDEX (parent_id)");
}

// 2. Veiksmų apdorojimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    // --- NAUJA KATEGORIJA ---
    if ($action === 'new_category') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        
        // Griežtas parent_id nustatymas
        $parentId = null;
        if (isset($_POST['parent_id']) && is_numeric($_POST['parent_id'])) {
            $pid = (int)$_POST['parent_id'];
            if ($pid > 0) { // Tik jei ID daugiau už 0, laikome tai tėvu
                $parentId = $pid;
            }
        }

        if ($name && $slug) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $parentId]);
            
            // Perkrauname puslapį, kad matytume rezultatą
            header('Location: /admin.php?view=categories');
            exit;
        }
    }

    // --- IŠTRYNIMAS ---
    if ($action === 'delete_category') {
        $id = (int)$_POST['id'];
        // Atkabiname vaikus (padarome juos pagrindiniais)
        $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        // Ištriname ryšius su produktais
        $pdo->prepare("DELETE FROM product_category_relations WHERE category_id = ?")->execute([$id]);
        // Ištriname pačią kategoriją
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        
        header('Location: /admin.php?view=categories');
        exit;
    }
}

// 3. Duomenų gavimas
// Svarbu: Rūšiuojame taip, kad tėvai būtų apdorojami, bet atvaizdavimui naudosime rekursiją
$allCategories = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM product_category_relations pcr WHERE pcr.category_id = c.id) as product_count 
    FROM categories c 
    ORDER BY c.name ASC
")->fetchAll();

// Sukuriame medžio struktūrą PHP pusėje
$catsById = [];
$catsByParent = [];

// Pirmas praėjimas: suindeksuojame
foreach ($allCategories as $c) {
    $c['id'] = (int)$c['id'];
    // Jei parent_id yra NULL arba 0, laikome 0 (root)
    $pid = !empty($c['parent_id']) ? (int)$c['parent_id'] : 0;
    
    $catsById[$c['id']] = $c;
    $catsByParent[$pid][] = $c;
}

// Funkcija select option'ams
function buildCategoryOptions($catsByParent, $parentId = 0, $prefix = '') {
    if (!isset($catsByParent[$parentId])) return;
    
    foreach ($catsByParent[$parentId] as $cat) {
        echo '<option value="' . $cat['id'] . '">' . $prefix . htmlspecialchars($cat['name']) . '</option>';
        // Rekursija
        buildCategoryOptions($catsByParent, $cat['id'], $prefix . '&nbsp;&nbsp;&nbsp;↳ ');
    }
}

// Funkcija lentelės eilutėms
function renderCategoryRows($catsByParent, $parentId = 0, $level = 0) {
    if (!isset($catsByParent[$parentId])) return;

    foreach ($catsByParent[$parentId] as $cat) {
        $padding = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . ($level > 0 ? '↳ ' : '');
        $style = $level === 0 ? 'font-weight:bold; background:#f9f9ff;' : '';
        ?>
        <tr style="<?php echo $style; ?>">
            <td><?php echo $padding . htmlspecialchars($cat['name']); ?></td>
            <td style="color:#666; font-size:13px;"><?php echo htmlspecialchars($cat['slug']); ?></td>
            <td><?php echo (int)$cat['product_count']; ?></td>
            <td class="inline-actions">
                <a class="btn" href="/category_edit.php?id=<?php echo $cat['id']; ?>" style="padding:4px 8px; font-size:12px;">Redaguoti</a>
                <form method="post" onsubmit="return confirm('Ištrinti kategoriją?');" style="margin:0;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                    <button class="btn" type="submit" style="background:#fff; color:#c0392b; border-color:#e74c3c; padding:4px 8px; font-size:12px;">Trinti</button>
                </form>
            </td>
        </tr>
        <?php
        // Rekursija
        renderCategoryRows($catsByParent, $cat['id'], $level + 1);
    }
}
?>

<div class="grid">
  <div class="card">
    <h3>Nauja kategorija</h3>
    <form method="post" action="/admin.php?view=categories">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="new_category">
      
      <label>Kategorijos pavadinimas</label>
      <input name="name" placeholder="Pvz.: Saldainiai" required>
      
      <label>Nuoroda (Slug)</label>
      <input name="slug" placeholder="pvz-saldainiai" required>
      
      <label>Tėvinė kategorija</label>
      <select name="parent_id">
        <option value="0">-- Pagrindinė kategorija --</option>
        <?php buildCategoryOptions($catsByParent, 0); ?>
      </select>
      
      <button class="btn" type="submit" style="margin-top:10px;">Išsaugoti</button>
    </form>
  </div>

  <div class="card" style="grid-column: span 2;">
    <h3>Kategorijų struktūra</h3>
    <table class="table-form">
      <thead><tr><th>Pavadinimas</th><th>Slug</th><th>Prekės</th><th>Veiksmai</th></tr></thead>
      <tbody>
        <?php if (empty($catsByParent[0])): ?>
            <tr><td colspan="4" style="text-align:center; padding:20px;">Kategorijų nėra</td></tr>
        <?php else: ?>
            <?php renderCategoryRows($catsByParent, 0); ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
