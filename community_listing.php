<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureNavigationTable($pdo);
ensureDirectMessages($pdo);
$systemUserId = ensureSystemUser($pdo);

$user = currentUser();
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$listingId) {
    header('Location: /community_market.php');
    exit;
}

// Fetch listing with seller
$stmt = $pdo->prepare('SELECT l.*, u.name AS seller_name, c.name AS category_name FROM community_listings l JOIN users u ON u.id = l.user_id LEFT JOIN community_listing_categories c ON c.id = l.category_id WHERE l.id = ? LIMIT 1');
$stmt->execute([$listingId]);
$listing = $stmt->fetch();
if (!$listing) {
    header('Location: /community_market.php');
    exit;
}

$blocked = $user['id'] ? isCommunityBlocked($pdo, (int)$user['id']) : null;
$messages = [];
$errors = [];

function notifyDirectMessage(PDO $pdo, int $systemUserId, int $toId, string $body): void {
    if ($systemUserId === $toId) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO direct_messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$systemUserId, $toId, $body]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['id'] && !$blocked) {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_status') {
        $status = $_POST['status'] === 'sold' ? 'sold' : 'active';
        if ((int)$listing['user_id'] === (int)$user['id'] || $user['is_admin']) {
            $pdo->prepare('UPDATE community_listings SET status = ? WHERE id = ?')->execute([$status, $listingId]);
            $listing['status'] = $status;
            $messages[] = 'Būsena atnaujinta';
        }
    }

    if ($action === 'create_order') {
        $note = trim($_POST['note'] ?? '');
        if ((int)$listing['user_id'] === (int)$user['id']) {
            $errors[] = 'Negalite pateikti užklausos savo skelbimui.';
        } elseif ($listing['status'] !== 'active') {
            $errors[] = 'Šis skelbimas nebeaktyvus.';
        } else {
            $pdo->prepare('INSERT INTO community_orders (listing_id, buyer_id, note) VALUES (?, ?, ?)')
                ->execute([$listingId, $user['id'], $note]);
            $messages[] = 'Užklausa išsiųsta pardavėjui';

            $orderLink = '/community_listing.php?id=' . $listingId;
            $safeTitle = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
            $safeSender = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
            $body = 'Nauja užklausa dėl skelbimo „' . $safeTitle . '“ nuo ' . $safeSender . '.<br><a href="' . $orderLink . '">Peržiūrėti</a>';
            notifyDirectMessage($pdo, $systemUserId, (int)$listing['user_id'], $body);
        }
    }

    if ($action === 'order_message') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if ($orderId && $body) {
            $stmt = $pdo->prepare('SELECT o.*, l.user_id AS seller_id FROM community_orders o JOIN community_listings l ON l.id = o.listing_id WHERE o.id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if ($order && ((int)$order['buyer_id'] === (int)$user['id'] || (int)$order['seller_id'] === (int)$user['id'])) {
                $pdo->prepare('INSERT INTO community_order_messages (order_id, user_id, body) VALUES (?, ?, ?)')
                    ->execute([$orderId, $user['id'], $body]);
                $messages[] = 'Žinutė išsiųsta';

                $recipientId = (int)$order['buyer_id'] === (int)$user['id'] ? (int)$order['seller_id'] : (int)$order['buyer_id'];
                $contextLink = '/community_listing.php?id=' . (int)$order['listing_id'];
                $safeTitle = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
                $safeSender = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
                $safeBody = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
                $bodyText = $safeSender . ' parašė dėl skelbimo „' . $safeTitle . '“: ' . $safeBody . '<br><a href="' . $contextLink . '">Peržiūrėti</a>';
                notifyDirectMessage($pdo, $systemUserId, $recipientId, $bodyText);
            }
        }
    }

    if ($action === 'upload_image' && ((int)$listing['user_id'] === (int)$user['id'] || $user['is_admin'])) {
        $img = uploadImageWithValidation($_FILES['image'] ?? [], 'community_', $errors, null, false);
        if ($img) {
            $pdo->prepare('UPDATE community_listings SET image_url = ? WHERE id = ?')->execute([$img, $listingId]);
            $listing['image_url'] = $img;
            $messages[] = 'Nuotrauka atnaujinta';
        }
    }
}

// Reload orders/messages
$orderStmt = $pdo->prepare('SELECT o.*, u.name AS buyer_name FROM community_orders o JOIN users u ON u.id = o.buyer_id WHERE o.listing_id = ? ORDER BY o.created_at DESC');
$orderStmt->execute([$listingId]);
$orders = $orderStmt->fetchAll();

