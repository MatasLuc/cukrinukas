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
$threadCategories = $pdo->query('SELECT * FROM community_thread_categories ORDER BY name ASC')->fetchAll();

if (!empty($_SESSION['flash_success'])) {
    $messages[] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$threadsSql = 'SELECT t.*, u.name, c.name AS category_name FROM community_threads t JOIN users u ON u.id = t.user_id LEFT JOIN community_thread_categories c ON c.id = t.category_id';
if ($categoryId > 0) {
    $threadsSql .= ' WHERE t.category_id = :cid';
}
$threadsSql .= ' ORDER BY t.created_at DESC';
$threadsStmt = $pdo->prepare($threadsSql);
if ($categoryId > 0) {
    $threadsStmt->bindValue(':cid', $categoryId, PDO::PARAM_INT);
}
$threadsStmt->execute();
$threads = $threadsStmt->fetchAll();
$commentCounts = [];
if ($threads) {
    $ids = array_column($threads, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT thread_id, COUNT(*) AS c FROM community_comments WHERE thread_id IN ($in) GROUP BY thread_id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $commentCounts[$row['thread_id']] = (int)$row['c'];
    }
}

$memberCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bendruomenės diskusijos | Cukrinukas</title>
  <?php echo headerStyles(); ?>
<style>
.page-wrap { max-width: 1100px; margin: 36px auto 70px; padding: 0 18px; display:flex; flex-direction:column; gap:18px; }
.hero-box { background: linear-gradient(135deg,#0b0b0b,#1f2341); color:#fff; border-radius:22px; padding:26px; box-shadow:0 22px 48px rgba(10,12,32,0.32); }
.hero-box h1 { color:#fff; }
.hero-box p { color: rgba(255,255,255,0.82); }
.actions { display:flex; gap:12px; flex-wrap:wrap; }
.card { background:#fff; border:1px solid #e6e6ef; border-radius:16px; padding:16px; box-shadow:0 14px 32px rgba(0,0,0,0.06); }
.thread-list { display:flex; flex-direction:column; gap:12px; }
.thread-item { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; background:#f7f7fb; border:1px solid #e2e4f3; border-radius:14px; padding:14px; transition: transform .08s ease, box-shadow .12s ease; text-decoration:none; color:inherit; }
.thread-item:hover { transform: translateY(-2px); box-shadow:0 14px 28px rgba(0,0,0,0.07); text-decoration:none; }
.meta { display:flex; gap:10px; flex-wrap:wrap; align-items:center; font-size:13px; color:#434352; }
.chip { padding:6px 10px; border-radius:999px; background:#fff; border:1px solid #d7dbf3; font-weight:700; font-size:12px; }
.badge-soft { padding:6px 10px; border-radius:999px; background:rgba(130,158,214,0.16); color:#1d2238; font-weight:700; border:1px solid rgba(130,158,214,0.28); }
.alert { border-radius:12px; padding:12px; }
.alert-success { background:#edf9f0; border:1px solid #b8e2c4; }
.alert-error { background:#fff1f1; border:1px solid #f3b7b7; }
.filter-row { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start; }
.filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:#f6f7fb; border:1px solid #e0e4f4; border-radius:999px; padding:6px 10px; flex:1; row-gap:6px; }
.filter-pill { padding:8px 12px; border-radius:14px; border:1px solid transparent; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,0.04); font-weight:700; font-size:13px; color:#232334; text-decoration:none; transition: all .12s ease; white-space:nowrap; }
.filter-pill:hover { transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,0.08); text-decoration:none; }
.filter-pill.active { background:linear-gradient(135deg,#1f2341,#0b0b0b); color:#fff; border-color:rgba(255,255,255,0.26); box-shadow:0 14px 26px rgba(15,16,35,0.28); }
.filter-label { font-size:13px; font-weight:700; color:#5b5b6c; margin-right:4px; }
.filter-meta { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:8px; }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main class="page-wrap">
  <section class="hero-box">
    <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start;">
      <div style="max-width:650px;">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <span style="padding:6px 10px;border-radius:999px;background:rgba(255,255,255,0.16);font-weight:700;">Diskusijos</span>
          <span style="padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.25);">Pokalbiai be reklamų</span>
        </div>
        <h1 style="margin:10px 0 6px 0;">Dalinkitės klausimais ir patirtimi</h1>
        <p style="margin:0;">Pasikalbėkite su bendruomene, gaukite atsakymų ir padėkite kitiems. Mandagumas ir pagarba yra būtini.</p>
      </div>
      <div class="actions">
        <?php if ($user['id'] && !$blocked): ?>
          <a class="btn" href="/community_thread_new.php" style="background:#fff;color:#0b0b0b;border-color:rgba(255,255,255,0.45);">Kurti temą</a>
        <?php else: ?>
          <a class="btn" href="/login.php" style="background:#fff;color:#0b0b0b;border-color:rgba(255,255,255,0.45);">Prisijunkite kurti</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/community_market.php" style="border-color:rgba(255,255,255,0.4);color:#fff;">Į turgų</a>
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

  <section class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0;">Naujausios temos</h2>
        <p class="muted" style="margin:4px 0 0 0;">Atraskite, apie ką kalba bendruomenė šiandien.</p>
      </div>
      <div class="filter-meta">
        <span class="chip">Temų: <?php echo count($threads); ?></span>
        <span class="chip">Narių: <?php echo (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); ?></span>
      </div>
      <div class="filter-row">
        <div class="filter-bar">
          <span class="filter-label">Kategorija:</span>
          <a class="filter-pill <?php echo $categoryId === 0 ? 'active' : ''; ?>" href="/community_discussions.php">Visos</a>
          <?php foreach ($threadCategories as $cat): ?>
            <a class="filter-pill <?php echo $categoryId === (int)$cat['id'] ? 'active' : ''; ?>" href="/community_discussions.php?category=<?php echo (int)$cat['id']; ?>">#<?php echo htmlspecialchars($cat['name']); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="thread-list" style="margin-top:12px;">
      <?php foreach ($threads as $thread): ?>
        <a class="thread-item" href="/community_thread.php?id=<?php echo (int)$thread['id']; ?>">
          <div>
            <div style="font-weight:700;font-size:16px;">
              <?php echo htmlspecialchars($thread['title']); ?>
            </div>
            <div class="meta">
              <span class="badge-soft"><?php echo htmlspecialchars($thread['name']); ?></span>
              <span>Pradėta: <?php echo htmlspecialchars(date('Y-m-d', strtotime($thread['created_at']))); ?></span>
              <?php if ($thread['category_name']): ?>
                <span class="chip" style="padding:4px 8px;">#<?php echo htmlspecialchars($thread['category_name']); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
            <span class="chip">Komentarai: <?php echo $commentCounts[$thread['id']] ?? 0; ?></span>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$threads): ?>
        <div class="muted">Diskusijų dar nėra – būkite pirmieji!</div>
      <?php endif; ?>
    </div>
  </section>
</main>

  <?php renderFooter($pdo); ?>
</body>
</html>
