<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
tryAutoLogin($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Pataisyta užklausa, kad gautume ir user_id bei kitus laukus
$stmt = $pdo->prepare('
    SELECT l.*, u.name as seller_name, u.email as seller_real_email, c.name as category_name 
    FROM community_listings l 
    JOIN users u ON u.id = l.user_id 
    LEFT JOIN community_listing_categories c ON c.id = l.category_id
    WHERE l.id = ?
');
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: /community_market.php');
    exit;
}

$currentUser = currentUser();
// Pataisymas: tikriname ar yra ID, kad nustatytume savininką
$isOwner = (!empty($currentUser['id']) && $currentUser['id'] == $listing['user_id']);
$isAdmin = !empty($_SESSION['is_admin']);
$canEdit = ($isOwner || $isAdmin);
$isLoggedIn = !empty($currentUser['id']); // Pagalbinis kintamasis tikrinimui

// --- Veiksmai (Parduota / Ištrinti) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        die('Neturite teisių.');
    }
    validateCsrfToken();
    
    if (isset($_POST['mark_sold'])) {
        $upd = $pdo->prepare('UPDATE community_listings SET status = "sold" WHERE id = ?');
        $upd->execute([$id]);
        $_SESSION['flash_success'] = 'Skelbimas pažymėtas kaip parduotas.';
        header("Location: /community_listing.php?id=$id");
        exit;
    }
    
    if (isset($_POST['delete'])) {
        $del = $pdo->prepare('DELETE FROM community_listings WHERE id = ?');
        $del->execute([$id]);
        $_SESSION['flash_success'] = 'Skelbimas ištrintas.';
        header('Location: /community_market.php');
        exit;
    }
}

$messages = [];
if (!empty($_SESSION['flash_success'])) {
    $messages[] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($listing['title']); ?> | Turgus</title>
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
  display: flex; flex-direction: column; gap: 16px;
}
.hero-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }

.hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; padding:6px 12px; border-radius:999px; font-weight:700; font-size: 13px; color: #0f172a; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.hero h1 { margin: 10px 0 4px; font-size: clamp(24px, 4vw, 32px); color: #0f172a; }

