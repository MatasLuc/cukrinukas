<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureRecipesTable($pdo);
ensureSavedContentTables($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, title, image_url, body, created_at FROM recipes WHERE id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    echo 'Receptas nerastas';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    validateCsrfToken();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    saveItemForUser($pdo, (int)$_SESSION['user_id'], 'recipe', $id);
    header('Location: /saved.php');
    exit;
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($recipe['title']); ?> | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; --border:#e4e6f0; --pill:#eef7ff; }
    * { box-sizing: border-box; }
    body { background: var(--color-bg); }
    a { color:inherit; text-decoration:none; }
    .shell { max-width:1080px; margin:32px auto 64px; padding:0 20px; display:flex; flex-direction:column; gap:18px; }
    .hero { background:linear-gradient(140deg,#fff 0%,#ecf4ff 100%); border:1px solid var(--border); border-radius:22px; box-shadow:0 16px 40px rgba(0,0,0,0.06); padding:22px; display:flex; flex-direction:column; gap:12px; }
    .crumb { display:flex; align-items:center; gap:10px; color:#6b6b7a; font-size:14px; }
    .meta { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .pill { padding:6px 12px; border-radius:999px; background:var(--pill); border:1px solid var(--border); font-weight:600; font-size:13px; color:#1f2b46; }
    .heart-btn { width:44px; height:44px; border-radius:14px; border:1px solid var(--border); background:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:18px; cursor:pointer; box-shadow:0 10px 22px rgba(0,0,0,0.08); }
    .media { overflow:hidden; border-radius:18px; border:1px solid var(--border); background:#fff; box-shadow:0 16px 38px rgba(0,0,0,0.06); }
    .media img { width:100%; object-fit:cover; max-height:460px; display:block; }
    .grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:18px; align-items:start; }
    .card { background:#fff; border:1px solid var(--border); border-radius:18px; padding:22px; box-shadow:0 14px 30px rgba(0,0,0,0.06); line-height:1.7; color:#1f2b46; }
    .card img { max-width:100%; height:auto; display:block; margin:12px auto; border-radius:14px; }
    .info { background:#fff; border:1px solid var(--border); border-radius:18px; padding:16px; box-shadow:0 12px 26px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:10px; }
    .info h3 { margin:0; font-size:15px; }
    .hint { color:#6b6b7a; font-size:13px; line-height:1.5; }
    @media(max-width: 900px){ .grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'recipes'); ?>
  <main class="shell">
    <section class="hero">
      <div class="crumb"><a href="/recipes.php">← Receptai</a><span>/</span><span>Įkvėpimas</span></div>
      <div style="display:flex; align-items:flex-start; gap:14px; justify-content:space-between; flex-wrap:wrap;">
        <div style="display:flex; flex-direction:column; gap:8px;">
          <h1 style="margin:0; font-size:30px; line-height:1.2; color:#0b0b0b;"><?php echo htmlspecialchars($recipe['title']); ?></h1>
          <div class="meta">
            <span class="pill">Publikuota <?php echo date('Y-m-d', strtotime($recipe['created_at'])); ?></span>
            <span class="pill" style="background:#e8fff5; border-color:#cfe8dc; color:#0d8a4d;">Šefų patarimas</span>
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <form method="post" style="margin:0;">
              <?php echo csrfField(); ?>
<input type="hidden" name="action" value="save">
              <button class="heart-btn" type="submit" aria-label="Išsaugoti receptą">♥</button>
            </form>
          <?php else: ?>
            <a class="heart-btn" href="/login.php" aria-label="Prisijunkite, kad išsaugotumėte">♥</a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($recipe['image_url']): ?>
        <div class="media"><img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>"></div>
      <?php endif; ?>
    </section>

    <section class="grid">
      <article class="card"><?php echo sanitizeHtml($recipe['body']); ?></article>
      <aside class="info">
        <h3>Greiti faktai</h3>
        <div class="hint">Lengva skaityti struktūra ir šviesus fonas leidžia susitelkti į recepto turinį.</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:14px; color:#1f2b46;">
          <span>Recepto ID: <strong>#<?php echo (int)$recipe['id']; ?></strong></span>
          <span>Publikuota: <strong><?php echo date('Y-m-d', strtotime($recipe['created_at'])); ?></strong></span>
        </div>
        <a class="pill" href="/recipes.php" style="text-align:center; display:inline-flex; justify-content:center;">Grįžti į receptus</a>
      </aside>
    </section>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
