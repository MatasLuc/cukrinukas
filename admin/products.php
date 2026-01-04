<?php
// admin/products.php

// 1. Duomenų paruošimas
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();
$categoryCounts = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
$featuredIds = getFeaturedProductIds($pdo);

$featuredProducts = [];
if ($featuredIds) {
    $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
    $stmt = $pdo->prepare("SELECT id, title, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($featuredIds);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) { $map[$row['id']] = $row; }
    foreach ($featuredIds as $fid) {
        if (!empty($map[$fid])) { $featuredProducts[] = $map[$fid]; }
    }
}
?>

<div class="card">
  <h3>Nauja prekė</h3>
  <form method="post" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="new_product">
    <input name="title" placeholder="Pavadinimas" required>
    <input name="subtitle" placeholder="Paantraštė">
    <textarea name="description" placeholder="Aprašymas" rows="3" required></textarea>
    <input name="ribbon_text" placeholder="Juostelė ant nuotraukos (nebūtina)">
    <input name="price" type="number" step="0.01" placeholder="Kaina" required>
    <input name="sale_price" type="number" step="0.01" placeholder="Kaina su nuolaida (nebūtina)">
    <input name="quantity" type="number" min="0" placeholder="Kiekis" required>
    <select name="category_id">
      <option value="">Be kategorijos</option>
      <?php foreach ($categoryCounts as $cat): ?>
        <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
      <?php endforeach; ?>
    </select>
    <input name="meta_tags" placeholder="Žymės / SEO tagai">
    <label>Susijusios prekės</label>
    <select name="related_products[]" multiple size="4">
      <?php foreach ($products as $product): ?>
        <option value="<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['title']); ?></option>
      <?php endforeach; ?>
    </select>
    <div class="card" style="margin-top:10px;">
      <h4>Papildomi laukeliai</h4>
      <div id="attrs-create" class="input-row">
        <input class="chip-input" name="attr_label[]" placeholder="Laukelio pavadinimas">
        <input class="chip-input" name="attr_value[]" placeholder="Aprašymas">
      </div>
      <button type="button" class="btn" style="margin-top:8px; background:#fff; color:#0b0b0b; border-color:#d7d7e2;" onclick="addAttrRow('attrs-create')">+ Pridėti laukelį</button>
    </div>
    <div class="card" style="margin-top:10px;">
      <h4>Variacijos</h4>
      <div id="vars-create" class="input-row">
        <input class="chip-input" name="variation_name[]" placeholder="Variacijos pavadinimas">
        <input class="chip-input" name="variation_price[]" type="number" step="0.01" placeholder="Kainos pokytis">
      </div>
      <button type="button" class="btn" style="margin-top:8px; background:#fff; color:#0b0b0b; border-color:#d7d7e2;" onclick="addVarRow('vars-create')">+ Pridėti variaciją</button>
    </div>
    <label>Nuotraukos (galite pasirinkti kelias)</label>
    <input type="file" name="images[]" multiple accept="image/*">
    <button class="btn" type="submit">Sukurti</button>
  </form>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Prekių sąrašas</h3>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Kategorija</th><th>Kaina</th><th>Kiekis</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($products as $product): ?>
        <tr>
          <td><?php echo htmlspecialchars($product['title']); ?></td>
          <td><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></td>
          <td><?php echo number_format((float)$product['price'], 2); ?> €</td>
          <td><?php echo (int)$product['quantity']; ?> vnt</td>
          <td style="display:flex; gap:8px;">
            <a class="btn" href="/product_edit.php?id=<?php echo (int)$product['id']; ?>">Redaguoti</a>
            <form method="post" style="margin:0;" onsubmit="return confirm('Ar tikrai norite ištrinti šią prekę?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                <button class="btn" type="submit" style="background:#e74c3c; color:#fff; border-color:#c0392b;">Ištrinti</button>
            </form>
            </td>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div style="margin-top:16px; display:grid; gap:12px;">
    <h4>Parinkite 3 prekes pagrindiniam puslapiui</h4>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php foreach ($featuredProducts as $fp): ?>
        <div style="border:1px solid #e6e6ef; border-radius:12px; padding:10px 12px; background:#f9f9ff; display:flex; align-items:center; gap:10px;">
          <div>
            <strong><?php echo htmlspecialchars($fp['title']); ?></strong><br>
            <span class="muted"><?php echo number_format((float)$fp['price'], 2); ?> €</span>
          </div>
          <form method="post" style="margin:0;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="featured_remove">
            <input type="hidden" name="remove_id" value="<?php echo (int)$fp['id']; ?>">
            <button class="btn" type="submit" style="background:#fff; color:#0b0b0b;">Atžymėti</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($featuredProducts) < 3): ?>
      <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="featured_add">
        <input name="featured_query" list="products-list" placeholder="Įveskite prekės pavadinimą" style="flex:1; min-width:240px; margin:0;">
        <datalist id="products-list">
          <?php foreach ($products as $product): ?>
            <option value="<?php echo htmlspecialchars($product['title']); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <button class="btn" type="submit">Pridėti</button>
      </form>
    <?php endif; ?>
  </div>
</div>
