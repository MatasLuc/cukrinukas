<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
$messages = [];
$errors = [];
ensureUsersTable($pdo);
$dmReady = true;
try {
    ensureDirectMessages($pdo);
} catch (Throwable $e) {
    $dmReady = false;
    logError('Direct messages table bootstrap failed', $e);
    $errors[] = friendlyErrorMessage();
}
ensureNavigationTable($pdo);
$systemUserId = ensureSystemUser($pdo);

$user = currentUser();
if (!$user['id']) {
    header('Location: /login.php');
    exit;
}
$activePartnerId = isset($_GET['user']) ? (int)$_GET['user'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';
    if (!$dmReady) {
        $errors[] = 'Žinučių siųsti nepavyko (lentelė nepasiekiama).';
    } elseif ($action === 'send_new') {
        $recipientEmail = trim($_POST['recipient_email'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if (!$recipientEmail || !$body) {
            $errors[] = 'Užpildykite gavėją ir žinutę.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$recipientEmail]);
                $recipient = $stmt->fetch();
                if (!$recipient) {
                    $errors[] = 'Gavėjas nerastas.';
                } elseif ((int)$recipient['id'] === (int)$user['id']) {
                    $errors[] = 'Negalite rašyti sau.';
                } else {
                    $insert = $pdo->prepare('INSERT INTO direct_messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
                    $insert->execute([$user['id'], $recipient['id'], $body]);
                    $messages[] = 'Žinutė išsiųsta ' . htmlspecialchars($recipient['name']);
                    $activePartnerId = (int)$recipient['id'];
                }
            } catch (Throwable $e) {
                logError('Sending new direct message failed', $e);
                $errors[] = friendlyErrorMessage();
            }
        }
    }

    if ($dmReady && $action === 'send_existing') {
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if ($partnerId && $body) {
            try {
                $insert = $pdo->prepare('INSERT INTO direct_messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
                $insert->execute([$user['id'], $partnerId, $body]);
                $messages[] = 'Žinutė išsiųsta.';
                $activePartnerId = $partnerId;
            } catch (Throwable $e) {
                logError('Sending existing direct message failed', $e);
                $errors[] = friendlyErrorMessage();
            }
        }
    }
}

$conversations = [];
$partnerIds = [];
if ($dmReady) {
    try {
        $stmt = $pdo->prepare('SELECT CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner_id, MAX(created_at) AS last_time FROM direct_messages WHERE sender_id = ? OR recipient_id = ? GROUP BY partner_id ORDER BY last_time DESC');
        $stmt->execute([$user['id'], $user['id'], $user['id']]);
        foreach ($stmt->fetchAll() as $row) {
            $partnerIds[] = (int)$row['partner_id'];
            $conversations[(int)$row['partner_id']] = ['partner_id' => (int)$row['partner_id'], 'last_time' => $row['last_time']];
        }
    } catch (Throwable $e) {
        logError('Loading conversation list failed', $e);
        $errors[] = friendlyErrorMessage();
    }
}

if ($partnerIds) {
    $in = implode(',', array_fill(0, count($partnerIds), '?'));
    $detailStmt = $pdo->prepare("SELECT id, name FROM users WHERE id IN ($in)");
    $detailStmt->execute($partnerIds);
    foreach ($detailStmt->fetchAll() as $u) {
        $pid = (int)$u['id'];
        if (isset($conversations[$pid])) {
            $conversations[$pid]['name'] = $u['name'];
        }
    }
}

if (!$activePartnerId && $partnerIds) {
    $activePartnerId = $partnerIds[0];
}

$activePartnerName = null;
if ($activePartnerId) {
    if (isset($conversations[$activePartnerId]['name'])) {
        $activePartnerName = $conversations[$activePartnerId]['name'];
    } else {
        try {
            $nameStmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
            $nameStmt->execute([$activePartnerId]);
            $foundName = $nameStmt->fetchColumn();
            if ($foundName) {
                $activePartnerName = $foundName;
            }
        } catch (Throwable $e) {
            // Leave as null
        }
    }
}

$threadMessages = [];
if ($dmReady && $activePartnerId) {
    try {
        $stmt = $pdo->prepare('SELECT m.*, s.name AS sender_name, s.profile_photo AS sender_photo FROM direct_messages m JOIN users s ON s.id = m.sender_id WHERE (m.sender_id = :uid1 AND m.recipient_id = :pid1) OR (m.sender_id = :pid2 AND m.recipient_id = :uid2) ORDER BY m.created_at ASC');
        $stmt->execute([':uid1' => $user['id'], ':pid1' => $activePartnerId, ':pid2' => $activePartnerId, ':uid2' => $user['id']]);
        $threadMessages = $stmt->fetchAll();
        markDirectMessagesRead($pdo, $user['id'], $activePartnerId);
    } catch (Throwable $e) {
        logError('Loading direct message thread failed', $e);
        $errors[] = friendlyErrorMessage();
    }
}

echo headerStyles();
renderHeader($pdo, 'community');
?>
<style>
  :root {
    --bg: #f7f7fb;
    --card: #ffffff;
    --border: #e4e7ec;
    --text: #0f172a;
    --muted: #52606d;
    --accent: #4338ca;
  }
  body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
  a { color:inherit; text-decoration:none; }

  .page { max-width:1200px; margin:0 auto; padding:30px 20px 60px; display:flex; flex-direction:column; gap:18px; }
  .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border:1px solid #e5e7eb; border-radius:28px; padding:22px 20px; box-shadow:0 24px 60px rgba(0,0,0,0.08); display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap; align-items:center; }
  .hero h1 { margin:0; font-size:clamp(26px, 5vw, 34px); letter-spacing:-0.02em; color:#0b1224; }
  .hero p { margin:6px 0 0; color: var(--muted); line-height:1.6; max-width:640px; }
  .pill { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; background:#fff; border:1px solid #e4e7ec; font-weight:700; color:#0b1224; box-shadow:0 12px 26px rgba(0,0,0,0.08); }

  .layout { display:grid; grid-template-columns:320px 1fr; gap:18px; align-items:start; }
  @media(max-width: 960px){ .layout { grid-template-columns:1fr; } }

  .card { background:var(--card); border:1px solid var(--border); border-radius:18px; box-shadow:0 14px 32px rgba(0,0,0,0.06); padding:18px; }
  .card h3 { margin:0 0 10px; font-size:18px; }
  .muted { color: var(--muted); }
  .btn { padding:10px 14px; border-radius:12px; border:1px solid transparent; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 14px 36px rgba(124,58,237,0.25); transition: transform .18s ease, box-shadow .18s ease; }
  .btn:hover { transform: translateY(-1px); box-shadow:0 18px 52px rgba(67,56,202,0.35); }
  .ghost { background:transparent; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none; }

  .conversation-list { display:flex; flex-direction:column; gap:8px; }
  .conversation { padding:12px; border-radius:12px; border:1px solid var(--border); background:#fff; display:flex; justify-content:space-between; gap:8px; align-items:center; transition: border-color .18s ease, box-shadow .18s ease; }
  .conversation.active { border-color:#c7d2fe; box-shadow:0 12px 28px rgba(67,56,202,0.12); background:#f5f7ff; }

  .bubble { border-radius:14px; padding:12px 14px; max-width:75%; line-height:1.5; }
  .bubble.me { background:#e6efff; }
  .bubble.them { background:#f9fafb; }
  .message { display:flex; flex-direction:column; gap:6px; }
  .bubble-row { display:flex; align-items:flex-end; gap:10px; }
  .bubble-row.me { justify-content:flex-end; }
  .bubble-row.them { justify-content:flex-start; }
  .mini-avatar { width:36px; height:36px; border-radius:12px; background:#eef2ff; border:1px solid #e4e7ec; display:flex; align-items:center; justify-content:center; font-weight:700; color:#4338ca; overflow:hidden; flex-shrink:0; }
  .mini-avatar img { width:100%; height:100%; object-fit:cover; }
  textarea, input[type=email], input[type=text] { width:100%; border-radius:12px; border:1px solid var(--border); padding:12px; background:#f9fafb; font-family:inherit; }
  @media(max-width: 720px){
    .bubble { max-width:100%; }
    .layout { gap:12px; }
  }
</style>
<main class="page">
  <section class="hero">
    <div>
      <div class="pill">💬 Žinutės</div>
      <h1>Privatūs pokalbiai</h1>
      <p>Kurkite naujus susirašinėjimus, tęskite esamus ir gaukite pranešimus vienoje vietoje, išlaikant #f7f7fb šviesų foną.</p>
    </div>
    <a class="btn ghost" href="#new">Nauja žinutė</a>
  </section>

  <div class="layout">
    <div style="display:flex; flex-direction:column; gap:12px;">
      <section class="card" id="new">
        <h3 style="margin:0 0 8px 0;">Nauja žinutė</h3>
        <form method="post" style="display:grid; gap:10px;">
          <?php echo csrfField(); ?>
<input type="hidden" name="action" value="send_new">
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:13px;">Gavėjo el. paštas</span>
            <input type="email" name="recipient_email" required>
          </label>
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:13px;">Žinutė</span>
            <textarea name="body" style="min-height:90px;" required></textarea>
          </label>
          <button class="btn ghost" style="align-self:flex-start;">Siųsti</button>
        </form>
      </section>

      <section class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <h3 style="margin:0;">Pokalbiai</h3>
          <span class="muted" style="font-size:13px;"><?php echo count($conversations); ?> gijų</span>
        </div>
        <?php if ($conversations): ?>
          <div class="conversation-list">
            <?php foreach ($conversations as $conv): ?>
              <a class="conversation <?php echo $activePartnerId===(int)$conv['partner_id'] ? 'active' : ''; ?>" href="?user=<?php echo (int)$conv['partner_id']; ?>">
                <div>
                  <div style="font-weight:700;"><?php echo htmlspecialchars($conv['name'] ?? 'Narys #' . $conv['partner_id']); ?></div>
                  <div class="muted" style="font-size:12px;">Atnaujinta: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($conv['last_time']))); ?></div>
                </div>
                <span style="font-size:18px; color:#c7d2fe;">•</span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="muted">Dar neturite žinučių.</div>
        <?php endif; ?>
      </section>
    </div>

    <section class="card" style="display:flex; flex-direction:column; gap:12px;">
      <?php foreach ($messages as $msg): ?>
        <div style="background:#edf9f0;border:1px solid #b8e2c4;padding:10px;border-radius:10px;">&check; <?php echo $msg; ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $err): ?>
        <div style="background:#fff1f1;border:1px solid #f3b7b7;padding:10px;border-radius:10px;">&times; <?php echo $err; ?></div>
      <?php endforeach; ?>

      <?php if ($activePartnerId): ?>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <h3 style="margin:0; font-size:18px;">Pokalbis su <span style="color:#5671c4;"><?php echo htmlspecialchars($activePartnerName ?? ('Narys #' . $activePartnerId)); ?></span></h3>
          <span class="muted" style="font-size:13px;">#<?php echo (int)$activePartnerId; ?></span>
        </div>
          <div style="border:1px solid var(--border); border-radius:14px; padding:12px; display:flex; flex-direction:column; gap:10px; max-height:460px; overflow:auto; background:#fff;">
            <?php if ($threadMessages): ?>
              <?php foreach ($threadMessages as $tm): ?>
                <?php
                  $isMe = (int)$tm['sender_id'] === (int)$user['id'];
                  $avatarInitial = strtoupper(substr($tm['sender_name'] ?? 'V', 0, 1));
                  $avatarImg = !empty($tm['sender_photo']) ? $tm['sender_photo'] : null;
                ?>
                <div class="message" style="<?php echo $isMe ? 'align-items:flex-end;' : ''; ?>">
                  <div style="font-size:13px; color:#6b6b7a; display:flex; gap:6px; align-items:center; <?php echo $isMe ? 'flex-direction:row-reverse;' : ''; ?>">
                    <span><?php echo htmlspecialchars($tm['sender_name']); ?></span>
                    <span aria-hidden="true">•</span>
                    <span><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($tm['created_at']))); ?></span>
                  </div>
                  <div class="bubble-row <?php echo $isMe ? 'me' : 'them'; ?>">
                    <?php if (!$isMe): ?>
                      <div class="mini-avatar">
                        <?php if ($avatarImg): ?>
                          <img src="<?php echo htmlspecialchars($avatarImg); ?>" alt="">
                        <?php else: ?>
                          <?php echo htmlspecialchars($avatarInitial); ?>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                    <div class="bubble <?php echo $isMe ? 'me' : 'them'; ?>">
                      <?php
                        $isSystemMessage = $systemUserId && ((int)$tm['sender_id'] === (int)$systemUserId);
                        echo $isSystemMessage ? $tm['body'] : nl2br(htmlspecialchars($tm['body']));
                      ?>
                    </div>
                    <?php if ($isMe): ?>
                      <div class="mini-avatar">
                        <?php if ($avatarImg): ?>
                          <img src="<?php echo htmlspecialchars($avatarImg); ?>" alt="">
                        <?php else: ?>
                          <?php echo htmlspecialchars($avatarInitial); ?>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="muted">Dar nėra žinučių.</div>
            <?php endif; ?>
          </div>
        <form method="post" style="display:flex; gap:10px; align-items:flex-start;">
          <?php echo csrfField(); ?>
<input type="hidden" name="action" value="send_existing">
          <input type="hidden" name="partner_id" value="<?php echo (int)$activePartnerId; ?>">
          <textarea name="body" style="flex:1; min-height:80px;" placeholder="Parašykite žinutę"></textarea>
          <button class="btn" type="submit">Siųsti</button>
        </form>
      <?php else: ?>
        <div class="muted">Pasirinkite pokalbį arba sukurkite naują.</div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php renderFooter(); ?>
