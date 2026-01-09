<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

$pdo = getPdo();
ensureProductsTable($pdo);
ensureCategoriesTable($pdo);
ensureCartTables($pdo);
ensureSavedContentTables($pdo);
tryAutoLogin($pdo);

if (function_exists('ensureProductRelations')) {
    ensureProductRelations($pdo);
}
ensureAdminAccount($pdo);
$freeShippingIds = getFreeShippingProductIds($pdo);

$id = (int) ($_GET['id'] ?? 0);

// Pagrindinė produkto užklausa
$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? LIMIT 1');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo 'Prekė nerasta';
    exit;
}

// Nuotraukos
$imagesStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC');
$imagesStmt->execute([$id]);
$images = $imagesStmt->fetchAll();

// Atributai
$attributesStmt = $pdo->prepare('SELECT label, value FROM product_attributes WHERE product_id = ?');
$attributesStmt->execute([$id]);
$attributes = $attributesStmt->fetchAll();

// Variacijos
$variationsStmt = $pdo->prepare('SELECT id, name, price_delta, group_name, quantity FROM product_variations WHERE product_id = ? ORDER BY group_name ASC, id ASC');
$variationsStmt->execute([$id]);
$variations = $variationsStmt->fetchAll();

// Variacijų grupavimas
$groupedVariations = [];
$variationMap = [];
foreach ($variations as $var) {
    $group = $var['group_name'] ?: 'Pasirinkimas'; // Jei nėra grupės vardo
    $groupedVariations[$group][] = $var;
    $variationMap[(int)$var['id']] = $var;
}

// Susijusios prekės
$relStmt = $pdo->prepare('SELECT pr.related_product_id, p.title, p.image_url, p.sale_price, p.price, p.subtitle FROM product_related pr JOIN products p ON p.id = pr.related_product_id WHERE pr.product_id = ? LIMIT 4');
$relStmt->execute([$id]);
$related = $relStmt->fetchAll();

$isFreeShippingGift = in_array($id, $freeShippingIds, true);

// POST logika (Krepšelis / Norų sąrašas)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    if (($_POST['action'] ?? '') === 'wishlist') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }
        saveItemForUser($pdo, (int)$_SESSION['user_id'], 'product', $id);
        header('Location: /saved.php');
        exit;
    }

    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    
    // Krepšelio logika
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;

    $selectedVarId = (int) ($_POST['variation_id'] ?? 0);
    if ($selectedVarId && isset($variationMap[$selectedVarId])) {
        $sel = $variationMap[$selectedVarId];
        $_SESSION['cart_variations'][$id] = [
            'id' => $selectedVarId,
            'name' => ($sel['group_name'] ? $sel['group_name'] . ': ' : '') . $sel['name'],
            'delta' => (float)$sel['price_delta'],
        ];
    } else {
        // Jei variacija nepasirinkta, bet jos egzistuoja, galima įdėti validaciją. 
        // Šiuo atveju tiesiog išvalome seną variaciją, jei buvo.
        unset($_SESSION['cart_variations'][$id]);
    }

    if (!empty($_SESSION['user_id'])) {
        saveCartItem($pdo, (int)$_SESSION['user_id'], $id, $qty);
    }
    header('Location: /cart.php');
    exit;
}

// Nuolaidų skaičiavimas
$categoryDiscounts = getCategoryDiscounts($pdo);
$globalDiscount = getGlobalDiscount($pdo);
$productCategoryDiscount = null;
if (!empty($product['category_id'])) {
    $productCategoryDiscount = $categoryDiscounts[(int)$product['category_id']] ?? null;
}
$priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts);

