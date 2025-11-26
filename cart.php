<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureProductsTable($pdo);
ensureCartTables($pdo);
ensureAdminAccount($pdo);

$cartData = getCartData($pdo, $_SESSION['cart'] ?? [], $_SESSION['cart_variations'] ?? []);
$items = $cartData['items'];
$total = $cartData['total'];
$freeShippingIds = $cartData['free_shipping_ids'] ?? [];
$freeShippingOffers = getFreeShippingProducts($pdo);
$hasGiftProduct = false;
foreach ($items as $it) {
    if (!empty($it['free_shipping_gift'])) {
        $hasGiftProduct = true;
        break;
    }
}

if (isset($_POST['remove_id'])) {
    validateCsrfToken();
    $removeId = (int) $_POST['remove_id'];
    unset($_SESSION['cart'][$removeId]);
    unset($_SESSION['cart_variations'][$removeId]);
    if (!empty($_SESSION['user_id'])) {
        deleteCartItem($pdo, (int)$_SESSION['user_id'], $removeId);
    }
    header('Location: /cart.php');
    exit;
}

if (isset($_POST['add_promo_product'])) {
    validateCsrfToken();
    $pid = (int)$_POST['add_promo_product'];
    if ($pid > 0) {
        $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
        if (!empty($_SESSION['user_id'])) {
            saveCartItem($pdo, (int)$_SESSION['user_id'], $pid, $_SESSION['cart'][$pid]);
        }
    }
    header('Location: /cart.php');
    exit;
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Krepšelis | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #475467;
      --accent: #7c3aed;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    .page { max-width:1000px; margin:0 auto; padding:28px 22px 64px; display:grid; gap:16px; }
    .head-row { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
    .btn { padding:12px 16px; border-radius:12px; border:none; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 14px 36px rgba(124,58,237,0.2); }
    .btn.secondary { background:#fff; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none; }
    .card { background:var(--card); border-radius:18px; padding:16px; border:1px solid var(--border); box-shadow:0 12px 30px rgba(0,0,0,0.08); display:grid; gap:12px; }
    .item { display:grid; grid-template-columns: 120px 1fr auto; gap:14px; align-items:center; padding:10px 0; border-bottom:1px solid #eee; }
    .item:last-child { border-bottom:none; }
    .item img { width:120px; height:90px; object-fit:cover; border-radius:12px; }
    .muted { color: var(--muted); }
    .summary { background: #f8fafc; border-radius:14px; padding:12px 14px; border:1px solid var(--border); }
    .promo { background: linear-gradient(135deg, rgba(16,185,129,0.14), rgba(67,56,202,0.1)); border:1px solid rgba(67,56,202,0.18); border-radius:16px; padding:12px 14px; display:flex; flex-direction:column; gap:8px; }
    .promo-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:10px; }
    .promo-card { border:1px solid var(--border); border-radius:14px; padding:10px; display:grid; gap:8px; background:#fff; box-shadow:0 10px 20px rgba(0,0,0,0.06); }
    .promo-card img { width:100%; height:120px; object-fit:cover; border-radius:10px; }
    .promo-btn { padding:10px 12px; border-radius:10px; border:none; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'cart'); ?>
  <div class="page">
    <div class="head-row">
      <a href="/products.php" class="btn secondary">← Grįžti į parduotuvę</a>
      <a href="/checkout.php" class="btn" onclick="fbq('track', 'InitiateCheckout');">Apmokėti</a>
    </div>

    <?php if ($freeShippingOffers): ?>
      <div class="promo">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <strong><?php echo $hasGiftProduct ? 'Nemokamas pristatymas jau pritaikytas su jūsų pasirinkta dovanos preke.' : 'Pridėkite vieną iš šių prekių ir gaukite nemokamą pristatymą.'; ?></strong>
          <span class="summary" style="margin:0;">Iki 4 parinktų prekių</span>
        </div>
        <div class="promo-grid">
          <?php foreach ($freeShippingOffers as $offer): $offerPrice = $offer['sale_price'] !== null ? (float)$offer['sale_price'] : (float)$offer['price']; ?>
            <div class="promo-card">
              <img src="<?php echo htmlspecialchars($offer['image_url']); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>">
              <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <strong><?php echo htmlspecialchars($offer['title']); ?></strong>
                <span><?php echo number_format($offerPrice, 2); ?> €</span>
              </div>
              <form method="post" style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="add_promo_product" value="<?php echo (int)$offer['product_id']; ?>">
                <button class="promo-btn" type="submit">Į krepšelį</button>
                <a class="muted" style="text-decoration:underline;" href="/product.php?id=<?php echo (int)$offer['product_id']; ?>">Plačiau</a>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0;">Jūsų krepšelis</h2>
        <div class="summary">Iš viso prekių: <?php echo count($items); ?></div>
      </div>
      <?php if (!$items): ?>
        <p class="muted">Krepšelis tuščias.</p>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <div class="item">
            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
            <div>
              <strong><?php echo htmlspecialchars($item['title']); ?></strong>
              <?php if (!empty($item['variation']['name'])): ?>
                <div class="muted">Variacija: <?php echo htmlspecialchars($item['variation']['name']); ?></div>
              <?php endif; ?>
              <?php if (!empty($item['free_shipping_gift'])): ?>
                <div style="color:#166534; font-weight:600;">Nemokamas pristatymas su šia preke</div>
              <?php endif; ?>
              <p class="muted">Kiekis: <?php echo $item['quantity']; ?> | Kaina: <?php echo number_format((float)$item['price'], 2); ?> €</p>
            </div>
            <div style="text-align:right;">
              <div style="font-weight:700; font-size:16px;"><?php echo number_format($item['line_total'], 2); ?> €</div>
              <form method="post" style="margin-top:8px;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="remove_id" value="<?php echo (int)$item['id']; ?>">
                <button class="btn secondary" type="submit">Pašalinti</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <div style="display:flex; justify-content:flex-end; gap:12px; align-items:center; padding-top:12px;">
          <strong style="font-size:18px;">Iš viso: <?php echo number_format($total, 2); ?> €</strong>
          <a class="btn" href="/checkout.php" onclick="fbq('track', 'InitiateCheckout');">Tęsti</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
