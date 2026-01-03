<?php
// admin/dashboard.php

// Užkrauname tik šiam puslapiui reikalingus duomenis
$latestOrders = $pdo->query('SELECT id, customer_name, total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 5')->fetchAll();
$categoryCounts = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();

// Pastaba: $totalSalesHero, $ordersCountHero, $userCountHero, $averageOrderHero kintamieji 
// ateina iš admin/hero_stats.php, kuris yra įtrauktas pagrindiniame admin.php.
?>

<div class="section-stack">
  <div class="grid" style="margin-top:4px;">
    <div class="card">
        <h3>VISO PARDAVIMŲ</h3>
        <p style="font-size:32px; font-weight:700;"><?php echo number_format($totalSalesHero, 2); ?> €</p>
    </div>
    <div class="card">
        <h3>VISO UŽSAKYMŲ</h3>
        <p style="font-size:32px; font-weight:700;"><?php echo (int)$ordersCountHero; ?></p>
    </div>
    <div class="card">
        <h3>VIDUTINĖ UŽSAKYMO VERTĖ</h3>
        <p style="font-size:32px; font-weight:700;"><?php echo number_format($averageOrderHero, 2); ?> €</p>
    </div>
    <div class="card">
        <h3>Vartotojai</h3>
        <p style="font-size:32px; font-weight:700;"><?php echo (int)$userCountHero; ?></p>
    </div>
  </div>

  <div class="grid" style="grid-template-columns:2fr 1fr; gap:16px;">
    <div class="card">
      <h3>Naujausi užsakymai</h3>
      <table>
        <thead><tr><th>#</th><th>Vardas</th><th>Suma</th><th>Statusas</th><th>Data</th></tr></thead>
        <tbody>
          <?php foreach ($latestOrders as $o): ?>
            <tr>
              <td><?php echo (int)$o['id']; ?></td>
              <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
              <td><?php echo number_format((float)$o['total'], 2); ?> €</td>
              <td><?php echo htmlspecialchars($o['status']); ?></td>
              <td><?php echo htmlspecialchars($o['created_at']); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$latestOrders): ?>
            <tr><td colspan="5" class="muted">Užsakymų dar nėra.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <h3>Produktai pagal kategoriją</h3>
      <table>
        <thead><tr><th>Kategorija</th><th>Prekių skaičius</th></tr></thead>
        <tbody>
          <?php foreach ($categoryCounts as $cat): ?>
            <tr><td><?php echo htmlspecialchars($cat['name']); ?></td><td><?php echo (int)$cat['product_count']; ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
