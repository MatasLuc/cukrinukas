<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureProductsTable($pdo);
ensureCategoriesTable($pdo);
ensureCartTables($pdo);
ensureSavedContentTables($pdo);
ensureProductRelations($pdo);
ensureAdminAccount($pdo);
$freeShippingIds = getFreeShippingProductIds($pdo);

$id = (int) ($_GET['id'] ?? 0);

// 1. Atnaujinta užklausa: įtrauktas c.slug kategorijos nuorodai
$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? LIMIT 1');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo 'Prekė nerasta';
    exit;
}

$imagesStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id DESC');
$imagesStmt->execute([$id]);
$images = $imagesStmt->fetchAll();
$attributesStmt = $pdo->prepare('SELECT label, value FROM product_attributes WHERE product_id = ?');
$attributesStmt->execute([$id]);
$attributes = $attributesStmt->fetchAll();
$variationsStmt = $pdo->prepare('SELECT id, name, price_delta FROM product_variations WHERE product_id = ?');
$variationsStmt->execute([$id]);
$variations = $variationsStmt->fetchAll();
$variationMap = [];
foreach ($variations as $var) {
    $variationMap[(int)$var['id']] = $var;
}

$relStmt = $pdo->prepare('SELECT pr.related_product_id, p.title, p.image_url, p.sale_price, p.price, p.subtitle FROM product_related pr JOIN products p ON p.id = pr.related_product_id WHERE pr.product_id = ? LIMIT 4');
$relStmt->execute([$id]);
$related = $relStmt->fetchAll();
$isFreeShippingGift = in_array($id, $freeShippingIds, true);

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
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;

    $selectedVarId = (int) ($_POST['variation_id'] ?? 0);
    if ($selectedVarId && isset($variationMap[$selectedVarId])) {
        $sel = $variationMap[$selectedVarId];
        $_SESSION['cart_variations'][$id] = [
            'id' => $selectedVarId,
            'name' => $sel['name'],
            'delta' => (float)$sel['price_delta'],
        ];
    } else {
        unset($_SESSION['cart_variations'][$id]);
    }
    if (!empty($_SESSION['user_id'])) {
        saveCartItem($pdo, (int)$_SESSION['user_id'], $id, $qty);
    }
    header('Location: /cart.php');
    exit;
}

$categoryDiscounts = getCategoryDiscounts($pdo);
$globalDiscount = getGlobalDiscount($pdo);
$productCategoryDiscount = null;
if (!empty($product['category_id'])) {
    $productCategoryDiscount = $categoryDiscounts[(int)$product['category_id']] ?? null;
}
$priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts);

