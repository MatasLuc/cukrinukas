<?php
// admin/community.php

$communityThreads = $pdo->query('SELECT t.*, u.name AS author FROM community_threads t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC')->fetchAll();
$communityComments = $pdo->query('SELECT thread_id, COUNT(*) AS total FROM community_comments GROUP BY thread_id')->fetchAll();
$commentCounts = [];
foreach ($communityComments as $c) { $commentCounts[$c['thread_id']] = $c['total']; }
$users = $pdo->query('SELECT id, name, email FROM users ORDER BY created_at DESC')->fetchAll();
$communityBlocks = $pdo->query('SELECT b.*, u.name, u.email FROM community_blocks b JOIN users u ON u.id = b.user_id')->fetchAll();
$threadCategories = $pdo->query('SELECT * FROM community_thread_categories ORDER BY name ASC')->fetchAll();
$listingCategories = $pdo->query('SELECT * FROM community_listing_categories ORDER BY name ASC')->fetchAll();
$communityListings = $pdo->query('SELECT l.*, u.name FROM community_listings l JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC')->fetchAll();
$communityOrders = $pdo->query('SELECT co.*, l.title AS listing_title, u.name AS buyer_name FROM community_orders co JOIN community_listings l ON l.id = co.listing_id JOIN users u ON u.id = co.buyer_id ORDER BY co.created_at DESC')->fetchAll();
?>

<div class="grid" style="margin-top:10px; grid-template-columns:2fr 1fr;">
  <div class="card">
    <h3>Diskusijos</h3>
    <table>
      <thead><tr><th>Pavadinimas</th><th>Autorius</th><th>Komentarai</th><th>Data</th></tr></thead>
      <tbody>
        <?php foreach ($communityThreads as $t): ?>
          <tr>
            <td><?php echo htmlspecialchars($t['title']); ?></td>
            <td><?php echo htmlspecialchars($t['author']); ?></td>
            <td><?php echo isset($commentCounts[$t['id']]) ? (int)$commentCounts[$t['id']] : 0; ?></td>
            <td><?php echo htmlspecialchars($t['created_at']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$communityThreads): ?><tr><td colspan="4" class="muted">Diskusijų nėra.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3>Blokavimai</h3>
    <form method="post" style="display:flex;flex-direction:column;gap:8px;">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="community_block">
      <label>Vartotojas</label>
      <select name="user_id" required>
        <option value="">Pasirinkite</option>
        <?php foreach ($users as $u): ?>
          <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?></option>
        <?php endforeach; ?>
      </select>
      <label>Blokuotas iki (palikite tuščią neterminuotai)</label>
      <input type="datetime-local" name="banned_until">
      <label>Priežastis</label>
      <input name="reason">
      <button class="btn" type="submit">Išsaugoti</button>
    </form>
    <div style="margin-top:10px;">
      <?php foreach ($communityBlocks as $block): ?>
        <div style="border:1px solid #e6e6ef; border-radius:10px; padding:8px; margin-bottom:8px;">
          <strong><?php echo htmlspecialchars($block['name']); ?></strong>
          <div class="muted" style="font-size:13px;">Iki: <?php echo htmlspecialchars($block['banned_until'] ?? 'neribotai'); ?></div>
          <?php if ($block['reason']): ?><div style="font-size:13px; margin-top:4px;">Priežastis: <?php echo htmlspecialchars($block['reason']); ?></div><?php endif; ?>
          <form method="post" style="margin-top:6px;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="community_unblock">
            <input type="hidden" name="user_id" value="<?php echo (int)$block['user_id']; ?>">
            <button class="btn" type="submit" style="background:#f1f1f5; color:#0b0b0b; border-color:#e0e0ea;">Nuimti bloką</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$communityBlocks): ?><div class="muted">Apribojimų nėra.</div><?php endif; ?>
    </div>
  </div>
</div>

