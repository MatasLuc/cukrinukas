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
/* Bendras stilius (kaip products.php) */
:root { --bg: #f7f7fb; --card: #ffffff; --border: #e4e7ec; --text: #1f2937; --muted: #52606d; --accent: #2563eb; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
a { color:inherit; text-decoration:none; }

.page { max-width: 1200px; margin: 0 auto; padding: 32px 20px 72px; display: grid; gap: 28px; }

/* Hero sekcija (kaip products.php) */
.hero {
  padding: 26px; border-radius: 28px; background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border: 1px solid #e5e7eb; box-shadow: 0 18px 48px rgba(0,0,0,0.08);
  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 22px;
}
.hero__pill { display:inline-flex; align-items:center; gap:8px; background:#fff; padding:10px 14px; border-radius:999px; font-weight:700; font-size: 15px; color: #0f172a; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
.hero h1 { margin: 10px 0 8px; font-size: clamp(26px, 5vw, 36px); color: #0f172a; }
.hero p { margin: 0; color: var(--muted); line-height: 1.6; max-width: 600px; }

/* Mygtukai */
.btn-large { padding: 11px 24px; border-radius: 12px; border: 1px solid #1d4ed8; background: #fff; color: #1d4ed8; font-weight: 600; transition: all .2s; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.btn-large:hover { background: #1d4ed8; color: #fff; }

.btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 12px; background: #0b0b0b; color: #fff; border: 1px solid #0b0b0b; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
.btn:hover { opacity: 0.9; }
.btn.secondary { background: #fff; color: #0b0b0b; border-color: var(--border); }

/* Kortelės ir sąrašai */
.card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.thread-list { display:flex; flex-direction:column; gap:12px; margin-top: 16px; }
.thread-item { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; background:#f9fafb; border:1px solid var(--border); border-radius:14px; padding:16px; transition: transform .2s, border-color .2s; text-decoration:none; color:inherit; }
.thread-item:hover { transform: translateY(-2px); border-color: var(--accent); background: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1); }

.meta { display:flex; gap:10px; flex-wrap:wrap; align-items:center; font-size:13px; color: var(--muted); margin-top: 6px; }
.badge-soft { padding:4px 10px; border-radius:999px; background:#eff6ff; color:#1e40af; font-weight:600; border:1px solid #dbeafe; font-size: 12px; }

/* Filtrai (Chips) */
.filter-row { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-top: 16px; }
.chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 99px;
  background: #fff; border: 1px solid var(--border);
  font-weight: 600; color: var(--muted); cursor: pointer; transition: all .2s;
  white-space: nowrap; user-select: none; text-decoration: none; font-size: 13px;
}
.chip:hover, .chip.active {
  border-color: var(--accent); color: var(--accent); background: #f0f9ff;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
}
.chip-count { font-size: 11px; opacity: 0.7; margin-left: 4px; }

.alert { border-radius:12px; padding:12px; margin-bottom: 12px; }
.alert-success { background:#ecfdf5; border:1px solid #a7f3d0; color: #065f46; }
.alert-error { background:#fef2f2; border:1px solid #fecaca; color: #991b1b; }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
  
<div class="page">
  <section class="hero">
    <div style="flex:1; min-width: 300px;">
        <div class="hero__pill">💬 Diskusijos</div>
        <h1>Dalinkitės klausimais ir patirtimi</h1>
        <p>Pasikalbėkite su bendruomene, gaukite atsakymų ir padėkite kitiems. Mandagumas ir pagarba yra būtini.</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if ($user['id'] && !$blocked): ?>
          <a class="btn-large" href="/community_thread_new.php">Kurti temą</a>
        <?php else: ?>
          <a class="btn-large" href="/login.php">Prisijunkite kurti</a>
        <?php endif; ?>
        <a class="btn-large" href="/community_market.php" style="border-color:var(--border); color:var(--text);">Į turgų</a>
    </div>
  </section>

  <?php if (!empty($messages) || !empty($errors) || $blocked): ?>
    <div>
        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-success">&check; <?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error">&times; <?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
        <?php if ($blocked): ?>
            <div class="alert alert-error">Jūsų prieiga prie bendruomenės apribota iki <?php echo htmlspecialchars($blocked['banned_until'] ?? 'neribotai'); ?>.</div>
        <?php endif; ?>
    </div>
  <?php endif; ?>

  <section class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0; font-size: 20px;">Naujausios temos</h2>
        <p style="margin:4px 0 0 0; color:var(--muted); font-size:14px;">Atraskite, apie ką kalba bendruomenė šiandien.</p>
      </div>
      
      <div style="display:flex; gap:8px;">
        <span class="chip" style="cursor:default; hover:none;">Temos: <?php echo count($threads); ?></span>
        <span class="chip" style="cursor:default;">Nariai: <?php echo $memberCount; ?></span>
      </div>
    </div>

    <div class="filter-row">
      <a class="chip <?php echo $categoryId === 0 ? 'active' : ''; ?>" href="/community_discussions.php">Visos</a>
      <?php foreach ($threadCategories as $cat): ?>
        <a class="chip <?php echo $categoryId === (int)$cat['id'] ? 'active' : ''; ?>" href="/community_discussions.php?category=<?php echo (int)$cat['id']; ?>">
            #<?php echo htmlspecialchars($cat['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="thread-list">
      <?php foreach ($threads as $thread): ?>
        <a class="thread-item" href="/community_thread.php?id=<?php echo (int)$thread['id']; ?>">
          <div>
            <div style="font-weight:700;font-size:16px; color:#1f2937;">
              <?php echo htmlspecialchars($thread['title']); ?>
            </div>
            <div class="meta">
              <span class="badge-soft"><?php echo htmlspecialchars($thread['name']); ?></span>
              <span><?php echo htmlspecialchars(date('Y-m-d', strtotime($thread['created_at']))); ?></span>
              <?php if ($thread['category_name']): ?>
                <span style="color:var(--accent); font-weight:600;">#<?php echo htmlspecialchars($thread['category_name']); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
            <span class="chip" style="font-size:12px; padding:4px 10px;">💬 <?php echo $commentCounts[$thread['id']] ?? 0; ?></span>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$threads): ?>
        <div style="text-align:center; padding: 40px; color: var(--muted);">Diskusijų dar nėra – būkite pirmieji!</div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php renderFooter($pdo); ?>
</body>
</html>
