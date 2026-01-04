<?php
// admin/categories.php

// 1. Užtikriname, kad DB lentelė turi parent_id
try {
    $pdo->query("SELECT parent_id FROM categories LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id");
    $pdo->exec("ALTER TABLE categories ADD INDEX (parent_id)");
}

// 2. Veiksmų apdorojimas (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    // NAUJA KATEGORIJA
    if ($action === 'new_category') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        
        // Griežtesnis patikrinimas tėvinei kategorijai
        $parentId = null;
        if (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') {
            $parentId = (int)$_POST['parent_id'];
        }

        if ($name && $slug) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $parentId]);
        }
    }

    // IŠTRYNIMAS
    if ($action === 'delete_category') {
        $id = (int)$_POST['id'];
        // Atkabiname vaikus (padarome juos pagrindiniais)
        $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        // Ištriname ryšius
        $pdo->prepare("DELETE FROM product_category_relations WHERE category_id = ?")->execute([$id]);
        // Ištriname pačią kategoriją
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    }
}

// 3. Duomenų gavimas atvaizdavimui
// Gauname viską plokščiu sąrašu, rūšiuojame pagal parent_id (kad tėvai būtų aukščiau) ir vardą
$allCategories = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM product_category_relations pcr WHERE pcr.category_id = c.id) as product_count 
    FROM categories c 
    ORDER BY c.parent_id ASC, c.name ASC
")->fetchAll();

// Pagalbinė funkcija rekursyviam select atvaizdavimui
function buildOptions($cats, $parentId = 0, $prefix = '') {
    foreach ($cats as $c) {
        $cParent = !empty($c['parent_id']) ? (int)$c['parent_id'] : 0;
        if ($cParent === $parentId) {
            echo '<option value="' . $c['id'] . '">' . $prefix . htmlspecialchars($c['name']) . '</option>';
            // Rekursija subkategorijoms
            buildOptions($cats, (int)$c['id'], $prefix . '&nbsp;&nbsp;&nbsp;↳ ');
        }
    }
}

// Formuojame medį sąrašo atvaizdavimui (Table view)
$tree = [];
$catsById = [];
foreach ($allCategories as $c) {
    $c['children'] = [];
    $catsById[$c['id']] = $c;
}
foreach ($catsById as $id => $c) {
    if (!empty($c['parent_id'])) {
        if (isset($catsById[$c['parent_id']])) {
            $catsById[$c['parent_id']]['children'][] = &$catsById[$id];
        }
    } else {
        $tree[] = &$catsById[$id];
    }
}
?>

<div class="grid">
  <div class="card">
    <h3>Nauja kategorija</h3>
    <form method="post">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="new_category">
      
      <label>Kategorijos pavadinimas</label>
      <input name="name" placeholder="Pvz.: Saldainiai" required>
      
      <label>Nuoroda (Slug)</label>
      <input name="slug" placeholder="pvz-saldainiai" required>
      
      <label>Tėvinė kategorija</label>
      <select name="parent_id">
        <option value="">-- Pagrindinė kategorija --</option>
        <?php 
            // Naudojame rekursinę funkciją, kad matytume ir gilesnius lygius
            buildOptions($allCategories, 0); 
        ?>
      </select>
      
      <button class="btn" type="submit" style="margin-top:10px;">Išsaugoti</button>
    </form>
  </div>

  <div class="card" style="grid-column: span 2;">
    <h3>Kategorijų struktūra</h3>
    <table class="table-form">
      <thead><tr><th>Pavadinimas</th><th>Slug</th><th>Prekės</th><th>Veiksmai</th></tr></thead>
      <tbody>
        <?php if (empty($tree)): ?>
            <tr><td colspan="4" style="text-align:center; padding:20px;">Kategorijų nėra</td></tr>
        <?php else: ?>
            <?php 
            // Rekursinė funkcija lentelės atvaizdavimui
            function renderRows($nodes, $level = 0) {
                foreach ($nodes as $cat) {
                    $pad = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . ($level > 0 ? '↳ ' : '');
                    $bg = $level === 0 ? 'font-weight:bold; background:#fdfdfd;' : '';
                    ?>
                    <tr style="<?php echo $bg; ?>">
                        <td style="color: <?php echo $level > 0 ? '#555' : '#000'; ?>;">
                            <?php echo $pad . htmlspecialchars($cat['name']); ?>
                        </td>
                        <td style="color:#777; font-size:13px;"><?php echo htmlspecialchars($cat['slug']); ?></td>
                        <td><?php echo (int)$cat['product_count']; ?></td>
                        <td class="inline-actions">
                            <a class="btn" href="/category_edit.php?id=<?php echo (int)$cat['id']; ?>" style="padding:4px 8px; font-size:12px;">Redaguoti</a>
                            <form method="post" onsubmit="return confirm('Ištrinti kategoriją?');" style="margin:0;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                <button class="btn" type="submit" style="background:#fff; color:#c0392b; border-color:#e74c3c; padding:4px 8px; font-size:12px;">Trinti</button>
                            </form>
                        </td>
                    </tr>
                    <?php
                    if (!empty($cat['children'])) {
                        renderRows($cat['children'], $level + 1);
                    }
                }
            }
            renderRows($tree);
            ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
