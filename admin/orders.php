<?php
// admin/orders.php

$allOrders = $pdo->query('SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC')->fetchAll();
$categoryDiscounts = getCategoryDiscounts($pdo);
$allCategoriesSimple = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$orderItemsStmt = $pdo->prepare('SELECT oi.*, p.title, p.image_url FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE order_id = ?');
?>

<div class="card">
  <h3>Visi užsakymai</h3>
  <table>
    <thead><tr><th>#</th><th>Vartotojas</th><th>Suma</th><th>Statusas</th><th>Data</th><th>Adresas</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($allOrders as $order): ?>
        <tr>
          <td><?php echo (int)$order['id']; ?></td>
          <td>
            <?php echo htmlspecialchars($order['customer_name']); ?><br>
            <span class="muted"><?php echo htmlspecialchars($order['customer_email']); ?></span>
            <?php if (!empty($order['customer_phone'])): ?><br><span class="muted">Tel.: <?php echo htmlspecialchars($order['customer_phone']); ?></span><?php endif; ?>
          </td>
          <td><?php echo number_format((float)$order['total'], 2); ?> €</td>
          <td>
            <form method="post" style="display:flex; gap:6px; align-items:center;">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="order_status">
              <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
              <select name="status" style="margin:0;">
                <?php foreach (["laukiama","apdorojama","išsiųsta","įvykdyta","atšaukta"] as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn" type="submit" style="background:#fff; color:#0b0b0b;">Atnaujinti</button>
            </form>
          </td>
          <td><?php echo htmlspecialchars($order['created_at']); ?></td>
          <td style="max-width:200px;"> <?php echo nl2br(htmlspecialchars($order['customer_address'])); ?> </td>
          <td>
            <?php $orderItemsStmt->execute([$order['id']]); $items = $orderItemsStmt->fetchAll(); ?>
            <details>
              <summary style="cursor:pointer; font-weight:600;">Peržiūrėti</summary>
              <div class="items" style="margin-top:8px;">
                <?php foreach ($items as $item): ?>
                  <div style="display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px dashed #eaeaea;">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:8px;">
                    <div style="flex:1;">
                      <div style="font-weight:600;"><?php echo htmlspecialchars($item['title']); ?></div>
                      <div class="muted">Kiekis: <?php echo (int)$item['quantity']; ?> × <?php echo number_format((float)$item['price'], 2); ?> €</div>
                    </div>
                    <div style="font-weight:700;"><?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?> €</div>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$allOrders): ?>
        <tr><td colspan="7" class="muted">Užsakymų nėra.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Aktyvios kategorijų nuolaidos</h3>
  <table>
    <thead><tr><th>Kategorija</th><th>Tipas</th><th>Reikšmė</th><th>Nemokamas pristatymas</th><th>Aktyvi</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($categoryDiscounts as $catId => $disc): ?>
        <tr>
          <td><?php echo htmlspecialchars($allCategoriesSimple[array_search($catId, array_column($allCategoriesSimple, 'id'))]['name'] ?? ('ID ' . $catId)); ?></td>
          <?php
            $typeLabel = 'Išjungta';
            if ($disc['type'] === 'percent') { $typeLabel = 'Procentai'; }
            elseif ($disc['type'] === 'amount') { $typeLabel = 'Suma'; }
            elseif ($disc['type'] === 'free_shipping') { $typeLabel = 'Nemokamas pristatymas'; }
          ?>
          <td><?php echo htmlspecialchars($typeLabel); ?></td>
          <td><?php echo $disc['type'] === 'free_shipping' ? '–' : number_format((float)$disc['value'], 2) . ($disc['type'] === 'percent' ? ' %' : ' €'); ?></td>
          <td><?php echo (!empty($disc['free_shipping']) || $disc['type'] === 'free_shipping') ? 'Taip' : 'Ne'; ?></td>
          <td><?php echo (int)$disc['active'] ? 'Taip' : 'Ne'; ?></td>
          <td>
            <form method="post" style="display:inline-block;">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="delete_category_discount">
              <input type="hidden" name="category_id" value="<?php echo (int)$catId; ?>">
              <button class="btn" type="submit" style="background:#f1f1f5; color:#0b0b0b; border-color:#e0e0ea; padding:8px 10px;">Šalinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$categoryDiscounts): ?>
        <tr><td colspan="6" class="muted">Kategorijų nuolaidų nėra.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
