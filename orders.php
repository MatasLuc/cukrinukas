<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureOrdersTables($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);

$userId = (int) $_SESSION['user_id'];

$orderStmt = $pdo->prepare('SELECT id, total, status, created_at, customer_name, customer_address FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll();

$itemStmt = $pdo->prepare('SELECT oi.*, p.title, p.image_url FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mano užsakymai | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #52606d;
      --accent: #7c3aed;
      --accent-2: #22c55e;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    .page { max-width: 1100px; margin:0 auto; padding:32px 20px 60px; display:flex; flex-direction:column; gap:18px; }

    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border:1px solid #e5e7eb; border-radius:28px; padding:24px 22px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center; gap:18px; flex-wrap:wrap; }
    .hero h1 { margin:0; font-size: clamp(26px, 5vw, 34px); letter-spacing:-0.02em; color:#0b1224; }
    .hero p { margin:4px 0 0; color: var(--muted); max-width:560px; line-height:1.6; }
    .hero .pill { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:999px; background:#fff; border:1px solid #e4e7ec; font-weight:700; color:#0b1224; box-shadow:0 12px 26px rgba(0,0,0,0.08); }

    .list { display:grid; gap:16px; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:18px; box-shadow:0 14px 32px rgba(0,0,0,0.06);
      display:flex; flex-direction:column; gap:10px; }
    .status { padding:6px 12px; border-radius:999px; background:#f5f3ff; color:#4c1d95; font-weight:700; border:1px solid #e4e7ec; display:inline-flex; align-items:center; gap:6px; }
    .items { display:grid; gap:10px; margin-top:8px; }
    .item { display:flex; gap:12px; align-items:center; padding:10px; border-radius:14px; background:#f9fafb; border:1px solid #edf0f5; }
    .item img { width:64px; height:64px; object-fit:cover; border-radius:12px; border:1px solid #e4e7ec; }
    .muted { color: var(--muted); }
    .total { display:flex; justify-content:space-between; align-items:center; font-weight:800; font-size:17px; color:#0b1224; }
    .pay-btn { padding:12px 16px; border-radius:12px; border:1px solid transparent; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 18px 44px rgba(124,58,237,0.3); transition: transform .18s ease, box-shadow .18s ease; }
    .pay-btn:hover { transform: translateY(-1px); box-shadow:0 22px 60px rgba(67,56,202,0.35); }
    .pay-btn.outline { background:transparent; color:#4338ca; border-color:#c7d2fe; box-shadow:none; }
    .section-title { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .section-title small { color: var(--muted); }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'orders'); ?>
  <div class="page">
    <section class="hero">
      <div>
        <div class="pill">📦 Užsakymų suvestinė</div>
        <h1>Mano užsakymai</h1>
        <p>Sekite pristatymo eigą, apmokėkite laukiančius užsakymus ir peržiūrėkite prekių detales vienoje vietoje.</p>
      </div>
      <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end; min-width:200px; text-align:right;">
        <div style="font-weight:800; font-size:28px; color:#0b1224;">#<?php echo (int)($orders[0]['id'] ?? 0); ?></div>
        <small style="color: var(--muted);">Naujausias užsakymas</small>
        <a class="pay-btn outline" href="/products.php">Tęsti apsipirkimą</a>
      </div>
    </section>

    <div class="section-title">
      <h2 style="margin:0; font-size:22px;">Užsakymų istorija</h2>
      <small><?php echo count($orders); ?> užsakymas(-ai)</small>
    </div>

    <?php if (!$orders): ?>
      <div class="card">
        <div style="font-weight:700; font-size:18px; margin-bottom:6px;">Kol kas neturite užsakymų</div>
        <p class="muted" style="margin:0 0 12px;">Vos keli paspaudimai iki pirmojo pirkimo.</p>
        <a class="pay-btn outline" href="/products.php">Apsipirkti dabar</a>
      </div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($orders as $order): ?>
          <?php $itemStmt->execute([$order['id']]); $orderItems = $itemStmt->fetchAll(); ?>
          <div class="card">
            <div class="section-title">
              <div>
                <div style="font-size:15px; color: var(--muted);">Užsakymas</div>
                <div style="font-weight:800; font-size:20px;">#<?php echo (int)$order['id']; ?></div>
                <div class="muted" style="margin-top:4px;">Sukurta: <?php echo htmlspecialchars($order['created_at']); ?></div>
              </div>
              <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                <span class="status">Statusas: <?php echo htmlspecialchars($order['status']); ?></span>
                <span class="muted" style="font-size:14px;">Pristatymas: <?php echo htmlspecialchars($order['customer_name']); ?>, <?php echo htmlspecialchars($order['customer_address']); ?></span>
              </div>
            </div>

            <div class="items">
              <?php foreach ($orderItems as $item): ?>
                <div class="item">
                  <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                  <div style="flex:1;">
                    <div style="font-weight:700; font-size:16px; color:#0b1224;"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="muted">Kiekis: <?php echo (int)$item['quantity']; ?> × <?php echo number_format((float)$item['price'], 2); ?> €</div>
                  </div>
                  <div style="font-weight:800; font-size:16px;"><?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?> €</div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="total">
              <span>Iš viso</span>
              <span><?php echo number_format((float)$order['total'], 2); ?> €</span>
            </div>

            <?php if ($order['status'] === 'laukiama apmokėjimo'): ?>
              <div style="display:flex; justify-content:flex-end;">
                <a class="pay-btn" href="/libwebtopay/redirect.php?order_id=<?php echo (int)$order['id']; ?>">Apmokėti užsakymą</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
