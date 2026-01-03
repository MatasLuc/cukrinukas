<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureNewsTable($pdo);
seedNewsExamples($pdo);
ensureProductsTable($pdo);
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

$stmt = $pdo->query('SELECT id, title, image_url, body, is_featured, created_at FROM news ORDER BY created_at DESC');
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
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #475467;
      --accent: #7c3aed;
      --accent-2: #22c55e;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    a { color: inherit; text-decoration: none; }
    .page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display:grid; gap:28px; }
    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border-radius: 28px; padding: 26px 26px 30px; border:1px solid #e5e7eb; box-shadow:0 18px 48px rgba(0,0,0,0.08); display:grid; grid-template-columns: 1.4fr 0.6fr; gap:22px; align-items:center; }
    .hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; border:1px solid #e4e7ec; padding:10px 14px; border-radius:999px; font-weight:700; box-shadow:0 12px 30px rgba(0,0,0,0.08); }
    .hero h1 { margin:10px 0 8px; font-size: clamp(26px, 4vw, 36px); letter-spacing:-0.02em; }
    .hero p { margin:0; color: var(--muted); line-height:1.6; }
    .cta { display:inline-flex; align-items:center; gap:10px; padding:12px 16px; border-radius:12px; border:none; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 14px 36px rgba(124,58,237,0.25); transition: transform .18s ease, box-shadow .18s ease; }
    .cta.secondary { background:#fff; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none; }
    .cta:hover { transform: translateY(-1px); box-shadow:0 18px 52px rgba(124,58,237,0.3); }
    .hero__actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
    .hero__card { background:#fff; border:1px solid #e4e7ec; border-radius:18px; padding:16px 18px; text-align:right; box-shadow:0 12px 30px rgba(0,0,0,0.08); }
    .hero__card strong { display:block; font-size:24px; letter-spacing:-0.02em; margin-top:6px; }
    .page__head { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .page__title { margin:0; font-size:28px; letter-spacing:-0.01em; }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; background:#eef2ff; color:#4338ca; font-weight:700; font-size:13px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:18px; }
    .card { background: var(--card); border-radius:20px; overflow:hidden; border:1px solid var(--border); box-shadow:0 14px 32px rgba(0,0,0,0.08); display:grid; grid-template-rows:auto 1fr; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .card:hover { transform: translateY(-3px); box-shadow:0 18px 46px rgba(0,0,0,0.12); border-color: rgba(124,58,237,0.35); }
    .card img { width: 100%; height: 190px; object-fit: cover; display: block; }
    .card__body { padding: 16px 18px 20px; display: grid; gap: 10px; }
    .card__title { margin: 0; font-size: 20px; letter-spacing:-0.01em; }
    .card__meta { font-size: 13px; color: var(--muted); }
    .card__excerpt { margin: 0; color: #111827; line-height: 1.55; }
    .badge { display: inline-block; background: linear-gradient(135deg, #4338ca, #7c3aed); color: #fff; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight:700; }
    .heart-btn { width:38px; height:38px; border-radius:12px; border:1px solid var(--border); background:#f8fafc; display:inline-flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; box-shadow:0 10px 24px rgba(0,0,0,0.08); transition: transform .16s ease, border-color .18s ease; }
    .heart-btn:hover { border-color: rgba(124,58,237,0.55); transform: translateY(-2px); }
    footer { text-align: center; padding: 28px 0; color: #777; font-size: 14px; }

  </style>
</head>
<body>
  <?php renderHeader($pdo, 'news'); ?>

  <main class="page">
    <section class="hero">
      <div>
        <div class="hero__pill"><?php echo htmlspecialchars($siteContent['news_hero_pill'] ?? '📰 Mūsų naujienos'); ?></div>
        <h1><?php echo htmlspecialchars($siteContent['news_hero_title'] ?? 'Šviežiausios naujienos ir patarimai'); ?></h1>
        <p><?php echo htmlspecialchars($siteContent['news_hero_body'] ?? 'Aktualijos apie diabetą, kasdienę priežiūrą ir mūsų parduotuvės atnaujinimus – viskas vienoje vietoje.'); ?></p>
        <div class="hero__actions">
          <a class="cta" href="<?php echo htmlspecialchars($siteContent['news_hero_cta_url'] ?? '#news'); ?>"><?php echo htmlspecialchars($siteContent['news_hero_cta_label'] ?? 'Peržiūrėti straipsnius'); ?></a>
          <?php if ($isAdmin): ?>
            <a class="cta secondary" href="/news_create.php">+ Pridėti naujieną</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <div class="page__head" id="news">
      <div>
        <p class="card__meta" style="margin:0 0 6px;">Aktualijos apie diabetą ir kasdienę priežiūrą</p>
        <h2 class="page__title">Naujienos</h2>
      </div>
      <span class="pill">Atnaujinimai kiekvieną savaitę</span>
    </div>

    <div class="grid">
      <?php foreach ($allNews as $news): ?>
        <article class="card">
          <a href="/news_view.php?id=<?php echo (int)$news['id']; ?>">
            <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
          </a>
          <div class="card__body">
            <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;">
              <h2 class="card__title"><a href="/news_view.php?id=<?php echo (int)$news['id']; ?>"><?php echo htmlspecialchars($news['title']); ?></a></h2>
              <?php if ((int)$news['is_featured'] === 1): ?>
                <span class="badge">Titulinė</span>
              <?php endif; ?>
            </div>
            <p class="card__meta"><?php echo date('Y-m-d', strtotime($news['created_at'])); ?></p>
            <p class="card__excerpt"><?php $plain = trim(strip_tags($news['body'])); echo mb_substr($plain, 0, 240); ?><?php echo mb_strlen($plain) > 240 ? '…' : ''; ?></p>
              <div style="display:flex; gap:10px; align-items:center; justify-content:space-between;">
                <div style="display:flex; gap:8px; align-items:center;">
                  <a class="cta" style="padding:9px 12px; font-size:14px;" href="/news_view.php?id=<?php echo (int)$news['id'];?>">Skaityti</a>
                  <?php if ($isLoggedIn): ?>
                    <form method="post" style="margin:0;">
                      <?php echo csrfField(); ?>
<input type="hidden" name="news_id" value="<?php echo (int)$news['id']; ?>">
                      <button class="heart-btn" type="submit" aria-label="Išsaugoti naujieną">♥</button>
                    </form>
                  <?php else: ?>
                    <a class="heart-btn" href="/login.php" aria-label="Prisijunkite, kad išsaugotumėte" style="text-decoration:none; color:var(--text); display:inline-flex; align-items:center; justify-content:center;">♥</a>
                  <?php endif; ?>
                </div>
                <?php if ($isAdmin): ?>
                  <a style="font-weight:600; color:var(--text);" href="/news_edit.php?id=<?php echo (int)$news['id']; ?>">Redaguoti</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
    </div>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
