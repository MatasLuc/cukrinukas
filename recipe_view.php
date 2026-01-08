<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

$pdo = getPdo();
ensureRecipesTable($pdo);
ensureSavedContentTables($pdo);
ensureRecipeRatingsTable($pdo); // Užtikriname, kad lentelė yra
tryAutoLogin($pdo);

$id = (int)($_GET['id'] ?? 0);
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Recepto užklausa
$stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    echo 'Receptas nerastas';
    exit;
}

// Kategorijos
$catStmt = $pdo->prepare("
    SELECT c.name, c.id 
    FROM recipe_categories c 
    JOIN recipe_category_relations r ON r.category_id = c.id 
    WHERE r.recipe_id = ?
");
$catStmt->execute([$id]);
$categories = $catStmt->fetchAll();

// POST veiksmai (Išsaugojimas ir Vertinimas)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    if (!$userId) {
        header('Location: /login.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        saveItemForUser($pdo, $userId, 'recipe', $id);
        header('Location: /saved.php');
        exit;
    }
    
    if ($action === 'rate') {
        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating >= 1 && $rating <= 5) {
            rateRecipe($pdo, $userId, $id, $rating);
        }
        // Perkrauname tą patį puslapį, kad atsinaujintų statistika
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Gauname reitingo statistiką
$ratingStats = getRecipeRatingStats($pdo, $id);
$userRating = $userId ? getUserRecipeRating($pdo, $userId, $id) : 0;

$canViewFull = ($recipe['visibility'] ?? 'public') !== 'members' || $userId;
$authorName = !empty($recipe['author']) ? $recipe['author'] : 'Cukrinukas';

$meta = [
    'title' => $recipe['title'] . ' | Receptai',
    'description' => $recipe['summary'] ?: mb_substr(strip_tags($recipe['body']), 0, 160),
    'image' => 'https://cukrinukas.lt' . $recipe['image_url']
];

$currentRecipeUrl = 'https://cukrinukas.lt/receptas/' . slugify($recipe['title']) . '-' . $id;
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo headerStyles(); ?>
  <style>
    :root { 
        --color-bg: #f7f7fb; 
        --color-primary: #0b0b0b; 
        --pill: #f0f2ff; 
        --border: #e4e6f0;
        --star-color: #d1d5db; /* Pilka */
        --star-active: #f59e0b; /* Gintarinė/Auksinė */
        --accent-blue: #2563eb;
    }
    * { box-sizing: border-box; }
    a { color:inherit; text-decoration:none; }
    body { background: var(--color-bg); }
    .shell { max-width:1080px; margin:32px auto 64px; padding:0 20px; display:flex; flex-direction:column; gap:24px; }
    
    /* Hero kortelė */
    .hero { 
        background:linear-gradient(135deg,#ffffff 0%,#eef0ff 100%); 
        border:1px solid var(--border); 
        border-radius:24px; 
        box-shadow:0 16px 40px rgba(0,0,0,0.06); 
        padding:28px; 
        display:flex; 
        flex-direction:column; 
        gap:16px; 
    }
    
    .crumb { display:flex; align-items:center; gap:10px; color:#6b6b7a; font-size:14px; font-weight: 500; }
    .crumb a:hover { color: var(--accent-blue); }

    .meta { display:flex; align-items:center; gap:10px; color:#6b6b7a; font-size:14px; flex-wrap:wrap; margin-top: 4px; }
    
    .badge { padding:6px 12px; border-radius:999px; background:var(--pill); border:1px solid var(--border); font-weight:600; font-size:13px; color:#2b2f4c; display: inline-flex; align-items: center; gap: 6px; }
    .badge-cat { background:#f0f7ff; border-color:#dbeafe; color:#1e40af; text-decoration:none; transition:0.2s; }
    .badge-cat:hover { background:#dbeafe; }
    
    /* Reitingo žvaigždutė badge viduje */
    .badge-rating { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
    .star-icon { width: 14px; height: 14px; fill: currentColor; }

    /* Veiksmų mygtukai */
    .heart-btn { width:44px; height:44px; border-radius:12px; border:1px solid var(--border); background:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:20px; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.05); transition: all .2s ease; color: #64748b; }
    .heart-btn:hover { border-color: #ef4444; color: #ef4444; transform: translateY(-2px); }
    
    .media { overflow:hidden; border-radius:20px; border:1px solid var(--border); background:#fff; box-shadow:0 16px 38px rgba(0,0,0,0.06); position: relative; }
    .media img { width:100%; object-fit:cover; max-height:480px; display:block; }
    
    /* Turinys */
    .content-card { background:#fff; border:1px solid var(--border); border-radius:20px; padding:32px; box-shadow:0 14px 30px rgba(0,0,0,0.06); line-height:1.8; color:#334155; font-size: 17px; }
    .content-card img { max-width:100%; height:auto; display:block; margin:24px auto; border-radius:14px; }
    .content-card ul, .content-card ol { padding-left:24px; margin-bottom: 24px; }
    .content-card h2, .content-card h3 { color: var(--color-primary); margin-top: 32px; }

    /* --- INTERAKTYVUS REITINGO BLOKAS --- */
    .rating-box {
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        margin-top: 8px;
    }

    .rating-label { font-weight: 600; font-size: 15px; color: var(--color-primary); margin-bottom: 4px; }
    .rating-desc { font-size: 13px; color: #64748b; }

    /* Žvaigždučių logika */
    .star-rating {
        display: flex;
        flex-direction: row-reverse; /* Reikalinga hover efektui "į kairę" */
        gap: 4px;
    }

    .star-rating input { display: none; }

    .star-rating label {
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s;
        color: var(--star-color);
    }

    .star-rating label svg { width: 28px; height: 28px; fill: currentColor; }

    /* Hover ir Checked būsenos */
    .star-rating label:hover,
    .star-rating label:hover ~ label,
    .star-rating input:checked ~ label {
        color: var(--star-active);
    }
    
    .star-rating label:hover { transform: scale(1.2); }

    .rating-display-large {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .big-score { font-size: 32px; font-weight: 800; color: var(--color-primary); line-height: 1; }
    .star-row-static { display: flex; color: var(--star-active); gap: 2px; }
    .count-text { font-size: 13px; color: #64748b; font-weight: 500; }

    @media (max-width: 600px) {
        .rating-box { flex-direction: column; align-items: flex-start; }
        .hero h1 { font-size: 24px; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'recipes', $meta); ?>
  
  <main class="shell">
    
    <section class="hero">
      <div class="crumb"><a href="/recipes.php">← Grįžti į receptus</a></div>
      
      <div style="display:flex; align-items:flex-start; gap:14px; justify-content:space-between;">
        <div style="display:flex; flex-direction:column; gap:10px; flex: 1; min-width: 0;">
          <h1 style="margin:0; font-size:32px; line-height:1.2; color:#0b0b0b; word-wrap: break-word;"><?php echo htmlspecialchars($recipe['title']); ?></h1>
          
          <div class="meta">
            <span class="badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <?php echo date('Y-m-d', strtotime($recipe['created_at'])); ?>
            </span>
            
            <span class="badge" style="background:#fff7ed; border-color:#ffedd5; color:#c2410c;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <?php echo htmlspecialchars($authorName); ?>
            </span>

            <?php if ($ratingStats['count'] > 0): ?>
            <span class="badge badge-rating">
                <svg class="star-icon" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <?php echo $ratingStats['average']; ?> <span style="font-weight:400; color:#b45309; margin-left:4px;">(<?php echo $ratingStats['count']; ?>)</span>
            </span>
            <?php endif; ?>

            <?php if ($categories): ?>
                <?php foreach ($categories as $cat): ?>
                    <a href="/recipes.php?cat=<?php echo $cat['id']; ?>" class="badge badge-cat"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        
        <div style="display:flex; gap:10px; align-items:center; flex-shrink: 0; margin-left: 10px;">
          <?php if ($userId): ?>
            <form method="post" style="margin:0;">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="save">
              <button class="heart-btn" type="submit" aria-label="Išsaugoti receptą" title="Išsaugoti receptą">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
              </button>
            </form>
          <?php else: ?>
            <a class="heart-btn" href="/login.php" aria-label="Prisijunkite" title="Prisijunkite, kad išsaugotumėte">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
      
      <?php if (!empty($recipe['summary'])): ?>
        <p style="margin:0; font-size:18px; line-height:1.6; color:#4b5563;">
            <?php echo nl2br(htmlspecialchars($recipe['summary'])); ?>
        </p>
      <?php endif; ?>

      <div class="rating-box">
          <div class="rating-display-large">
              <div class="big-score"><?php echo $ratingStats['average'] ?: '-'; ?></div>
              <div>
                  <div class="star-row-static">
                      <?php 
                      $avg = round($ratingStats['average']);
                      for($i=1; $i<=5; $i++): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $i <= $avg ? 'currentColor' : '#e5e7eb'; ?>"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                      <?php endfor; ?>
                  </div>
                  <div class="count-text"><?php echo $ratingStats['count']; ?> vertinimai</div>
              </div>
          </div>

          <?php if ($userId): ?>
            <div>
                <div class="rating-label">Jūsų įvertinimas:</div>
                <form method="post" style="margin:0;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="rate">
                    <div class="star-rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo $userRating === $i ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> žvaigždutės">
                                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </label>
                        <?php endfor; ?>
                    </div>
                </form>
                <div class="rating-desc">Paspauskite, kad įvertintumėte</div>
            </div>
          <?php else: ?>
             <div style="text-align:right;">
                 <div class="rating-label">Norite įvertinti?</div>
                 <a href="/login.php" style="font-size:14px; color:var(--accent-blue); text-decoration:underline;">Prisijunkite prie bendruomenės</a>
             </div>
          <?php endif; ?>
      </div>

    </section>

    <?php if ($recipe['image_url']): ?>
      <div class="media"><img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>"></div>
    <?php endif; ?>

    <section>
      <article class="content-card">
        <?php if ($canViewFull): ?>
          <?php echo sanitizeHtml($recipe['body']); ?>
        <?php else: ?>
          <div style="text-align:center; padding:40px 20px;">
              <div style="font-size:48px; margin-bottom:16px;">🔒</div>
              <h3 style="margin:0 0 12px; color:#1e293b;">Receptas tik nariams</h3>
              <p style="color:#64748b; margin-bottom:24px;">Šis receptas skirtas tik registruotiems bendruomenės nariams. Prisijunkite, kad pamatytumėte gaminimo eigą ir ingredientus.</p>
              <a href="/login.php" style="display:inline-block; background:#0b0b0b; color:#fff; padding:12px 24px; border-radius:12px; font-weight:600;">Prisijungti</a>
          </div>
        <?php endif; ?>
      </article>
    </section>

  </main>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org/",
    "@type": "Recipe",
    "name": <?php echo json_encode($recipe['title']); ?>,
    "image": [<?php echo json_encode('https://cukrinukas.lt' . $recipe['image_url']); ?>],
    "author": {
      "@type": "Person",
      "name": <?php echo json_encode($authorName); ?>
    },
    "datePublished": <?php echo json_encode(date('Y-m-d', strtotime($recipe['created_at']))); ?>,
    "description": <?php echo json_encode(mb_substr(strip_tags($recipe['summary'] ?: $recipe['body']), 0, 300)); ?>,
    <?php if ($ratingStats['count'] > 0): ?>
    "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "<?php echo $ratingStats['average']; ?>",
        "reviewCount": "<?php echo $ratingStats['count']; ?>"
    },
    <?php endif; ?>
    "recipeCategory": "Diabetui draugiški"
  }
  </script>

  <?php renderFooter($pdo); ?>
</body>
</html>
