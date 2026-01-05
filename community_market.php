<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureNavigationTable($pdo);

$user = currentUser();
$blocked = $user['id'] ? isCommunityBlocked($pdo, (int)$user['id']) : null;
$messages = [];
$errors = [];
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$categories = $pdo->query('SELECT * FROM community_listing_categories ORDER BY name ASC')->fetchAll();

if (!empty($_SESSION['flash_success'])) {
    $messages[] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$listingsSql = 'SELECT l.*, u.name, c.name AS category_name FROM community_listings l JOIN users u ON u.id = l.user_id LEFT JOIN community_listing_categories c ON c.id = l.category_id';
if ($categoryId > 0) {
    $listingsSql .= ' WHERE l.category_id = :cid';
}
$listingsSql .= ' ORDER BY l.created_at DESC';
$listingsStmt = $pdo->prepare($listingsSql);
if ($categoryId > 0) {
    $listingsStmt->bindValue(':cid', $categoryId, PDO::PARAM_INT);
}
$listingsStmt->execute();
$listings = $listingsStmt->fetchAll();

?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bendruomenės turgus | Cukrinukas</title>
  <?php echo headerStyles(); ?>
<style>
/* Bendras stilius */
:root { --bg: #f7f7fb; --card: #ffffff; --border: #e4e7ec; --text: #1f2937; --muted: #52606d; --accent: #2563eb; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
a { color:inherit; text-decoration:none; }

.page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display: grid; gap: 28px; }

/* Hero sekcija */
.hero {
  padding: 26px; border-radius: 28px; background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border: 1px solid #e5e7eb; box-shadow: 0 18px 48px rgba(0,0,0,0.08);
  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 22px;
}
.hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; padding:10px 14px; border-radius:999px; font-weight:700; font-size: 15px; color: #0f172a; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
.hero h1 { margin: 10px 0 8px; font-size: clamp(26px, 5vw, 36px); color: #0f172a; }
.hero p { margin: 0; color: var(--muted); line-height: 1.6; max-width: 640px; }

/* Mygtukai */
.btn-large { padding: 11px 24px; border-radius: 12px; border: 1px solid #1d4ed8; background: #fff; color: #1d4ed8; font-weight: 600; transition: all .2s; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.btn-large:hover { background: #1d4ed8; color: #fff; }

.card-container { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

/* Skelbimų tinklelis */
.cards-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-top: 20px; }
.listing-card { 
    background: #fff; 
    border: 1px solid var(--border); 
    border-radius: 16px; 
    padding: 16px; 
    display: flex; gap: 14px; 
    transition: transform .2s, border-color .2s; 
    text-decoration: none; color: inherit; 
    align-items: flex-start;
}
.listing-card:hover { transform: translateY(-4px); border-color: var(--accent); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1); }

.thumb { width:80px; height:80px; border-radius:12px; object-fit:cover; background:#f3f4f6; display:flex; align-items:center; justify-content:center; font-weight:600; color:var(--muted); flex-shrink:0; font-size: 12px; border: 1px solid var(--border); }
.meta { font-size:13px; color:var(--muted); margin-top: 4px; }
.price { font-weight:800; font-size:16px; color: var(--text); }
.sold { color:#dc2626; font-weight:800; font-size:15px; }

/* Filtrai (Chips) */
.filter-row { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-top: 16px; }
.chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 99px;
  background: #fff; border: 1px solid var(--border);
  font-weight: 600; color: var(--muted); cursor: pointer; transition: all .2s;
  white-space: nowrap; user-select: none; text-decoration: none; font-size: 13px;
}
.chip:hover, .chip.active {
  border-color: var(--accent); color: var(--accent); background: #f0f9ff;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
}

.alert { border-radius:12px; padding:12px; margin-bottom: 12px; }
.alert-success { background:#ecfdf5; border:1px solid #a7f3d0; color: #065f46; }
.alert-error { background:#fef2f2; border:1px solid #fecaca; color: #991b1b; }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
  
<div class="page">
  <section class="hero">
    <div style="flex:1; min-width: 300px;">
        <div class="hero__pill">🛍️ Bendruomenės turgus</div>
        <h1>Parduok, mainyk arba rask</h1>
        <p>Skaidrus kainų nurodymas ir pagarbus bendravimas – pagrindinės taisyklės. Čia skelbimai tarp bendruomenės narių.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?php if ($user['id'] && !$blocked): ?>
          <a class="btn-large" href="/community_listing_new.php">Naujas skelbimas</a>
        <?php else: ?>
          <a class="btn-large" href="/login.php">Prisijunkite kelti</a>
        <?php endif; ?>
        <a class="btn-large" href="/community_discussions.php" style="border-color:var(--border); color:var(--text);">Į diskusijas</a>
    </div>
  </section>

  <?php if (!empty($messages) || !empty($errors) || $blocked): ?>
    <div>
        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-success">&check; <?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error">&times; <?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
        <?php if ($blocked): ?>
            <div class="alert alert-error">Jūsų prieiga prie bendruomenės apribota iki <?php echo htmlspecialchars($blocked['banned_until'] ?? 'neribotai'); ?>.</div>
        <?php endif; ?>
    </div>
  <?php endif; ?>

  <section class="card-container">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0; font-size: 20px;">Naujausi pasiūlymai</h2>
        <p style="margin:4px 0 0 0; color:var(--muted); font-size:14px;">Aktyvūs bendruomenės skelbimai.</p>
      </div>
      <div>
        <span class="chip" style="cursor:default; hover:none;">Skelbimų: <?php echo count($listings); ?></span>
      </div>
    </div>

    <div class="filter-row">
      <a class="chip <?php echo $categoryId === 0 ? 'active' : ''; ?>" href="/community_market.php">Visos kategorijos</a>
      <?php foreach ($categories as $cat): ?>
        <a class="chip <?php echo $categoryId === (int)$cat['id'] ? 'active' : ''; ?>" href="/community_market.php?category=<?php echo (int)$cat['id']; ?>">
            #<?php echo htmlspecialchars($cat['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="cards-grid">
      <?php foreach ($listings as $listing): ?>
        <a class="listing-card" href="/community_listing.php?id=<?php echo (int)$listing['id']; ?>">
          <?php if ($listing['image_url']): ?>
            <img class="thumb" src="<?php echo htmlspecialchars($listing['image_url']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>">
          <?php else: ?>
            <div class="thumb">Nėra</div>
          <?php endif; ?>
          
          <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
              <div>
                <div style="font-weight:700;font-size:15px; color:#1f2937;">
                  <?php echo htmlspecialchars($listing['title']); ?>
                </div>
                <div class="meta">
                   <?php echo htmlspecialchars($listing['name']); ?>
                   <?php if ($listing['category_name']): ?>
                     <span style="color:var(--accent);"> • <?php echo htmlspecialchars($listing['category_name']); ?></span>
                   <?php endif; ?>
                </div>
              </div>
              <div style="text-align:right;">
                <?php if ($listing['status'] === 'sold'): ?>
                  <div class="sold">Parduota</div>
                <?php else: ?>
                  <div class="price">€<?php echo number_format((float)$listing['price'], 2); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <p style="margin:0; font-size:13px; color:var(--muted); line-height:1.4; max-height:40px; overflow:hidden;">
                <?php echo htmlspecialchars($listing['description']); ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$listings): ?>
        <div style="grid-column: 1/-1; text-align:center; padding: 40px; color: var(--muted);">Kol kas nėra skelbimų – sukurkite pirmąjį!</div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php renderFooter($pdo); ?>
</body>
</html>
