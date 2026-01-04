<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

// Simple PHP page that renders the provided head metadata and a static landing layout
$headerShadowIntensity = 70;
$GLOBALS['headerShadowIntensity'] = $headerShadowIntensity;

$pdo = getPdo();
ensureUsersTable($pdo);
ensureNewsTable($pdo);
ensureCategoriesTable($pdo);
ensureProductsTable($pdo);
ensureOrdersTables($pdo);
ensureRecipesTable($pdo);
ensureAdminAccount($pdo);
ensureSiteContentTable($pdo);
ensureFooterLinks($pdo);
seedStoreExamples($pdo);
seedNewsExamples($pdo);
seedRecipeExamples($pdo);
$siteContent = getSiteContent($pdo);
$globalDiscount = getGlobalDiscount($pdo);
$categoryDiscounts = getCategoryDiscounts($pdo);
$heroShadowIntensity = max(0, min(100, (int)($siteContent['hero_shadow_intensity'] ?? 70)));
$heroOverlayStrong = round(0.75 * ($heroShadowIntensity / 100), 3);
$heroOverlaySoft = round(0.17 * ($heroShadowIntensity / 100), 3);
$heroMedia = [
    'type' => $siteContent['hero_media_type'] ?? 'image',
    'color' => $siteContent['hero_media_color'] ?? '#829ed6',
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
$heroTitle = $siteContent['hero_title'] ?? 'Pagalba kasdienei diabeto priežiūrai';
$heroBody = $siteContent['hero_body'] ?? 'Gliukometrai, sensoriai, maži GI užkandžiai ir bendruomenės patarimai – viskas vienoje vietoje, kad matavimai būtų ramūs.';
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
    'badge' => $siteContent['storyband_badge'] ?? 'Nuo gliukometro iki lėkštės',
    'title' => $siteContent['storyband_title'] ?? 'Kasdieniai sprendimai diabetui',
    'body' => $siteContent['storyband_body'] ?? 'Sudėjome priemones ir žinias, kurios palengvina cukrinio diabeto priežiūrą: nuo matavimų iki receptų ir užkandžių.',
    'cta_label' => $siteContent['storyband_cta_label'] ?? 'Rinktis rinkinį',
    'cta_url' => $siteContent['storyband_cta_url'] ?? '/products.php',
    'card_eyebrow' => $siteContent['storyband_card_eyebrow'] ?? 'Reklaminis akcentas',
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
    'eyebrow' => $siteContent['storyrow_eyebrow'] ?? 'Dienos rutina',
    'title' => $siteContent['storyrow_title'] ?? 'Stebėjimas, užkandžiai ir ramybė',
    'body' => $siteContent['storyrow_body'] ?? 'Greitai pasiekiami sensorių pleistrai, cukraus kiekį subalansuojantys batonėliai ir starterių rinkiniai, kad kiekviena diena būtų šiek tiek lengvesnė.',
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
    'meta' => $siteContent['support_meta'] ?? 'Bendruomenė',
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
$footerContent = [
    'brand_title' => $siteContent['footer_brand_title'] ?? 'Cukrinukas.lt',
    'brand_body' => $siteContent['footer_brand_body'] ?? 'Diabeto priemonės, mažo GI užkandžiai ir kasdienių sprendimų gidai vienoje vietoje.',
    'brand_pill' => $siteContent['footer_brand_pill'] ?? 'Kasdienė priežiūra',
    'quick_title' => $siteContent['footer_quick_title'] ?? 'Greitos nuorodos',
    'help_title' => $siteContent['footer_help_title'] ?? 'Pagalba',
    'contact_title' => $siteContent['footer_contact_title'] ?? 'Kontaktai',
];
$footerLinks = getFooterLinks($pdo);


$featuredNews = $pdo->query('SELECT id, title, image_url, body, created_at FROM news WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 4')->fetchAll();
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
$heroClass = $heroMedia['type'] === 'color' ? 'hero hero--color' : 'hero hero--media';
$heroSectionStyle = ($heroMedia['type'] === 'color'
    ? 'background:' . htmlspecialchars($heroMedia['color']) . ';'
    : 'background:#829ed6;') . ' --hero-overlay-strong:' . $heroOverlayStrong . '; --hero-overlay-soft:' . $heroOverlaySoft . ';';
$heroMediaStyle = 'background:' . htmlspecialchars($heroMedia['color']) . ';';
if ($heroMedia['type'] === 'image') {
    $heroMediaStyle = 'background-image:url(' . htmlspecialchars($heroMedia['src']) . '); background-size:cover; background-position:center;';
} elseif ($heroMedia['type'] === 'video') {
    $heroMediaStyle = 'background:#000;';
}
?>
<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="generator" content="Hostinger Website Builder">
<title>Cukrinukas.lt – diabeto priemonės ir žinios</title>
  <?php echo headerStyles($headerShadowIntensity ?? null); ?>
<meta name="description" content="Cukrinukas.lt rasite gliukometrus, sensorius, juosteles, mažo GI užkandžius ir patarimus gyvenimui su diabetu.">
<link rel="icon" href="https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=48,h=48,fit=crop,f=png/YZ9VqrOVpxS49M9g/favicon-32x32-YbNvQ81bK5S5reOP.png">
<link rel="apple-touch-icon" href="https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=48,h=48,fit=crop,f=png/YZ9VqrOVpxS49M9g/favicon-32x32-YbNvQ81bK5S5reOP.png">
<meta content="https://cukrinukas.lt/" property="og:url">
<link rel="canonical" href="https://cukrinukas.lt/">
<meta content="Cukrinukas.lt – diabeto priemonės ir žinios" property="og:title">
<meta name="twitter:title" content="Cukrinukas.lt – diabeto priemonės ir žinios">
<meta content="website" property="og:type">
<meta property="og:description" content="Gliukometrų, sensorių ir subalansuotos mitybos priemonių parduotuvė su naujienomis apie diabetą.">
<meta name="twitter:description" content="Gliukometrų, sensorių ir subalansuotos mitybos priemonių parduotuvė su naujienomis apie diabetą.">
<meta property="og:site_name" content="cukrinukas.lt">
<meta name="keywords" content="monetos, banknotai, numizmatika">
<meta content="https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=1200,h=630,fit=crop,f=jpeg/YZ9VqrOVpxS49M9g/logo-m2Wp732ga7FolMLe.png" property="og:image">
<meta content="https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=1200,h=630,fit=crop,f=jpeg/YZ9VqrOVpxS49M9g/logo-m2Wp732ga7FolMLe.png" name="twitter:image">
<meta content="" property="og:image:alt">
<meta content="" name="twitter:image:alt">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect">
<link rel="preconnect">
<link rel="alternate" hreflang="x-default" href="https://cukrinukas.lt/">
<link rel="stylesheet" href="/_astro-1733393565931/_slug_.DlClk9-n.css">
<style>
:root {
  --color-primary: #0b0b0b;
  --color-primary-dark: #050505;
  --color-gray: #565766;
  --color-light: #ffffff;
  --color-bg: #f7f7fb;
  --accent: #829ed6;
  --surface: rgba(255, 255, 255, 0.9);
  --shadow-soft: 0 20px 60px rgba(0, 0, 0, 0.08);
}

* { box-sizing: border-box; }
body {
  margin: 0;
  color: var(--text-color);
  background: var(--color-bg);
  min-height: 100vh;
}
a { text-decoration: none; color: inherit; }
img { max-width: 100%; display: block; }

.page-shell {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.page-shell > * {
  width: 100%;
}

.section-shell {
  width: min(1200px, 100%);
  margin: 0 auto;
  padding: 0 22px;
}

.section-shell > * {
  width: 100%;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 14px;
  border: 1px solid var(--color-primary);
  background: var(--color-primary);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 14px 36px rgba(0,0,0,0.22);
  transition: transform .15s ease, box-shadow .15s ease;
}

.btn:hover { transform: translateY(-2px); box-shadow: 0 18px 46px rgba(0,0,0,0.28); }
.btn.ghost { background: rgba(255,255,255,0.16); color: #fff; border-color: rgba(255,255,255,0.45); box-shadow: none; }

.pill, .chip { display: inline-flex; align-items: center; padding: 9px 12px; border-radius: 999px; background: rgba(130,158,214,0.16); color: #0b0b0b; font-weight: 600; border: 1px solid rgba(130,158,214,0.28); }
.pill--ghost { background: rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.08); }

.section-head { display:flex; align-items:flex-end; justify-content:space-between; gap:14px; margin-bottom:18px; }
.section-head h2 { margin:0; font-size:32px; letter-spacing:-0.01em; }
.eyebrow { text-transform: uppercase; letter-spacing: 0.28em; font-size: 12px; font-weight:700; color:#4a4a55; }

.hero {
  position: relative;
  width: 100%;
  margin: 0 0 54px;
  background: #829ed6;
  color: #fff;
  overflow: hidden;
  isolation: isolate;
}

.hero::after {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.22), transparent 45%),
              radial-gradient(circle at 80% 0%, rgba(0,0,0,var(--hero-overlay-strong,0.48)), transparent 54%),
              linear-gradient(120deg, rgba(0,0,0,var(--hero-overlay-soft,0.22)), rgba(0,0,0,var(--hero-overlay-strong,0.55)));
  z-index: 1;
}

.hero-media {
  position: absolute;
  inset: 0;
  background: #829ed6;
  overflow: hidden;
  z-index: 0;
}

.media-embed { width:100%; height:100%; min-height:68vh; background:#829ed6; }
.media-embed video, .media-embed img { width:100%; height:100%; object-fit:cover; display:block; }

.hero__content {
  position: relative;
  z-index: 2;
  max-width: 1200px;
  margin: 0 auto;
  padding: 90px 22px 84px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 22px;
  align-items: center;
}

.hero__copy h1 { margin: 0 0 12px; font-size: 44px; letter-spacing: -0.02em; }
.hero__copy p { margin: 0 0 16px; color: #edf1ff; line-height: 1.7; max-width: 640px; }
.hero__actions { display:flex; gap:10px; flex-wrap:wrap; margin: 12px 0; }

.glass-stack { display:grid; gap:12px; }
.glass-card {
  background: rgba(255,255,255,0.14);
  border: 1px solid rgba(255,255,255,0.28);
  border-radius: 16px;
  padding: 16px 18px;
  backdrop-filter: blur(8px);
  box-shadow: 0 16px 40px rgba(0,0,0,0.24);
}
.glass-card h3 { color:#fff; margin:0 0 8px; }
.glass-card p, .glass-card span { color:#f4f6ff; margin:0; }

.metric-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); gap:10px; margin-top:8px; }
.metric-card { background: rgba(255,255,255,0.16); border-radius:12px; padding:10px 12px; border:1px solid rgba(255,255,255,0.22); }
.metric-card strong { display:block; color:#fff; font-size:18px; }

.quote-card blockquote { margin:0; font-size:15px; line-height:1.6; color:#f7f8ff; }
.quote-card footer { margin-top:8px; color:#e4e8ff; font-weight:700; }

.support-mini { background: rgba(0,0,0,0.55); border:1px solid rgba(255,255,255,0.18); }
.support-mini a { color:#fff; font-weight:700; }

.promo-section { padding: 10px 0 40px; }
.promo-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px; }
.promo-card { background: var(--surface); border-radius: 18px; padding: 16px; border:1px solid #e1e4f0; box-shadow: var(--shadow-soft); display:flex; gap:12px; align-items:flex-start; position:relative; overflow:hidden; }
.promo-card::after { content:""; position:absolute; width:120px; height:120px; background:rgba(130,158,214,0.16); border-radius:50%; right:-40px; bottom:-60px; }
.promo-card h3 { margin:0 0 6px; }
.promo-card p { margin:0; color:#3f4150; line-height:1.5; }
.promo-icon { width:48px; height:48px; padding:6px; border-radius:12px; background:rgba(130,158,214,0.18); display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:#0b0b0b; position:relative; z-index:1; }

.storyband { margin: 0 0 60px; }
.storyband__layout { background: linear-gradient(135deg, #f1ecff, #e7f5ff); border-radius: 22px; padding: 24px; box-shadow: var(--shadow-soft); display:grid; grid-template-columns: 1.3fr 1fr; gap: 18px; border:1px solid #e5e7eb; position:relative; overflow:hidden; }
.storyband__layout::before { content:""; position:absolute; width:240px; height:240px; background:rgba(124, 58, 237, 0.08); border-radius:60% 40% 70% 40%; right:-90px; top:-70px; filter:blur(2px); }
.storyband__badge { display:inline-flex; padding:9px 14px; border-radius:999px; background:#fff; color:#0f172a; font-weight:700; letter-spacing:0.2px; border:1px solid #e5e7eb; box-shadow:0 12px 26px rgba(0,0,0,0.08); }
.metrics { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
.metric { background:#fff; border:1px solid #e4e7ec; padding:12px 14px; border-radius:12px; min-width:120px; box-shadow: 0 10px 24px rgba(0,0,0,0.06); }
.metric strong { display:block; margin:0 0 4px; font-size:20px; color:#0f172a; }
.metric span { color:#4a4a55; }
.storyband .card { background: #fff; color:#0f172a; border-radius:16px; padding:20px; box-shadow:0 18px 38px rgba(0,0,0,0.12); position:relative; z-index:1; border:1px solid #e5e7eb; }
.storyband .card a { color:#fff; font-weight:700; }
.storyband .card .btn { background: linear-gradient(135deg, #4338ca, #7c3aed); border-color: transparent; box-shadow: 0 16px 44px rgba(124, 58, 237, 0.25); }
.storyband .card .btn:hover { box-shadow: 0 18px 60px rgba(67, 56, 202, 0.35); }

.store-section { margin-bottom: 60px; }
.store-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap: 16px; }
.product-card { background: #fff; border-radius: 18px; overflow:hidden; border:1px solid #e7eaf5; box-shadow: var(--shadow-soft); display:flex; flex-direction:column; position:relative; }
.product-card::after { content:""; position:absolute; width:130px; height:130px; background:rgba(130,158,214,0.14); border-radius:50%; top:-60px; right:-60px; }
.product-card img { width:100%; height:200px; object-fit:cover; }
.product-card__body { padding: 16px; display:flex; flex-direction:column; gap:10px; position:relative; z-index:1; background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%); }
.badge { display:inline-flex; padding:7px 10px; border-radius:999px; background:#829ed6; color:#fff; font-size:12px; font-weight:700; letter-spacing:0.2px; box-shadow:0 10px 22px rgba(0,0,0,0.12); }
.product-card__title { margin:0; font-size:19px; letter-spacing:-0.01em; }
.product-card__meta { margin:0; color:#4f5160; line-height:1.5; }
.product-card__actions { display:flex; align-items:flex-end; justify-content:space-between; margin-top:auto; }

.highlight-section { margin: 0 0 60px; }
.split-panel { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:16px; }
.story-card { background: #fff; color:#0f172a; border-radius: 18px; padding: 22px; position:relative; overflow:hidden; box-shadow: 0 18px 42px rgba(0,0,0,0.12); border:1px solid #e5e7eb; }
.story-card::after { content:""; position:absolute; width:200px; height:200px; background:rgba(124, 58, 237, 0.08); border-radius:50%; bottom:-80px; right:-100px; }
.story-card h3, .story-card p { color:#0f172a; position:relative; z-index:1; }
.story-card__pills { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; position:relative; z-index:1; }
.story-card__pills .pill { background:#fff; color:#0f172a; border-color:#e5e7eb; box-shadow:0 10px 26px rgba(0,0,0,0.08); }
.story-visual { background: linear-gradient(135deg, #f1ecff, #e7f5ff); border-radius:18px; padding:20px; border:1px solid #e5e7eb; box-shadow: 0 18px 42px rgba(0,0,0,0.12); position:relative; overflow:hidden; color:#0f172a; }
.story-visual::after { content:""; position:absolute; width:160px; height:160px; background:rgba(67, 56, 202, 0.12); border-radius:40% 60% 50% 70%; top:-60px; right:-30px; filter:blur(2px); }
.story-row__bubble { background:#fff; color:#0f172a; padding:16px; border-radius:14px; box-shadow:0 16px 36px rgba(0,0,0,0.12); margin-bottom:12px; border:1px solid #e4e7ec; }
.story-row__bubble p { color:#1f2937; }
.story-row__floating { background:#fff; color:#0f172a; padding:14px 16px; border-radius:12px; box-shadow:0 16px 36px rgba(0,0,0,0.12); text-align:right; position:relative; border:1px solid #e4e7ec; }
.story-row__floating p { margin:6px 0 0; color:#1f2937; }

.testimonials { margin:0 0 60px; background: linear-gradient(135deg, #f1ecff, #e7f5ff); color:#0f172a; border-radius:22px; padding:26px 24px 30px; box-shadow:0 18px 38px rgba(0,0,0,0.12); border:1px solid #e5e7eb; }
.testimonial-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:12px; }
.testimonial { background:#fff; border-radius:16px; padding:16px; border:1px solid #e4e7ec; box-shadow:0 12px 24px rgba(0,0,0,0.08); position:relative; overflow:hidden; }
.testimonial::after { content:""; position:absolute; width:90px; height:90px; background:rgba(124, 58, 237, 0.08); border-radius:50%; top:-30px; right:-30px; }
.testimonial__name { margin:0 0 4px; font-size:18px; color:#0f172a; position:relative; z-index:1; }
.testimonial__role { margin:0 0 10px; color:#52606d; font-size:14px; position:relative; z-index:1; }
.testimonial__text { margin:0; line-height:1.6; position:relative; z-index:1; color:#1f2937; }
.free-shipping { margin: 10px 0 50px; display:grid; gap:18px; }
.free-shipping__banner { background: linear-gradient(135deg, rgba(6, 182, 212, 0.12), rgba(67, 56, 202, 0.18)); border:1px solid rgba(67,56,202,0.14); padding:16px 18px; border-radius:18px; display:flex; justify-content:space-between; gap:14px; align-items:center; flex-wrap:wrap; box-shadow:0 20px 50px rgba(67,56,202,0.12); }
.free-shipping__grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:14px; }
.free-card { background:#fff; border:1px solid #e4e7ec; border-radius:16px; padding:12px; display:grid; gap:10px; box-shadow:0 14px 30px rgba(0,0,0,0.06); }
.free-card img { width:100%; height:150px; object-fit:cover; border-radius:12px; }
.free-card__meta { display:flex; justify-content:space-between; align-items:center; gap:8px; }
.free-card__title { margin:0; font-size:15px; }
.free-card__price { text-align:right; }
.free-card__price .original { display:block; font-size:12px; color:#6b7280; text-decoration:line-through; }
.free-card__price strong { display:block; font-size:16px; }
.free-card .badge { background:#ecfeff; color:#0ea5e9; border-color:#bae6fd; }

.news-block { margin:0 0 60px; background: var(--surface); border-radius:22px; padding:24px; box-shadow: var(--shadow-soft); border:1px solid #e3e7f4; }
.news-block__header { display:flex; align-items:flex-end; justify-content:space-between; gap:14px; margin-bottom:18px; }
.news-block__title { margin:0; font-size:30px; display:flex; align-items:center; gap:10px; }
.news-card { background:#fff; border-radius:18px; overflow:hidden; box-shadow: var(--shadow-soft); border:1px solid #e6e9f4; display:flex; flex-direction:column; position:relative; }
.news-card img { width:100%; height:190px; object-fit:cover; }
.news-card__body { padding:16px; display:flex; flex-direction:column; gap:8px; }
.news-card__title { margin:0; font-size:18px; letter-spacing:-0.01em; }
.news-card__meta { margin:0; color:#4a4a55; font-size:13px; }
.news-card__excerpt { margin:0; color:#4f5160; line-height:1.6; }
.news-card__meta, .news-card__excerpt { position:relative; z-index:1; }
.news-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; }

.support-band { margin:0 0 60px; background: linear-gradient(135deg, #7a95cf, #8fb2eb); color:#fff; border-radius:22px; padding:22px; display:grid; grid-template-columns:1.1fr 1fr; gap:16px; box-shadow:0 22px 46px rgba(0,0,0,0.22); position:relative; overflow:hidden; }
.support-band::before { content:""; position:absolute; width:200px; height:200px; background:rgba(255,255,255,0.14); border-radius:50%; left:-60px; bottom:-80px; }
.support-band h2 { margin:4px 0 10px; color:#fff; }
.support-band__text { margin:0 0 12px; color:#f8fbff; line-height:1.6; }
.support-band__chips { display:flex; flex-wrap:wrap; gap:10px; }
.support-band__card { background: linear-gradient(135deg, #f1ecff, #e7f5ff); color:#0f172a; border-radius:16px; padding:18px; box-shadow:0 18px 32px rgba(0,0,0,0.12); border:1px solid #e5e7eb; position:relative; z-index:1; }
.support-band__card strong { color:#111827; }
.support-band__card a { color:#4338ca; font-weight:700; }
.support-band__card .btn { background: linear-gradient(135deg, #4338ca, #7c3aed); border-color: transparent; box-shadow: 0 16px 44px rgba(124, 58, 237, 0.25); color:#fff; }
.support-band__card .btn:hover { box-shadow: 0 18px 60px rgba(67, 56, 202, 0.35); }



.footer { background:#0b0b0b; color:#fff; padding:26px 22px 32px; margin-top:40px; }
.footer-grid { max-width:1200px; margin:0 auto; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:18px; }
.footer h3, .footer h4 { color:#fff; margin:0 0 10px; }
.footer p { margin:0 0 8px; color:#e2e6f5; }
.footer strong { color:#f5f6ff; }
.footer ul { list-style:none; padding:0; margin:0; display:grid; gap:6px; }
.footer a { color:#e2e6f5; text-decoration:none; }
.footer a:hover { color:#fff; }
.footer-pill { display:inline-flex; padding:8px 12px; border-radius:999px; background:rgba(255,255,255,0.12); color:#fff; border:1px solid rgba(255,255,255,0.26); }

@media (max-width: 900px) {
  .storyband__layout, .support-band { grid-template-columns: 1fr; }
  .hero__content { padding-top: 78px; }
  .news-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .hero__copy h1 { font-size: 36px; }
  .news-grid { grid-template-columns: 1fr; }
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
          <div class="eyebrow">Kas yra Cukrinukas?</div>
          <h1><?php echo htmlspecialchars($heroTitle); ?></h1>
          <p><?php echo htmlspecialchars($heroBody); ?></p>
          <div class="hero__actions">
            <a class="btn" href="<?php echo htmlspecialchars($heroCtaUrl); ?>"><?php echo htmlspecialchars($heroCtaLabel); ?></a>
          </div>
        </div>
        <div class="glass-stack">
          <div class="glass-card support-mini">
            <p class="eyebrow" style="color:#f2f4ff;"><?php echo htmlspecialchars($supportBand['card_meta']); ?></p>
            <h3 style="margin:4px 0 8px; color:#fff; "><?php echo htmlspecialchars($supportBand['card_title']); ?></h3>
            <p style="margin:0 0 10px; line-height:1.5; color:#f2f4ff; "><?php echo htmlspecialchars($supportBand['card_body']); ?></p>
            <a href="<?php echo htmlspecialchars($supportBand['card_cta_url']); ?>"><?php echo htmlspecialchars($supportBand['card_cta_label']); ?> →</a>
          </div>
        </div>
      </div>
    </section>

    <section class="section-shell promo-section">
      <div class="section-head">
        <div>
          <div class="eyebrow">Kasdieniams pasirinkimams</div>
          <h2>Greiti akcentai</h2>
        </div>
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
        <div>
          <div class="eyebrow"><?php echo htmlspecialchars($storyband['badge']); ?></div>
          <h2><?php echo htmlspecialchars($storyband['title']); ?></h2>
        </div>
        <a class="pill" href="<?php echo htmlspecialchars($storyband['cta_url']); ?>"><?php echo htmlspecialchars($storyband['cta_label']); ?> →</a>
      </div>
      <div class="storyband__layout">
        <div>
          <p style="margin:0 0 12px; color:#3f4150; line-height:1.7;"><?php echo htmlspecialchars($storyband['body']); ?></p>
          <div class="metrics">
            <?php foreach ($storybandMetrics as $metric): ?>
              <div class="metric"><strong><?php echo htmlspecialchars($metric['value']); ?></strong><span><?php echo htmlspecialchars($metric['label']); ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card">
          <p style="margin:0 0 8px; color:#4338ca; "><?php echo htmlspecialchars($storyband['card_eyebrow']); ?></p>
          <h3 style="margin:0 0 10px; color:#0f172a; "><?php echo htmlspecialchars($storyband['card_title']); ?></h3>
          <p style="margin:0 0 14px; color:#1f2937; "><?php echo htmlspecialchars($storyband['card_body']); ?></p>
          <a class="btn" href="<?php echo htmlspecialchars($storyband['cta_url']); ?>"><?php echo htmlspecialchars($storyband['cta_label']); ?></a>
        </div>
      </div>
    </section>

    <section class="section-shell store-section" id="parduotuve">
      <div class="section-head">
        <div>
          <div class="eyebrow">Parduotuvė</div>
          <h2 class="store-section__title">Populiariausios prekės</h2>
        </div>
        <a class="pill" href="/products.php">Peržiūrėti katalogą →</a>
      </div>

      <div class="store-grid">
        <?php foreach ($featuredProducts as $product): ?>
          <?php $priceDisplay = buildPriceDisplay($product, $globalDiscount, $categoryDiscounts); ?>
          <article class="product-card">
            <?php $cardImage = $product['primary_image'] ?: $product['image_url']; ?>
            <a href="/product.php?id=<?php echo (int)$product['id']; ?>" aria-label="<?php echo htmlspecialchars($product['title']); ?>">
              <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
            </a>
            <div class="product-card__body">
              <span class="badge"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></span>
              <h3 class="product-card__title"><a href="/product.php?id=<?php echo (int)$product['id']; ?>" style="color:inherit; text-decoration:none;">&rarr; <?php echo htmlspecialchars($product['title']); ?></a></h3>
              <p class="product-card__meta"><?php echo htmlspecialchars(mb_substr($product['description'], 0, 120)); ?><?php echo mb_strlen($product['description']) > 120 ? '…' : ''; ?></p>
              <div class="product-card__actions">
                <div>
                  <?php if ($priceDisplay['has_discount']): ?>
                    <div style="font-size:13px; color:#6b6b7a; text-decoration:line-through;"><?php echo number_format($priceDisplay['original'], 2); ?> €</div>
                  <?php endif; ?>
                  <strong><?php echo number_format($priceDisplay['current'], 2); ?> €</strong>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section-shell highlight-section">
      <div class="split-panel">
        <div class="story-card">
          <p class="eyebrow" style="color:#4338ca; letter-spacing:0.18em;"><?php echo htmlspecialchars($storyRow['eyebrow']); ?></p>
          <h3 style="margin:0 0 8px; color:#0f172a; "><?php echo htmlspecialchars($storyRow['title']); ?></h3>
          <p style="margin:0 0 12px; line-height:1.6; color:#1f2937; "><?php echo htmlspecialchars($storyRow['body']); ?></p>
          <div class="story-card__pills">
            <?php foreach ($storyRow['pills'] as $pill): ?>
              <span class="pill" style="background:#fff; color:#0f172a; border-color:#e5e7eb; box-shadow:0 10px 26px rgba(0,0,0,0.08); "><?php echo htmlspecialchars($pill); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="story-visual">
          <div class="story-row__bubble">
            <p class="muted" style="margin:0 0 6px; color:#4338ca; "><?php echo htmlspecialchars($storyRow['bubble_meta']); ?></p>
            <strong style="display:block; margin-bottom:6px; color:#0f172a; "><?php echo htmlspecialchars($storyRow['bubble_title']); ?></strong>
            <p style="margin:0; line-height:1.6; color:#1f2937; "><?php echo htmlspecialchars($storyRow['bubble_body']); ?></p>
          </div>
          <div class="story-row__floating">
            <span class="muted" style="color:#4b5563;"><?php echo htmlspecialchars($storyRow['floating_meta']); ?></span>
            <strong style="color:#0f172a; "><?php echo htmlspecialchars($storyRow['floating_title']); ?></strong>
            <p style="margin:6px 0 0; color:#1f2937; "><?php echo htmlspecialchars($storyRow['floating_body']); ?></p>
          </div>
        </div>
      </div>
    </section>

    <?php if ($freeShippingOffers): ?>
      <section class="section-shell free-shipping">
        <div class="free-shipping__banner">
          <div>
            <div class="eyebrow" style="color:#0ea5e9;">Dviguba nauda</div>
            <h2 style="margin:4px 0 6px;">Išsirinkite vieną iš šių prekių – viso užsakymo pristatymą apmokėsime mes!</h2>
            <p style="margin:0; color:#0f172a; max-width:780px;">Kodėl mokėti už atvežimą? Įsidėkite vieną iš šių atrinktų produktų į krepšelį ir pristatymas visam užsakymui nekainuos nieko. Puiki proga išbandyti kažką naujo.</p>
          </div>
          <span class="pill" style="background:#ecfeff; color:#0ea5e9; border-color:#bae6fd;">Verta išbandyti!</span>
        </div>
        <div class="free-shipping__grid">
          <?php foreach ($freeShippingOffers as $offer): ?>
            <?php $priceDisplay = buildPriceDisplay($offer, $globalDiscount, $categoryDiscounts); ?>
            <?php $cardImage = $offer['primary_image'] ?: $offer['image_url']; ?>
            <article class="free-card">
              <a href="/product.php?id=<?php echo (int)$offer['product_id']; ?>" aria-label="<?php echo htmlspecialchars($offer['title']); ?>">
                <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($offer['title']); ?>">
              </a>
              <div class="free-card__meta">
                <div style="display:grid; gap:6px;">
                  <h3 class="free-card__title"><a href="/product.php?id=<?php echo (int)$offer['product_id']; ?>" style="color:inherit; text-decoration:none;">&rarr; <?php echo htmlspecialchars($offer['title']); ?></a></h3>
                </div>
                <div class="free-card__price">
                  <?php if ($priceDisplay['has_discount']): ?>
                    <span class="original"><?php echo number_format($priceDisplay['original'], 2); ?> €</span>
                  <?php endif; ?>
                  <strong><?php echo number_format($priceDisplay['current'], 2); ?> €</strong>
                </div>
              </div>
              <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;">
                <span style="color:#475467;">Įsidėkite šią prekę ir pristatymas taps nemokamas.</span>
                <a class="pill" href="/product.php?id=<?php echo (int)$offer['product_id']; ?>" style="background:linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; border-color:#c7d2fe;">Peržiūrėti</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="section-shell testimonials">
      <div class="section-head">
        <div>
          <div class="eyebrow">Patirtys</div>
          <h2 class="testimonials__title">Atsiliepimai</h2>
        </div>
        <span class="pill" style="background:#fff; color:#0f172a; border-color:#e4e7ec; box-shadow:0 10px 26px rgba(0,0,0,0.08);">Kasdienė ramybė su Cukrinukas.lt</span>
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
      <div class="news-block__header">
        <div>
          <div class="eyebrow">Kas naujo Cukrinukas?</div>
          <h2 class="news-block__title">Naujienos</h2>
        </div>
        <a class="pill" href="/news.php">Visos naujienos →</a>
      </div>
      <div class="news-grid">
        <?php foreach ($featuredNews as $news): ?>
          <article class="news-card">
            <a href="/news_view.php?id=<?php echo (int)$news['id']; ?>" aria-label="<?php echo htmlspecialchars($news['title']); ?>">
              <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
            </a>
            <div class="news-card__body">
              <h3 class="news-card__title"><a href="/news_view.php?id=<?php echo (int)$news['id']; ?>" style="color:inherit; text-decoration:none;"><?php echo htmlspecialchars($news['title']); ?></a></h3>
              <p class="news-card__meta"><?php echo date('Y-m-d', strtotime($news['created_at'])); ?></p>
              <?php $newsPlain = trim(strip_tags($news['body'])); ?>
              <p class="news-card__excerpt"><?php echo htmlspecialchars(mb_substr($newsPlain, 0, 180)); ?><?php echo mb_strlen($newsPlain) > 180 ? '…' : ''; ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section-shell support-band">
      <div>
        <p class="eyebrow" style="color:#e4e9ff;"><?php echo htmlspecialchars($supportBand['meta']); ?></p>
        <h2><?php echo htmlspecialchars($supportBand['title']); ?></h2>
        <p class="support-band__text"><?php echo htmlspecialchars($supportBand['body']); ?></p>
        <div class="support-band__chips">
          <?php foreach ($supportBand['chips'] as $chip): ?>
            <span class="pill" style="background:rgba(255,255,255,0.18); color:#fff; border-color:rgba(255,255,255,0.2); "><?php echo htmlspecialchars($chip); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="support-band__card">
        <p class="muted" style="margin:0 0 4px; color:#4338ca; "><?php echo htmlspecialchars($supportBand['card_meta']); ?></p>
        <strong style="display:block; margin:0 0 10px; color:#0f172a; "><?php echo htmlspecialchars($supportBand['card_title']); ?></strong>
        <p style="margin:0 0 12px; line-height:1.6; color:#1f2937; "><?php echo htmlspecialchars($supportBand['card_body']); ?></p>
        <a class="btn" href="<?php echo htmlspecialchars($supportBand['card_cta_url']); ?>"><?php echo htmlspecialchars($supportBand['card_cta_label']); ?></a>
      </div>
    </section>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
