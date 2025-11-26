<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureDirectMessages($pdo);
ensureNavigationTable($pdo);
$systemUserId = ensureSystemUser($pdo);

$user = currentUser();
$blocked = $user['id'] ? isCommunityBlocked($pdo, (int)$user['id']) : null;
$messages = [];
$errors = [];
$threadId = (int)($_GET['id'] ?? 0);
$categories = $pdo->query('SELECT * FROM community_thread_categories ORDER BY name ASC')->fetchAll();

function sendSystemMessage(PDO $pdo, int $systemUserId, int $recipientId, string $body): void {
    if ($systemUserId === $recipientId) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO direct_messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$systemUserId, $recipientId, $body]);
}

if (!$threadId) {
    header('Location: /community_discussions.php');
    exit;
}

$threadStmt = $pdo->prepare('SELECT t.*, u.name, c.name AS category_name FROM community_threads t JOIN users u ON u.id = t.user_id LEFT JOIN community_thread_categories c ON c.id = t.category_id WHERE t.id = ?');
$threadStmt->execute([$threadId]);
$thread = $threadStmt->fetch();
if (!$thread) {
    header('Location: /community_discussions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';
    if (!$user['id']) {
        $errors[] = 'Prisijunkite, kad galėtumėte dalyvauti diskusijoje.';
    } elseif ($blocked) {
        $errors[] = 'Jūsų prieiga prie bendruomenės apribota iki ' . ($blocked['banned_until'] ?? 'neribotai');
    } else {
        if ($action === 'create_comment') {
            $body = trim($_POST['body'] ?? '');
            if ($body) {
                $stmt = $pdo->prepare('INSERT INTO community_comments (thread_id, user_id, body) VALUES (?, ?, ?)');
                $stmt->execute([$threadId, $user['id'], $body]);
                $messages[] = 'Komentaras pridėtas.';

                $threadLink = '/community_thread.php?id=' . $threadId;
                $safeTitle = htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8');
                $safeAuthor = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
                $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
                $dmBody = $safeAuthor . ' pakomentavo diskusijoje „' . $safeTitle . '“:<br>' . $safeBody . '<br><a href="' . $threadLink . '">Peržiūrėti</a>';

                $recipientIds = [];
                $ownerId = (int)$thread['user_id'];
                if ($ownerId && $ownerId !== (int)$user['id']) {
                    $recipientIds[] = $ownerId;
                }

                $participantStmt = $pdo->prepare('SELECT DISTINCT user_id FROM community_comments WHERE thread_id = ? AND user_id != ?');
                $participantStmt->execute([$threadId, $user['id']]);
                foreach ($participantStmt->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                    $pidInt = (int)$pid;
                    if (!in_array($pidInt, $recipientIds, true) && $pidInt !== (int)$user['id']) {
                        $recipientIds[] = $pidInt;
                    }
                }

                foreach ($recipientIds as $rid) {
                    sendSystemMessage($pdo, $systemUserId, $rid, $dmBody);
                }
            } else {
                $errors[] = 'Įrašykite žinutę.';
            }
        }
        if ($action === 'update_thread' && ($user['is_admin'] || (int)$thread['user_id'] === (int)$user['id'])) {
            $title = trim($_POST['title'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            if ($title && $body) {
                $upd = $pdo->prepare('UPDATE community_threads SET title = ?, body = ?, category_id = ? WHERE id = ?');
                $upd->execute([$title, $body, $categoryId ?: null, $threadId]);
                $messages[] = 'Diskusija atnaujinta.';
            } else {
                $errors[] = 'Užpildykite pavadinimą ir tekstą.';
            }
        }
        if ($action === 'delete_thread' && ($user['is_admin'] || (int)$thread['user_id'] === (int)$user['id'])) {
            $pdo->prepare('DELETE FROM community_threads WHERE id = ?')->execute([$threadId]);
            $_SESSION['flash_success'] = 'Diskusija ištrinta.';
            header('Location: /community_discussions.php');
            exit;
        }
    }
    $threadStmt->execute([$threadId]);
    $thread = $threadStmt->fetch();
}

$commentsStmt = $pdo->prepare('SELECT c.*, u.name FROM community_comments c JOIN users u ON u.id = c.user_id WHERE c.thread_id = ? ORDER BY c.created_at ASC');
$commentsStmt->execute([$threadId]);
$comments = $commentsStmt->fetchAll();

$canEdit = $user['id'] && ($user['is_admin'] || (int)$thread['user_id'] === (int)$user['id']);

?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Diskusija | Cukrinukas</title>
  <?php echo headerStyles(); ?>
<style>
  :root { --bg:#f7f7fb; --border:#e3e6f2; --pill:#eef2ff; --shadow:0 16px 34px rgba(0,0,0,0.06); }
  body { background: var(--bg); }
  .thread-wrap { max-width:1000px; margin:38px auto 72px; padding:0 20px; display:flex; flex-direction:column; gap:18px; }
  .card { background:#fff; border:1px solid var(--border); border-radius:20px; box-shadow:var(--shadow); }
  .thread-card { padding:20px; display:flex; flex-direction:column; gap:12px; }
  .thread-meta { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
  .badge { padding:6px 12px; border-radius:999px; background:var(--pill); border:1px solid var(--border); font-weight:600; font-size:13px; color:#1f2b46; }
  .muted { color:#6b6b7a; }
  .pill-line { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .comment-stack { display:flex; flex-direction:column; gap:12px; }
  .comment { background:#f6f8ff; border:1px solid var(--border); border-radius:14px; padding:12px; }
  .alert { border-radius:12px; padding:12px; }
  .alert.success { background:#edf9f0; border:1px solid #b8e2c4; }
  .alert.error { background:#fff1f1; border:1px solid #f3b7b7; }
  textarea, input, select { border-radius:10px; border:1px solid var(--border); padding:10px; width:100%; }
.btn { padding:10px 14px; border-radius:12px; border:1px solid #0b0b0b; background:#0b0b0b; color:#fff; cursor:pointer; box-shadow:0 10px 24px rgba(0,0,0,0.08); }
.btn.ghost { background:#fff; color:#0b0b0b; border-color:var(--border); box-shadow:0 8px 22px rgba(0,0,0,0.05); }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main class="thread-wrap">
  <section class="card thread-card">
    <div class="thread-meta">
      <div>
        <div class="pill-line">
          <a href="/community_discussions.php" class="muted">← Diskusijos</a>
          <?php if (!empty($thread['category_name'])): ?><span class="badge">#<?php echo htmlspecialchars($thread['category_name']); ?></span><?php endif; ?>
        </div>
        <h1 style="margin:6px 0 6px 0; font-size:28px; line-height:1.2; color:#0b0b0b;">
          <?php echo htmlspecialchars($thread['title']); ?>
        </h1>
        <div class="muted" style="font-size:14px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <span>Autorius: <?php echo htmlspecialchars($thread['name']); ?></span>
          <span class="badge" style="background:#e8fff5; border-color:#cfe8dc; color:#0d8a4d;">Bendruomenės tema</span>
        </div>
      </div>
      <?php if ($canEdit): ?>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <form method="post" onsubmit="return confirm('Ištrinti diskusiją?');">
            <?php echo csrfField(); ?>
<input type="hidden" name="action" value="delete_thread">
            <button class="btn ghost" style="background:#fff;">Trinti</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
    <p style="line-height:1.6; margin-top:4px; color:#1f2b46;"><?php echo nl2br(htmlspecialchars($thread['body'])); ?></p>
    <?php if ($canEdit): ?>
      <details style="margin-top:6px;">
        <summary style="cursor:pointer; font-weight:600;">Redaguoti diskusiją</summary>
        <form method="post" style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
          <?php echo csrfField(); ?>
<input type="hidden" name="action" value="update_thread">
          <input type="text" name="title" value="<?php echo htmlspecialchars($thread['title']); ?>" required>
          <textarea name="body" rows="5" required><?php echo htmlspecialchars($thread['body']); ?></textarea>
          <select name="category_id">
            <option value="">Be kategorijos</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)$thread['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn" style="align-self:flex-start; background:#0b0b0b; border-color:#0b0b0b; color:#fff;">Išsaugoti</button>
        </form>
      </details>
    <?php endif; ?>
  </section>

  <?php foreach ($messages as $msg): ?>
    <div class="alert success">&check; <?php echo htmlspecialchars($msg); ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert error">&times; <?php echo htmlspecialchars($err); ?></div>
  <?php endforeach; ?>

  <section class="card thread-card">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0;">Komentarai</h2>
      <div class="badge" style="background:#fff;"><?php echo count($comments); ?> įrašų</div>
    </div>
    <div class="comment-stack">
      <?php foreach ($comments as $comment): ?>
        <div class="comment">
          <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap;">
            <strong><?php echo htmlspecialchars($comment['name']); ?></strong>
            <span class="muted" style="font-size:12px;"><?php echo htmlspecialchars($comment['created_at']); ?></span>
          </div>
          <div style="margin-top:6px; line-height:1.5; color:#1f2b46;">&bull; <?php echo nl2br(htmlspecialchars($comment['body'])); ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$comments): ?><div class="muted">Dar nėra komentarų.</div><?php endif; ?>
    </div>

    <?php if ($user['id'] && !$blocked): ?>
      <form method="post" style="display:flex; flex-direction:column; gap:10px; margin-top:14px;">
        <?php echo csrfField(); ?>
<input type="hidden" name="action" value="create_comment">
        <textarea name="body" rows="4" placeholder="Jūsų komentaras" required></textarea>
        <button class="btn" style="align-self:flex-start; background:#0b0b0b; border-color:#0b0b0b; color:#fff;">Siųsti</button>
      </form>
    <?php else: ?>
      <div class="muted" style="margin-top:10px;">Prisijunkite, kad komentuotumėte.</div>
    <?php endif; ?>
  </section>
</main>
  <?php renderFooter($pdo); ?>
</body>
</html>
