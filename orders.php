<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php'; // Būtina slugify funkcijai

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

// --- Facebook Pixel Purchase Event Logic ---
$newPurchaseScript = '';
if (!empty($_SESSION['flash_success']) && strpos($_SESSION['flash_success'], 'Apmokėjimas patvirtintas') !== false) {
    // Gauname naujausią užsakymą (kuris ką tik buvo apmokėtas)
    $latestOrderStmt = $pdo->prepare('SELECT id, total FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $latestOrderStmt->execute([$userId]);
    $latest = $latestOrderStmt->fetch();
    
    if ($latest) {
        $safeTotal = (float)$latest['total'];
        $safeId = (int)$latest['id'];
        $newPurchaseScript = "
        <script>
          if(typeof fbq === 'function') {
              fbq('track', 'Purchase', {
                value: {$safeTotal},
                currency: 'EUR',
                content_ids: ['{$safeId}'],
                content_type: 'product'
              });
          }
        </script>";
    }
}

// Gauname visus vartotojo užsakymus
$orderStmt = $pdo->prepare('
    SELECT id, total, status, created_at, customer_name, customer_address, delivery_method, delivery_details 
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
');
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll();

// Paruošiame užklausą prekių gavimui (su variation_info)
$itemStmt = $pdo->prepare('
    SELECT oi.*, p.title, p.image_url 
    FROM order_items oi 
    JOIN products p ON p.id = oi.product_id 
    WHERE oi.order_id = ?
');
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
      
      --success-bg: #ecfdf5;
      --success-text: #065f46;
      --success-border: #6ee7b7;

      --warning-bg: #fffbeb;
      --warning-text: #92400e;
      --warning-border: #fcd34d;

      --danger-bg: #fef2f2;
      --danger-text: #991b1b;
      --danger-border: #fca5a5;

      --neutral-bg: #f1f5f9;
      --neutral-text: #475467;
      --neutral-border: #cbd5e1;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; }
    a { color:inherit; text-decoration:none; transition: color .2s; }
    
    .page { max-width: 1000px; margin:0 auto; padding:32px 20px 80px; display:flex; flex-direction:column; gap:28px; }

    /* Hero Section */
    .hero { 
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border:1px solid #dbeafe; 
        border-radius:20px; 
        padding:24px 32px; 
        display:flex; 
        flex-direction: column;
        align-items: flex-start;
        gap:12px; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .hero h1 { margin:0; font-size:26px; color:#1e3a8a; letter-spacing:-0.5px; }
    .hero p { margin:0; color:#1e40af; line-height:1.5; font-size:14px; }
    .hero .pill { 
        display:inline-flex; align-items:center; gap:6px; 
        padding:4px 10px; border-radius:999px; 
        background:#fff; border:1px solid #bfdbfe; 
        font-weight:600; font-size:12px; color:#1e40af; 
    }

    .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom: 4px; padding: 0 4px; }
    .section-header h2 { margin:0; font-size:18px; color: var(--text-main); font-weight: 700; }
    .section-header span { font-size: 13px; color: var(--text-muted); font-weight: 500; background: #e2e8f0; padding: 2px 8px; border-radius: 12px; }

    /* Order Cards */
    .order-list { display:flex; flex-direction: column; gap:20px; }
    .card { 
        background:var(--card); 
        border:1px solid var(--border); 
        border-radius:16px; 
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: transform .2s, box-shadow .2s;
    }
    .card:hover { border-color: #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    
    .card-header {
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .order-meta { display: flex; gap: 24px; align-items: center; }
    .meta-group { display: flex; flex-direction: column; gap: 2px; }
    .meta-label { font-size: 10px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px; }
    .meta-value { font-size: 14px; font-weight: 600; color: var(--text-main); font-family: 'Roboto Mono', monospace; }
    .meta-value.date { font-family: 'Inter', sans-serif; }
    
    .status-badge { 
        padding:5px 12px; border-radius:6px; 
        font-size:12px; font-weight:600; text-transform: uppercase; letter-spacing: 0.5px;
        display:inline-flex; align-items:center; gap:6px;
    }
    .status-pending { background: var(--warning-bg); color: var(--warning-text); border: 1px solid var(--warning-border); }
    .status-completed { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
    .status-cancelled { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
    .status-default { background: var(--neutral-bg); color: var(--neutral-text); border: 1px solid var(--neutral-border); }

    .card-body { padding: 20px; }
    
    .delivery-info {
        font-size: 13px; color: var(--text-muted); margin-bottom: 16px; 
        display: flex; align-items: flex-start; gap: 8px;
        background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px dashed var(--border);
    }

    .item-list { display:grid; gap:16px; margin-bottom: 24px; }
    .item { display:flex; gap:16px; align-items:center; }
    .item img { 
        width:60px; height:60px; object-fit:contain; 
        border-radius:8px; border:1px solid var(--border); 
        background: #fff; padding: 4px;
        flex-shrink: 0;
    }
    .item-details { flex:1; min-width: 0; }
    .item-title { font-weight:600; font-size:15px; color:var(--text-main); margin-bottom: 2px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .item-variation { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: block; }
    .item-meta { font-size:13px; color: var(--text-main); font-weight: 500; }
    .item-price { font-weight:700; font-size:15px; color:var(--text-main); text-align: right; white-space: nowrap; }

    .card-footer {
        padding-top: 16px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }
    
    .total-price { display: flex; flex-direction: column; align-items: flex-end; }
    .total-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
    .total-value { font-size: 18px; font-weight: 700; color: var(--accent); }

    /* Buttons */
    .btn, .btn-outline { 
        padding:10px 20px; border-radius:8px; 
        font-weight:600; font-size:14px;
        cursor:pointer; text-decoration:none; 
        display:inline-flex; align-items:center; justify-content:center;
        transition: all .2s;
    }
    .btn { border:none; background: #0f172a; color:#fff; }
    .btn:hover { background: #1e293b; color:#fff; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    
    .btn-outline { background: #fff; color: var(--text-main); border: 1px solid var(--border); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); background: #f8fafc; }

    .empty-state {
        text-align: center;
        padding: 64px 20px;
        background: #fff;
        border-radius: 16px;
        border: 1px dashed var(--border);
    }

    @media (max-width: 600px) {
        .card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        .order-meta { width: 100%; justify-content: space-between; }
        .status-badge { width: 100%; justify-content: center; }
        .card-footer { flex-direction: column-reverse; align-items: stretch; }
        .total-price { align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 16px; width: 100%; }
        .btn, .btn-outline { width: 100%; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'orders'); ?>
  
  <div class="page">
    <section class="hero">
      <div class="pill">📦 Istorija</div>
      <div>
        <h1>Mano užsakymai</h1>
        <p>Sekite užsakymų būseną ir peržiūrėkite pirkinių istoriją.</p>
      </div>
    </section>

    <div class="section-header">
      <h2>Visi užsakymai</h2>
      <span><?php echo count($orders); ?></span>
    </div>

    <?php if (!$orders): ?>
      <div class="empty-state">
        <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">🛒</div>
        <h3 style="margin: 0 0 8px; font-size: 18px;">Kol kas neturite užsakymų</h3>
        <p style="color: var(--text-muted); margin: 0 0 24px; font-size: 15px;">Atraskite mūsų asortimentą ir atlikite savo pirmąjį užsakymą.</p>
        <a class="btn" href="/products.php">Pradėti apsipirkimą</a>
      </div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($orders as $order): ?>
          <?php 
            $itemStmt->execute([$order['id']]); 
            $orderItems = $itemStmt->fetchAll(); 
            
            // Būsenų logika
            $statusLower = mb_strtolower($order['status']);
            $statusClass = 'status-default';
            $canPay = false;

            if (strpos($statusLower, 'laukiama') !== false) {
                $statusClass = 'status-pending';
                if ($statusLower === 'laukiama apmokėjimo') {
                    $canPay = true;
                }
            } elseif (
                strpos($statusLower, 'patvirtintas') !== false || 
                strpos($statusLower, 'įvykdytas') !== false || 
                strpos($statusLower, 'apmokėta') !== false
            ) {
                $statusClass = 'status-completed';
            } elseif (
                strpos($statusLower, 'atšaukta') !== false || 
                strpos($statusLower, 'atmesta') !== false
            ) {
                $statusClass = 'status-cancelled';
            }
          ?>
          <div class="card">
            <div class="card-header">
              <div class="order-meta">
                  <div class="meta-group">
                      <span class="meta-label">Užsakymas</span>
                      <span class="meta-value">#<?php echo (int)$order['id']; ?></span>
                  </div>
                  <div class="meta-group">
                      <span class="meta-label">Data</span>
                      <span class="meta-value date"><?php echo htmlspecialchars(date('Y-m-d', strtotime($order['created_at']))); ?></span>
                  </div>
              </div>
              <div class="<?php echo $statusClass; ?> status-badge">
                 <?php echo htmlspecialchars($order['status']); ?>
              </div>
            </div>

            <div class="card-body">
                <div class="delivery-info">
                    <div style="margin-top:2px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                        <?php echo htmlspecialchars($order['customer_address']); ?>
                        <?php 
                            // Jei yra papildomų detalių (pvz. paštomatas)
                            if (!empty($order['delivery_details'])) {
                                $details = json_decode($order['delivery_details'], true);
                                if ($details && isset($details['method']) && $details['method'] === 'locker') {
                                    echo '<div style="margin-top:4px; font-size:12px; color:#2563eb;">📦 Paštomatas: ' . htmlspecialchars($details['address'] ?? '') . '</div>';
                                }
                            }
                        ?>
                    </div>
                </div>

                <div class="item-list">
                  <?php foreach ($orderItems as $item): ?>
                    <?php 
                      // SEO URL
                      $itemUrl = '/produktas/' . slugify($item['title']) . '-' . (int)$item['product_id']; 
                    ?>
                    <div class="item">
                      <a href="<?php echo htmlspecialchars($itemUrl); ?>">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                      </a>
                      <div class="item-details">
                        <a href="<?php echo htmlspecialchars($itemUrl); ?>" class="item-title"><?php echo htmlspecialchars($item['title']); ?></a>
                        
                        <?php if (!empty($item['variation_info'])): ?>
                            <span class="item-variation"><?php echo htmlspecialchars($item['variation_info']); ?></span>
                        <?php endif; ?>

                        <div class="item-meta"><?php echo (int)$item['quantity']; ?> vnt. × <?php echo number_format((float)$item['price'], 2); ?> €</div>
                      </div>
                      <div class="item-price"><?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?> €</div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="card-footer">
                   <div style="flex-grow:1;">
                       <?php if ($canPay): ?>
                          <a class="btn" href="/libwebtopay/redirect.php?order_id=<?php echo (int)$order['id']; ?>">Apmokėti užsakymą</a>
                       <?php else: ?>
                          <a class="btn-outline" href="/products.php">Pirkti vėl</a>
                       <?php endif; ?>
                   </div>
                   
                   <div class="total-price">
                       <span class="total-label">Viso mokėti</span>
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