$orderMessages = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $msgStmt = $pdo->prepare("SELECT m.*, u.name FROM community_order_messages m JOIN users u ON u.id = m.user_id WHERE m.order_id IN ($in) ORDER BY m.created_at ASC");
    $msgStmt->execute($ids);
    foreach ($msgStmt->fetchAll() as $row) {
        $orderMessages[$row['order_id']][] = $row;
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Skelbimas | Cukrinukas</title>
  <?php echo headerStyles(); ?>
<style>
  :root { --bg:#f7f7fb; --border:#e3e6f2; --pill:#eef2ff; --shadow:0 16px 34px rgba(0,0,0,0.06); }
  body { background: var(--bg); }
  .community-shell { max-width:1100px; margin:32px auto 70px; padding:0 20px; display:flex; flex-direction:column; gap:18px; }
  .card { background:#fff; border:1px solid var(--border); border-radius:20px; box-shadow:var(--shadow); }
  .hero-card { padding:20px; display:grid; grid-template-columns:1fr 1.1fr; gap:18px; align-items:start; }
  .hero-media { position:relative; overflow:hidden; border-radius:16px; border:1px solid var(--border); background:#fff; min-height:240px; display:flex; align-items:center; justify-content:center; color:#7b809a; }
  .hero-media img { width:100%; height:100%; object-fit:cover; max-height:360px; }
  .hero-body { display:flex; flex-direction:column; gap:12px; }
  .meta-line { display:flex; align-items:center; gap:10px; flex-wrap:wrap; color:#6b6b7a; font-size:14px; }
  .pill { padding:6px 12px; border-radius:999px; background:var(--pill); border:1px solid var(--border); font-weight:600; font-size:13px; color:#1f2b46; }
  .price { font-size:26px; font-weight:800; color:#0b0b0b; }
  .stack { display:flex; flex-direction:column; gap:10px; }
  .muted { color:#6b6b7a; }
  .btn-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .btn { padding:10px 14px; border-radius:12px; border:1px solid #0b0b0b; background:#0b0b0b; color:#fff; cursor:pointer; box-shadow:0 10px 24px rgba(0,0,0,0.08); }
  .btn.ghost { background:#fff; color:#0b0b0b; border-color:var(--border); box-shadow:0 8px 22px rgba(0,0,0,0.05); }
  .panel { border:1px solid var(--border); border-radius:14px; padding:12px; background:#f9f9ff; }
  .alert { border-radius:12px; padding:12px; }
  .alert.success { background:#edf9f0; border:1px solid #b8e2c4; }
  .alert.error { background:#fff1f1; border:1px solid #f3b7b7; }
  .orders-card { padding:18px; display:flex; flex-direction:column; gap:12px; }
  .order-item { border:1px solid var(--border); border-radius:12px; padding:12px; background:#f9f9ff; display:flex; flex-direction:column; gap:8px; }
  .order-messages { border:1px solid var(--border); border-radius:10px; padding:8px; background:#fff; display:flex; flex-direction:column; gap:6px; }
  @media(max-width: 900px){ .hero-card { grid-template-columns:1fr; } }
</style>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main class="community-shell">
  <section class="card hero-card">
    <div class="hero-media">
      <?php if ($listing['image_url']): ?>
        <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>">
      <?php else: ?>
        <div>Nuotrauka bus čia</div>
      <?php endif; ?>
      <?php if ($user['id'] && ((int)$listing['user_id'] === (int)$user['id'] || $user['is_admin'])): ?>
        <form method="post" enctype="multipart/form-data" style="position:absolute; left:16px; bottom:16px; background:rgba(255,255,255,0.9); border:1px solid var(--border); padding:10px; border-radius:12px; display:flex; gap:8px; align-items:center;">
          <?php echo csrfField(); ?>
<input type="hidden" name="action" value="upload_image">
          <input type="file" name="image" accept="image/*" required>
          <button class="btn ghost" style="padding:8px 12px;">Atnaujinti</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="hero-body">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div class="stack">
          <div class="meta-line">
            <a href="/community_market.php" class="muted">← Skelbimai</a>
            <?php if (!empty($listing['category_name'])): ?><span class="pill">#<?php echo htmlspecialchars($listing['category_name']); ?></span><?php endif; ?>
          </div>
          <h1 style="margin:0; font-size:28px; line-height:1.15; color:#0b0b0b;"><?php echo htmlspecialchars($listing['title']); ?></h1>
          <div class="meta-line">
            <span>Pardavėjas: <?php echo htmlspecialchars($listing['seller_name']); ?></span>
            <span class="pill" style="background:<?php echo $listing['status']==='sold' ? '#ffe9e9' : '#e8fff5'; ?>; border-color:<?php echo $listing['status']==='sold' ? '#f7c9c9' : '#cfe8dc'; ?>; color:<?php echo $listing['status']==='sold' ? '#a93131' : '#0d8a4d'; ?>;">Statusas: <?php echo $listing['status']==='sold' ? 'Parduota' : 'Aktyvi'; ?></span>
          </div>
        </div>
        <div class="price">€<?php echo number_format((float)$listing['price'], 2); ?></div>
      </div>
      <div style="font-size:16px; line-height:1.7; color:#1f2b46;"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></div>
      <div class="btn-bar">
        <?php if ($user['id'] && ((int)$listing['user_id'] === (int)$user['id'] || $user['is_admin'])): ?>
          <form method="post" style="margin:0; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <?php echo csrfField(); ?>
<input type="hidden" name="action" value="mark_status">
            <input type="hidden" name="status" value="<?php echo $listing['status']==='sold' ? 'active' : 'sold'; ?>">
            <button class="btn ghost" style="color:#0b0b0b; border-color:#0b0b0b;">
              <?php echo $listing['status']==='sold' ? 'Pažymėti aktyviu' : 'Pažymėti parduotu'; ?>
            </button>
            <a class="btn" href="/community_listing_edit.php?id=<?php echo $listingId; ?>" style="background:#829ed6; border-color:#829ed6; color:#0b0b0b;">Redaguoti</a>
          </form>
        <?php endif; ?>
      </div>
      <?php if ($user['id']): ?>
        <div class="panel">
          <div style="font-weight:700;">Kontaktai</div>
          <?php if ($listing['seller_email']): ?><div class="muted" style="font-size:13px;">El. paštas: <?php echo htmlspecialchars($listing['seller_email']); ?></div><?php endif; ?>
          <?php if ($listing['seller_phone']): ?><div class="muted" style="font-size:13px;">Tel.: <?php echo htmlspecialchars($listing['seller_phone']); ?></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="muted" style="font-size:13px;">Prisijunkite, kad matytumėte pardavėjo kontaktus.</div>
      <?php endif; ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert success">&check; <?php echo htmlspecialchars($msg); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert error">&times; <?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>
      <?php if ($user['id'] && !$blocked && $listing['status'] === 'active' && (int)$listing['user_id'] !== (int)$user['id']): ?>
        <form method="post" class="panel" style="display:flex; flex-direction:column; gap:8px;">
          <?php echo csrfField(); ?>
<input type="hidden" name="action" value="create_order">
          <label style="display:flex; flex-direction:column; gap:6px;">
            <span class="muted" style="font-size:13px;">Trumpa pastaba pardavėjui</span>
            <textarea name="note" style="min-height:90px; border-radius:10px; border:1px solid var(--border); padding:10px;" placeholder="Norėčiau daugiau info apie..."></textarea>
          </label>
          <button class="btn" style="background:#829ed6; border-color:#829ed6; color:#0b0b0b; align-self:flex-start;">Siųsti užklausą</button>
        </form>
      <?php elseif ($listing['status'] === 'sold'): ?>
        <div class="muted">Skelbimas parduotas.</div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($orders): ?>
    <section class="card orders-card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <h2 style="margin:0;">Užklausos</h2>
        <div class="muted" style="font-size:13px;">Matomos pirkėjui ir pardavėjui</div>
      </div>
      <div style="display:grid; gap:10px;">
        <?php foreach ($orders as $order): ?>
          <div class="order-item">
            <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; align-items:center;">
              <strong><?php echo htmlspecialchars($order['buyer_name']); ?></strong>
              <span class="pill" style="background:#fff;">Statusas: <?php echo htmlspecialchars($order['status']); ?></span>
            </div>
            <?php if ($order['note']): ?><div><?php echo nl2br(htmlspecialchars($order['note'])); ?></div><?php endif; ?>
            <?php if (!empty($orderMessages[$order['id']])): ?>
              <div class="order-messages">
                <?php foreach ($orderMessages[$order['id']] as $msg): ?>
                  <div>
                    <strong><?php echo htmlspecialchars($msg['name']); ?>:</strong>
                    <div style="margin-top:2px;">• <?php echo nl2br(htmlspecialchars($msg['body'])); ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($user['id'] && ((int)$order['buyer_id'] === (int)$user['id'] || (int)$listing['user_id'] === (int)$user['id'] || $user['is_admin'])): ?>
              <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start;">
                <?php echo csrfField(); ?>
<input type="hidden" name="action" value="order_message">
                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                <textarea name="body" style="flex:1; min-height:70px; border-radius:10px; border:1px solid var(--border); padding:10px;" placeholder="Rašyti žinutę"></textarea>
                <button class="btn" style="background:#0b0b0b; border-color:#0b0b0b; color:#fff;">Siųsti</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
  <?php renderFooter($pdo); ?>
</body>
</html>
