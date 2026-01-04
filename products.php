<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCategoriesTable($pdo);
ensureProductsTable($pdo);
ensureCartTables($pdo);
ensureSavedContentTables($pdo);
ensureAdminAccount($pdo);
$globalDiscount = getGlobalDiscount($pdo);
$categoryDiscounts = getCategoryDiscounts($pdo);
$freeShippingIds = getFreeShippingProductIds($pdo);

$selectedSlug = $_GET['category'] ?? null;
$searchQuery = $_GET['query'] ?? null;
$categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();

$params = [];
$whereClauses = [];

if ($selectedSlug) {
    $whereClauses[] = 'c.slug = ?';
    $params[] = $selectedSlug;
}

if ($searchQuery) {
    $whereClauses[] = 'p.title LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}

$where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
        (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 ORDER BY id DESC LIMIT 1) AS primary_image
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ' . $where . '
     ORDER BY p.created_at DESC'
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$cartData = getCartData($pdo, $_SESSION['cart'] ?? [], $_SESSION['cart_variations'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    validateCsrfToken();
    $pid = (int) $_POST['product_id'];
    if (($_POST['action'] ?? '') === 'wishlist') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }
        saveItemForUser($pdo, (int)$_SESSION['user_id'], 'product', $pid);
        header('Location: /saved.php');
        exit;
    }

    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
    if (!empty($_SESSION['user_id'])) {
        saveCartItem($pdo, (int)$_SESSION['user_id'], $pid, $qty);
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
  <title>Parduotuvė | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #1f2937;
      --muted: #52606d;
      --accent: #7c3aed;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    a { color:inherit; text-decoration:none; }

    .page { max-width:1200px; margin:0 auto; padding:32px 20px 56px; }

    .hero {
      margin-top: 12px;
      padding: 24px 22px;
      border-radius: 28px;
      background: linear-gradient(135deg, #eef2ff, #e0f2fe);
      border: 1px solid #e5e7eb;
      box-shadow: 0 18px 48px rgba(0,0,0,0.08);
      display: grid;
      grid-template-columns: 1.3fr 0.7fr;
      align-items: center;
      gap: 24px;
    }
    .hero h1 { margin: 0 0 10px; font-size: clamp(26px, 5vw, 36px); letter-spacing: -0.02em; color: #0f172a; }
    .hero p { margin: 0; color: var(--muted); line-height: 1.6; }
    
    .hero-cta { margin-top: 14px; display:flex; gap:10px; flex-wrap:wrap; }
    
    /* Atnaujinti mygtukai - baltas fonas, vienodos spalvos */
    .btn-large {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 24px;
      border-radius: 12px;
      border: 1px solid #4338ca; /* Indigo rėmelis */
      background: #ffffff; /* Baltas fonas */
      color: #4338ca; /* Indigo tekstas */
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: all .2s ease;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    
    /* Hover efektas - išlieka baltas, tik patamsėja elementai */
    .btn-large:hover {
      background: #ffffff; 
      color: #312e81; /* Tamsesnė indigo */
      border-color: #312e81;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(67, 56, 202, 0.12);
    }

    /* .ghost klasė dabar neturi skirtingų spalvų, kad abu mygtukai būtų vienodi */
    .ghost {
      /* Galima palikti tuščią arba naudoti papildomiems nustatymams ateityje */
    }

    .filter-bar {
      display:flex;
      justify-content: space-between;
      align-items:center;
      gap:16px;
      margin: 28px 0 18px;
      flex-wrap: wrap;
    }
    .filter-title { font-size: 18px; letter-spacing: 0.01em; color: #111827; }
    .chips { display:flex; flex-wrap:wrap; gap:12px; }
    .chip {
      padding:10px 14px;
      border-radius:12px;
      background: #fff;
      border:1px solid var(--border);
      font-weight:600;
      color: var(--muted);
      transition: all .18s ease;
    }
    .chip:hover, .chip:focus-visible { border-color: rgba(124, 58, 237, 0.45); color: #111827; box-shadow: 0 10px 26px rgba(0,0,0,0.08); }
    .search-input {
      flex-grow: 1;
      padding: 10px 14px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #fff;
      font-size: 15px;
      min-width: 200px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.04);
      transition: all .18s ease;
    }
    .search-input:focus {
      border-color: rgba(124, 58, 237, 0.45);
      outline: none;
    }

    .grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:20px; justify-items:stretch; }
    @media (max-width: 1080px) { .grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
    @media (max-width: 860px) { .grid { grid-template-columns: repeat(2, minmax(0,1fr)); } .hero { grid-template-columns: 1fr; } }
    @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }

    .card {
      position:relative;
      background: var(--card);
      border:1px solid var(--border);
      border-radius:20px;
      overflow:hidden;
      display:flex;
      flex-direction:column;
      min-height: 440px;
      box-shadow: 0 14px 32px rgba(0,0,0,0.08);
      transition: transform .18s ease, box-shadow .2s ease, border-color .18s ease;
    }
    .card:hover { transform: translateY(-4px); border-color: rgba(124, 58, 237, 0.35); box-shadow: 0 22px 48px rgba(0,0,0,0.12); }
    
    .card-image-wrapper {
        position: relative;
    }
    .card img { width:100%; height:210px; object-fit:cover; transition: transform .18s ease; display: block; }
    .card:hover img { transform: scale(1.03); }
    
    .ribbon {
      position:absolute;
      top:12px;
      left:12px;
      background: linear-gradient(135deg, #4338ca, #7c3aed);
      color:#ffffff;
      padding:6px 12px;
      border-radius:8px;
      font-weight:700;
      font-size:12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 2;
    }
    
    .gift-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(255, 255, 255, 0.95);
        color: #4338ca;
        font-weight: 700;
        font-size: 12px;
        padding: 6px 12px;
        border-radius: 99px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #eef2ff;
    }

    .card__body { padding:18px; display:flex; flex-direction:column; gap:10px; flex:1; }
    .badge { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:999px; background: #f9fafb; color:#0f172a; font-size:12px; border:1px solid #e5e7eb; width: fit-content; }
    
    .card h3 { margin:2px 0 0; font-size:20px; letter-spacing:-0.01em; color:#0f172a; }
    .card p { margin:0; color: var(--muted); line-height:1.5; }
    .price-row { display:flex; justify-content: space-between; align-items:center; gap:10px; margin-top:auto; flex-wrap:wrap; }
    .price { display:flex; flex-direction:column; gap:4px; }
    .old { font-size:13px; color:#9ca3af; text-decoration:line-through; }
    .current { font-size:22px; font-weight:800; letter-spacing:-0.02em; color:#111827; }

    .form-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .qty { width:72px; padding:10px; border-radius:12px; border:1px solid var(--border); background: #f9fafb; color: #111827; }
    .btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid transparent;
      background: linear-gradient(135deg, #4338ca, #7c3aed);
      color:#ffffff;
      font-weight:700;
      cursor:pointer;
      box-shadow: 0 14px 36px rgba(124,58,237,0.18);
      transition: transform .16s ease, box-shadow .16s ease;
    }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 18px 46px rgba(124,58,237,0.26); }
    .heart-btn {
      width:40px;
      height:40px;
      border-radius:12px;
      border:1px solid var(--border);
      background: #f8fafc;
      color:#0f172a;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:17px;
      cursor:pointer;
      box-shadow: 0 10px 26px rgba(0,0,0,0.08);
      transition: border-color .18s ease, transform .16s ease;
    }
    .heart-btn:hover { border-color: rgba(124,58,237,0.5); transform: translateY(-2px); }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'products'); ?>
  <div class="page">

    <section class="hero">
      <div>
        <div class="hero__pill"><?php echo htmlspecialchars($siteContent['faq_hero_pill'] ?? '💡 Test'); ?></div>
        <h1>Parduotuvė</h1>
        <p>Čia galite rasti visus mūsų turimus produktus.</p>
        <div class="hero-cta">
          <a class="btn-large" href="#products">Visi produktai</a>
          <a class="btn-large ghost" href="/saved.php">Norų sąrašas</a>
        </div>
      </div>
    </section>

    <div class="filter-bar">
        <form method="get" class="search-form" style="display: flex; gap: 10px; align-items: center; flex-grow: 1;">
            <input type="text" name="query" placeholder="Ieškoti prekių pagal pavadinimą..." class="search-input" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
            <?php if ($selectedSlug): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedSlug); ?>">
            <?php endif; ?>
            <button type="submit" class="btn" style="padding: 10px 14px; border-radius: 12px; background: #0b0b0b; color: #fff; border-color: #0b0b0b;">Ieškoti</button>
            <?php if ($searchQuery || $selectedSlug): ?>
                <a href="/products.php" class="btn secondary" style="padding: 10px 14px; border-radius: 12px; background:#fff; color:#0b0b0b; border:1px solid var(--border);">Valyti filtrus</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="filter-bar" style="margin-top: -10px;">
      <div class="filter-title">Kategorijos</div>
      <div class="chips">
        <a class="chip" href="/products.php<?php echo $searchQuery ? '?query=' . urlencode($searchQuery) : ''; ?>">Visos</a>
        <?php foreach ($categories as $cat): ?>
          <a class="chip" href="/products.php?category=<?php echo urlencode($cat['slug']); ?><?php echo $searchQuery ? '&query=' . urlencode($searchQuery) : ''; ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid" id="products">
      <?php foreach ($products as $product): $priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts); $isGift = in_array((int)$product['id'], $freeShippingIds, true); ?>
        <article class="card">
          <?php $cardImage = $product['primary_image'] ?: $product['image_url']; ?>
          
          <div class="card-image-wrapper">
              <?php if (!empty($product['ribbon_text'])): ?>
                <div class="ribbon"><?php echo htmlspecialchars($product['ribbon_text']); ?></div>
              <?php endif; ?>

              <?php if ($isGift): ?>
                <div class="gift-badge">
                   <span>🎁</span> Nemokamas siuntimas
                </div>
              <?php endif; ?>

              <a href="/product.php?id=<?php echo (int)$product['id']; ?>" aria-label="<?php echo htmlspecialchars($product['title']); ?>">
                <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
              </a>
          </div>

          <div class="card__body">
            <span class="badge">
              <span style="width:6px; height:6px; border-radius:50%; background: var(--accent);"></span>
              <?php echo htmlspecialchars($product['category_name'] ?? ''); ?>
            </span>
            
            <h3><a href="/product.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['title']); ?></a></h3>
            <?php if (!empty($product['subtitle'])): ?><p style="color:#6b21a8;"><?php echo htmlspecialchars($product['subtitle']); ?></p><?php endif; ?>
            <p><?php echo htmlspecialchars(mb_substr($product['description'], 0, 120)); ?><?php echo mb_strlen($product['description']) > 120 ? '…' : ''; ?></p>
            <div class="price-row">
              <div class="price">
                <?php if ($priceDisplay['has_discount']): ?>
                  <div class="old"><?php echo number_format($priceDisplay['original'], 2); ?> €</div>
                <?php endif; ?>
                <strong class="current"><?php echo number_format($priceDisplay['current'], 2); ?> €</strong>
              </div>
              <form method="post" class="form-row">
                <?php echo csrfField(); ?>
<input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                <input class="qty" type="number" name="quantity" min="1" value="1">
                <button class="btn" type="submit">Į krepšelį</button>
                <button class="heart-btn" name="action" value="wishlist" type="submit" aria-label="Į norų sąrašą">♥</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