// 2. Meta duomenų paruošimas SEO
$meta = [
    'title' => $product['title'] . ' | Cukrinukas',
    'description' => mb_substr(strip_tags($product['description']), 0, 160),
    'image' => 'https://cukrinukas.lt' . $product['image_url']
];
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
      --muted: #475467;
      /* Pagrindinė spalva */
      --accent: #829ed6;
      --accent-hover: #6a8bc9;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    .page { max-width:1100px; margin:0 auto; padding:28px 24px 64px; display:grid; gap:24px; }
    .breadcrumbs { display:flex; align-items:center; gap:10px; font-weight:600; color: var(--muted); flex-wrap: wrap; }
    .shell { display:grid; grid-template-columns: 1.05fr 0.95fr; gap:22px; align-items:start; }
    .gallery { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:16px; box-shadow:0 14px 34px rgba(0,0,0,0.08); display:grid; gap:12px; }
    .main-image { position:relative; border-radius:16px; overflow:hidden; background:#fff; cursor:zoom-in; }
    .main-image img { width:100%; display:block; height:420px; object-fit:cover; }
    
    .ribbon { position:absolute; top:12px; left:12px; background: var(--accent); color:#fff; padding:8px 12px; border-radius:12px; font-weight:700; box-shadow:0 10px 22px rgba(0,0,0,0.12); }
    
    .thumbs { display:flex; gap:10px; flex-wrap:wrap; }
    .thumbs img { width:86px; height:70px; object-fit:cover; border-radius:10px; border:2px solid var(--border); background:#fff; cursor:pointer; transition:transform 0.15s ease, border-color 0.15s ease; }
    .thumbs img:hover { transform:translateY(-2px); }
    
    .details { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:18px 20px; box-shadow:0 14px 34px rgba(0,0,0,0.08); display:grid; gap:12px; }
    .badge { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; background:#eef2ff; color:var(--accent); font-weight:700; }
    .badge.gift { background:#ecfdf3; color:#166534; border:1px solid #bbf7d0; }
    h1 { margin:0; font-size:32px; letter-spacing:-0.02em; }
    .subtitle { margin:0; color:var(--accent); }
    
    /* Aprašymo stilius (kad veiktų sąrašai ir tarpai) */
    .description { margin:0; color: var(--muted); line-height:1.65; }
    .description ul, .description ol { margin: 10px 0; padding-left: 20px; }
    .description li { margin-bottom: 5px; }
    
    .price-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .old { color:#9ca3af; text-decoration:line-through; }
    .current { font-size:28px; font-weight:800; letter-spacing:-0.02em; }
    .form-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:4px; }
    .quantity { width:90px; padding:11px; border-radius:12px; border:1px solid var(--border); background:#f8fafc; }
    
    /* Mygtukas */
    .btn { 
        display:inline-flex; align-items:center; justify-content:center; 
        padding:12px 16px; border-radius:12px; border:none; 
        background: var(--accent); color:#fff; 
        font-weight:700; cursor:pointer; transition: background 0.2s ease;
        box-shadow:0 4px 12px rgba(130, 158, 214, 0.3);
    }
    .btn:hover { background: var(--accent-hover); }
    
    /* Širdelės mygtukas */
    .heart-btn { 
        width:44px; height:44px; border-radius:12px; 
        border:1px solid var(--border); background:#fff; 
        display:inline-flex; align-items:center; justify-content:center; 
        font-size:18px; cursor:pointer; color: var(--text);
        transition: all 0.2s ease;
    }
    .heart-btn:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }

    .info-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:12px; }
    .info-card { background:#f8fafc; border:1px solid var(--border); border-radius:14px; padding:12px 14px; color: var(--muted); font-weight:600; }
    .section { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:16px 18px; box-shadow:0 12px 28px rgba(0,0,0,0.08); display:grid; gap:12px; }
    
    .attr-card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:12px; box-shadow:0 8px 18px rgba(0,0,0,0.06); }
    /* PAKEISTA: Pridėtas stilius, kad ypatybių tekstas atrodytų tvarkingai */
    .attr-value ul, .attr-value ol { margin: 6px 0 6px 20px; padding: 0; }
    .attr-value li { margin-bottom: 2px; }
    .attr-value p { margin: 0 0 4px 0; }

    .pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; background:#ecfdf3; color:#15803d; font-weight:700; font-size:13px; }
    .related-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:14px; }
    .related-card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:12px; box-shadow:0 12px 26px rgba(0,0,0,0.08); color:var(--text); display:grid; gap:8px; }
    .related-card img { width:100%; height:150px; object-fit:cover; border-radius:12px; }
    .related-price { font-weight:800; }
    
    .variation-chip { border:1px solid var(--border); padding:10px 12px; border-radius:12px; background:#f8fafc; color:var(--text); font-weight:700; cursor:pointer; display:inline-flex; gap:8px; align-items:center; transition:border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease; }
    .variation-chip.active { border-color:var(--accent); box-shadow:0 8px 18px rgba(130, 158, 214, 0.2); background:#eef2ff; color: var(--accent); }
    
    .lightbox { position:fixed; inset:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; padding:20px; z-index:999; opacity:0; pointer-events:none; transition:opacity 0.2s ease; }
    .lightbox.show { opacity:1; pointer-events:all; }
    .lightbox img { max-width:90vw; max-height:90vh; border-radius:12px; box-shadow:0 16px 40px rgba(0,0,0,0.35); background:#fff; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'product', $meta); ?>
  <?php $mainImage = $images[0]['path'] ?? $product['image_url']; ?>
  <div class="page">
    <div class="breadcrumbs">
      <a href="/">Pagrindinis</a> <span>/</span>
      <a href="/products.php">Parduotuvė</a> <span>/</span>
      <?php if (!empty($product['category_name'])): ?>
         <a href="/products.php?category=<?php echo urlencode($product['category_slug'] ?? ''); ?>">
            <?php echo htmlspecialchars($product['category_name']); ?>
         </a> <span>/</span>
      <?php endif; ?>
      <span style="color: #0b0b0b; font-weight: 700;"><?php echo htmlspecialchars($product['title']); ?></span>
    </div>

    <div class="shell">
      <div class="gallery">
        <div class="main-image">
          <?php if (!empty($product['ribbon_text'])): ?><div class="ribbon"><?php echo htmlspecialchars($product['ribbon_text']); ?></div><?php endif; ?>
          <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
        </div>
        <?php if ($images): ?>
          <div class="thumbs">
            <?php foreach ($images as $img): ?>
              <img src="<?php echo htmlspecialchars($img['path']); ?>" alt="Miniatiūra" style="border-color: <?php echo $img['is_primary'] ? 'var(--accent)' : 'var(--border)'; ?>;">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="details">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <span class="badge"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></span>
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="/product_edit.php?id=<?php echo (int)$product['id']; ?>" style="font-weight:700; color:var(--text);">Redaguoti</a>
          <?php endif; ?>
        </div>
        <h1><?php echo htmlspecialchars($product['title']); ?></h1>
        <?php if (!empty($product['subtitle'])): ?><p class="subtitle"><?php echo htmlspecialchars($product['subtitle']); ?></p><?php endif; ?>
        <div class="price-row">
          <span id="price-old" class="old" style="display: <?php echo $priceDisplay['has_discount'] ? 'inline-flex' : 'none'; ?>;">
            <?php echo number_format($priceDisplay['original'], 2); ?> €
          </span>
          <strong id="price-current" class="current"><?php echo number_format($priceDisplay['current'], 2); ?> €</strong>
        </div>
        <form method="post" class="form-row">
          <?php echo csrfField(); ?>
          <input class="quantity" type="number" name="quantity" min="1" value="1">
          <input type="hidden" name="variation_id" id="variation-id" value="0">
          <button class="btn" type="submit">Į krepšelį</button>
          <button class="heart-btn" name="action" value="wishlist" type="submit" aria-label="Į norų sąrašą">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
          </button>
        </form>
        <div class="info-grid">
          <?php if ($isFreeShippingGift): ?>
            <div class="info-card" style="display:flex; align-items:center; gap:8px; background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
              <span>🎁Pirkite šią prekę ir gausite nemokamą viso užsakymo pristatymą dovanų.</span>
            </div>
          <?php endif; ?>
          <div class="info-card">🔄 14 dienų grąžinimo garantija</div>
          <div class="info-card">💬 Turite klausimų? Drąsiai rašykite labas@cukrinukas.lt</div>
        </div>
      </div>
    </div>

    <?php if ($variations): ?>
      <section class="section" id="variations-section">
        <div style="display:flex; align-items:center; justify-content:space-between;">
          <h3 style="margin:0;">Variacijos</h3>
          <span class="badge" style="background:#fff; border:1px solid var(--border);">Pasirinkimai</span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
          <?php foreach ($variations as $index => $var): ?>
            <button type="button" class="variation-chip" data-var-id="<?php echo (int)$var['id']; ?>" data-var-delta="<?php echo (float)$var['price_delta']; ?>">
              <span><?php echo htmlspecialchars($var['name']); ?></span>
              <small style="color:#475467; font-weight:600;">(<?php echo $var['price_delta'] >= 0 ? '+' : ''; ?><?php echo number_format((float)$var['price_delta'], 2); ?> €)</small>
            </button>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($product['description'])): ?>
      <section class="section">
        <h3 style="margin:0;">Aprašymas</h3>
        <div class="description">
            <?php echo $product['description']; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($attributes): ?>
      <section class="section">
        <div style="display:flex; align-items:center; justify-content:space-between;">
          <h3 style="margin:0;">Produkto ypatybės</h3>
          <span class="pill">Papildoma info</span>
        </div>
        <div class="info-grid">
          <?php foreach ($attributes as $attr): ?>
            <div class="attr-card">
              <div style="font-weight:700; margin-bottom:4px; color:var(--text); "><?php echo htmlspecialchars($attr['label']); ?></div>
              <div class="attr-value" style="color:var(--muted);"><?php echo $attr['value']; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($related): ?>
      <section class="section">
        <div style="display:flex; align-items:center; justify-content:space-between;">
          <h3 style="margin:0;">Susijusios prekės</h3>
          <a href="/products.php" style="color:var(--accent); font-weight:700;">Peržiūrėti visą katalogą</a>
        </div>
        <div class="related-grid">
          <?php foreach ($related as $rel): $relDisplay = buildPriceDisplay($rel, $globalDiscount, $categoryDiscounts); ?>
            <a href="/product.php?id=<?php echo (int)$rel['related_product_id']; ?>" class="related-card">
              <img src="<?php echo htmlspecialchars($rel['image_url']); ?>" alt="<?php echo htmlspecialchars($rel['title']); ?>">
              <div style="font-weight:700; font-size:16px; letter-spacing:-0.01em;">&nbsp;<?php echo htmlspecialchars($rel['title']); ?></div>
              <?php if (!empty($rel['subtitle'])): ?><div style="color:#6b6b7a; font-size:14px;">&nbsp;<?php echo htmlspecialchars($rel['subtitle']); ?></div><?php endif; ?>
              <div class="related-price">
                <?php if ($relDisplay['has_discount']): ?><span style="text-decoration:line-through; color:#6b6b7a; font-size:13px; margin-right:6px;">&nbsp;<?php echo number_format($relDisplay['original'], 2); ?> €</span><?php endif; ?>
                &nbsp;<?php echo number_format($relDisplay['current'], 2); ?> €
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
  <div class="lightbox" id="lightbox" aria-hidden="true">
    <img src="" alt="Padidinta produkto nuotrauka">
  </div>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org/",
    "@type": "Product",
    "name": <?php echo json_encode($product['title']); ?>,
    "image": [<?php echo json_encode('https://cukrinukas.lt' . $product['image_url']); ?>],
    "description": <?php echo json_encode(mb_substr(strip_tags($product['description']), 0, 300)); ?>,
    "sku": <?php echo json_encode($product['id']); ?>,
    "offers": {
      "@type": "Offer",
      "url": <?php echo json_encode("https://cukrinukas.lt/product.php?id=" . $product['id']); ?>,
      "priceCurrency": "EUR",
      "price": <?php echo json_encode($priceDisplay['current']); ?>,
      "availability": <?php echo ((int)$product['quantity'] > 0) ? '"https://schema.org/InStock"' : '"https://schema.org/OutOfStock"'; ?>,
      "itemCondition": "https://schema.org/NewCondition"
    }
  }
  </script>

  <script>
    // Sekti prekės peržiūrą
    fbq('track', 'ViewContent', {
      content_name: '<?php echo htmlspecialchars($product['title']); ?>',
      content_ids: ['<?php echo $product['id']; ?>'],
      content_type: 'product',
      value: <?php echo $priceDisplay['current']; ?>,
      currency: 'EUR'
    });

    // Sekti įdėjimą į krepšelį
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form.form-row');
        if (form) {
            const addToCartBtn = form.querySelector('button[type="submit"]:not(.heart-btn)');
            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', function() {
                    const qtyInput = form.querySelector('input[name="quantity"]');
                    const qty = qtyInput ? qtyInput.value : 1;
                    
                    fbq('track', 'AddToCart', {
                        content_name: '<?php echo htmlspecialchars($product['title']); ?>',
                        content_ids: ['<?php echo $product['id']; ?>'],
                        content_type: 'product',
                        value: <?php echo $priceDisplay['current']; ?>,
                        currency: 'EUR',
                        contents: [{
                            'id': '<?php echo $product['id']; ?>',
                            'quantity': qty
                        }]
                    });
                });
            }
        }
    });
  </script>

  <?php renderFooter($pdo); ?>

  <script>
    const mainImage = document.querySelector('.main-image img');
    const thumbs = document.querySelectorAll('.thumbs img');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = lightbox ? lightbox.querySelector('img') : null;

    thumbs.forEach((thumb) => {
      thumb.addEventListener('click', () => {
        if (mainImage) {
          mainImage.src = thumb.src;
        }
        thumbs.forEach(t => t.style.borderColor = 'var(--border)');
        thumb.style.borderColor = 'var(--accent)';
      });
    });

    if (mainImage && lightbox && lightboxImg) {
      const openLightbox = () => {
        lightboxImg.src = mainImage.src;
        lightbox.classList.add('show');
        lightbox.setAttribute('aria-hidden', 'false');
      };

      mainImage.addEventListener('click', openLightbox);
      lightbox.addEventListener('click', () => {
        lightbox.classList.remove('show');
        lightbox.setAttribute('aria-hidden', 'true');
      });
    }

    const formatPrice = (amount) => new Intl.NumberFormat('lt-LT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);

    const globalDiscount = {
      type: '<?php echo $globalDiscount['type'] ?? 'none'; ?>',
      value: parseFloat('<?php echo (float)($globalDiscount['value'] ?? 0); ?>')
    };

    const categoryDiscount = {
      type: '<?php echo $productCategoryDiscount['type'] ?? 'none'; ?>',
      value: parseFloat('<?php echo (float)($productCategoryDiscount['value'] ?? 0); ?>')
    };

    const baseOriginal = parseFloat('<?php echo (float)($product['price'] ?? 0); ?>');
    const baseSale = <?php echo $product['sale_price'] !== null ? 'parseFloat(' . json_encode((float)$product['sale_price']) . ')' : 'null'; ?>;

    const applyGlobal = (amount) => {
      if (globalDiscount.type === 'percent' && globalDiscount.value > 0) {
        return Math.max(0, amount - (amount * (globalDiscount.value / 100)));
      }
      if (globalDiscount.type === 'amount' && globalDiscount.value > 0) {
        return Math.max(0, amount - globalDiscount.value);
      }
      return Math.max(0, amount);
    };

    const applyCategory = (amount) => {
      if (categoryDiscount.type === 'percent' && categoryDiscount.value > 0) {
        return Math.max(0, amount - (amount * (categoryDiscount.value / 100)));
      }
      if (categoryDiscount.type === 'amount' && categoryDiscount.value > 0) {
        return Math.max(0, amount - categoryDiscount.value);
      }
      return Math.max(0, amount);
    };

    const computePrice = (delta = 0) => {
      const original = baseOriginal + delta;
      const effectiveBase = (baseSale !== null ? baseSale : baseOriginal) + delta;
      const afterGlobal = applyGlobal(effectiveBase);
      const final = applyCategory(afterGlobal);
      const hasDiscount = (baseSale !== null) || (globalDiscount.type !== 'none' && globalDiscount.value > 0) || (categoryDiscount.type !== 'none' && categoryDiscount.value > 0);
      return {
        original: hasDiscount ? original : final,
        current: final,
        hasDiscount: hasDiscount && final < original
      };
    };

    const priceOldEl = document.getElementById('price-old');
    const priceCurrentEl = document.getElementById('price-current');
    const variationInput = document.getElementById('variation-id');
    const variationButtons = Array.from(document.querySelectorAll('.variation-chip'));

    const applyPrice = (selection) => {
      if (priceCurrentEl) {
        priceCurrentEl.textContent = `${formatPrice(selection.current)} €`;
      }
      if (priceOldEl) {
        if (selection.hasDiscount) {
          priceOldEl.style.display = 'inline-flex';
          priceOldEl.textContent = `${formatPrice(selection.original)} €`;
        } else {
          priceOldEl.style.display = 'none';
        }
      }
    };

    const resetVariation = () => {
      variationButtons.forEach(b => b.classList.remove('active'));
      if (variationInput) {
        variationInput.value = '0';
      }
      applyPrice(computePrice(0));
    };

    const setVariation = (btn) => {
      const isActive = btn.classList.contains('active');
      variationButtons.forEach(b => b.classList.remove('active'));
      if (isActive) {
        resetVariation();
        return;
      }
      btn.classList.add('active');
      const delta = parseFloat(btn.dataset.varDelta || '0');
      applyPrice(computePrice(delta));
      if (variationInput) {
        variationInput.value = btn.dataset.varId || '0';
      }
    };

    if (variationButtons.length) {
      variationButtons.forEach((btn) => {
        btn.addEventListener('click', () => setVariation(btn));
      });
      resetVariation();
    }
  </script>
</body>
</html>
