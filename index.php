<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

// Simple PHP page that renders the provided head metadata and a static landing layout
$headerShadowIntensity = 70;
$GLOBALS['headerShadowIntensity'] = $headerShadowIntensity;

$pdo = getPdo();

// DB struktūros ir duomenų užtikrinimas
ensureUsersTable($pdo);
ensureNewsTable($pdo);
ensureCategoriesTable($pdo);
ensureProductsTable($pdo);
ensureOrdersTables($pdo);
ensureRecipesTable($pdo);
ensureAdminAccount($pdo);
ensureSiteContentTable($pdo);
ensureFooterLinks($pdo);
ensureSavedContentTables($pdo);
seedStoreExamples($pdo);
seedNewsExamples($pdo);
seedRecipeExamples($pdo);

$siteContent = getSiteContent($pdo);
$globalDiscount = getGlobalDiscount($pdo);
$categoryDiscounts = getCategoryDiscounts($pdo);

// Krepšelio logika
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

// Hero settings setup
$heroShadowIntensity = max(0, min(100, (int)($siteContent['hero_shadow_intensity'] ?? 70)));
$heroOverlayStrong = round(0.75 * ($heroShadowIntensity / 100), 3);
$heroOverlaySoft = round(0.17 * ($heroShadowIntensity / 100), 3);
$heroMedia = [
    'type' => $siteContent['hero_media_type'] ?? 'image',
    'color' => $siteContent['hero_media_color'] ?? '#2563eb',
    'src' => '',
    'poster' => $siteContent['hero_media_poster'] ?? '',
    'alt' => $siteContent['hero_media_alt'] ?? 'Cukrinukas fonas',
];

if ($heroMedia['type'] === 'video') {
    $heroMedia['src'] = $siteContent['hero_media_video'] ?? '';
} else {
    $heroMedia['src'] = $siteContent['hero_media_image'] ?? 'https://images.pexels.com/photos/6942003/pexels-photo-6942003.jpeg';
    $heroMedia['type'] = $heroMedia['type'] ?: 'image';
}

if ($heroMedia['type'] === 'video' && !$heroMedia['src']) {
    $heroMedia['type'] = 'color';
}
if ($heroMedia['type'] === 'image' && !$heroMedia['src']) {
    $heroMedia['src'] = 'https://images.pexels.com/photos/6942003/pexels-photo-6942003.jpeg';
}

// Content Text setup
$heroTitle = $siteContent['hero_title'] ?? 'Pagalba kasdienei diabeto priežiūrai';
$heroBody = $siteContent['hero_body'] ?? 'Gliukometrai, sensoriai, maži GI užkandžiai ir bendruomenės patarimai – viskas vienoje vietoje.';
$heroCtaLabel = $siteContent['hero_cta_label'] ?? 'Peržiūrėti pasiūlymus →';
$heroCtaUrl = $siteContent['hero_cta_url'] ?? '/products.php';

$testimonials = [];
for ($i = 1; $i <= 3; $i++) {
    $testimonials[] = [
        'name' => $siteContent['testimonial_' . $i . '_name'] ?? '',
        'role' => $siteContent['testimonial_' . $i . '_role'] ?? '',
        'text' => $siteContent['testimonial_' . $i . '_text'] ?? '',
    ];
}

$promoCards = [];
for ($i = 1; $i <= 3; $i++) {
    $promoCards[] = [
        'icon' => $siteContent['promo_' . $i . '_icon'] ?? ($i === 1 ? '1%' : ($i === 2 ? '24/7' : '★')),
        'title' => $siteContent['promo_' . $i . '_title'] ?? '',
        'body' => $siteContent['promo_' . $i . '_body'] ?? '',
    ];
}

