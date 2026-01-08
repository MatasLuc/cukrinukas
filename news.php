<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php'; // Būtina slugify funkcijai

$pdo = getPdo();
ensureNewsTable($pdo);
tryAutoLogin($pdo);
// Ryšių lentelė užtikrinama per ensureNewsTable
if (function_exists('ensureNewsCategoryRelationsTable')) {
    ensureNewsCategoryRelationsTable($pdo);
}

ensureSavedContentTables($pdo);
ensureAdminAccount($pdo);
$siteContent = getSiteContent($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['news_id'])) {
    validateCsrfToken();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    saveItemForUser($pdo, (int)$_SESSION['user_id'], 'news', (int)$_POST['news_id']);
    header('Location: /saved.php');
    exit;
}

// 1. Gauname kategorijas
$activeCategories = $pdo->query("
    SELECT c.id, c.name, COUNT(r.news_id) as count 
    FROM news_categories c 
    JOIN news_category_relations r ON r.category_id = c.id 
    GROUP BY c.id 
    HAVING count > 0 
    ORDER BY c.name ASC
")->fetchAll();

// 2. Filtravimas
$selectedCatId = isset($_GET['cat']) ? (int)$_GET['cat'] : null;

$sql = 'SELECT n.id, n.title, n.image_url, n.body, n.summary, n.is_featured, n.created_at 
        FROM news n ';
$params = [];

if ($selectedCatId) {
    $sql .= 'JOIN news_category_relations r ON n.id = r.news_id WHERE r.category_id = ? ';
    $params[] = $selectedCatId;
}

$sql .= 'ORDER BY n.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allNews = $stmt->fetchAll();

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = !empty($_SESSION['is_admin']);
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Naujienos | Cukrinukas</title>
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
    /* Mygtukas "Skaityti" - atrodo kaip action-btn, bet platesnis tekstui */
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

    /* Širdelė - identiška products.php action-btn */
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
        font-size: 18px; /* Šiek tiek didesnė širdelė */
    }
    .action-btn:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
    }
      
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'news'); ?>

  <main class="page">
    <section class="hero">
      <div>
        <div class="hero__pill"><?php echo htmlspecialchars($siteContent['news_hero_pill'] ?? '📰 Mūsų naujienos'); ?></div>
        <h1><?php echo htmlspecialchars($siteContent['news_hero_title'] ?? 'Šviežiausios naujienos ir patarimai'); ?></h1>
        <p><?php echo htmlspecialchars($siteContent['news_hero_body'] ?? 'Aktualijos apie diabetą, kasdienę priežiūrą ir mūsų parduotuvės atnaujinimus.'); ?></p>
        <div class="hero__actions">
          <a class="btn-large" href="<?php echo htmlspecialchars($siteContent['news_hero_cta_url'] ?? '#news'); ?>"><?php echo htmlspecialchars($siteContent['news_hero_cta_label'] ?? 'Skaityti'); ?></a>
          <?php if ($isAdmin): ?>
            <a class="btn-large" href="/news_create.php">+ Pridėti naujieną</a>
            <a class="btn-large" href="/admin.php?view=content">Valdyti turinį</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div class="page__head" id="news">
      <div>
        <h2 class="page__title">Naujienos</h2>
      </div>
    </div>

    <?php if (!empty($activeCategories)): ?>
    <div class="chips">
        <a href="/news.php" class="chip <?php echo $selectedCatId === null ? 'active' : ''; ?>">
            Visos naujienos
        </a>
        <?php foreach ($activeCategories as $cat): ?>
            <a href="/news.php?cat=<?php echo $cat['id']; ?>" class="chip <?php echo $selectedCatId === $cat['id'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid">
      <?php if (empty($allNews)): ?>
        <p style="grid-column: 1/-1; text-align:center; padding: 40px; color: var(--muted);">Šioje kategorijoje naujienų kol kas nėra.</p>
      <?php else: ?>
        <?php foreach ($allNews as $news): 
            // SEO nuoroda
            $newsUrl = '/naujiena/' . slugify($news['title']) . '-' . (int)$news['id'];
        ?>
            <article class="card">
            <a href="<?php echo htmlspecialchars($newsUrl); ?>">
                <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
            </a>
            <div class="card__body">
                <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;">
                    <h2 class="card__title"><a href="<?php echo htmlspecialchars($newsUrl); ?>" style="text-decoration:none; color:inherit;"><?php echo htmlspecialchars($news['title']); ?></a></h2>
                </div>
                <p class="card__meta"><?php echo date('Y-m-d', strtotime($news['created_at'])); ?></p>
                
                <p class="card__excerpt">
                    <?php 
                        $excerpt = trim($news['summary'] ?? '');
                        if (!$excerpt) {
                            $excerpt = strip_tags($news['body']);
                        }
                        if (mb_strlen($excerpt) > 400) {
                            $excerpt = mb_substr($excerpt, 0, 400) . '...';
                        }
                        echo htmlspecialchars($excerpt);
                    ?>
                </p>
                
                <div style="display:flex; gap:10px; align-items:center; justify-content:space-between; margin-top:auto;">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a class="btn-text-action" href="<?php echo htmlspecialchars($newsUrl);?>">Skaityti</a>
                        <?php if ($isLoggedIn): ?>
                            <form method="post" style="margin:0;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="news_id" value="<?php echo (int)$news['id']; ?>">
                                <button class="action-btn" type="submit" aria-label="Išsaugoti">♥</button>
                            </form>
                        <?php else: ?>
                            <a class="action-btn" href="/login.php" style="text-decoration:none;">♥</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($isAdmin): ?>
                    <a style="font-weight:600; color:#475467; font-size:13px;" href="/news_edit.php?id=<?php echo (int)$news['id']; ?>">Redaguoti</a>
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
