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

    if ($action === 'new_category') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        // Jei parinkta kategorija, bet value="" (pvz "Pagrindinė"), verčiame į NULL
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($name && $slug) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $parentId]);
        }
    }

    if ($action === 'delete_category') {
        $id = (int)$_POST['id'];
        // Prieš trinant, reikia nuspręsti ką daryti su vaikais. 
        // Šiuo atveju tiesiog "paleidžiam" vaikus (parent_id = NULL) arba galima trinti.
        // Paprasčiausia: nustatyti vaikų parent_id į NULL
        $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        
        // Ištriname ryšius su produktais
        $pdo->prepare("DELETE FROM product_category_relations WHERE category_id = ?")->execute([$id]);
        
        // Senas suderinamumas (jei naudojamas senas category_id stulpelis)
        $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?")->execute([$id]);

        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    }
}

// 3. Duomenų gavimas
// Gauname visas kategorijas ir produktų skaičių
$sql = "
    SELECT c.*, p.name as parent_name, 
    (SELECT COUNT(*) FROM product_category_relations pcr WHERE pcr.category_id = c.id) as product_count 
    FROM categories c 
    LEFT JOIN categories p ON c.parent_id = p.id 
    ORDER BY c.parent_id ASC, c.name ASC
";
$allCategories = $pdo->query($sql)->fetchAll();

// Atskiriame tėvines ir vaikus atvaizdavimui
$tree = [];
$orphans = []; // Jei netyčia būtų blogų parent_id
$map = [];

foreach ($allCategories as $cat) {
    $map[$cat['id']] = $cat;
}

foreach ($allCategories as $cat) {
    if (empty($cat['parent_id'])) {
        $tree[$cat['id']]['self'] = $cat;
        $tree[$cat['id']]['children'] = [];
    }
}
foreach ($allCategories as $cat) {
    if (!empty($cat['parent_id'])) {
        if (isset($tree[$cat['parent_id']])) {
            $tree[$cat['parent_id']]['children'][] = $cat;
        } else {
            // Jei tėvo masyve neradom (gal tėvas ištrintas?), dedam prie pagrindinių
            $tree[$cat['id']]['self'] = $cat;
            $tree[$cat['id']]['self']['name'] .= ' (Tėvas nerastas)'; 
        }
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
        <?php foreach ($tree as $top): ?>
             <option value="<?php echo $top['self']['id']; ?>"><?php echo htmlspecialchars($top['self']['name']); ?></option>
        <?php endforeach; ?>
      </select>
      
      <button class="btn" type="submit" style="margin-top:10px;">Išsaugoti</button>
    </form>
  </div>

  <div class="card" style="grid-column: span 2;">
    <h3>Kategorijų medis</h3>
    <table class="table-form">
      <thead><tr><th>Pavadinimas</th><th>Slug</th><th>Prekės</th><th>Veiksmai</th></tr></thead>
      <tbody>
        <?php if (empty($tree)): ?>
            <tr><td colspan="4" style="text-align:center;">Kategorijų nėra</td></tr>
        <?php else: ?>
            <?php foreach ($tree as $branch): ?>
                <tr style="background:#fdfdfd; font-weight:bold;">
                    <td><?php echo htmlspecialchars($branch['self']['name']); ?></td>
                    <td><?php echo htmlspecialchars($branch['self']['slug']); ?></td>
                    <td><?php echo (int)$branch['self']['product_count']; ?></td>
                    <td class="inline-actions">
                        <a class="btn" href="/category_edit.php?id=<?php echo (int)$branch['self']['id']; ?>" style="padding:6px 10px; font-size:12px;">Redaguoti</a>
                        <form method="post" onsubmit="return confirm('Ištrinti kategoriją?');" style="margin:0;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="id" value="<?php echo (int)$branch['self']['id']; ?>">
                            <button class="btn" type="submit" style="background:#fff; color:#c0392b; border-color:#e74c3c; padding:6px 10px; font-size:12px;">Trinti</button>
                        </form>
                    </td>
                </tr>
                <?php foreach ($branch['children'] as $child): ?>
                    <tr>
                        <td style="padding-left:40px; color:#555;">⤷ <?php echo htmlspecialchars($child['name']); ?></td>
                        <td style="color:#777;"><?php echo htmlspecialchars($child['slug']); ?></td>
                        <td><?php echo (int)$child['product_count']; ?></td>
                        <td class="inline-actions">
                            <a class="btn" href="/category_edit.php?id=<?php echo (int)$child['id']; ?>" style="padding:6px 10px; font-size:12px; background:#fff; color:#0b0b0b;">Redaguoti</a>
                            <form method="post" onsubmit="return confirm('Ištrinti subkategoriją?');" style="margin:0;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?php echo (int)$child['id']; ?>">
                                <button class="btn" type="submit" style="background:#fff; color:#c0392b; border-color:#ddd; padding:6px 10px; font-size:12px;">Trinti</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
