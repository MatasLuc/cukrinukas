<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureOrdersTables($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);
tryAutoLogin($pdo);

$userId = (int) $_SESSION['user_id'];

// Facebook Pixel Purchase Event Logic
$newPurchaseScript = '';
if (!empty($_SESSION['flash_success']) && strpos($_SESSION['flash_success'], 'Apmokėjimas patvirtintas') !== false) {
    // Gauname naujausią užsakymą
    $latestOrderStmt = $pdo->prepare('SELECT id, total FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $latestOrderStmt->execute([$userId]);
    $latest = $latestOrderStmt->fetch();
    
    if ($latest) {
        $safeTotal = (float)$latest['total'];
        $safeId = (int)$latest['id'];
        $newPurchaseScript = "
        <script>
          fbq('track', 'Purchase', {
            value: {$safeTotal},
            currency: 'EUR',
            content_ids: ['{$safeId}'],
            content_type: 'product'
          });
        </script>";
    }
}

$orderStmt = $pdo->prepare('SELECT id, total, status, created_at, customer_name, customer_address FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll();

$itemStmt = $pdo->prepare('SELECT oi.*, p.title, p.image_url FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mano užsakymai | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text-main: #0f172a;
      --text-muted: #475467;
      --accent: #2563eb;
      --accent-hover: #1d4ed8;
      --focus-ring: rgba(37, 99, 235, 0.2);
      --success-bg: #ecfdf5;
      --success-text: #065f46;
      --warning-bg: #fffbeb;
      --warning-text: #92400e;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; }
    a { color:inherit; text-decoration:none; }
    
    /* Pakeistas max-width į 1200px, padding ir gap suvienodinti su news.php */
    .page { max-width: 1200px; margin:0 auto; padding:32px 20px 72px; display:flex; flex-direction:column; gap:28px; }

    /* Hero Section */
    .hero { 
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border:1px solid #dbeafe; 
        border-radius:24px; 
        padding:32px; 
        display:flex; 
        flex-direction: column;
        align-items: flex-start;
        gap:16px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .hero h1 { margin:0 0 8px; font-size:28px; color:#1e3a8a; letter-spacing:-0.5px; }
    .hero p { margin:0; color:#1e40af; line-height:1.5; max-width:600px; font-size:15px; }
    .hero .pill { 
        display:inline-flex; align-items:center; gap:8px; 
        padding:6px 12px; border-radius:999px; 
        background:#fff; border:1px solid #bfdbfe; 
        font-weight:600; font-size:13px; color:#1e40af; 
    }

    .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom: 4px; }
    .section-header h2 { margin:0; font-size:20px; color: var(--text-main); }
    .section-header span { font-size: 14px; color: var(--text-muted); font-weight: 500; }

    /* Order Cards */
    .order-list { display:flex; flex-direction: column; gap:20px; }
    .card { 
        background:var(--card); 
        border:1px solid var(--border); 
        border-radius:16px; 
        overflow: hidden;
        box-shadow: 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        transition: transform .2s, box-shadow .2s;
    }
    .card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); border-color: #cbd5e1; }
    
    .card-header {
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .order-meta { display: flex; gap: 24px; }
    .meta-group { display: flex; flex-direction: column; gap: 2px; }
    .meta-label { font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px; }
    .meta-value { font-size: 14px; font-weight: 600; color: var(--text-main); }
    
    .status-badge { 
        padding:4px 10px; border-radius:999px; 
        font-size:12px; font-weight:600; text-transform: uppercase; letter-spacing: 0.5px;
        display:inline-flex; align-items:center; gap:6px; 
    }
    .status-pending { background: var(--warning-bg); color: var(--warning-text); border: 1px solid #fcd34d; }
    .status-completed { background: var(--success-bg); color: var(--success-text); border: 1px solid #6ee7b7; }
    .status-default { background: #f1f5f9; color: #475467; border: 1px solid #cbd5e1; }

    .card-body { padding: 20px; }
    
    .item-list { display:grid; gap:12px; margin-bottom: 20px; }
    .item { display:flex; gap:16px; align-items:center; }
    .item img { 
        width:64px; height:64px; object-fit:contain; 
        border-radius:8px; border:1px solid var(--border); 
        background: #fff; padding: 4px;
    }
    .item-details { flex:1; }
    .item-title { font-weight:600; font-size:15px; color:var(--text-main); margin-bottom: 4px; display: block; }
    .item-meta { font-size:13px; color: var(--text-muted); }
    .item-price { font-weight:700; font-size:15px; color:var(--text-main); }

    .card-footer {
        padding-top: 20px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .total-price { display: flex; flex-direction: column; align-items: flex-end; }
    .total-label { font-size: 13px; color: var(--text-muted); }
    .total-value { font-size: 20px; font-weight: 700; color: var(--accent); }

    /* Buttons Styling */
    .btn, .btn-outline { 
        padding:10px 18px; border-radius:10px; 
        font-weight:600; font-size:14px;
        cursor:pointer; text-decoration:none; 
        display:inline-flex; align-items:center; justify-content:center;
        transition: all .2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    /* Primary Button */
    .btn {
        border:none; 
        background: #0f172a; 
        color:#fff; 
    }
    .btn:hover { 
        background: #1e293b; 
        transform: translateY(-1px); 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    /* Secondary/Outline Button */
    .btn-outline { 
        background: #fff; 
        color: var(--text-main); 
        border: 1px solid var(--border); 
    }
    .btn-outline:hover { 
        border-color: var(--accent); 
        color: var(--accent); 
        background: #eff6ff;
        transform: translateY(-1px); 
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.15);
    }

    .empty-state {
        text-align: center;
        padding: 48px 20px;
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--border);
    }

    @media (max-width: 600px) {
        .card-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        .order-meta { width: 100%; justify-content: space-between; }
        .card-footer { flex-direction: column; gap: 16px; align-items: stretch; }
        .total-price { align-items: flex-start; }
        .btn, .btn-outline { width: 100%; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'orders'); ?>
  
  <div class="page">
    <section class="hero">
      <div class="pill">📦 Užsakymų istorija</div>
      <div>
        <h1>Mano užsakymai</h1>
        <p>Sekite užsakymų būseną, peržiūrėkite pirkinių istoriją ir lengvai pakartokite mėgstamus užsakymus.</p>
      </div>
    </section>

    <div class="section-header">
      <h2>Visi užsakymai</h2>
      <span>Viso: <?php echo count($orders); ?></span>
    </div>

    <?php if (!$orders): ?>
      <div class="empty-state">
        <div style="font-size: 48px; margin-bottom: 16px;">🛒</div>
        <h3 style="margin: 0 0 8px; font-size: 18px;">Kol kas neturite užsakymų</h3>
        <p class="muted" style="margin: 0 0 24px; font-size: 15px;">Atraskite mūsų asortimentą ir atlikite savo pirmąjį užsakymą.</p>
        <a class="btn" href="/products.php">Pradėti apsipirkimą</a>
      </div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($orders as $order): ?>
          <?php 
            $itemStmt->execute([$order['id']]); 
            $orderItems = $itemStmt->fetchAll(); 
            
            // Status styling logic
            $statusLower = mb_strtolower($order['status']);
            $statusClass = 'status-default';
            if (strpos($statusLower, 'laukiama') !== false) {
                $statusClass = 'status-pending';
            } elseif (strpos($statusLower, 'patvirtintas') !== false || strpos($statusLower, 'įvykdytas') !== false) {
                $statusClass = 'status-completed';
            }
          ?>
          <div class="card">
            <div class="card-header">
              <div class="order-meta">
                  <div class="meta-group">
                      <span class="meta-label">Užsakymo Nr.</span>
                      <span class="meta-value">#<?php echo (int)$order['id']; ?></span>
                  </div>
                  <div class="meta-group">
                      <span class="meta-label">Data</span>
                      <span class="meta-value"><?php echo htmlspecialchars(date('Y-m-d', strtotime($order['created_at']))); ?></span>
                  </div>
              </div>
              <div class="<?php echo $statusClass; ?> status-badge">
                 <?php echo htmlspecialchars($order['status']); ?>
              </div>
            </div>

            <div class="card-body">
                <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span><?php echo htmlspecialchars($order['customer_name']); ?>, <?php echo htmlspecialchars($order['customer_address']); ?></span>
                </div>

                <div class="item-list">
                  <?php foreach ($orderItems as $item): ?>
                    <div class="item">
                      <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                      <div class="item-details">
                        <a href="/product.php?id=<?php echo (int)$item['product_id']; ?>" class="item-title"><?php echo htmlspecialchars($item['title']); ?></a>
                        <div class="item-meta"><?php echo (int)$item['quantity']; ?> vnt. × <?php echo number_format((float)$item['price'], 2); ?> €</div>
                      </div>
                      <div class="item-price"><?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?> €</div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="card-footer">
                   <?php if ($statusLower === 'laukiama apmokėjimo'): ?>
                      <a class="btn" href="/libwebtopay/redirect.php?order_id=<?php echo (int)$order['id']; ?>">Apmokėti užsakymą</a>
                   <?php else: ?>
                      <a class="btn-outline" href="/products.php">Pirkti vėl</a>
                   <?php endif; ?>
                   
                   <div class="total-price">
                       <span class="total-label">Iš viso:</span>
                       <span class="total-value"><?php echo number_format((float)$order['total'], 2); ?> €</span>
                   </div>
                </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php renderFooter($pdo); ?>
  
  <?php echo $newPurchaseScript; ?>
</body>
</html>