$storyband = [
    'title' => $siteContent['storyband_title'] ?? 'Kasdieniai sprendimai diabetui',
    'body' => $siteContent['storyband_body'] ?? 'Sudėjome priemones ir žinias, kurios palengvina cukrinio diabeto priežiūrą: nuo matavimų iki receptų ir užkandžių.',
    'cta_label' => $siteContent['storyband_cta_label'] ?? 'Rinktis rinkinį',
    'cta_url' => $siteContent['storyband_cta_url'] ?? '/products.php',
    'card_title' => $siteContent['storyband_card_title'] ?? '„Cukrinukas“ rinkiniai',
    'card_body' => $siteContent['storyband_card_body'] ?? 'Starteriai su gliukometrais, užkandžiais ir atsargomis 30 dienų. Pradėkite be streso.',
];
$storybandMetrics = [];
for ($i = 1; $i <= 3; $i++) {
    $storybandMetrics[] = [
        'value' => $siteContent['storyband_metric_' . $i . '_value'] ?? '',
        'label' => $siteContent['storyband_metric_' . $i . '_label'] ?? '',
    ];
}

$storyRow = [
    'title' => $siteContent['storyrow_title'] ?? 'Stebėjimas, užkandžiai ir ramybė',
    'body' => $siteContent['storyrow_body'] ?? 'Greitai pasiekiami sensorių pleistrai, cukraus kiekį subalansuojantys batonėliai ir starterių rinkiniai.',
    'pills' => [
        $siteContent['storyrow_pill_1'] ?? 'Gliukozės matavimai',
        $siteContent['storyrow_pill_2'] ?? 'Subalansuotos užkandžių dėžutės',
        $siteContent['storyrow_pill_3'] ?? 'Kelionėms paruošti rinkiniai',
    ],
    'bubble_meta' => $siteContent['storyrow_bubble_meta'] ?? 'Rekomendacija',
    'bubble_title' => $siteContent['storyrow_bubble_title'] ?? '„Cukrinukas“ specialistai',
    'bubble_body' => $siteContent['storyrow_bubble_body'] ?? 'Suderiname atsargas pagal jūsų dienos režimą: nuo ankstyvų matavimų iki vakaro koregavimų.',
    'floating_meta' => $siteContent['storyrow_floating_meta'] ?? 'Greitas pristatymas',
    'floating_title' => $siteContent['storyrow_floating_title'] ?? '1-2 d.d.',
    'floating_body' => $siteContent['storyrow_floating_body'] ?? 'Visoje Lietuvoje nuo 2.50 €',
];

$supportBand = [
    'title' => $siteContent['support_title'] ?? 'Pagalba jums ir šeimai',
    'body' => $siteContent['support_body'] ?? 'Nuo pirmo sensoriaus iki subalansuotos vakarienės – čia rasite trumpus gidus, vaizdo pamokas ir dietologės patarimus.',
    'chips' => [
        $siteContent['support_chip_1'] ?? 'Vaizdo gidai',
        $siteContent['support_chip_2'] ?? 'Dietologės Q&A',
        $siteContent['support_chip_3'] ?? 'Tėvų kampelis',
    ],
    'card_meta' => $siteContent['support_card_meta'] ?? 'Gyva konsultacija',
    'card_title' => $siteContent['support_card_title'] ?? '5 d. per savaitę',
    'card_body' => $siteContent['support_card_body'] ?? 'Trumpi pokalbiai su cukrinio diabeto slaugytoja per „Messenger“ – pasikalbam apie sensorius, vaikus ar receptų koregavimus.',
    'card_cta_label' => $siteContent['support_card_cta_label'] ?? 'Rezervuoti laiką',
    'card_cta_url' => $siteContent['support_card_cta_url'] ?? '/contact.php',
];

$featuredNews = $pdo->query('SELECT id, title, image_url, body, summary, created_at FROM news WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 4')->fetchAll();
$featuredIds = getFeaturedProductIds($pdo);
$featuredProducts = [];
if ($featuredIds) {
    $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
    $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name,
        (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 ORDER BY id DESC LIMIT 1) AS primary_image
        FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN (' . $placeholders . ')');
    $stmt->execute($featuredIds);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) { $map[$row['id']] = $row; }
    foreach ($featuredIds as $fid) { if (!empty($map[$fid])) { $featuredProducts[] = $map[$fid]; } }
}
$categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();
$freeShippingOffers = getFreeShippingProducts($pdo);
$cartData = getCartData($pdo, $_SESSION['cart'] ?? [], $_SESSION['cart_variations'] ?? []);

