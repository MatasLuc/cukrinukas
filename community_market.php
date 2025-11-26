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
.page-wrap { max-width: 1100px; margin: 36px auto 70px; padding: 0 18px; display:flex; flex-direction:column; gap:18px; }
.hero-box { background: linear-gradient(135deg,#7896e9,#a3b8ff); color:#0b0b0b; border-radius:22px; padding:26px; box-shadow:0 22px 48px rgba(90,118,194,0.35); position:relative; overflow:hidden; }
.hero-box:after { content:''; position:absolute; width:180px; height:180px; border-radius:50%; background:rgba(255,255,255,0.24); top:-60px; right:-40px; filter:blur(6px); }
.cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px; }
.card { background:#fff; border:1px solid #e6e6ef; border-radius:16px; padding:14px; box-shadow:0 14px 32px rgba(0,0,0,0.06); display:flex; gap:12px; transition: transform .08s ease, box-shadow .12s ease; position:relative; overflow:hidden; text-decoration:none; color:inherit; }
.card:hover { transform: translateY(-2px); box-shadow:0 16px 34px rgba(0,0,0,0.08); }
.thumb { width:74px; height:74px; border-radius:12px; object-fit:cover; background:#e8edfa; display:flex; align-items:center; justify-content:center; font-weight:700; color:#6b6b7a; flex-shrink:0; }
.meta { font-size:12px; color:#4a4a55; }
.price { font-weight:800; font-size:16px; }
.sold { color:#c0392b; font-weight:800; font-size:15px; }
.badge { padding:6px 10px; border-radius:999px; background:rgba(0,0,0,0.08); font-weight:700; }
.chip { padding:6px 10px; border-radius:999px; background:#fff; border:1px solid #d7dbf3; font-weight:700; font-size:12px; }
.alert { border-radius:12px; padding:12px; }
.alert-success { background:#edf9f0; border:1px solid #b8e2c4; }
.alert-error { background:#fff1f1; border:1px solid #f3b7b7; }
.filter-row { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start; }
.filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:#f6f7fb; border:1px solid #e0e4f4; border-radius:999px; padding:6px 10px; flex:1; row-gap:6px; }
.filter-pill { padding:8px 12px; border-radius:14px; border:1px solid transparent; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,0.04); font-weight:700; font-size:13px; color:#232334; text-decoration:none; transition: all .12s ease; white-space:nowrap; }
.filter-pill:hover { transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,0.08); text-decoration:none; }
.filter-pill.active { background:linear-gradient(135deg,#5a76c2,#0b0b0b); color:#fff; border-color:rgba(255,255,255,0.26); box-shadow:0 14px 26px rgba(15,16,35,0.28); }
.filter-label { font-size:13px; font-weight:700; color:#5b5b6c; margin-right:4px; }
.filter-meta { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:8px; }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main class="page-wrap">
  <section class="hero-box">
    <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start;position:relative;z-index:2;">
      <div style="max-width:640px;">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <span class="badge">Bendruomenės turgus</span>
          <span class="badge" style="background:rgba(255,255,255,0.4);">Skelbimai tarp narių</span>
        </div>
        <h1 style="margin:10px 0 6px 0;">Parduok, mainyk arba rask tai, ko ieškai</h1>
        <p style="margin:0; max-width:640px;">Skaidrus kainų nurodymas ir pagarbus bendravimas – pagrindinės taisyklės. Jei prekė parduota, ji aiškiai pažymima.</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?php if ($user['id'] && !$blocked): ?>
          <a class="btn" href="/community_listing_new.php" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Naujas skelbimas</a>
        <?php else: ?>
          <a class="btn" href="/login.php" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Prisijunkite kelti</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/community_discussions.php" style="border-color:#0b0b0b;">Į diskusijas</a>
      </div>
    </div>
  </section>

  <?php foreach ($messages as $msg): ?>
    <div class="alert alert-success">&check; <?php echo htmlspecialchars($msg); ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">&times; <?php echo htmlspecialchars($err); ?></div>
  <?php endforeach; ?>
  <?php if ($blocked): ?>
    <div class="alert alert-error">Jūsų prieiga prie bendruomenės apribota iki <?php echo htmlspecialchars($blocked['banned_until'] ?? 'neribotai'); ?>.</div>
  <?php endif; ?>

  <section style="background:#fff;border:1px solid #e6e6ef;border-radius:18px;padding:16px;box-shadow:0 14px 32px rgba(0,0,0,0.06);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0;">Naujausi pasiūlymai</h2>
        <p class="muted" style="margin:4px 0 0 0;">Aktyvūs bendruomenės skelbimai ir aiškios kainos.</p>
      </div>
      <div class="filter-meta" style="margin-top:0;">
        <span class="chip">Skelbimų: <?php echo count($listings); ?></span>
      </div>
    </div>
    <div class="filter-row" style="margin-top:10px;">
      <div class="filter-bar">
        <span class="filter-label">Kategorija:</span>
        <a class="filter-pill <?php echo $categoryId === 0 ? 'active' : ''; ?>" href="/community_market.php">Visos</a>
        <?php foreach ($categories as $cat): ?>
          <a class="filter-pill <?php echo $categoryId === (int)$cat['id'] ? 'active' : ''; ?>" href="/community_market.php?category=<?php echo (int)$cat['id']; ?>">#<?php echo htmlspecialchars($cat['name']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cards" style="margin-top:12px;">
      <?php foreach ($listings as $listing): ?>
        <a class="card" href="/community_listing.php?id=<?php echo (int)$listing['id']; ?>">
          <?php if ($listing['image_url']): ?>
            <img class="thumb" src="<?php echo htmlspecialchars($listing['image_url']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>">
          <?php else: ?>
            <div class="thumb">Nėra</div>
          <?php endif; ?>
          <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
              <div>
                <div style="font-weight:700;font-size:15px;">
                  <?php echo htmlspecialchars($listing['title']); ?>
                </div>
                <div class="meta" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                  <span>Pardavėjas: <?php echo htmlspecialchars($listing['name']); ?></span>
                  <?php if ($listing['category_name']): ?>
                    <span class="badge" style="background:#f6f8ff;border:1px solid #e0e5f7;">#<?php echo htmlspecialchars($listing['category_name']); ?></span>
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
            <p class="muted" style="margin:0;line-height:1.5;max-height:60px;overflow:hidden;"><?php echo htmlspecialchars($listing['description']); ?></p>
          </div>
        </a>
  <?php endforeach; ?>
  <?php if (!$listings): ?>
    <div class="muted">Kol kas nėra skelbimų – sukurkite pirmąjį!</div>
  <?php endif; ?>
  </div>
</section>
</main>

<?php renderFooter($pdo); ?>
</body>
</html>
