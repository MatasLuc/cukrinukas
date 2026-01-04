<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureRecipesTable($pdo);
ensureSavedContentTables($pdo);
ensureAdminAccount($pdo);
$siteContent = getSiteContent($pdo);

// Išsaugoti receptą (Mėgstamiausi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipe_id'])) {
    validateCsrfToken();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    saveItemForUser($pdo, (int)$_SESSION['user_id'], 'recipe', (int)$_POST['recipe_id']);
    header('Location: /saved.php');
    exit;
}

// 1. Gauname aktyvias receptų kategorijas
$activeCategories = $pdo->query("
    SELECT c.id, c.name, COUNT(r.recipe_id) as count 
    FROM recipe_categories c 
    JOIN recipe_category_relations r ON r.category_id = c.id 
    GROUP BY c.id 
    HAVING count > 0 
    ORDER BY c.name ASC
")->fetchAll();

// 2. Filtravimo logika
$selectedCatId = isset($_GET['cat']) ? (int)$_GET['cat'] : null;

// Pagrindinė užklausa
$sql = 'SELECT r.id, r.title, r.image_url, r.summary, r.body, r.created_at, r.visibility 
        FROM recipes r ';
$params = [];

if ($selectedCatId) {
    $sql .= 'JOIN recipe_category_relations rel ON r.id = rel.recipe_id WHERE rel.category_id = ? ';
    $params[] = $selectedCatId;
}

$sql .= 'ORDER BY r.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = !empty($_SESSION['is_admin']);
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receptai | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #475467;
      --accent: #22c55e;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); }
    .page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display:grid; gap:28px; }
    
    .hero { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 28px; padding: 26px 26px 30px; border:1px solid #bbf7d0; box-shadow:0 18px 48px rgba(0,0,0,0.04); display:grid; grid-template-columns: 1.4fr 0.6fr; gap:22px; align-items:center; }
    .hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; border:1px solid #bbf7d0; padding:10px 14px; border-radius:999px; font-weight:700; color: #166534; box-shadow:0 12px 30px rgba(0,0,0,0.04); }
    .hero h1 { margin:10px 0 8px; font-size: clamp(26px, 4vw, 36px); letter-spacing:-0.02em; color:#14532d; }
    .hero p { margin:0; color: #3f6212; line-height:1.6; }
    .hero__actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
    
    .cta { display:inline-flex; align-items:center; gap:10px; padding:12px 16px; border-radius:12px; border:none; background: linear-gradient(135deg, #16a34a, #15803d); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 14px 36px rgba(22,163,74,0.25); text-decoration:none; }
    .cta.secondary { background:#fff; color:#166534; border:1px solid #86efac; box-shadow:none; }
    
    .page__head { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .page__title { margin:0; font-size:28px; letter-spacing:-0.01em; }
    
    .pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; background:#f0fdf4; color:#15803d; font-weight:700; font-size:13px; transition: all 0.2s ease; text-decoration:none; border:1px solid transparent; }
    .pill.active { background:#16a34a; color:#fff; }
    .pill:hover { opacity: 0.9; transform: translateY(-1px); border-color:#bbf7d0; }
    
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:18px; }
    .card { background: var(--card); border-radius:20px; overflow:hidden; border:1px solid var(--border); box-shadow:0 14px 32px rgba(0,0,0,0.08); display:grid; grid-template-rows:auto 1fr; transition: transform .18s ease; }
    .card:hover { transform: translateY(-3px); box-shadow:0 18px 46px rgba(0,0,0,0.12); border-color: #86efac; }
    .card img { width: 100%; height: 190px; object-fit: cover; display: block; }
    .card__body { padding: 16px 18px 20px; display: grid; gap: 10px; }
    .card__title { margin: 0; font-size: 20px; letter-spacing:-0.01em; }
    .card__meta { font-size: 13px; color: var(--muted); }
    
    /* PAKEITIMAS: Apribojame tekstą iki 5 eilučių */
    .card__excerpt { 
        margin: 0; 
        color: #111827; 
        line-height: 1.55;
        
        /* Šios eilutės sukuria 5 eilučių limitą su daugtaškiu */
        display: -webkit-box;
        -webkit-line-clamp: 5;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .heart-btn { width:38px; height:38px; border-radius:12px; border:1px solid var(--border); background:#f8fafc; display:inline-flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'recipes'); ?>

  <main class="page">
    <section class="hero">
      <div>
        <div class="hero__pill"><?php echo htmlspecialchars($siteContent['recipes_hero_pill'] ?? '🥗 Sveika mityba'); ?></div>
        <h1><?php echo htmlspecialchars($siteContent['recipes_hero_title'] ?? 'Receptai, kurie įkvepia'); ?></h1>
        <p><?php echo htmlspecialchars($siteContent['recipes_hero_body'] ?? 'Subalansuoti patiekalai diabeto kontrolei ir gerai savijautai.'); ?></p>
        <div class="hero__actions">
          <a class="cta" href="#list">Žiūrėti receptus</a>
          <?php if ($isAdmin): ?>
            <a class="cta secondary" href="/recipe_create.php">+ Naujas receptas</a>
            <a class="cta secondary" href="/admin.php?view=content">Valdyti</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div class="page__head" id="list">
      <div>
        <h2 class="page__title">Receptai</h2>
      </div>
    </div>

    <?php if (!empty($activeCategories)): ?>
    <div class="categories-nav" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom: -10px;">
        <a href="/recipes.php" class="pill <?php echo $selectedCatId === null ? 'active' : ''; ?>">
            Visi receptai
        </a>
        <?php foreach ($activeCategories as $cat): ?>
            <a href="/recipes.php?cat=<?php echo $cat['id']; ?>" class="pill <?php echo $selectedCatId === $cat['id'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid">
      <?php if (empty($recipes)): ?>
        <p style="grid-column: 1/-1; text-align:center; padding: 40px; color: var(--muted);">Šioje kategorijoje receptų kol kas nėra.</p>
      <?php else: ?>
        <?php foreach ($recipes as $r): ?>
            <article class="card">
            <a href="/recipe_view.php?id=<?php echo (int)$r['id']; ?>">
                <img src="<?php echo htmlspecialchars($r['image_url']); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>">
            </a>
            <div class="card__body">
                <h2 class="card__title">
                    <a href="/recipe_view.php?id=<?php echo (int)$r['id']; ?>" style="text-decoration:none; color:inherit;">
                        <?php echo htmlspecialchars($r['title']); ?>
                    </a>
                </h2>
                
                <p class="card__meta"><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></p>
                
                <p class="card__excerpt">
                    <?php 
                        $excerpt = trim($r['summary'] ?? '');
                        // Jei santraukos nėra, imame iš body
                        if (!$excerpt) {
                            $excerpt = strip_tags($r['body']);
                        }
                        // Apkerpame PHP pusėje tik jei tekstas labai ilgas (kad nekrautų HTML), 
                        // bet vizualų 5 eilučių limitą sutvarko CSS
                        if (mb_strlen($excerpt) > 400) {
                            $excerpt = mb_substr($excerpt, 0, 400) . '...';
                        }
                        echo htmlspecialchars($excerpt);
                    ?>
                </p>
                
                <div style="display:flex; gap:10px; align-items:center; justify-content:space-between; margin-top:auto;">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a class="cta" style="padding:9px 12px; font-size:14px;" href="/recipe_view.php?id=<?php echo (int)$r['id'];?>">Gaminti</a>
                        <?php if ($isLoggedIn): ?>
                            <form method="post" style="margin:0;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="recipe_id" value="<?php echo (int)$r['id']; ?>">
                            <button class="heart-btn" type="submit" aria-label="Išsaugoti">♥</button>
                            </form>
                        <?php else: ?>
                            <a class="heart-btn" href="/login.php" style="text-decoration:none; color:inherit; display:flex; align-items:center; justify-content:center;">♥</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($isAdmin): ?>
                        <a style="font-weight:600; color:#475467; font-size:13px;" href="/recipe_edit.php?id=<?php echo (int)$r['id']; ?>">Redaguoti</a>
                    <?php endif; ?>
                </div>
            </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