// SEO
$meta = [
    'title' => $product['title'] . ' | Cukrinukas',
    'description' => mb_substr(strip_tags($product['description']), 0, 160),
    'image' => 'https://cukrinukas.lt' . $product['image_url']
];
$currentProductUrl = 'https://cukrinukas.lt/produktas/' . slugify($product['title']) . '-' . $id;
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #64748b;
      --accent: #829ed6;
      --accent-hover: #6a8bc9;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    
    .page { max-width:1150px; margin:0 auto; padding:20px 20px 60px; display:grid; gap:32px; }
    
    .breadcrumbs { display:flex; align-items:center; gap:8px; font-weight:500; font-size: 14px; color: var(--muted); flex-wrap: wrap; margin-bottom: -10px;}
    .breadcrumbs a:hover { color: var(--accent); }
    
    /* Layout */
    .shell { display:grid; grid-template-columns: 1.1fr 0.9fr; gap:32px; align-items:start; }
    
    /* Gallery */
    .gallery { display:grid; gap:16px; position: sticky; top: 20px; }
    .main-image { position:relative; border-radius:16px; overflow:hidden; background:#fff; cursor:zoom-in; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .main-image img { width:100%; display:block; height:auto; aspect-ratio: 1/1; object-fit:contain; }
    
    .ribbon { position:absolute; top:16px; left:16px; background: var(--accent); color:#fff; padding:6px 12px; border-radius:8px; font-weight:700; font-size: 13px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index: 2; }
    
    .thumbs { display:flex; gap:12px; flex-wrap:wrap; }
    .thumbs img { width:80px; height:80px; object-fit:contain; border-radius:10px; border:2px solid transparent; background:#fff; cursor:pointer; transition:all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .thumbs img:hover { transform:translateY(-2px); border-color: #d1d5db; }
    .thumbs img.active-thumb { border-color: var(--accent); }
    
    /* Product Info */
    .details { display:grid; gap:20px; }
    .product-header h1 { margin:0 0 8px 0; font-size:30px; letter-spacing:-0.02em; line-height: 1.2; }
    .subtitle { margin:0; color:var(--accent); font-weight: 500; font-size: 16px; }
    .category-tag { display: inline-block; font-size: 13px; font-weight: 600; color: var(--muted); background: #f1f5f9; padding: 4px 10px; border-radius: 6px; margin-bottom: 12px; }

    .price-block { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-top: 4px; }
    .old { color:#94a3b8; text-decoration:line-through; font-size: 18px; font-weight: 500; }
    .current { font-size:32px; font-weight:800; letter-spacing:-0.02em; color: var(--text); line-height: 1; }
    
    /* Controls */
    .controls-card { background: #fff; border: 1px solid var(--border); padding: 24px; border-radius: 20px; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05); }
    
    .var-group { margin-bottom: 20px; }
    .var-title { font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; letter-spacing: 0.03em; }
    .chips { display: flex; flex-wrap: wrap; gap: 10px; }
    
    .variation-chip { 
        border:1px solid var(--border); 
        padding:10px 16px; 
        border-radius:10px; 
        background:#fff; 
        color:var(--text); 
        font-weight:600; 
        cursor:pointer; 
        transition:all 0.15s ease;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        min-width: 60px;
        text-align: center;
        font-size: 14px;
    }
    .variation-chip:hover { border-color: var(--accent); background: #f8fafc; }
    .variation-chip.active { border-color:var(--accent); background:#eff6ff; color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
    .variation-chip small { font-size: 11px; font-weight: 400; opacity: 0.8; margin-top: 2px; }
    
    .add-to-cart-row { display:flex; gap:12px; margin-top: 24px; }
    .qty-input { width: 70px; text-align: center; border: 1px solid var(--border); border-radius: 12px; font-size: 18px; font-weight: 600; }
    .btn-main { flex: 1; background: var(--text); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; padding: 0 24px; }
    .btn-main:hover { background: #334155; }
    .btn-icon { width: 50px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: #fff; border-radius: 12px; cursor: pointer; color: var(--text); transition: all 0.2s; }
    .btn-icon:hover { border-color: var(--accent); color: var(--accent); }

    /* Content Sections */
    .content-section { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:24px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); }
    .content-section h3 { margin: 0 0 16px 0; font-size: 20px; }
    
    .description { color: var(--muted); line-height: 1.7; font-size: 15px; }
    .description img { max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0; }
    .description ul { padding-left: 20px; }
    
    .specs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
    .spec-item { background: #f8fafc; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); }
    .spec-label { font-weight: 700; font-size: 13px; color: var(--text); margin-bottom: 4px; }
    .spec-val { color: var(--muted); font-size: 14px; }
    .spec-val p { margin: 0; }

    /* Related */
    .related-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:20px; }
    .related-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:12px; box-shadow:0 2px 4px rgba(0,0,0,0.03); display:flex; flex-direction:column; gap:10px; transition: transform 0.2s; }
    .related-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.08); }
    .related-card img { width:100%; aspect-ratio: 1/1; object-fit:contain; border-radius:12px; background: #f8fafc; }
    .related-title { font-weight:600; font-size:15px; line-height: 1.4; color: var(--text); }
    .related-price { margin-top: auto; font-weight:700; font-size: 16px; }

    /* Lightbox */
    .lightbox { position:fixed; inset:0; background:rgba(0,0,0,0.9); display:flex; align-items:center; justify-content:center; padding:20px; z-index:1000; opacity:0; pointer-events:none; transition:opacity 0.2s ease; backdrop-filter: blur(5px); }
    .lightbox.show { opacity:1; pointer-events:all; }
    .lightbox img { max-width:90vw; max-height:90vh; border-radius:8px; box-shadow:0 20px 50px rgba(0,0,0,0.5); }

    /* Mobile Responsive */
    @media (max-width: 900px) {
        .shell { grid-template-columns: 1fr; gap: 24px; }
        .gallery { position: static; }
        .main-image img { max-height: 400px; }
        .page { padding: 16px; gap: 24px; }
        .product-header h1 { font-size: 24px; }
        .current { font-size: 28px; }
        .add-to-cart-row { position: sticky; bottom: 16px; z-index: 10; background: #fff; padding: 12px; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin: 0 -10px; width: calc(100% + 20px); }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'product', $meta); ?>
  
  <?php $mainImage = $images[0]['path'] ?? $product['image_url']; ?>
  
  <div class="page">
    <div class="breadcrumbs">
      <a href="/">Pagrindinis</a> <span>/</span>
      <a href="/products.php">Parduotuvė</a>
      <?php if (!empty($product['category_name'])): ?>
         <span>/</span> <a href="/products.php?category=<?php echo urlencode($product['category_slug'] ?? ''); ?>">
            <?php echo htmlspecialchars($product['category_name']); ?>
         </a>
      <?php endif; ?>
    </div>

    <div class="shell">
      <div class="gallery">
        <div class="main-image">
          <?php if (!empty($product['ribbon_text'])): ?><div class="ribbon"><?php echo htmlspecialchars($product['ribbon_text']); ?></div><?php endif; ?>
          <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" id="heroImage">
        </div>
        <?php if (count($images) > 0): ?>
          <div class="thumbs">
            <?php foreach ($images as $img): ?>
              <img src="<?php echo htmlspecialchars($img['path']); ?>" onclick="setMainImage(this)" class="<?php echo ($img['path'] === $mainImage) ? 'active-thumb' : ''; ?>">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        
        <div style="margin-top:10px; display:flex; gap:12px; font-size:13px; color:var(--muted); justify-content:center;">
             <span>🔄 14 dienų grąžinimas</span>
             <span>🛡️ 24 mėn. garantija</span>
        </div>
      </div>

      <div class="details">
        <div class="product-header">
            <?php if (!empty($product['category_name'])): ?>
                <a href="/products.php?category=<?php echo urlencode($product['category_slug']); ?>" class="category-tag"><?php echo htmlspecialchars($product['category_name']); ?></a>
            <?php endif; ?>
            
            <div style="display:flex; justify-content:space-between; align-items:start;">
                <h1><?php echo htmlspecialchars($product['title']); ?></h1>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                    <a href="/admin.php?view=products&edit=<?php echo (int)$product['id']; ?>" target="_blank" style="font-size:12px; background:#0f172a; color:#fff; padding:4px 8px; border-radius:6px;">Redaguoti</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($product['subtitle'])): ?><p class="subtitle"><?php echo htmlspecialchars($product['subtitle']); ?></p><?php endif; ?>
            
            <div class="price-block">
                <span id="price-old" class="old" style="display: <?php echo $priceDisplay['has_discount'] ? 'block' : 'none'; ?>;">
                    <?php echo number_format($priceDisplay['original'], 2); ?> €
                </span>
                <span id="price-current" class="current"><?php echo number_format($priceDisplay['current'], 2); ?> €</span>
            </div>
        </div>

        <form method="post" class="controls-card">
            <?php echo csrfField(); ?>
            <input type="hidden" name="variation_id" id="variation-id" value="0">
            
            <?php if ($groupedVariations): ?>
                <?php foreach ($groupedVariations as $groupName => $vars): ?>
                    <div class="var-group">
                        <div class="var-title"><?php echo htmlspecialchars($groupName); ?></div>
                        <div class="chips">
                            <?php foreach ($vars as $var): ?>
                                <div class="variation-chip" 
                                     data-id="<?php echo (int)$var['id']; ?>" 
                                     data-delta="<?php echo (float)$var['price_delta']; ?>"
                                     onclick="selectVariation(this)">
                                    <span><?php echo htmlspecialchars($var['name']); ?></span>
                                    <?php if($var['price_delta'] != 0): ?>
                                        <small><?php echo $var['price_delta'] > 0 ? '+' : ''; ?><?php echo number_format($var['price_delta'], 2); ?> €</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($isFreeShippingGift): ?>
                <div style="background:#ecfdf5; border:1px solid #6ee7b7; color:#064e3b; padding:10px; border-radius:8px; font-size:13px; margin-top:10px; display:flex; gap:8px; align-items:center;">
                    <span>🎁</span> <strong>Dovana:</strong> Pirkite šią prekę ir gaukite nemokamą pristatymą visam užsakymui!
                </div>
            <?php endif; ?>

            <div class="add-to-cart-row">
                <input class="qty-input" type="number" name="quantity" min="1" value="1" aria-label="Kiekis">
                
                <button class="btn-main" type="submit">
                    Į krepšelį
                </button>
                
                <button class="btn-icon" name="action" value="wishlist" type="submit" aria-label="Į norų sąrašą" title="Įsiminti">
                    ♡
                </button>
            </div>
        </form>

        <?php if (!empty($product['description'])): ?>
          <div class="content-section">
            <h3>Aprašymas</h3>
            <div class="description">
                <?php echo $product['description']; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($attributes): ?>
          <div class="content-section">
            <h3>Specifikacija</h3>
            <div class="specs-grid">
              <?php foreach ($attributes as $attr): ?>
                <div class="spec-item">
                  <div class="spec-label"><?php echo htmlspecialchars($attr['label']); ?></div>
                  <div class="spec-val"><?php echo $attr['value']; // Admin įveda HTML, todėl escapinti nereikia (saugoma admin pusėje) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($related): ?>
      <div style="margin-top:20px;">
        <h3 style="font-size:24px; margin-bottom:20px;">Taip pat gali patikti</h3>
        <div class="related-grid">
          <?php foreach ($related as $rel): 
              $relDisplay = buildPriceDisplay($rel, $globalDiscount, $categoryDiscounts); 
              $relUrl = '/produktas/' . slugify($rel['title']) . '-' . (int)$rel['related_product_id'];
          ?>
            <a href="<?php echo htmlspecialchars($relUrl); ?>" class="related-card">
              <img src="<?php echo htmlspecialchars($rel['image_url']); ?>" alt="<?php echo htmlspecialchars($rel['title']); ?>">
              <div class="related-title"><?php echo htmlspecialchars($rel['title']); ?></div>
              <div class="related-price">
                <?php if ($relDisplay['has_discount']): ?>
                    <span style="text-decoration:line-through; color:#94a3b8; font-size:13px; font-weight:400;"><?php echo number_format($relDisplay['original'], 2); ?> €</span>
                <?php endif; ?>
                <span><?php echo number_format($relDisplay['current'], 2); ?> €</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="lightbox" id="lightbox" aria-hidden="true" onclick="this.classList.remove('show')">
    <img src="" alt="Didelė nuotrauka">
  </div>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org/",
    "@type": "Product",
    "name": <?php echo json_encode($product['title']); ?>,
    "image": [<?php echo json_encode('https://cukrinukas.lt' . $product['image_url']); ?>],
    "description": <?php echo json_encode(mb_substr(strip_tags($product['description']), 0, 300)); ?>,
    "sku": <?php echo json_encode($product['id']); ?>,
    "brand": { "@type": "Brand", "name": "Cukrinukas" },
    "offers": {
      "@type": "Offer",
      "url": <?php echo json_encode($currentProductUrl); ?>,
      "priceCurrency": "EUR",
      "price": <?php echo json_encode($priceDisplay['current']); ?>,
      "availability": <?php echo ((int)$product['quantity'] > 0) ? '"https://schema.org/InStock"' : '"https://schema.org/OutOfStock"'; ?>
    }
  }
  </script>

  <?php renderFooter($pdo); ?>

  <script>
    // Nuotraukų logika
    const heroImage = document.getElementById('heroImage');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = lightbox.querySelector('img');

    function setMainImage(thumb) {
        heroImage.src = thumb.src;
        document.querySelectorAll('.thumbs img').forEach(t => t.classList.remove('active-thumb'));
        thumb.classList.add('active-thumb');
    }

    heroImage.addEventListener('click', () => {
        lightboxImg.src = heroImage.src;
        lightbox.classList.add('show');
    });

    // Kainų logika
    const baseOriginal = parseFloat('<?php echo (float)($product['price'] ?? 0); ?>');
    const baseSale = <?php echo $product['sale_price'] !== null ? 'parseFloat(' . json_encode((float)$product['sale_price']) . ')' : 'null'; ?>;
    
    // Nuolaidų duomenys iš PHP
    const globalDiscount = {
        type: '<?php echo $globalDiscount['type'] ?? 'none'; ?>',
        value: parseFloat('<?php echo (float)($globalDiscount['value'] ?? 0); ?>')
    };
    const categoryDiscount = {
        type: '<?php echo $productCategoryDiscount['type'] ?? 'none'; ?>',
        value: parseFloat('<?php echo (float)($productCategoryDiscount['value'] ?? 0); ?>')
    };

    function applyDiscounts(amount) {
        let final = amount;
        // Global
        if (globalDiscount.type === 'percent') final -= final * (globalDiscount.value / 100);
        else if (globalDiscount.type === 'amount') final -= globalDiscount.value;
        // Category
        if (categoryDiscount.type === 'percent') final -= final * (categoryDiscount.value / 100);
        else if (categoryDiscount.type === 'amount') final -= categoryDiscount.value;
        
        return Math.max(0, final);
    }

    function updatePrice(delta = 0) {
        const originalBase = baseOriginal + delta;
        const saleBase = (baseSale !== null ? baseSale : baseOriginal) + delta;
        
        const finalPrice = applyDiscounts(saleBase);
        const hasDiscount = (baseSale !== null) || (finalPrice < originalBase);

        document.getElementById('price-current').textContent = finalPrice.toFixed(2) + ' €';
        const oldEl = document.getElementById('price-old');
        if (hasDiscount) {
            oldEl.style.display = 'block';
            oldEl.textContent = originalBase.toFixed(2) + ' €';
        } else {
            oldEl.style.display = 'none';
        }
    }

    // Variacijų pasirinkimas
    const varInput = document.getElementById('variation-id');
    const allChips = document.querySelectorAll('.variation-chip');

    function selectVariation(el) {
        // Nuimame active nuo visų
        allChips.forEach(c => c.classList.remove('active'));
        
        // Uždedame ant paspausto
        el.classList.add('active');
        
        // Atnaujiname formos input
        varInput.value = el.dataset.id;
        
        // Perskaičiuojame kainą
        const delta = parseFloat(el.dataset.delta || 0);
        updatePrice(delta);
    }

    // Facebook Pixel events
    document.querySelector('form.controls-card').addEventListener('submit', function(e) {
        if(e.submitter && e.submitter.name !== 'action') { // Tik jei Add To Cart
             fbq('track', 'AddToCart', {
                content_ids: ['<?php echo $product['id']; ?>'],
                content_type: 'product',
                value: parseFloat(document.getElementById('price-current').innerText),
                currency: 'EUR'
            });
        }
    });
  </script>
</body>
</html>