.price-tag { font-size: 28px; font-weight: 800; color: var(--accent); }
.price-tag.sold { color: #dc2626; text-decoration: line-through; opacity: 0.7; }
.sold-badge { display:inline-block; background:#fef2f2; color:#dc2626; padding:4px 10px; border-radius:8px; font-weight:bold; font-size:14px; border:1px solid #fecaca; margin-left: 10px; text-decoration: none; }

/* Mygtukai */
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: 12px; background: #0b0b0b; color: #fff; border: 1px solid #0b0b0b; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; font-size: 14px; }
.btn:hover { opacity: 0.9; }
.btn.secondary { background: #fff; color: #0b0b0b; border-color: var(--border); }
.btn.danger { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
.btn.danger:hover { background: #fecaca; }
.btn-message { background: linear-gradient(135deg, #2563eb, #1d4ed8); border:none; width:100%; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
.btn-message:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3); opacity: 1; }

/* Išdėstymas */
.content-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; }
@media (max-width: 850px) { .content-grid { grid-template-columns: 1fr; } }

.card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.listing-image { width: 100%; height: auto; max-height: 500px; object-fit: contain; border-radius: 16px; background: #f1f5f9; border: 1px solid var(--border); }

.info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; align-items: center; }
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--muted); font-weight: 500; }
.info-value { color: var(--text); font-weight: 600; text-align: right; }
.info-value a { color: var(--accent); text-decoration: underline; }

.description { line-height: 1.6; color: #374151; white-space: pre-wrap; font-size: 15px; }

.alert { border-radius:12px; padding:12px; margin-bottom: 20px; background:#ecfdf5; border:1px solid #a7f3d0; color: #065f46; }

/* Messages Box */
.msg-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 16px; padding: 20px; margin-bottom: 24px; text-align: center; }
.msg-title { font-weight: 700; color: #0369a1; margin-bottom: 8px; font-size: 16px; display:flex; align-items:center; justify-content:center; gap:8px; }
.msg-text { font-size: 13px; color: #0c4a6e; margin: 0 0 16px 0; line-height: 1.5; }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>

  <div class="page">
    
    <section class="hero">
      <div style="margin-bottom: -10px;">
        <a href="/community_market.php" style="font-size:13px; font-weight:600; color:var(--muted); display:inline-flex; align-items:center; gap:4px;">← Atgal į turgų</a>
      </div>
      
      <div class="hero-top">
        <div>
           <?php if ($listing['category_name']): ?>
             <div class="hero__pill">#<?php echo htmlspecialchars($listing['category_name']); ?></div>
           <?php endif; ?>
           <h1><?php echo htmlspecialchars($listing['title']); ?></h1>
           <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
              <span class="price-tag <?php echo $listing['status'] === 'sold' ? 'sold' : ''; ?>">
                <?php echo number_format($listing['price'], 2); ?> €
              </span>
              <?php if ($listing['status'] === 'sold'): ?>
                <span class="sold-badge">PARDUOTA</span>
              <?php endif; ?>
           </div>
        </div>
        
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
           <?php if ($canEdit): ?>
             <div style="display:flex; gap:8px;">
                <a class="btn secondary" href="/community_listing_edit.php?id=<?php echo $id; ?>">Redaguoti</a>
                <form method="POST" onsubmit="return confirm('Ar tikrai trinti?');" style="margin:0;">
                    <?php echo csrfField(); ?>
                    <button type="submit" name="delete" class="btn danger">Trinti</button>
                </form>
                <?php if ($listing['status'] !== 'sold'): ?>
                    <form method="POST" style="margin:0;">
                         <?php echo csrfField(); ?>
                         <button type="submit" name="mark_sold" class="btn" style="background:#fff; color:#166534; border-color:#bbf7d0;">Pažymėti parduotu</button>
                    </form>
                <?php endif; ?>
             </div>
           <?php endif; ?>
        </div>
      </div>
    </section>

    <?php foreach ($messages as $msg): ?>
       <div class="alert">&check; <?php echo htmlspecialchars($msg); ?></div>
    <?php endforeach; ?>

    <div class="content-grid">
       <div style="display:flex; flex-direction:column; gap:24px;">
          <?php if ($listing['image_url']): ?>
            <img class="listing-image" src="<?php echo htmlspecialchars($listing['image_url']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>">
          <?php endif; ?>
          
          <div class="card">
             <h3 style="margin-top:0; font-size:18px;">Aprašymas</h3>
             <div class="description"><?php echo htmlspecialchars($listing['description']); ?></div>
          </div>
       </div>
       
       <div style="display:flex; flex-direction:column; gap:24px;">
          <div class="card">
             
             <?php if (!$isOwner && $listing['status'] !== 'sold'): ?>
                 <div class="msg-box">
                    <div class="msg-title">💬 Cukrinukas žinutės</div>
                    <p class="msg-text">
                        Greičiausias ir saugiausias būdas susitarti dėl prekės. 
                        Bendraukite tiesiogiai per sistemą.
                    </p>
                    <?php if ($isLoggedIn): ?>
                        <a href="/messages.php?recipient_id=<?php echo $listing['user_id']; ?>" class="btn btn-message">
                           Parašyti žinutę pardavėjui
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="btn btn-message">
                           Prisijunkite norėdami rašyti
                        </a>
                    <?php endif; ?>
                 </div>
             <?php endif; ?>

             <h3 style="margin-top:0; font-size:18px; margin-bottom:16px;">Skelbimo informacija</h3>
             
             <div class="info-row">
                <span class="info-label">Pardavėjas</span>
                <span class="info-value"><?php echo htmlspecialchars($listing['seller_name']); ?></span>
             </div>
             
             <div class="info-row">
                <span class="info-label">Įkelta</span>
                <span class="info-value"><?php echo date('Y-m-d', strtotime($listing['created_at'])); ?></span>
             </div>
             
             <div class="info-row">
                <span class="info-label">Būklė</span>
                <span class="info-value"><?php echo $listing['status'] === 'sold' ? 'Parduota' : 'Aktyvus'; ?></span>
             </div>

             <?php 
             // Patikriname, ar išvis yra įvesti kontaktai
             $hasContacts = !empty($listing['seller_email']) || !empty($listing['seller_phone']);
             
             // Čia buvo klaida: $currentUser visada true.
             // Dabar tikriname !$isLoggedIn (t.y. ar nėra user_id)
             if (!$isLoggedIn && $hasContacts): 
             ?>
                <div style="margin: 16px 0; padding: 12px; background: #fff7ed; border: 1px solid #ffedd5; border-radius: 8px; font-size: 13px; color: #9a3412; line-height: 1.4;">
                    🔒 Norėdami matyti pardavėjo kontaktinius duomenis (el. paštą, telefoną), <a href="/login.php" style="font-weight:700; text-decoration:underline;">prisijunkite</a> arba <a href="/register.php" style="font-weight:700; text-decoration:underline;">užsiregistruokite</a>.
                </div>
             <?php endif; ?>

             <?php if (!empty($listing['seller_email'])): ?>
                 <div class="info-row">
                    <span class="info-label">El. paštas</span>
                    <span class="info-value">
                        <?php if ($isLoggedIn): ?>
                            <a href="mailto:<?php echo htmlspecialchars($listing['seller_email']); ?>"><?php echo htmlspecialchars($listing['seller_email']); ?></a>
                        <?php else: ?>
                            <span style="color: var(--muted); font-style: italic; letter-spacing: 1px;">•••@•••.lt</span>
                        <?php endif; ?>
                    </span>
                 </div>
             <?php endif; ?>

             <?php if (!empty($listing['seller_phone'])): ?>
                 <div class="info-row">
                    <span class="info-label">Tel. nr.</span>
                    <span class="info-value">
                        <?php if ($isLoggedIn): ?>
                            <a href="tel:<?php echo htmlspecialchars($listing['seller_phone']); ?>"><?php echo htmlspecialchars($listing['seller_phone']); ?></a>
                        <?php else: ?>
                            <span style="color: var(--muted); font-style: italic; letter-spacing: 1px;">+370 6•• •••••</span>
                        <?php endif; ?>
                    </span>
                 </div>
             <?php endif; ?>
             
             <div style="margin-top:20px; font-size:12px; color:var(--muted); line-height:1.5; background:#f9fafb; padding:10px; border-radius:12px;">
                <p style="margin:0;">⚠️ Būkite atsargūs pervesdami pinigus. Cukrinukas.lt neatsako už sandorius tarp narių.</p>
             </div>
          </div>
       </div>
    </div>

  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
