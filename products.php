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

// Užtikriname, kad egzistuoja ryšių lentelė
$pdo->exec("CREATE TABLE IF NOT EXISTS product_category_relations (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id)
)");

$globalDiscount = getGlobalDiscount($pdo);
$categoryDiscounts = getCategoryDiscounts($pdo);
$freeShippingIds = getFreeShippingProductIds($pdo);

$selectedSlug = $_GET['category'] ?? null;
$searchQuery = $_GET['query'] ?? null;

// --- 1. KATEGORIJŲ MEDIS ---
$allCats = $pdo->query('SELECT id, name, slug, parent_id FROM categories ORDER BY name ASC')->fetchAll();

$catsByParent = [];
$catsById = [];

foreach ($allCats as $c) {
    $c['id'] = (int)$c['id'];
    $parentId = !empty($c['parent_id']) ? (int)$c['parent_id'] : 0;
    
    $catsById[$c['id']] = $c;
    $catsByParent[$parentId][] = $c;
}

$rootCats = $catsByParent[0] ?? [];

// --- 2. FILTRAVIMO LOGIKA ---
$params = [];
$whereClauses = [];

if ($selectedSlug) {
    $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmtCat->execute([$selectedSlug]);
    $catId = (int)$stmtCat->fetchColumn();

    if ($catId) {
        $targetIds = [$catId];
        if (isset($catsByParent[$catId])) {
            foreach ($catsByParent[$catId] as $child) {
                $targetIds[] = $child['id'];
            }
        }
        
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        
        $whereClauses[] = "(
            p.category_id IN ($placeholders) 
            OR 
            p.id IN (SELECT product_id FROM product_category_relations WHERE category_id IN ($placeholders))
        )";
        
        foreach ($targetIds as $tid) $params[] = $tid;
        foreach ($targetIds as $tid) $params[] = $tid;
    } else {
        $whereClauses[] = '1=0';
    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    validateCsrfToken();
    $pid = (int) $_POST['product_id'];
    if (($_POST['action'] ?? '') === 'wishlist') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php'); exit;
        }
        saveItemForUser($pdo, (int)$_SESSION['user_id'], 'product', $pid);
        header('Location: /saved.php'); exit;
    }
    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
    if (!empty($_SESSION['user_id'])) saveCartItem($pdo, (int)$_SESSION['user_id'], $pid, $qty);
    header('Location: /cart.php'); exit;
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
    :root { --bg: #f7f7fb; --card: #ffffff; --border: #e4e7ec; --text: #1f2937; --muted: #52606d; --accent: #7c3aed; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
    a { color:inherit; text-decoration:none; }

    .page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display: grid; gap: 28px; }

    .hero {
      padding: 26px; border-radius: 28px; background: linear-gradient(135deg, #eef2ff, #e0f2fe);
      border: 1px solid #e5e7eb; box-shadow: 0 18px 48px rgba(0,0,0,0.08);
      display: grid; grid-template-columns: 1.4fr 0.6fr; align-items: center; gap: 22px;
    }
    .hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; padding:10px 14px; border-radius:999px; font-weight:700; font-size: 15px; color: #0f172a; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
    .hero h1 { margin: 10px 0 8px; font-size: clamp(26px, 5vw, 36px); color: #0f172a; }
    .hero p { margin: 0; color: var(--muted); line-height: 1.6; }
    .hero-cta { margin-top: 14px; display:flex; gap:10px; }
    .btn-large { padding: 11px 24px; border-radius: 12px; border: 1px solid #4338ca; background: #fff; color: #4338ca; font-weight: 600; transition: all .2s; }
    .btn-large:hover { background: #4338ca; color: #fff; }

    /* FILTRAI */
    .filter-bar { display:flex; justify-content: space-between; align-items:center; gap:16px; flex-wrap: wrap; }
    .filter-title { font-size: 18px; font-weight: 600; color: #111827; }
    
    .search-form { display: flex; gap: 10px; align-items: center; flex-grow: 1; }
    .search-input { flex-grow: 1; padding: 10px 14px; border-radius: 12px; border: 1px solid var(--border); min-width: 200px; font-size: 15px; background: #fff; }
    .search-input:focus { border-color: var(--accent); outline: none; }

    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 12px; background: #0b0b0b; color: #fff; border: 1px solid #0b0b0b; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
    .btn:hover { opacity: 0.9; }
    .btn.secondary { background: #fff; color: #0b0b0b; border-color: var(--border); }

    /* DROPDOWN IR KATEGORIJOS */
    .chips { display:flex; flex-wrap:wrap; gap:12px; align-items: flex-start; }
    .chip-container { position: relative; display: inline-block; padding-bottom: 20px; margin-bottom: -20px; }
    
    .chip {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 99px;
      background: #fff; border: 1px solid var(--border);
      font-weight: 600; color: var(--muted); cursor: pointer; transition: all .2s;
      white-space: nowrap; user-select: none; position: relative; z-index: 20;
    }
    .chip:hover, .chip.active {
      border-color: var(--accent); color: var(--accent); background: #fdfaff;
      box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
    }
    .chip-arrow { font-size: 10px; opacity: 0.6; margin-left: 2px; }

    .dropdown-menu {
        display: none; position: absolute; top: calc(100% - 5px); left: 0;
        background: #fff; min-width: 220px; padding: 8px 0;
        border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        border: 1px solid var(--border); z-index: 100;
    }
    .dropdown-menu::before { content: ""; position: absolute; top: -20px; left: 0; width: 100%; height: 20px; background: transparent; }
    .chip-container:hover .dropdown-menu { display: block; animation: slideDown 0.15s ease; }
    
    .dropdown-item {
        display: block; padding: 10px 16px; color: var(--text);
        text-decoration: none; font-size: 14px; transition: background .1s;
    }
    .dropdown-item:hover { background: #f3f4f6; color: var(--accent); }
    .dropdown-item.active { font-weight: bold; color: var(--accent); background: #f9fafb; }
    @keyframes slideDown { from { opacity:0; transform:translateY(-5px); } to { opacity:1; transform:translateY(0); } }

    /* KORTELĖS */
    .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:24px; }
    .card { background: var(--card); border:1px solid var(--border); border-radius:20px; overflow:hidden; display:flex; flex-direction:column; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: transform .2s; }
    .card:hover { transform: translateY(-4px); border-color: var(--accent); }
    .card img { width:100%; height:220px; object-fit:cover; display:block; }
    .card__body { padding:18px; display:flex; flex-direction:column; gap:10px; flex:1; }
    
    .ribbon { position:absolute; top:12px; left:12px; background: var(--accent); color:#fff; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; }
    
    .price-row { 
        display:flex; 
        justify-content: space-between; 
        align-items:center; 
        margin-top:auto; 
        gap: 8px; 
    }
    .price { font-size:20px; font-weight:700; color:#111827; flex-grow: 1; }
    
    /* AKCIJOS MYGTUKAI */
    .action-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .2s;
    }

    /* KREPŠELIO IKONA - BALTAS FONAS + MĖLYNA IKONA */
    .btn-cart-icon {
        background: #ffffff;
        color: #829ed6;
        border: 1px solid #e4e7ec;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .btn-cart-icon:hover {
        border-color: #829ed6;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(130, 158, 214, 0.3);
    }

    .btn-wishlist {
        background: #fff;
        border: 1px solid var(--border);
        color: #1f2937;
        font-size: 20px;
    }
    .btn-wishlist:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }
    
    @media (max-width: 800px) { .hero { grid-template-columns: 1fr; } .filter-bar { flex-direction: column; align-items: stretch; } }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'products'); ?>
  <div class="page">
    <section class="hero">
      <div>
        <div class="hero__pill">🛍️ Mūsų produktai</div>
        <h1>Parduotuvė</h1>
        <p>Atraskite mūsų geriausius pasiūlymus.</p>
        <div class="hero-cta">
          <a class="btn-large" href="/products.php">Visos prekės</a>
          <a class="btn-large" href="/saved.php">Norų sąrašas</a>
        </div>
      </div>
    </section>

    <div class="filter-bar">
        <form method="get" class="search-form">
            <input type="text" name="query" placeholder="Ieškoti prekių..." class="search-input" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
            <?php if ($selectedSlug): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedSlug); ?>">
            <?php endif; ?>
            <button type="submit" class="btn">Ieškoti</button>
            <?php if ($searchQuery || $selectedSlug): ?>
                <a href="/products.php" class="btn secondary">Valyti</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="filter-bar">
      <div class="filter-title">Kategorijos</div>
      <div class="chips">
        <a class="chip <?php echo !$selectedSlug ? 'active' : ''; ?>" href="/products.php<?php echo $searchQuery ? '?query=' . urlencode($searchQuery) : ''; ?>">Visos</a>
        
        <?php foreach ($rootCats as $root): ?>
          <?php 
              $subCats = $catsByParent[$root['id']] ?? [];
              $isActive = ($selectedSlug === $root['slug']);
              $childActive = false;
              foreach ($subCats as $child) {
                  if ($selectedSlug === $child['slug']) {
                      $childActive = true; 
                      break;
                  }
              }
              $chipClass = ($isActive || $childActive) ? 'active' : '';
              $queryPart = $searchQuery ? '&query=' . urlencode($searchQuery) : '';
          ?>
          <div class="chip-container">
              <a class="chip <?php echo $chipClass; ?>" href="/products.php?category=<?php echo urlencode($root['slug']) . $queryPart; ?>">
                  <?php echo htmlspecialchars($root['name']); ?>
                  <?php if ($subCats): ?><span class="chip-arrow">▼</span><?php endif; ?>
              </a>
              <?php if ($subCats): ?>
                <div class="dropdown-menu">
                    <?php foreach ($subCats as $sub): ?>
                        <a class="dropdown-item <?php echo ($selectedSlug === $sub['slug']) ? 'active' : ''; ?>" 
                           href="/products.php?category=<?php echo urlencode($sub['slug']) . $queryPart; ?>">
                            <?php echo htmlspecialchars($sub['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
              <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid">
      <?php if (empty($products)): ?>
        <div style="grid-column: 1 / -1; text-align:center; padding: 40px; background:#fff; border-radius:16px; border:1px solid #eee;">
            <h3>Prekių nerasta :(</h3>
            <p style="color:#666;">Pabandykite pakeisti filtrus.</p>
        </div>
      <?php endif; ?>

      <?php foreach ($products as $product): 
          $priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts);
          $isGift = in_array((int)$product['id'], $freeShippingIds, true);
          $cardImage = $product['primary_image'] ?: $product['image_url'];
      ?>
        <article class="card">
          <div style="position:relative;">
              <?php if (!empty($product['ribbon_text'])): ?>
                <div class="ribbon"><?php echo htmlspecialchars($product['ribbon_text']); ?></div>
              <?php endif; ?>
              <?php if ($isGift): ?>
                <div style="position:absolute; top:12px; right:12px; background:#fff; color:#4338ca; padding:4px 8px; border-radius:20px; font-size:12px; font-weight:bold; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:1px solid #eef2ff;">🎁 Nemokamai</div>
              <?php endif; ?>
              <a href="/product.php?id=<?php echo (int)$product['id']; ?>">
                <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
              </a>
          </div>

          <div class="card__body">
            <div style="font-size:11px; color:var(--accent); font-weight:700; text-transform:uppercase;">
                <?php echo htmlspecialchars($product['category_name'] ?? ''); ?>
            </div>
            <h3 style="margin:0; font-size:18px; line-height:1.3;"><a href="/product.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['title']); ?></a></h3>
            <p style="margin:0; color:var(--muted); font-size:14px; line-height:1.5;">
                <?php echo htmlspecialchars(mb_substr(strip_tags($product['description']), 0, 80)); ?>...
            </p>
            
            <div class="price-row">
              <div class="price">
                <?php if ($priceDisplay['has_discount']): ?>
                  <span style="font-size:14px; text-decoration:line-through; color:#999; font-weight:normal; margin-right:4px; display:block;"><?php echo number_format($priceDisplay['original'], 2); ?> €</span>
                <?php endif; ?>
                <?php echo number_format($priceDisplay['current'], 2); ?> €
              </div>
              
              <form method="post" style="display:flex; gap:8px;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                
                <button class="action-btn btn-cart-icon" type="submit" aria-label="Į krepšelį">
                   <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </button>
                
                <button class="action-btn btn-wishlist" name="action" value="wishlist" type="submit" aria-label="Į norų sąrašą">
                   ♥
                </button>
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
