<?php
// admin/menus.php

$navItems = $pdo->query('SELECT id, label, url, parent_id, sort_order FROM navigation_items ORDER BY sort_order ASC, id ASC')->fetchAll();
?>

<div class="grid">
  <div class="card">
    <h3>Naujas meniu punktas</h3>
    <form method="post">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="nav_new">
      <input name="label" placeholder="Pavadinimas" required>
      <input name="url" placeholder="Nuoroda" required>
      <select name="parent_id">
        <option value="">Be tėvinio</option>
        <?php foreach ($navItems as $item): if ($item['parent_id']) continue; ?>
          <option value="<?php echo (int)$item['id']; ?>"><?php echo htmlspecialchars($item['label']); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Išsaugoti</button>
    </form>
  </div>
  <div class="card" style="grid-column: span 2;">
    <h3>Visi meniu punktai</h3>
    <table>
      <thead><tr><th>Pavadinimas</th><th>Nuoroda</th><th>Tėvinis</th><th>Veiksmai</th></tr></thead>
      <tbody>
        <?php foreach ($navItems as $item): ?>
          <tr>
            <td><?php echo htmlspecialchars($item['label']); ?></td>
            <td><?php echo htmlspecialchars($item['url']); ?></td>
            <td>
              <?php
                $parent = null;
                foreach ($navItems as $p) { if ($p['id'] == $item['parent_id']) { $parent = $p; break; } }
                echo $parent ? htmlspecialchars($parent['label']) : '—';
              ?>
            </td>
            <td style="display:flex; gap:6px;">
              <form method="post" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="nav_update">
                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                <input name="label" value="<?php echo htmlspecialchars($item['label']); ?>" style="width:140px; margin:0;">
                <input name="url" value="<?php echo htmlspecialchars($item['url']); ?>" style="width:200px; margin:0;">
                <select name="parent_id" style="margin:0;">
                  <option value="">Be tėvinio</option>
                  <?php foreach ($navItems as $p): if ($p['parent_id']) continue; ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo $item['parent_id'] == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['label']); ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Atnaujinti</button>
              </form>
              <form method="post" onsubmit="return confirm('Trinti meniu punktą?');" style="margin:0;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="nav_delete">
                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                <button class="btn" type="submit" style="background:#fff; color:#0b0b0b;">Trinti</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card" style="grid-column: span 2;">
    <h3>Bendras rikiavimas</h3>
    <form method="post" style="display:flex;flex-direction:column;gap:12px;">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="nav_reorder">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;align-items:center;">
        <strong>Pavadinimas</strong><strong>Tėvas</strong><strong>Eilė</strong>
        <?php foreach ($navItems as $item): ?>
          <div><?php echo htmlspecialchars($item['label']); ?></div>
          <div>
            <?php
              $parent = null;
              foreach ($navItems as $p) { if ($p['id'] == $item['parent_id']) { $parent = $p; break; } }
              echo $parent ? htmlspecialchars($parent['label']) : '—';
            ?>
          </div>
          <input type="number" name="order[<?php echo (int)$item['id']; ?>]" value="<?php echo (int)$item['sort_order']; ?>" style="width:80px;">
        <?php endforeach; ?>
      </div>
      <button class="btn" type="submit">Išsaugoti rikiavimą</button>
    </form>
  </div>
</div>
