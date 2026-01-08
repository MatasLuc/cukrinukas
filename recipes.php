<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php'; // Būtina slugify funkcijai

$pdo = getPdo();
ensureRecipesTable($pdo);
ensureSavedContentTables($pdo);
ensureAdminAccount($pdo);
tryAutoLogin($pdo);
$siteContent = getSiteContent($pdo);

// Išsaugoti receptą
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

// 1. Gauname kategorijas
$activeCategories = $pdo->query("
    SELECT c.id, c.name, COUNT(r.recipe_id) as count 
    FROM recipe_categories c 
    JOIN recipe_category_relations r ON r.category_id = c.id 
    GROUP BY c.id 
    HAVING count > 0 
    ORDER BY c.name ASC
")->fetchAll();

// 2. Filtravimas
$selectedCatId = isset($_GET['cat']) ? (int)$_GET['cat'] : null;

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
    /* Pakeista --accent į mėlyną */
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #475467;
      --accent: #2563eb;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); }
    a { text-decoration: none; color: inherit; }
    .page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display:grid; gap:28px; }
    
    /* Hero kaip products.php, mėlynas gradientas */
    .hero { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 28px; padding: 26px 26px 30px; border:1px solid #e5e7eb; box-shadow:0 18px 48px rgba(0,0,0,0.08); display:grid; grid-template-columns: 1.4fr 0.6fr; gap:22px; align-items:center; }
    .hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; border:1px solid #e4e7ec; padding:10px 14px; border-radius:999px; font-weight:700; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
    .hero h1 { margin:10px 0 8px; font-size: clamp(26px, 4vw, 36px); letter-spacing:-0.02em; }
    .hero p { margin:0; color: var(--muted); line-height:1.6; }
    .hero__actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
    
    /* --- MYGTUKAI (Stilius iš products.php) --- */
    .btn-large { 
        padding: 11px 24px; 
        border-radius: 12px; 
        border: 1px solid #1d4ed8; 
        background: #fff; 
        color: #1d4ed8; 
        font-weight: 600; 
        transition: all .2s; 
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-large:hover { background: #1d4ed8; color: #fff; transform: translateY(-1px); }

    /* --- KATEGORIJOS (Stilius iš products.php 'chip') --- */
    .chips { display:flex; flex-wrap:wrap; gap:12px; align-items: flex-start; }
    .chip {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 99px;
      background: #fff; border: 1px solid var(--border);
      font-weight: 600; color: var(--muted); cursor: pointer; transition: all .2s;
      white-space: nowrap; user-select: none; position: relative; z-index: 20;
    }
    .chip:hover, .chip.active {
      border-color: var(--accent); color: var(--accent); background: #f0f9ff;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
    }
    
    .page__head { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .page__title { margin:0; font-size:28px; letter-spacing:-0.01em; }
    
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:18px; }
    .card { background: var(--card); border-radius:20px; overflow:hidden; border:1px solid var(--border); box-shadow:0 14px 32px rgba(0,0,0,0.08); display:grid; grid-template-rows:auto 1fr; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .card:hover { transform: translateY(-3px); box-shadow:0 18px 46px rgba(0,0,0,0.12); border-color: rgba(37, 99, 235, 0.35); }
    .card img { width: 100%; height: 190px; object-fit: cover; display: block; }
    .card__body { padding: 16px 18px 20px; display: grid; gap: 10px; }
    .card__title { margin: 0; font-size: 20px; letter-spacing:-0.01em; }
    .card__meta { font-size: 13px; color: var(--muted); }
    
    .card__excerpt { 
        margin: 0; 
        color: #111827; 
        line-height: 1.55;
        display: -webkit-box;
        -webkit-line-clamp: 5;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* --- VEIKSMO MYGTUKAI KORTELĖSE (Stilius iš products.php) --- */
    /* Mygtukas "Gaminti" */
    .btn-text-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 16px;
        height: 42px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid var(--border);
        color: #1f2937;
        font-weight: 600;
        font-size: 14px;
        transition: all .2s;
    }
    .btn-text-action:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }

    /* Širdelė */
    .action-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .2s;
        background: #fff;
        border: 1px solid var(--border);
        color: #1f2937;
        font-size: 18px;
    }
    .action-btn:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }
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
          <a class="btn-large" href="#list">Žiūrėti receptus</a>
          <?php if ($isAdmin): ?>
            <a class="btn-large" href="/recipe_create.php">+ Naujas receptas</a>
            <a class="btn-large" href="/admin.php?view=content">Valdyti</a>
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
    <div class="chips">
        <a href="/recipes.php" class="chip <?php echo $selectedCatId === null ? 'active' : ''; ?>">
            Visi receptai
        </a>
        <?php foreach ($activeCategories as $cat): ?>
            <a href="/recipes.php?cat=<?php echo $cat['id']; ?>" class="chip <?php echo $selectedCatId === $cat['id'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid">
      <?php if (empty($recipes)): ?>
        <p style="grid-column: 1/-1; text-align:center; padding: 40px; color: var(--muted);">Šioje kategorijoje receptų kol kas nėra.</p>
      <?php else: ?>
        <?php foreach ($recipes as $r): 
            // SEO nuoroda
            $recipeUrl = '/receptas/' . slugify($r['title']) . '-' . (int)$r['id'];
        ?>
            <article class="card">
            <a href="<?php echo htmlspecialchars($recipeUrl); ?>">
                <img src="<?php echo htmlspecialchars($r['image_url']); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>">
            </a>
            <div class="card__body">
                <h2 class="card__title">
                    <a href="<?php echo htmlspecialchars($recipeUrl); ?>" style="text-decoration:none; color:inherit;">
                        <?php echo htmlspecialchars($r['title']); ?>
                    </a>
                </h2>
                
                <p class="card__meta"><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></p>
                
                <p class="card__excerpt">
                    <?php 
                        $excerpt = trim($r['summary'] ?? '');
                        if (!$excerpt) {
                            $excerpt = strip_tags($r['body']);
                        }
                        if (mb_strlen($excerpt) > 400) {
                            $excerpt = mb_substr($excerpt, 0, 400) . '...';
                        }
                        echo htmlspecialchars($excerpt);
                    ?>
                </p>
                
                <div style="display:flex; gap:10px; align-items:center; justify-content:space-between; margin-top:auto;">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a class="btn-text-action" href="<?php echo htmlspecialchars($recipeUrl);?>">Gaminti</a>
                        <?php if ($isLoggedIn): ?>
                            <form method="post" style="margin:0;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="recipe_id" value="<?php echo (int)$r['id']; ?>">
                                <button class="action-btn" type="submit" aria-label="Išsaugoti">♥</button>
                            </form>
                        <?php else: ?>
                            <a class="action-btn" href="/login.php" style="text-decoration:none;">♥</a>
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
