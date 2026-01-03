<?php
// admin/categories.php

$categoryCounts = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
?>

<div class="grid">
  <div class="card">
    <h3>Nauja kategorija</h3>
    <form method="post">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="new_category">
      <input name="name" placeholder="Pavadinimas" required>
      <input name="slug" placeholder="Nuoroda (slug)" required>
      <button class="btn" type="submit">Išsaugoti</button>
    </form>
  </div>
  <div class="card" style="grid-column: span 2;">
    <h3>Visos kategorijos</h3>
    <table class="table-form">
      <thead><tr><th>Pavadinimas</th><th>Slug</th><th>Prekės</th><th>Veiksmai</th></tr></thead>
      <tbody>
        <?php foreach ($categoryCounts as $cat): ?>
          <tr>
            <td><?php echo htmlspecialchars($cat['name']); ?></td>
            <td><?php echo htmlspecialchars($cat['slug']); ?></td>
            <td><?php echo (int)$cat['product_count']; ?></td>
            <td class="inline-actions">
              <a class="btn" href="/category_edit.php?id=<?php echo (int)$cat['id']; ?>" style="padding:8px 12px;">Redaguoti</a>
              <form method="post" onsubmit="return confirm('Ištrinti kategoriją?');" style="margin:0;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                <button class="btn" type="submit" style="background:#fff; color:#0b0b0b; padding:8px 12px;">Trinti</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
