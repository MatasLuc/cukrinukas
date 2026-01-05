<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCategoriesTable($pdo);
ensureProductsTable($pdo);
ensureNewsTable($pdo);

// 1. Gauname svetainės turinį (Hero tekstams)
$siteContent = getSiteContent($pdo);

// 2. Gauname populiariausias/pagrindines kategorijas (pvz., 4 pirmas)
$categoriesStmt = $pdo->query("SELECT * FROM categories WHERE parent_id = 0 ORDER BY name ASC LIMIT 4");
$homeCategories = $categoriesStmt->fetchAll();

// 3. Gauname naujausias prekes (8 vnt.)
// Pastaba: čia supaprastinta užklausa. Jei reikia nuolaidų logikos, ji identiška products.php
$productsStmt = $pdo->query("
    SELECT p.*, c.name as category_name, 
    (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 8
");
$newProducts = $productsStmt->fetchAll();

// 4. Gauname naujausias naujienas (3 vnt.)
$newsStmt = $pdo->query("SELECT id, title, image_url, created_at FROM news ORDER BY created_at DESC LIMIT 3");
$latestNews = $newsStmt->fetchAll();

// Nuolaidų logika kainų atvaizdavimui
$globalDiscount = getGlobalDiscount($pdo);
$categoryDiscounts = getCategoryDiscounts($pdo);

?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pagrindinis | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    /* Bendra spalvų paletė ir kintamieji (identiška products.php) */
    :root { 
        --bg: #f7f7fb; 
        --card: #ffffff; 
        --border: #e4e7ec; 
        --text: #1f2937; 
        --muted: #52606d; 
        --accent: #2563eb; 
        --accent-hover: #1d4ed8;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
    a { color: inherit; text-decoration: none; transition: color .2s; }

    .page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display: flex; flex-direction: column; gap: 48px; }

    /* --- HERO SEKCIJA --- */
    .hero {
      padding: 48px 32px; 
      border-radius: 28px; 
      background: linear-gradient(135deg, #eff6ff, #dbeafe); /* Mėlynas gradientas */
      border: 1px solid #e5e7eb; 
      box-shadow: 0 18px 48px rgba(0,0,0,0.06);
      display: grid; 
      grid-template-columns: 1fr 0.8fr; 
      align-items: center; 
      gap: 32px;
      overflow: hidden;
      position: relative;
    }
    
    .hero__content { z-index: 2; }
    .hero__pill { 
        display: inline-flex; align-items: center; gap: 8px; 
        background: #fff; padding: 8px 16px; border-radius: 999px; 
        font-weight: 700; font-size: 14px; color: #1e40af; 
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1); margin-bottom: 16px;
    }
    .hero h1 { margin: 0 0 16px; font-size: clamp(32px, 5vw, 48px); line-height: 1.1; color: #0f172a; letter-spacing: -0.02em; }
    .hero p { margin: 0 0 24px; color: var(--muted); line-height: 1.6; font-size: 18px; max-width: 500px; }
    
    .hero__actions { display: flex; gap: 12px; flex-wrap: wrap; }
    
    .btn-hero { 
        padding: 14px 28px; border-radius: 14px; font-weight: 600; font-size: 16px; 
        display: inline-flex; align-items: center; justify-content: center; transition: all .2s; cursor: pointer;
    }
    .btn-primary { background: var(--accent); color: #fff; border: 1px solid var(--accent); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
    .btn-primary:hover { background: var(--accent-hover); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }
    
    .btn-outline { background: #fff; color: var(--text); border: 1px solid var(--border); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-2px); }

    .hero__image-container {
        position: relative; height: 300px; display: flex; align-items: center; justify-content: center;
    }
    .hero__image { width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.1)); }

    /* --- PRIVALUMAI (Features) --- */
    .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; }
    .feature-card { 
        background: #fff; padding: 24px; border-radius: 20px; border: 1px solid var(--border); 
        display: flex; flex-direction: column; gap: 12px; transition: transform .2s; 
    }
    .feature-card:hover { transform: translateY(-4px); border-color: var(--accent); }
    .feature-icon { 
        width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; color: var(--accent); 
        display: flex; align-items: center; justify-content: center; font-size: 24px; 
    }
    .feature-title { font-weight: 700; font-size: 18px; color: #0f172a; margin: 0; }
    .feature-text { font-size: 14px; color: var(--muted); line-height: 1.5; margin: 0; }

    /* --- SEKCIJŲ ANTRAŠTĖS --- */
    .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    .section-title { font-size: 28px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.01em; }
    .section-link { color: var(--accent); font-weight: 600; display: inline-flex; align-items: center; gap: 4px; font-size: 15px; }
    .section-link:hover { text-decoration: underline; }

    /* --- KATEGORIJOS --- */
    .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .cat-card {
        background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 20px;
        text-align: center; transition: all .2s; display: flex; flex-direction: column; align-items: center; gap: 10px;
        height: 100%; justify-content: center;
    }
    .cat-card:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1); transform: translateY(-3px); color: var(--accent); }
    .cat-icon { font-size: 32px; margin-bottom: 4px; }
    .cat-name { font-weight: 600; font-size: 16px; }

    /* --- PRODUKTŲ TINKLELIS (Kaip products.php) --- */
    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
    .prod-card { 
        background: var(--card); border: 1px solid var(--border); border-radius: 20px; 
        overflow: hidden; display: flex; flex-direction: column; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: transform .2s, border-color .2s; 
    }
    .prod-card:hover { transform: translateY(-4px); border-color: var(--accent); }
    
    .prod-img-wrap { position: relative; height: 220px; background: #fff; }
    .prod-img { width: 100%; height: 100%; object-fit: contain; padding: 20px; display: block; }
    .prod-ribbon { position: absolute; top: 12px; left: 12px; background: var(--accent); color: #fff; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
    
    .prod-body { padding: 18px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .prod-cat { font-size: 11px; color: var(--accent); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .prod-title { margin: 0; font-size: 17px; line-height: 1.4; font-weight: 600; }
    .prod-title a { color: #111827; }
    .prod-title a:hover { color: var(--accent); }
    
    .prod-price-row { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 12px; }
    .prod-price { font-size: 18px; font-weight: 700; color: #111827; }
    .prod-old-price { font-size: 13px; text-decoration: line-through; color: #9ca3af; font-weight: 400; margin-right: 6px; }

    /* Mygtukai kortelėje */
    .action-btn {
        width: 38px; height: 38px; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: all .2s;
        background: #fff; border: 1px solid var(--border); color: #1f2937;
    }
    .action-btn:hover { border-color: var(--accent); color: var(--accent); background: #f0f9ff; }
    .btn-group { display: flex; gap: 8px; }

    /* --- BENDRUOMENĖS BLOKAS --- */
    .community-banner {
        background: radial-gradient(circle at 10% 20%, rgba(37, 99, 235, 0.05), transparent 40%),
                    linear-gradient(135deg, #fff, #f8fafc);
        border: 1px solid var(--border); border-radius: 24px; padding: 32px;
        display: flex; align-items: center; justify-content: space-between; gap: 32px; flex-wrap: wrap;
    }
    .comm-text h3 { margin: 0 0 10px; font-size: 24px; }
    .comm-text p { margin: 0; color: var(--muted); max-width: 500px; }

    /* --- MEDIA QUERIES --- */
    @media (max-width: 800px) {
        .hero { grid-template-columns: 1fr; text-align: center; padding: 32px 20px; }
        .hero__actions { justify-content: center; }
        .hero__image-container { height: 200px; }
        .section-header { flex-direction: column; align-items: flex-start; gap: 8px; }
        .community-banner { flex-direction: column; text-align: center; }
        .comm-text p { margin: 0 auto; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'home'); ?>

  <main class="page">
    
    <section class="hero">
      <div class="hero__content">
        <div class="hero__pill">👋 Sveiki atvykę</div>
        <h1><?php echo htmlspecialchars($siteContent['home_title'] ?? 'Viskas Jūsų diabeto kontrolei'); ?></h1>
        <p><?php echo htmlspecialchars($siteContent['home_subtitle'] ?? 'Aukščiausios kokybės prekės, patikimi patarimai ir palaikanti bendruomenė vienoje vietoje.'); ?></p>
        <div class="hero__actions">
          <a href="/products.php" class="btn-hero btn-primary">Pradėti apsipirkimą</a>
          <a href="/about.php" class="btn-hero btn-outline">Sužinoti daugiau</a>
        </div>
      </div>
      <div class="hero__image-container">
         <?php if (!empty($siteContent['home_image_url'])): ?>
            <img src="<?php echo htmlspecialchars($siteContent['home_image_url']); ?>" alt="Hero" class="hero__image">
         <?php else: ?>
            <div style="font-size:100px;">🩺</div>
         <?php endif; ?>
      </div>
    </section>

    <section class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">🚀</div>
            <div>
                <h3 class="feature-title">Greitas pristatymas</h3>
                <p class="feature-text">Prekes pristatome per 1-2 d.d. visoje Lietuvoje.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🛡️</div>
            <div>
                <h3 class="feature-title">Kokybės garantija</h3>
                <p class="feature-text">Tik patikrinti ir sertifikuoti produktai jūsų sveikatai.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💬</div>
            <div>
                <h3 class="feature-title">Aktyvi bendruomenė</h3>
                <p class="feature-text">Dalinkitės patirtimi ir gaukite patarimų forume.</p>
            </div>
        </div>
    </section>

    <?php if (!empty($homeCategories)): ?>
    <section>
        <div class="section-header">
            <h2 class="section-title">Populiariausios kategorijos</h2>
            <a href="/products.php" class="section-link">Visos kategorijos →</a>
        </div>
        <div class="categories-grid">
            <?php foreach ($homeCategories as $cat): ?>
            <a href="/products.php?category=<?php echo urlencode($cat['slug']); ?>" class="cat-card">
                <div class="cat-icon">📦</div>
                <div class="cat-name"><?php echo htmlspecialchars($cat['name']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section>
        <div class="section-header">
            <h2 class="section-title">Naujausios prekės</h2>
            <a href="/products.php" class="section-link">Visos prekės →</a>
        </div>
        
        <div class="products-grid">
            <?php foreach ($newProducts as $product): 
                $priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts);
                $img = $product['primary_image'] ?: $product['image_url'];
            ?>
            <article class="prod-card">
                <a href="/product.php?id=<?php echo $product['id']; ?>" class="prod-img-wrap">
                    <?php if (!empty($product['ribbon_text'])): ?>
                        <div class="prod-ribbon"><?php echo htmlspecialchars($product['ribbon_text']); ?></div>
                    <?php endif; ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="prod-img">
                </a>
                <div class="prod-body">
                    <div class="prod-cat"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></div>
                    <h3 class="prod-title">
                        <a href="/product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['title']); ?></a>
                    </h3>
                    
                    <div class="prod-price-row">
                        <div>
                            <?php if ($priceDisplay['has_discount']): ?>
                                <span class="prod-old-price"><?php echo number_format($priceDisplay['original'], 2); ?> €</span>
                            <?php endif; ?>
                            <span class="prod-price"><?php echo number_format($priceDisplay['current'], 2); ?> €</span>
                        </div>
                        
                        <form method="post" action="/products.php" class="btn-group">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <button type="submit" name="action" value="wishlist" class="action-btn" title="Į norų sąrašą">♥</button>
                            <button type="submit" name="quantity" value="1" class="action-btn" title="Į krepšelį" style="background:#1f2937; color:#fff; border-color:#1f2937;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="community-banner">
        <div class="comm-text">
            <h3>Prisijunkite prie bendruomenės</h3>
            <p>Turite klausimų? Norite pasidalinti patirtimi ar parduoti nenaudojamas priemones? Mūsų bendruomenė laukia jūsų.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="/community_discussions.php" class="btn-hero btn-primary">Diskusijos</a>
            <a href="/community_market.php" class="btn-hero btn-outline">Turgelis</a>
        </div>
    </section>
    
    <?php if (!empty($latestNews)): ?>
    <section>
        <div class="section-header">
            <h2 class="section-title">Naujienos</h2>
            <a href="/news.php" class="section-link">Skaityti visas →</a>
        </div>
        <div class="features-grid"> <?php foreach ($latestNews as $news): ?>
            <a href="/news_view.php?id=<?php echo $news['id']; ?>" class="feature-card" style="text-decoration:none; display:block;">
                <?php if($news['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="" style="width:100%; height:160px; object-fit:cover; border-radius:12px; margin-bottom:12px;">
                <?php endif; ?>
                <h3 class="feature-title" style="font-size:16px; margin-bottom:6px;"><?php echo htmlspecialchars($news['title']); ?></h3>
                <span style="font-size:13px; color:var(--muted);"><?php echo date('Y-m-d', strtotime($news['created_at'])); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