// Hero Styling
$heroClass = $heroMedia['type'] === 'color' ? 'hero hero--color' : 'hero hero--media';
$heroSectionStyle = ($heroMedia['type'] === 'color'
    ? 'background:' . htmlspecialchars($heroMedia['color']) . ';'
    : 'background:#2563eb;') . ' --hero-overlay-strong:' . $heroOverlayStrong . '; --hero-overlay-soft:' . $heroOverlaySoft . ';';
$heroMediaStyle = 'background:' . htmlspecialchars($heroMedia['color']) . ';';
if ($heroMedia['type'] === 'image') {
    $heroMediaStyle = 'background-image:url(' . htmlspecialchars($heroMedia['src']) . '); background-size:cover; background-position:center;';
} elseif ($heroMedia['type'] === 'video') {
    $heroMediaStyle = 'background:#000;';
}

$faviconSvg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ctext x='50%25' y='50%25' dy='.35em' text-anchor='middle' font-family='Arial, sans-serif' font-weight='900' font-size='60' fill='black'%3EC%3C/text%3E%3C/svg%3E";
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cukrinukas.lt – diabeto priemonės ir naujienos</title>

  <?php echo headerStyles($headerShadowIntensity ?? null); ?>

  <meta name="description" content="Cukrinukas.lt rasite gliukometrus, sensorius, juosteles, mažo GI užkandžius ir patarimus gyvenimui su diabetu.">
  <link rel="icon" type="image/svg+xml" href="<?php echo $faviconSvg; ?>">
  
  <style>
    /* ATNAUJINTA SPALVŲ PALETĖ IR IŠDĖSTYMAS */
    :root {
      --color-primary: #0b0b0b;
      --color-primary-dark: #050505;
      --color-gray: #565766;
      --color-light: #ffffff;
      --color-bg: #f7f7fb;
      --accent: #2563eb;
      --accent-hover: #1d4ed8;
      --accent-light: #eff6ff; 
      --surface: rgba(255, 255, 255, 0.95);
      --border: #e4e7ec;
      --muted: #52606d;
      --shadow-soft: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: #1f2937;
      background: var(--color-bg);
      min-height: 100vh;
      font-family: 'Inter', system-ui, sans-serif;
    }
    a { text-decoration: none; color: inherit; transition: color .2s; }
    img { max-width: 100%; display: block; }

    /* PAGRINDINIAI KONTEINERIAI - VIENODI KRAŠTAI */
    .page-shell { position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; width: 100%; }
    
    .section-shell { 
        width: 100%;
        max-width: 1200px; /* Visiems vienodas plotis */
        margin: 0 auto; 
        padding: 0 20px; /* Visiems vienodas atstumas nuo krašto */
    }

    /* MYGTUKAI */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 10px;
      padding: 12px 18px; border-radius: 12px;
      border: 1px solid var(--color-primary);
      background: var(--color-primary); color: #fff;
      font-weight: 600; cursor: pointer;
      transition: transform .15s ease, background .15s ease;
      font-size: 15px;
    }
    .btn:hover { transform: translateY(-2px); background: #1f2937; }
    .btn.ghost { background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.5); box-shadow: none; }
    .btn.ghost:hover { background: rgba(255,255,255,0.3); }

    /* PILLS */
    .pill { 
        display: inline-flex; align-items: center; padding: 8px 14px; 
        border-radius: 999px; background: #fff; 
        color: var(--accent); font-weight: 600; 
        border: 1px solid var(--border);
        font-size: 14px;
        transition: all .2s;
    }
    .pill:hover { border-color: var(--accent); color: var(--accent-hover); }

    .section-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:24px; }
    .section-head h2 { margin:0; font-size:28px; letter-spacing:-0.01em; color: #0f172a; }

    /* HERO - SUMAŽINTAS AUKŠTIS */
    .hero {
      position: relative; width: 100%; margin: 0 0 54px;
      background: var(--accent); color: #fff;
      overflow: hidden; isolation: isolate;
    }
    .hero::after {
      content: ""; position: absolute; inset: 0;
      background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.2), transparent 45%),
                  linear-gradient(120deg, rgba(37,99,235,0.4), rgba(15,23,42,0.6));
      z-index: 1;
    }
    .hero-media { position: absolute; inset: 0; background: var(--accent); overflow: hidden; z-index: 0; }
    .media-embed { width:100%; height:100%; min-height:500px; background: var(--accent); }
    .media-embed video, .media-embed img { width:100%; height:100%; object-fit:cover; display:block; }

    .hero__content {
      position: relative; z-index: 2; max-width: 1200px; margin: 0 auto;
      /* PAKEISTA: Sumažintas padding viršuje ir apačioje */
      padding: 60px 22px 50px; 
      display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 32px; align-items: center;
    }
    .hero__copy h1 { margin: 0 0 12px; font-size: clamp(32px, 5vw, 44px); letter-spacing: -0.02em; color: #fff; }
    .hero__copy p { margin: 0 0 20px; color: #e0f2fe; line-height: 1.6; max-width: 500px; font-size: 17px; }
    .hero__actions { display:flex; gap:10px; flex-wrap:wrap; margin-top: 12px; }

    .glass-card {
      background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
      border-radius: 16px; padding: 20px; backdrop-filter: blur(10px);
      box-shadow: 0 16px 40px rgba(0,0,0,0.1);
    }
    .glass-card h3 { color:#fff; margin:0 0 8px; }
    .glass-card p { color:#e0f2fe; margin:0 0 12px; }
    .support-mini a { color:#fff; font-weight:700; text-decoration: underline; text-decoration-color: rgba(255,255,255,0.4); }

    /* PROMO CARDS */
    .promo-section { margin-bottom: 60px; }
    .promo-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:16px; }
    .promo-card { 
        background: #fff; border-radius: 18px; padding: 20px; 
        border: 1px solid var(--border); box-shadow: var(--shadow-soft); 
        display:flex; gap:14px; align-items:flex-start; 
    }
    .promo-card h3 { margin:0 0 6px; font-size: 17px; }
    .promo-card p { margin:0; color: var(--muted); line-height:1.5; font-size: 14px; }
    .promo-icon { 
        width: 44px; height: 44px; border-radius: 12px; 
        background: var(--accent-light); color: var(--accent);
        display: inline-flex; align-items: center; justify-content: center; 
        font-weight: 700; flex-shrink: 0;
    }

    /* STORYBAND - SUTVARKYTAS IŠDĖSTYMAS */
    .storyband { margin: 0 0 60px; }
    .storyband__layout { 
        background: linear-gradient(135deg, #eff6ff, #dbeafe); 
        border-radius: 22px; padding: 32px; box-shadow: var(--shadow-soft); 
        display:grid; 
        /* PAKEISTA: Daugiau vietos tekstui, kortelė užima kiek reikia */
        grid-template-columns: 1fr 320px; 
        gap: 40px; 
        border:1px solid #dbeafe; position:relative; overflow:hidden; align-items: center;
    }
    .metrics { display:flex; flex-wrap:wrap; gap:12px; margin-top:20px; }
    .metric { background:#fff; border:1px solid var(--border); padding:12px 18px; border-radius:12px; min-width:110px; }
    .metric strong { display:block; margin:0 0 2px; font-size:20px; color:#0f172a; }
    .metric span { color:var(--muted); font-size: 13px; }
    
    .storyband .card { 
        background: #fff; color:#0f172a; border-radius:16px; padding:24px; 
        box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid var(--border); 
        /* Kortelė kompaktiškesnė */
        width: 100%;
    }

    /* STORE SECTION */
    .store-section { margin-bottom: 60px; }
    .store-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap: 16px; }
    
    .product-card { 
        background: #fff; border-radius: 16px; overflow:hidden; 
        border: 1px solid var(--border); box-shadow: var(--shadow-soft); 
        display:flex; flex-direction:column; transition: transform .2s, border-color .2s;
    }
    .product-card:hover { transform: translateY(-4px); border-color: var(--accent); }
    
    .product-card img { width:100%; height:200px; object-fit:contain; padding: 16px; background: #fff; }
    .product-card__body { padding: 16px; display:flex; flex-direction:column; gap:8px; flex: 1; border-top: 1px solid #f3f4f6; }
    
    .badge { display:inline-flex; padding:4px 10px; border-radius:6px; background:var(--accent); color:#fff; font-size:11px; font-weight:700; text-transform: uppercase; width: fit-content; }
    
    .product-card__title { margin:0; font-size:16px; font-weight: 600; line-height: 1.4; }
    .product-card__title a { color: #111827; }
    .product-card__meta { margin:0; color:var(--muted); font-size:13px; line-height:1.5; }
    
    .product-card__actions { display:flex; align-items:center; justify-content:space-between; margin-top:auto; padding-top: 10px; }
    
    .action-btn {
        width: 36px; height: 36px; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: all .2s;
        background: #fff; border: 1px solid var(--border); color: #1f2937;
    }
    .action-btn:hover { border-color: var(--accent); color: var(--accent); background: #f0f9ff; }

    /* HIGHLIGHT SECTION */
    .highlight-section { margin: 0 0 60px; }
    .split-panel { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:24px; }
    
    .story-card { 
        background: #fff; border-radius: 20px; padding: 26px; 
        border:1px solid var(--border); box-shadow: var(--shadow-soft); 
    }
    .story-visual { 
        background: linear-gradient(135deg, #eff6ff, #dbeafe); 
        border-radius:20px; padding:24px; border:1px solid #dbeafe; 
        color:#0f172a; 
    }
    .story-row__bubble { background:#fff; padding:16px; border-radius:14px; box-shadow:0 10px 20px rgba(0,0,0,0.05); margin-bottom:14px; border:1px solid #eef2ff; }
    .story-row__floating { background:#fff; padding:14px 16px; border-radius:12px; box-shadow:0 10px 20px rgba(0,0,0,0.05); text-align:right; border:1px solid #eef2ff; margin-left: auto; width: fit-content; }

    /* FREE SHIPPING */
    .free-shipping { margin-bottom: 60px; }
    .free-shipping__box {
        background: #f0f9ff; border: 1px solid #bae6fd;
        border-radius: 20px; padding: 24px;
        display: flex; flex-direction: column; gap: 20px;
    }
    .free-shipping__header { display:flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .free-shipping__header h2 { margin:0; font-size: 22px; color: #0369a1; }
    .free-shipping__grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px;
    }
    .free-card {
        background: #fff; border-radius: 14px; padding: 14px;
        border: 1px solid #e0f2fe; transition: transform .2s;
        display: flex; align-items: center; gap: 12px;
    }
    .free-card:hover { transform: translateY(-2px); border-color: #7dd3fc; }
    .free-card img { width: 60px; height: 60px; object-fit: contain; border-radius: 8px; background: #fff; border:1px solid #f0f0f0; }
    .free-card__meta h3 { font-size: 14px; margin: 0 0 4px; line-height: 1.3; }
    .free-card__price { color: #0284c7; font-weight: 700; font-size: 15px; }

    /* TESTIMONIALS - PAKEISTA: 1 eilutė */
    .testimonials { 
        margin:0 0 60px; 
        background: linear-gradient(135deg, #eff6ff, #dbeafe); 
        border-radius:22px; padding:32px; 
        border:1px solid #dbeafe; 
    }
    .testimonial-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 20px; 
    }
    .testimonial { background:#fff; border-radius:16px; padding:20px; border:1px solid #e4e7ec; box-shadow:0 4px 12px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
    .testimonial__name { margin:0 0 4px; font-size:16px; font-weight: 700; color:#0f172a; }
    .testimonial__role { margin:0 0 10px; color:var(--muted); font-size:13px; }
    .testimonial__text { margin:0; line-height:1.6; color:#374151; font-size: 14px; flex-grow: 1; }

    /* SUPPORT BAND - PAKEISTA: Šviesus fonas */
    .support-band { 
        margin:0 0 60px; 
        background: linear-gradient(135deg, #eff6ff, #dbeafe); /* Šviesus mėlynas */
        color: #0f172a; /* Tamsus tekstas */
        border-radius:22px; padding:32px; 
        display:grid; grid-template-columns:1.1fr 1fr; gap:32px; 
        border: 1px solid #dbeafe;
    }
    .support-band h2 { margin:4px 0 10px; color: #0f172a; }
    .support-band__text { margin:0 0 16px; color: #475467; line-height:1.6; }
    .support-band__chips { display:flex; flex-wrap:wrap; gap:8px; }
    .support-band__chips .pill { background: #fff; color: var(--accent); border-color: #dbeafe; }
    
    .support-band__card { 
        background: #fff; color:#0f172a; border-radius:16px; 
        padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border);
    }
    .support-band__card .btn { 
        background: var(--accent); border-color: var(--accent); color:#fff; width: 100%;
    }
    .support-band__card .btn:hover { background: var(--accent-hover); }

    /* MEDIA QUERIES */
    @media (max-width: 900px) {
      .storyband__layout, .support-band { grid-template-columns: 1fr; gap: 24px; }
      .news-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .testimonial-grid { grid-template-columns: 1fr; } 
    }
    @media (max-width: 640px) {
      .hero__copy h1 { font-size: 32px; }
      .news-grid { grid-template-columns: 1fr; }
      .product-card__actions { flex-wrap: wrap; gap: 10px; }
    }
  </style>
</head>
    
<body>
  <?php renderHeader($pdo, 'home'); ?>

  <main class="page-shell">
    <section class="<?php echo $heroClass; ?>" style="<?php echo $heroSectionStyle; ?>">
      <div class="hero-media" style="<?php echo $heroMediaStyle; ?>">
        <?php if ($heroMedia['type'] === 'video' || $heroMedia['type'] === 'image'): ?>
          <div class="media-embed">
            <?php if ($heroMedia['type'] === 'video'): ?>
              <video src="<?php echo htmlspecialchars($heroMedia['src']); ?>" poster="<?php echo htmlspecialchars($heroMedia['poster']); ?>" autoplay muted loop playsinline controls></video>
            <?php else: ?>
              <img src="<?php echo htmlspecialchars($heroMedia['src']); ?>" alt="<?php echo htmlspecialchars($heroMedia['alt']); ?>">
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="hero__content">
        <div class="hero__copy">
          <h1><?php echo htmlspecialchars($heroTitle); ?></h1>
          <p><?php echo htmlspecialchars($heroBody); ?></p>
          <div class="hero__actions">
            <a class="btn" style="background:#fff; color:#1d4ed8; border-color:#fff;" href="<?php echo htmlspecialchars($heroCtaUrl); ?>"><?php echo htmlspecialchars($heroCtaLabel); ?></a>
          </div>
        </div>
        <div class="glass-stack">
          <div class="glass-card support-mini">
            <h3 style="margin:0 0 8px; font-size:18px;"><?php echo htmlspecialchars($supportBand['card_title']); ?></h3>
            <p style="font-size:14px; margin-bottom:12px; line-height:1.5;"><?php echo htmlspecialchars($supportBand['card_body']); ?></p>
            <a href="<?php echo htmlspecialchars($supportBand['card_cta_url']); ?>"><?php echo htmlspecialchars($supportBand['card_cta_label']); ?> →</a>
          </div>
        </div>
      </div>
    </section>

    <section class="section-shell promo-section">
      <div class="section-head">
        <h2>Greiti akcentai</h2>
      </div>
      <div class="promo-grid">
        <?php foreach ($promoCards as $card): ?>
          <article class="promo-card">
            <div class="promo-icon"><?php echo htmlspecialchars($card['icon']); ?></div>
            <div>
              <h3><?php echo htmlspecialchars($card['title']); ?></h3>
              <p><?php echo htmlspecialchars($card['body']); ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section-shell storyband">
      <div class="section-head">
        <h2><?php echo htmlspecialchars($storyband['title']); ?></h2>
        <a class="pill" href="<?php echo htmlspecialchars($storyband['cta_url']); ?>"><?php echo htmlspecialchars($storyband['cta_label']); ?> →</a>
      </div>
      <div class="storyband__layout">
        <div>
          <p style="margin:0 0 16px; color:#475467; line-height:1.7; font-size:16px;"><?php echo htmlspecialchars($storyband['body']); ?></p>
          <div class="metrics">
            <?php foreach ($storybandMetrics as $metric): ?>
              <div class="metric"><strong><?php echo htmlspecialchars($metric['value']); ?></strong><span><?php echo htmlspecialchars($metric['label']); ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card">
          <h3 style="margin:0 0 10px; color:#0f172a;"><?php echo htmlspecialchars($storyband['card_title']); ?></h3>
          <p style="margin:0; color:#4b5563; font-size:14px; line-height: 1.5;"><?php echo htmlspecialchars($storyband['card_body']); ?></p>
        </div>
      </div>
    </section>

    <section class="section-shell store-section" id="parduotuve">
      <div class="section-head">
        <h2 class="store-section__title">Populiariausios prekės</h2>
        <a class="pill" href="/products.php">Peržiūrėti katalogą →</a>
      </div>

      <div class="store-grid">
        <?php foreach ($featuredProducts as $product): ?>
          <?php $priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts); ?>
          <article class="product-card">
            <div style="position:relative;">
                <?php if (!empty($product['ribbon_text'])): ?>
                    <div style="position:absolute; top:12px; left:12px; background:var(--accent); color:#fff; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; z-index:2;"><?php echo htmlspecialchars($product['ribbon_text']); ?></div>
                <?php endif; ?>
                <a href="/product.php?id=<?php echo (int)$product['id']; ?>" aria-label="<?php echo htmlspecialchars($product['title']); ?>">
                  <img src="<?php echo htmlspecialchars($product['primary_image'] ?: $product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                </a>
            </div>
            
            <div class="product-card__body">
              <span class="badge" style="background:transparent; color:var(--accent); padding:0; box-shadow:none;"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></span>
              <h3 class="product-card__title"><a href="/product.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['title']); ?></a></h3>
              
              <div class="product-card__actions">
                <div>
                  <?php if ($priceDisplay['has_discount']): ?>
                    <div style="font-size:13px; color:#9ca3af; text-decoration:line-through;"><?php echo number_format($priceDisplay['original'], 2); ?> €</div>
                  <?php endif; ?>
                  <strong style="font-size:18px; color:#111827;"><?php echo number_format($priceDisplay['current'], 2); ?> €</strong>
                </div>
                
                <form method="post" style="display:flex; gap:8px;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                    
                    <button class="action-btn btn-cart-icon" type="submit" name="quantity" value="1" aria-label="Į krepšelį">
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
    </section>

    <section class="section-shell highlight-section">
      <div class="split-panel">
        <div class="story-card">
          <h3 style="margin:0 0 8px; color:#0f172a; font-size:22px;"><?php echo htmlspecialchars($storyRow['title']); ?></h3>
          <p style="margin:0 0 16px; line-height:1.6; color:#374151;"><?php echo htmlspecialchars($storyRow['body']); ?></p>
          <div class="story-card__pills">
            <?php foreach ($storyRow['pills'] as $pill): ?>
              <span class="pill"><?php echo htmlspecialchars($pill); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="story-visual">
          <div class="story-row__bubble">
            <strong style="display:block; margin-bottom:6px; color:#0f172a;"><?php echo htmlspecialchars($storyRow['bubble_title']); ?></strong>
            <p style="margin:0; line-height:1.5; font-size:14px; color:#4b5563;"><?php echo htmlspecialchars($storyRow['bubble_body']); ?></p>
          </div>
          <div class="story-row__floating">
            <strong style="display:block; color:#0f172a;"><?php echo htmlspecialchars($storyRow['floating_title']); ?></strong>
            <p style="margin:4px 0 0; font-size:13px; color:#4b5563;"><?php echo htmlspecialchars($storyRow['floating_body']); ?></p>
          </div>
        </div>
      </div>
    </section>

    <?php if ($freeShippingOffers): ?>
      <section class="section-shell free-shipping">
        <div class="free-shipping__box">
            <div class="free-shipping__header">
                <h2 style="margin:0;">Nemokamas pristatymas</h2>
                <div style="font-size:14px; color:#0c4a6e;">Perkant šias prekes pristatymas į paštomatus – 0 €</div>
            </div>
            
            <div class="free-shipping__grid">
              <?php foreach ($freeShippingOffers as $offer): ?>
                <?php $priceDisplay = buildPriceDisplay($offer, $globalDiscount, $categoryDiscounts); ?>
                <a href="/product.php?id=<?php echo (int)$offer['product_id']; ?>" class="free-card">
                    <img src="<?php echo htmlspecialchars($offer['primary_image'] ?: $offer['image_url']); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>">
                    <div class="free-card__meta">
                        <h3><?php echo htmlspecialchars($offer['title']); ?></h3>
                        <div class="free-card__price">
                          <?php echo number_format($priceDisplay['current'], 2); ?> €
                        </div>
                    </div>
                </a>
              <?php endforeach; ?>
            </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="section-shell testimonials">
      <div class="section-head">
        <h2 class="testimonials__title">Atsiliepimai</h2>
        <span class="pill">Klientų istorijos</span>
      </div>
      <div class="testimonial-grid">
        <?php foreach ($testimonials as $t): ?>
          <article class="testimonial">
            <h3 class="testimonial__name"><?php echo htmlspecialchars($t['name']); ?></h3>
            <p class="testimonial__role"><?php echo htmlspecialchars($t['role']); ?></p>
            <p class="testimonial__text"><?php echo htmlspecialchars($t['text']); ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section-shell news-block" id="naujienos">
      <div class="section-head">
        <h2 class="news-block__title">Aktualijos ir patarimai</h2>
        <a class="pill" href="/news.php">Visos naujienos →</a>
      </div>
      <div class="news-grid">
        <?php foreach ($featuredNews as $news): ?>
          <article class="news-card">
            <a href="/news_view.php?id=<?php echo (int)$news['id']; ?>">
              <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
            </a>
            <div class="news-card__body">
              <h3 class="news-card__title"><a href="/news_view.php?id=<?php echo (int)$news['id']; ?>"><?php echo htmlspecialchars($news['title']); ?></a></h3>
              <p class="news-card__meta"><?php echo date('Y-m-d', strtotime($news['created_at'])); ?></p>
              <?php 
                $excerpt = trim($news['summary'] ?? '');
                if (!$excerpt) $excerpt = strip_tags($news['body']);
                if (mb_strlen($excerpt) > 400) $excerpt = mb_substr($excerpt, 0, 400) . '...';
              ?>
              <p class="news-card__excerpt"><?php echo htmlspecialchars($excerpt); ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section-shell support-band">
      <div>
        <h2><?php echo htmlspecialchars($supportBand['title']); ?></h2>
        <p class="support-band__text"><?php echo htmlspecialchars($supportBand['body']); ?></p>
        <div class="support-band__chips">
          <?php foreach ($supportBand['chips'] as $chip): ?>
            <span class="pill"><?php echo htmlspecialchars($chip); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="support-band__card">
        <strong style="display:block; margin:0 0 10px; color:#0f172a; font-size:18px;"><?php echo htmlspecialchars($supportBand['card_title']); ?></strong>
        <p style="margin:0 0 16px; line-height:1.6; font-size:14px; color:#4b5563;"><?php echo htmlspecialchars($supportBand['card_body']); ?></p>
        <a class="btn" href="<?php echo htmlspecialchars($supportBand['card_cta_url']); ?>"><?php echo htmlspecialchars($supportBand['card_cta_label']); ?></a>
      </div>
    </section>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