<div class="grid" style="margin-top:14px; grid-template-columns:1.6fr 1.4fr;">
  <div class="card">
    <h3>Diskusijų kategorijos</h3>
    <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="new_thread_category">
      <input name="name" placeholder="Pvz. Mityba" required>
      <button class="btn" type="submit">Pridėti</button>
    </form>
    <div style="display:flex;flex-direction:column;gap:6px;">
      <?php foreach ($threadCategories as $cat): ?>
        <form method="post" style="display:flex;gap:8px;align-items:center;border:1px solid #e6e6ef;padding:8px;border-radius:10px;">
          <?php echo csrfField(); ?>
          <span><?php echo htmlspecialchars($cat['name']); ?></span>
          <input type="hidden" name="action" value="delete_thread_category">
          <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
          <button class="btn" type="submit" style="background:#fff;color:#0b0b0b;border-color:#e0e0ea;">Šalinti</button>
        </form>
      <?php endforeach; ?>
      <?php if (!$threadCategories): ?><div class="muted">Kategorijų dar nėra.</div><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h3>Turgus kategorijos</h3>
    <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="new_listing_category">
      <input name="name" placeholder="Pvz. Technika" required>
      <button class="btn" type="submit">Pridėti</button>
    </form>
    <div style="display:flex;flex-direction:column;gap:6px;">
      <?php foreach ($listingCategories as $cat): ?>
        <form method="post" style="display:flex;gap:8px;align-items:center;border:1px solid #e6e6ef;padding:8px;border-radius:10px;">
          <?php echo csrfField(); ?>
          <span><?php echo htmlspecialchars($cat['name']); ?></span>
          <input type="hidden" name="action" value="delete_listing_category">
          <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
          <button class="btn" type="submit" style="background:#fff;color:#0b0b0b;border-color:#e0e0ea;">Šalinti</button>
        </form>
      <?php endforeach; ?>
      <?php if (!$listingCategories): ?><div class="muted">Kategorijų dar nėra.</div><?php endif; ?>
    </div>
  </div>
</div>

<div class="grid" style="margin-top:14px; grid-template-columns:1.6fr 1.4fr;">
  <div class="card">
    <h3>Skelbimai</h3>
    <table>
      <thead><tr><th>Pavadinimas</th><th>Pardavėjas</th><th>Kaina</th><th>Statusas</th><th>Veiksmai</th></tr></thead>
      <tbody>
        <?php foreach ($communityListings as $l): ?>
          <tr>
            <td><?php echo htmlspecialchars($l['title']); ?></td>
            <td><?php echo htmlspecialchars($l['name']); ?></td>
            <td>€<?php echo number_format((float)$l['price'],2); ?></td>
            <td><?php echo htmlspecialchars($l['status']); ?></td>
            <td>
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="community_listing_status">
                <input type="hidden" name="listing_id" value="<?php echo (int)$l['id']; ?>">
                <select name="status" style="margin:0;">
                  <option value="active" <?php echo $l['status']==='active'?'selected':''; ?>>Aktyvi</option>
                  <option value="sold" <?php echo $l['status']==='sold'?'selected':''; ?>>Parduota</option>
                </select>
                <button class="btn" type="submit" style="background:#fff; color:#0b0b0b;">Išsaugoti</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$communityListings): ?><tr><td colspan="5" class="muted">Skelbimų nėra.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3>Pirkėjų užklausos</h3>
    <table>
      <thead><tr><th>Skelbimas</th><th>Pirkėjas</th><th>Statusas</th><th>Data</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($communityOrders as $co): ?>
          <tr>
            <td><?php echo htmlspecialchars($co['listing_title']); ?></td>
            <td><?php echo htmlspecialchars($co['buyer_name']); ?></td>
            <td>
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="community_order_status">
                <input type="hidden" name="order_id" value="<?php echo (int)$co['id']; ?>">
                <select name="status" style="margin:0;">
                  <?php foreach (["laukiama","patvirtinta","įvykdyta","atšaukta"] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $co['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn" type="submit" style="background:#fff; color:#0b0b0b;">Atnaujinti</button>
              </form>
            </td>
            <td><?php echo htmlspecialchars($co['created_at']); ?></td>
            <td><?php echo htmlspecialchars($co['note']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$communityOrders): ?><tr><td colspan="5" class="muted">Užklausų nėra.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
