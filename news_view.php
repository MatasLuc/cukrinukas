<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureNewsTable($pdo);
ensureSavedContentTables($pdo);

$id = (int)($_GET['id'] ?? 0);
// NAUJA: Įtrauktas 'author'
$stmt = $pdo->prepare('SELECT id, title, summary, author, image_url, body, visibility, created_at FROM news WHERE id = ?');
$stmt->execute([$id]);
$news = $stmt->fetch();

if (!$news) {
    http_response_code(404);
    echo 'Naujiena nerasta';
    exit;
}

$canViewFull = ($news['visibility'] ?? 'public') !== 'members' || !empty($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    validateCsrfToken();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    saveItemForUser($pdo, (int)$_SESSION['user_id'], 'news', $id);
    header('Location: /saved.php');
    exit;
}

// SEO Meta duomenys
$meta = [
    'title' => $news['title'] . ' | Naujienos',
    'description' => $news['summary'] ?: mb_substr(strip_tags($news['body']), 0, 160),
    'image' => 'https://cukrinukas.lt' . $news['image_url']
];

// NAUJA: Autoriaus atvaizdavimo logika
$authorName = !empty($news['author']) ? $news['author'] : 'Redakcijos naujiena';
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo headerStyles(); ?>
  <style>
    /* Jūsų stiliai lieka tie patys */
    :root { --color-bg: #f7f7fb; --color-primary: #0b0b0b; --pill:#f0f2ff; --border:#e4e6f0; }
    * { box-sizing: border-box; }
    a { color:inherit; text-decoration:none; }
    body { background: var(--color-bg); }
    .shell { max-width:1080px; margin:32px auto 64px; padding:0 20px; display:flex; flex-direction:column; gap:18px; }
    .hero { background:linear-gradient(135deg,#ffffff 0%,#eef0ff 100%); border:1px solid var(--border); border-radius:20px; box-shadow:0 16px 40px rgba(0,0,0,0.06); padding:22px; display:flex; flex-direction:column; gap:12px; }
    .crumb { display:flex; align-items:center; gap:10px; color:#6b6b7a; font-size:14px; }
    .meta { display:flex; align-items:center; gap:10px; color:#6b6b7a; font-size:14px; flex-wrap:wrap; }
    .badge { padding:6px 12px; border-radius:999px; background:var(--pill); border:1px solid var(--border); font-weight:600; font-size:13px; color:#2b2f4c; }
    .heart-btn { width:44px; height:44px; border-radius:14px; border:1px solid var(--border); background:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:18px; cursor:pointer; box-shadow:0 10px 22px rgba(0,0,0,0.08); }
    .media { overflow:hidden; border-radius:18px; border:1px solid var(--border); background:#fff; box-shadow:0 16px 38px rgba(0,0,0,0.06); }
    .media img { width:100%; object-fit:cover; max-height:460px; display:block; }
    .content-card { background:#fff; border:1px solid var(--border); border-radius:18px; padding:22px; box-shadow:0 14px 30px rgba(0,0,0,0.06); line-height:1.7; color:#2b2f4c; }
    .content-card img { max-width:100%; height:auto; display:block; margin:12px auto; border-radius:14px; }
    .grid { display:grid; grid-template-columns: minmax(0,1fr) 320px; gap:18px; align-items:start; }
    .info-card { background:#fff; border:1px solid var(--border); border-radius:18px; padding:16px; box-shadow:0 12px 26px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:10px; }
    .info-title { font-weight:700; font-size:15px; color:#1c1c28; }
    .info-note { color:#6b6b7a; font-size:13px; line-height:1.5; }
    .ghost-btn { padding:10px 16px; border-radius:12px; border:1px solid var(--border); background:#fff; color:#0b0b0b; box-shadow:0 8px 22px rgba(0,0,0,0.05); cursor:pointer; text-align:center; }
    @media(max-width: 900px){ .grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'news', $meta); ?>
  
  <main class="shell">
    <section class="hero">
      <div class="crumb"><a href="/news.php">← Naujienos</a><span>/</span><span>Nauja istorija</span></div>
      <div style="display:flex; align-items:flex-start; gap:14px; justify-content:space-between; flex-wrap:wrap;">
        <div style="display:flex; flex-direction:column; gap:8px;">
          <h1 style="margin:0; font-size:30px; line-height:1.2; color:#0b0b0b;"><?php echo htmlspecialchars($news['title']); ?></h1>
          <div class="meta">
            <span class="badge">Publikuota <?php echo date('Y-m-d', strtotime($news['created_at'])); ?></span>
            <span class="badge" style="background:#e8fff5; border-color:#cfe8dc; color:#0d8a4d;"><?php echo htmlspecialchars($authorName); ?></span>
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <form method="post" style="margin:0;">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="save">
              <button class="heart-btn" type="submit" aria-label="Išsaugoti naujieną">♥</button>
            </form>
          <?php else: ?>
            <a class="heart-btn" href="/login.php" aria-label="Prisijunkite, kad išsaugotumėte">♥</a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($news['image_url']): ?>
        <div class="media"><img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>"></div>
      <?php endif; ?>
    </section>

    <section class="grid">
      <article class="content-card">
        <?php if ($canViewFull): ?>
          <?php echo sanitizeHtml($news['body']); ?>
        <?php else: ?>
          <p style="margin-top:0;"><?php echo nl2br(htmlspecialchars($news['summary'] ?? '')); ?></p>
          <h5 class="text-center text-muted" style="text-align:center; color:#6b6b7a; margin-top:24px;"><a href="/login.php" style="text-decoration:underline;">Prisijunkite</a>, kad perskaitytumėte visą naujieną</h5>
        <?php endif; ?>
      </article>
      <aside class="info-card">
        <div class="info-title">Apžvalga</div>
        <div class="info-note">Pastebėjote klaidą? Atsiprašome ir kviečiame apie ją pranešti el. paštu labas@cukrinukas.lt</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:14px; color:#2b2f4c;">
          <span>Skelbimo ID: <strong>#<?php echo (int)$news['id']; ?></strong></span>
          <span>Publikavimo data: <strong><?php echo date('Y-m-d', strtotime($news['created_at'])); ?></strong></span>
        </div>
        <a class="ghost-btn" href="/news.php">Peržiūrėti kitas naujienas</a>
      </aside>
    </section>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
