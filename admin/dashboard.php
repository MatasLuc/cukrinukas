<?php
// admin/dashboard.php

// 1. STATISTIKOS SURINKIMAS
// ------------------------

$totalSalesHero = 0;
$ordersCountHero = 0;
$userCountHero = 0;
$averageOrderHero = 0;
$currentMonthSales = 0;
$salesGrowth = 0;
$latestOrders = [];
$lowStockItems = []; // Pakeistas pavadinimas, nes talpins ir variacijas
$chartDataRaw = [];

try {
    // --- VARTOTOJAI ---
    $userCountHero = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;

    // --- UŽSAKYMAI (TIK ĮVYKDYTI) ---
    // Viso pardavimai
    $totalSalesHero = $pdo->query("SELECT SUM(total) FROM orders WHERE status = 'įvykdyta'")->fetchColumn() ?: 0;
    
    // Viso užsakymų skaičius (įvykdytų)
    $ordersCountHero = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'įvykdyta'")->fetchColumn() ?: 0;
    
    // Vidutinis krepšelis
    $averageOrderHero = $pdo->query("SELECT AVG(total) FROM orders WHERE status = 'įvykdyta'")->fetchColumn() ?: 0;

    // Šio mėnesio pardavimai
    $currentMonthSales = $pdo->query("
        SELECT SUM(total) FROM orders 
        WHERE status = 'įvykdyta' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ")->fetchColumn() ?: 0;
    
    // Praėjusio mėnesio pardavimai
    $lastMonthSales = $pdo->query("
        SELECT SUM(total) FROM orders 
        WHERE status = 'įvykdyta' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
    ")->fetchColumn() ?: 0;
    
    // Augimas %
    if ($lastMonthSales > 0) {
        $salesGrowth = (($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100;
    } elseif ($currentMonthSales > 0) {
        $salesGrowth = 100;
    }

    // --- NAUJAUSI UŽSAKYMAI (Rodome visus ne atšauktus, bet paryškiname įvykdytus, arba pagal prašymą - tik įvykdytus) ---
    // Jei norite matyti TIK įvykdytus sąraše: WHERE status = 'įvykdyta'
    // Tačiau naujausių užsakymų sąraše logiška matyti ir 'laukiama', kad galėtumėte juos administruoti.
    // Palieku rodyti visus NE ATŠAUKTUS, kad matytumėte įeinančius užsakymus, 
    // bet statistika viršuje skaičiuojama tik nuo įvykdytų.
    $latestOrders = $pdo->query("
        SELECT id, customer_name, total, status, created_at 
        FROM orders 
        WHERE status != 'atšaukta' 
        ORDER BY created_at DESC 
        LIMIT 6
    ")->fetchAll();

    // --- MAŽAS LIKUTIS (PREKĖS IR VARIACIJOS <= 1) ---
    // Naudojame UNION, kad sujungtume paprastas prekes ir variacijas
    // Pastaba: Pataisytas stulpelio pavadinimas iš 'stock_quantity' į 'quantity'
    $lowStockQuery = "
        (SELECT p.id, p.title, p.quantity, p.image_url, 'simple' as type 
         FROM products p 
         WHERE p.quantity <= 1)
        UNION ALL
        (SELECT p.id, CONCAT(p.title, ' (', pv.name, ')') as title, pv.quantity, p.image_url, 'variation' as type 
         FROM product_variations pv 
         JOIN products p ON pv.product_id = p.id 
         WHERE pv.quantity <= 1)
        ORDER BY quantity ASC 
        LIMIT 10
    ";
    $lowStockItems = $pdo->query($lowStockQuery)->fetchAll();

    // --- TOP PREKĖS (Pagal pardavimus iš įvykdytų užsakymų) ---
    $topProducts = $pdo->query("
        SELECT p.id, p.title, p.image_url, SUM(oi.quantity) as sold_count
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status = 'įvykdyta'
        GROUP BY p.id
        ORDER BY sold_count DESC
        LIMIT 5
    ")->fetchAll();

    // --- GRAFIKAS (7 DIENOS, TIK ĮVYKDYTI) ---
    $chartDataRaw = $pdo->query("
        SELECT DATE(created_at) as date, SUM(total) as total 
        FROM orders 
        WHERE created_at >= DATE(NOW()) - INTERVAL 7 DAY 
        AND status = 'įvykdyta'
        GROUP BY DATE(created_at)
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (Exception $e) {
    // Jei vis dar yra klaidų, parodome jas klaidų masyve (admin.php), kad žinotumėte kas negerai
    $errors[] = "Dashboard Error: " . $e->getMessage();
}

// Grafiko duomenų paruošimas
$dates = [];
$chartData = [];
$maxVal = 0;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $val = $chartDataRaw[$d] ?? 0;
    $chartData[$d] = $val;
    if ($val > $maxVal) $maxVal = $val;
}
if ($maxVal == 0) $maxVal = 1;
?>

<style>
    .stat-card {
        background: #fff; border-radius: 12px; padding: 20px;
        border: 1px solid #e5e7eb; position: relative;
        display: flex; flex-direction: column; justify-content: space-between;
    }
    .stat-title { color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 700; color: #111827; }
    .stat-trend { font-size: 13px; font-weight: 600; margin-top: 8px; display: inline-flex; align-items: center; gap: 4px; }
    .trend-up { color: #059669; }
    .trend-down { color: #dc2626; }
    
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-laukiama { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .status-apdorojama { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
    .status-išsiųsta { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
    .status-įvykdyta { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
    .status-atšaukta { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

    .chart-container {
        display: flex; align-items: flex-end; justify-content: space-between;
        height: 200px; margin-top: 20px; gap: 8px;
    }
    .bar-group {
        display: flex; flex-direction: column; align-items: center; flex: 1;
    }
    .bar {
        width: 100%; background: #e0e7ff; border-radius: 4px 4px 0 0;
        transition: height 0.5s ease; position: relative;
        min-height: 4px;
    }
    .bar:hover { background: #6366f1; }
    .bar:hover::after {
        content: attr(data-val) ' €';
        position: absolute; top: -25px; left: 50%; transform: translateX(-50%);
        background: #1f2937; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px;
        white-space: nowrap; pointer-events: none; z-index: 10;
    }
    .bar-label { margin-top: 8px; font-size: 11px; color: #6b7280; }
    
    .product-list-item {
        display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f4f6;
    }
    .product-list-item:last-child { border-bottom: none; }
    .list-img { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; background: #f3f4f6; }
</style>

<div class="grid grid-4" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div>
            <div class="stat-title">Šio mėnesio pardavimai (Įvykdyta)</div>
            <div class="stat-value"><?php echo number_format($currentMonthSales, 2); ?> €</div>
        </div>
        <div class="stat-trend <?php echo $salesGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
            <?php echo $salesGrowth >= 0 ? '📈 +' : '📉 '; ?><?php echo number_format(abs($salesGrowth), 1); ?>%
            <span style="color:#9ca3af; font-weight:400;"> lyginant su praėjusiu mėn.</span>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-title">Įvykdyti užsakymai</div>
            <div class="stat-value"><?php echo (int)$ordersCountHero; ?></div>
        </div>
        <div class="stat-trend trend-up">
            <span style="color:#9ca3af; font-weight:400;">Sėkmingi pardavimai</span>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-title">Vartotojai</div>
            <div class="stat-value"><?php echo (int)$userCountHero; ?></div>
        </div>
        <div class="stat-trend">
            <span style="color:#9ca3af; font-weight:400;">Registruoti pirkėjai</span>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-title">Vidutinis krepšelis</div>
            <div class="stat-value"><?php echo number_format($averageOrderHero, 2); ?> €</div>
        </div>
        <div class="stat-trend">
            <span style="color:#9ca3af; font-weight:400;">Pagal įvykdytus užsakymus</span>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom: 24px;">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Pardavimai per 7 dienas (Įvykdyta)</h3>
            <span style="font-size:12px; color:#6b7280;">Savaitės apžvalga</span>
        </div>
        <div class="chart-container">
            <?php foreach ($chartData as $date => $val): 
                $heightPct = ($val / $maxVal) * 100;
            ?>
            <div class="bar-group">
                <div class="bar" style="height: <?php echo $heightPct; ?>%;" data-val="<?php echo number_format($val, 2); ?>"></div>
                <div class="bar-label"><?php echo date('m-d', strtotime($date)); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>⚠️ Mažas likutis (≤ 1 vnt.)</h3>
            <a href="?view=products" class="btn secondary" style="font-size:11px; padding:4px 8px;">Visos prekės</a>
        </div>
        <?php if ($lowStockItems): ?>
            <div style="margin-top:10px;">
                <?php foreach ($lowStockItems as $lp): ?>
                <div class="product-list-item">
                    <img src="<?php echo htmlspecialchars($lp['image_url'] ?: '/uploads/no-image.png'); ?>" class="list-img">
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($lp['title']); ?></div>
                        <div style="font-size:12px; color:#ef4444; font-weight:600;">Liko tik: <?php echo $lp['quantity']; ?> vnt.</div>
                    </div>
                    <a href="?view=products&id=<?php echo $lp['id']; ?>&action=edit" class="btn" style="padding:4px 8px; font-size:11px;">Papildyti</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="padding:20px; text-align:center; color:#10b981;">Visų prekių likučiai pakankami! ✅</div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3>Naujausi užsakymai</h3>
            <a href="?view=orders" class="btn secondary" style="font-size:12px;">Visi užsakymai</a>
        </div>
        <table style="font-size:13px;">
            <thead><tr><th>ID</th><th>Klientas</th><th>Suma</th><th>Statusas</th></tr></thead>
            <tbody>
              <?php foreach ($latestOrders as $o): ?>
                <tr>
                  <td>#<?php echo (int)$o['id']; ?></td>
                  <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                  <td><?php echo number_format((float)$o['total'], 2); ?> €</td>
                  <td><span class="status-badge status-<?php echo htmlspecialchars($o['status']); ?>"><?php echo ucfirst($o['status']); ?></span></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$latestOrders): ?>
                <tr><td colspan="4" class="muted">Užsakymų dar nėra.</td></tr>
              <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>🏆 Perkamiausios prekės</h3>
        <?php if ($topProducts): ?>
            <div>
                <?php foreach ($topProducts as $tp): ?>
                <div class="product-list-item">
                    <img src="<?php echo htmlspecialchars($tp['image_url'] ?: '/uploads/no-image.png'); ?>" class="list-img">
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($tp['title']); ?></div>
                        <div style="font-size:12px; color:#6b7280;">Parduota: <strong><?php echo $tp['sold_count']; ?></strong> vnt.</div>
                    </div>
                    <div style="font-size:16px; font-weight:700; color:#d97706;">#<?php echo array_search($tp, $topProducts) + 1; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="muted">Statistikos dar nėra.</div>
        <?php endif; ?>
    </div>
</div>
