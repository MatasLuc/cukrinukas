<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/libwebtopay/WebToPay.php';
require __DIR__ . '/libwebtopay/helpers.php';

try {
    $pdo = getPdo();
} catch (Throwable $e) {
    http_response_code(500);
    logError('DB connection failed on checkout', $e);
    echo 'Įvyko klaida apdorojant užsakymą. Bandykite dar kartą vėliau.';
    exit;
}

$schemaReady = true;
try {
    ensureProductsTable($pdo);
    ensureOrdersTables($pdo);
    ensureCartTables($pdo);
    ensureAdminAccount($pdo);
    ensureLockerTables($pdo);
    ensureShippingSettings($pdo);
    ensureDiscountTables($pdo);
    tryAutoLogin($pdo);
} catch (Throwable $e) {
    $schemaReady = false;
    logError('Checkout schema init failed', $e);
}

$cartData = $schemaReady ? getCartData($pdo, $_SESSION['cart'] ?? [], $_SESSION['cart_variations'] ?? []) : ['items' => [], 'total' => 0, 'count' => 0];
$items = $cartData['items'];
$subtotal = $cartData['total'];

$shippingSettings = $schemaReady ? getShippingSettings($pdo) : [];
$courierPrice = isset($shippingSettings['courier_price']) ? (float)$shippingSettings['courier_price'] : 3.99;
$lockerPrice = isset($shippingSettings['locker_price']) ? (float)$shippingSettings['locker_price'] : 2.49;
$freeOver = isset($shippingSettings['free_over']) ? (float)$shippingSettings['free_over'] : null;

$lockerNetworks = $schemaReady ? getLockerNetworks($pdo) : [];

$globalDiscount = $cartData['global_discount'] ?? ($schemaReady ? getGlobalDiscount($pdo) : ['free_shipping' => 0]);
$categoryDiscounts = $cartData['category_discounts'] ?? ($schemaReady ? getCategoryDiscounts($pdo) : []);
$freeShippingIds = $cartData['free_shipping_ids'] ?? [];
$hasFreeShippingProduct = false;
foreach ($items as $itm) {
    if (in_array((int)$itm['id'], $freeShippingIds, true)) {
        $hasFreeShippingProduct = true;
        break;
    }
}
$hasCategoryFreeShipping = false;
foreach ($items as $item) {
    $catId = $item['category_id'] ?? null;
    if ($catId && !empty($categoryDiscounts[$catId]['free_shipping'])) {
        $hasCategoryFreeShipping = true;
        break;
    }
}
$qualifiesForFreeByTotal = $freeOver !== null && $subtotal >= $freeOver;
$freeShippingFlag = !empty($globalDiscount['free_shipping']) || $hasCategoryFreeShipping || $hasFreeShippingProduct || $qualifiesForFreeByTotal;

$name = '';
$email = '';
$phone = '';
$address = '';
$lockerRequest = '';
$deliveryMethod = 'courier';
$lockerProvider = array_key_first($lockerNetworks) ?: '';
$lockerLocation = '';
$errors = [];
$orderId = null;
$shippingAmount = $courierPrice;

// Pasiimame vartotojo info, jei prisijungęs
if (empty($_POST) && !empty($_SESSION['user_id'])) {
    $userStmt = $pdo->prepare('SELECT name, email, phone, address FROM users WHERE id = ?');
    $userStmt->execute([$_SESSION['user_id']]);
    $uData = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($uData) {
        $name = $uData['name'];
        $email = $uData['email'];
        $phone = $uData['phone'];
        $address = $uData['address'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $lockerRequest = trim($_POST['locker_request'] ?? '');
    $deliveryMethod = $_POST['delivery_method'] ?? 'courier';
    $lockerProvider = $_POST['locker_provider'] ?? '';
    $lockerLocation = $_POST['locker_location'] ?? '';

    if ($deliveryMethod !== 'locker') {
        $lockerProvider = '';
        $lockerLocation = '';
        $lockerRequest = '';
    }

    if ($name === '' || $email === '' || $phone === '') {
        $errors[] = 'Užpildykite kontaktinę informaciją.';
    }

    if ($deliveryMethod === 'courier' && $address === '') {
        $errors[] = 'Kurjerio pristatymui būtinas adresas.';
    }

    $selectedLocker = null;
    if ($deliveryMethod === 'locker') {
        if ($lockerProvider === '') {
            $errors[] = 'Pasirinkite paštomatų tinklą (Omniva arba LP Express).';
        }
        $lockerId = (int)$lockerLocation;
        $selectedLocker = $lockerId > 0 ? getLockerById($pdo, $lockerId) : null;
        if (!$selectedLocker) {
            $errors[] = 'Pasirinkite konkretų paštomatą.';
        } elseif ($selectedLocker['provider'] !== $lockerProvider) {
            $errors[] = 'Pasirinktas paštomatas nepriklauso šiam tinklui.';
        }
    }

    if (!$items) {
        $errors[] = 'Krepšelis tuščias.';
    }

    $shippingAmount = $freeShippingFlag ? 0 : ($deliveryMethod === 'locker' ? $lockerPrice : $courierPrice);
    $totalPayable = max(0, $subtotal + $shippingAmount);

    if (!$errors) {
        $cartBackup = $_SESSION['cart'] ?? [];
        $cartVarBackup = $_SESSION['cart_variations'] ?? [];
        try {
            $deliveryDetails = [
                'method' => $deliveryMethod,
                'provider' => $deliveryMethod === 'locker' ? $lockerProvider : null,
                'locker_id' => $deliveryMethod === 'locker' ? $selectedLocker['id'] ?? null : null,
                'locker_note' => $deliveryMethod === 'locker' ? ($selectedLocker['note'] ?? null) : null,
                'locker_request' => $deliveryMethod === 'locker' ? ($lockerRequest ?: null) : null,
                'phone' => $phone,
            ];

            $finalAddress = $deliveryMethod === 'locker'
                ? trim(($selectedLocker['title'] ?? '') . ' — ' . ($selectedLocker['address'] ?? ''))
                : $address;

            $pdo->beginTransaction();
            $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, customer_address, discount_code, discount_amount, shipping_amount, total, status, delivery_method, delivery_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $orderStmt->execute([
                $_SESSION['user_id'] ?? null,
                $name,
                $email,
                $phone,
                $finalAddress,
                null,
                0,
                $shippingAmount,
                $totalPayable,
                'laukiama apmokėjimo',
                $deliveryMethod,
                json_encode($deliveryDetails, JSON_UNESCAPED_UNICODE),
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['id'], $item['quantity'], $item['price']]);
            }

            $pdo->commit();
            $config = require __DIR__ . '/libwebtopay/config.php';
            $paymentParams = buildPayseraParams(['id' => $orderId, 'total' => $totalPayable], $config);

            $_SESSION['cart'] = [];
            $_SESSION['cart_variations'] = [];
            if (!empty($_SESSION['user_id'])) {
                clearUserCart($pdo, (int)$_SESSION['user_id']);
            }

            WebToPay::redirectToPayment($paymentParams);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($orderId) {
                $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
                $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
            }
            $_SESSION['cart'] = $cartBackup;
            $_SESSION['cart_variations'] = $cartVarBackup;
            $errors[] = 'Nepavyko inicijuoti Paysera apmokėjimo. Bandykite dar kartą.';
            logError('Checkout order save failed', $e);
        }
    }
}

$shippingAmount = $freeShippingFlag ? 0 : ($deliveryMethod === 'locker' ? $lockerPrice : $courierPrice);
$payable = max(0, $subtotal + $shippingAmount);

if ($lockerProvider === '' && !empty($lockerNetworks)) {
    $lockerProvider = array_key_first($lockerNetworks);
}
if ($lockerLocation === '' && $lockerProvider && !empty($lockerNetworks[$lockerProvider])) {
    $lockerLocation = (string)($lockerNetworks[$lockerProvider][0]['id'] ?? '');
}

$selectedLockerData = null;
if ($lockerProvider && $lockerLocation !== '' && !empty($lockerNetworks[$lockerProvider])) {
    foreach ($lockerNetworks[$lockerProvider] as $loc) {
        if ((string)($loc['id'] ?? '') === (string)$lockerLocation) {
            $selectedLockerData = $loc;
            break;
        }
    }
}
$lockerDisplay = '';
if ($selectedLockerData) {
    $lockerDisplay = ($selectedLockerData['title'] ?? '') . ' — ' . ($selectedLockerData['address'] ?? '');
    if (!empty($selectedLockerData['note'])) {
        $lockerDisplay .= ' (' . $selectedLockerData['note'] . ')';
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Apmokėjimas | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text-main: #0f172a;
      --text-muted: #475467;
      --accent: #2563eb;
      --accent-light: #eff6ff;
      --focus-ring: rgba(37, 99, 235, 0.2);
      --danger-bg: #fef2f2;
      --danger-text: #991b1b;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; }
    
    .page { max-width: 1100px; margin:0 auto; padding:32px 20px 80px; }
    
    .page-title { margin: 0 0 24px; font-size: 28px; color: var(--text-main); letter-spacing: -0.5px; }

    .checkout-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 24px;
        align-items: start;
    }

    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        padding: 24px;
    }
    .card-title {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }

    /* Form Styles */
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: var(--text-main); }
    
    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="search"],
    textarea,
    select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 14px;
        font-family: inherit;
        background: #fff;
        color: var(--text-main);
        transition: all .2s;
        outline: none;
    }
    input:focus, textarea:focus, select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--focus-ring);
    }
    textarea { min-height: 80px; resize: vertical; }

    /* Radio Chips */
    .delivery-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .radio-chip {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px;
        border: 1px solid var(--border);
        border-radius: 12px;
        cursor: pointer;
        transition: all .2s;
        background: #fff;
    }
    .radio-chip:hover { border-color: #cbd5e1; background: #f8fafc; }
    .radio-chip.checked {
        border-color: var(--accent);
        background: var(--accent-light);
        color: var(--accent);
    }
    .radio-chip input { margin: 0; accent-color: var(--accent); width: 16px; height: 16px; }
    .radio-chip span { font-weight: 600; font-size: 14px; }

    /* Locker Styles */
    .locker-container {
        background: #f8fafc;
        border: 1px dashed var(--border);
        border-radius: 12px;
        padding: 16px;
        margin-top: 16px;
    }
    .locker-combobox { position: relative; }
    .locker-results {
        display: none;
        position: absolute;
        top: 100%; left: 0; right: 0;
        margin-top: 6px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        max-height: 240px;
        overflow-y: auto;
        z-index: 50;
    }
    .locker-result { padding: 10px 14px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .locker-result:last-child { border-bottom: none; }
    .locker-result:hover { background: #f1f5f9; }
    .locker-empty { padding: 12px; text-align: center; color: var(--text-muted); font-size: 13px; }

    /* Alerts */
    .alert {
        padding: 12px 16px;
        border-radius: 12px;
        background: var(--danger-bg);
        border: 1px solid #fecaca;
        color: var(--danger-text);
        margin-bottom: 20px;
        font-size: 14px;
    }

    /* Summary Sidebar */
    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }
    .summary-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .summary-title { font-weight: 500; color: var(--text-main); margin-bottom: 2px; }
    .summary-meta { font-size: 12px; color: var(--text-muted); }
    
    .totals-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
        color: var(--text-muted);
    }
    .totals-row.final {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
        color: var(--text-main);
        font-weight: 700;
        font-size: 18px;
        align-items: center;
    }

    .btn-pay {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 14px;
        margin-top: 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        border: none;
        background: #0f172a;
        color: #fff;
        transition: all .2s;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .btn-pay:hover {
        background: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 900px) {
        .checkout-grid { grid-template-columns: 1fr; }
        .card.sticky { position: static !important; }
        .page-title { font-size: 24px; }
    }
    @media (max-width: 600px) {
        .delivery-options { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'checkout'); ?>
  
  <div class="page">
    <h1 class="page-title">Užsakymo apmokėjimas</h1>

    <div class="checkout-grid">
      <div class="main-column">
        <?php if (!$schemaReady): ?>
          <div class="alert">Techninė klaida: duomenų bazės struktūra neatnaujinta. Susisiekite su administracija.</div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="alert">
            <?php foreach ($errors as $e): ?>
              <div>• <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" id="checkout-form">
          <?php echo csrfField(); ?>
          
          <div class="card" style="margin-bottom: 24px;">
            <h2 class="card-title">Kontaktinė informacija</h2>
            <div class="form-group">
                <label for="name">Vardas, pavardė</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required placeholder="Įveskite vardą">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label for="email">El. paštas</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="pvz@pastas.lt">
                </div>
                <div class="form-group">
                    <label for="phone">Telefono numeris</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required placeholder="+37060000000">
                </div>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title">Pristatymo būdas</h2>
            
            <div class="delivery-options">
              <label class="radio-chip <?php echo $deliveryMethod === 'courier' ? 'checked' : ''; ?>" id="chip-courier">
                <input type="radio" name="delivery_method" value="courier" <?php echo $deliveryMethod === 'courier' ? 'checked' : ''; ?>>
                <span>Kurjeris į namus<?php echo $freeShippingFlag ? '' : ' (' . number_format($courierPrice, 2) . ' €)'; ?></span>
              </label>
              <label class="radio-chip <?php echo $deliveryMethod === 'locker' ? 'checked' : ''; ?>" id="chip-locker">
                <input type="radio" name="delivery_method" value="locker" <?php echo $deliveryMethod === 'locker' ? 'checked' : ''; ?>>
                <span>Paštomatas<?php echo $freeShippingFlag ? '' : ' (' . number_format($lockerPrice, 2) . ' €)'; ?></span>
              </label>
            </div>

            <div id="courier-fields" style="display: <?php echo $deliveryMethod === 'courier' ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label for="address">Pristatymo adresas</label>
                    <textarea id="address" name="address" placeholder="Gatvė, namo nr., miestas, pašto kodas" <?php echo $deliveryMethod === 'courier' ? 'required' : ''; ?>><?php echo htmlspecialchars($address); ?></textarea>
                </div>
            </div>

            <div id="locker-fields" class="locker-container" style="display: <?php echo $deliveryMethod === 'locker' ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label for="locker-provider">Pasirinkite tinklą</label>
                    <select id="locker-provider" name="locker_provider">
                        <option value="">-- Pasirinkite --</option>
                        <option value="omniva" <?php echo $lockerProvider === 'omniva' ? 'selected' : ''; ?>>Omniva</option>
                        <option value="lpexpress" <?php echo $lockerProvider === 'lpexpress' ? 'selected' : ''; ?>>LP Express</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="locker-location-input">Paštomato paieška</label>
                    <div class="locker-combobox">
                        <input id="locker-location-input" type="search" placeholder="Pradėkite vesti miestą ar adresą..." value="<?php echo htmlspecialchars($lockerDisplay); ?>" autocomplete="off">
                        <input type="hidden" id="locker-location" name="locker_location" value="<?php echo htmlspecialchars($lockerLocation); ?>">
                        <div id="locker-location-results" class="locker-results"></div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label for="locker-request">Kitas paštomatas (jei neradote sąraše)</label>
                    <input type="text" id="locker-request" name="locker_request" value="<?php echo htmlspecialchars($lockerRequest); ?>" placeholder="Pvz.: artimiausias PC Akropolis">
                </div>
            </div>
          </div>

        </form>
      </div>

      <div class="sidebar">
        <div class="card sticky" style="position: sticky; top: 100px;">
            <h2 class="card-title">Jūsų užsakymas</h2>
            
            <?php if (!$items): ?>
                <p class="muted">Krepšelis tuščias.</p>
            <?php else: ?>
                <div style="margin-bottom: 20px;">
                    <?php foreach ($items as $item): ?>
                        <div class="summary-item">
                            <div>
                                <div class="summary-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div class="summary-meta"><?php echo $item['quantity']; ?> vnt. × <?php echo number_format((float)$item['price'], 2); ?> €</div>
                            </div>
                            <div style="font-weight:600;"><?php echo number_format($item['line_total'], 2); ?> €</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="totals-row">
                    <span>Tarpinė suma</span>
                    <span><?php echo number_format($subtotal, 2); ?> €</span>
                </div>
                
                <div class="totals-row">
                    <span>Pristatymas</span>
                    <span id="shipping-summary"><?php echo number_format($shippingAmount, 2); ?> €</span>
                </div>

                <div class="totals-row final">
                    <span>Viso mokėti</span>
                    <span id="payable-total"><?php echo number_format($payable, 2); ?> €</span>
                </div>

                <button type="submit" form="checkout-form" class="btn-pay">Apmokėti (Paysera)</button>
                
                <p style="margin:16px 0 0; font-size:12px; color:var(--text-muted); text-align:center; line-height:1.5;">
                    Paspausdami „Apmokėti“, jūs sutinkate su pirkimo taisyklėmis ir privatumo politika.
                </p>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const courierFields = document.getElementById('courier-fields');
      const lockerFields = document.getElementById('locker-fields');
      const methodRadios = document.querySelectorAll('input[name="delivery_method"]');
      const chipCourier = document.getElementById('chip-courier');
      const chipLocker = document.getElementById('chip-locker');
      const providerSelect = document.getElementById('locker-provider');
      const locationInput = document.getElementById('locker-location-input');
      const locationHidden = document.getElementById('locker-location');
      const resultsBox = document.getElementById('locker-location-results');
      const shippingSummary = document.getElementById('shipping-summary');
      const payableTotal = document.getElementById('payable-total');
      
      const lockerOptions = <?php echo json_encode($lockerNetworks, JSON_UNESCAPED_UNICODE); ?>;
      const prices = { courier: <?php echo json_encode($courierPrice); ?>, locker: <?php echo json_encode($lockerPrice); ?> };
      const freeShipping = <?php echo $freeShippingFlag ? 'true' : 'false'; ?>;
      const subtotal = <?php echo json_encode($subtotal); ?>;

      function formatPrice(num) { return Number(num).toFixed(2) + ' €'; }

      function updateTotals(method) {
        const shipping = freeShipping ? 0 : (method === 'locker' ? prices.locker : prices.courier);
        if (shippingSummary) shippingSummary.textContent = formatPrice(shipping);
        if (payableTotal) payableTotal.textContent = formatPrice(Math.max(0, subtotal + shipping));
        
        // Update styling
        if (method === 'courier') {
            chipCourier.classList.add('checked');
            chipLocker.classList.remove('checked');
        } else {
            chipCourier.classList.remove('checked');
            chipLocker.classList.add('checked');
        }
      }

      function getFilteredLocations(provider) {
        const locations = lockerOptions[provider] || [];
        const query = (locationInput?.value || '').trim().toLowerCase();
        if (!query) return locations;

        return locations.filter(function(loc) {
          return [loc.title, loc.address, loc.note]
            .filter(Boolean)
            .some(function(field) { return String(field).toLowerCase().includes(query); });
        });
      }

      function formatLockerLabel(loc) {
        return (loc.title || '') + ' — ' + (loc.address || '') + (loc.note ? ' (' + loc.note + ')' : '');
      }

      function findLocation(provider, id) {
        if (!provider || !id) return null;
        return (lockerOptions[provider] || []).find(function(loc) { return String(loc.id ?? '') === String(id); }) || null;
      }

      function renderLocations(provider) {
        if (!resultsBox) return;
        const locations = provider ? getFilteredLocations(provider) : [];
        resultsBox.innerHTML = '';

        if (!locations.length) {
          const empty = document.createElement('div');
          empty.className = 'locker-empty';
          empty.textContent = provider ? 'Nėra galimų paštomatų pagal paiešką' : 'Pirmiausia pasirinkite tinklą';
          resultsBox.appendChild(empty);
          resultsBox.style.display = 'block';
          return;
        }

        locations.forEach(function(loc) {
          const option = document.createElement('div');
          option.className = 'locker-result';
          option.textContent = formatLockerLabel(loc);
          option.addEventListener('mousedown', function(event) {
            event.preventDefault();
            if (locationInput) locationInput.value = formatLockerLabel(loc);
            if (locationHidden) locationHidden.value = String(loc.id ?? '');
            hideResults();
          });
          resultsBox.appendChild(option);
        });

        resultsBox.style.display = 'block';
      }

      function hideResults() {
        if (resultsBox) resultsBox.style.display = 'none';
      }

      function toggleSections(method) {
        if (courierFields) courierFields.style.display = method === 'courier' ? 'block' : 'none';
        if (lockerFields) lockerFields.style.display = method === 'locker' ? 'block' : 'none';
        if (method === 'courier') {
          // Reset locker selection if switching away? Optional. 
          // Keeping values is better for UX if user switches back.
          hideResults();
        } else if (providerSelect && providerSelect.value) {
           // If returning to locker, maybe re-render? No need unless focused.
        }
      }

      methodRadios.forEach(function(radio) {
        radio.addEventListener('change', function(event) {
          const method = event.target.value;
          toggleSections(method);
          updateTotals(method);
        });
      });

      providerSelect?.addEventListener('change', function(event) {
        if (locationInput) locationInput.value = '';
        if (locationHidden) locationHidden.value = '';
        renderLocations(event.target.value);
      });

      locationInput?.addEventListener('input', function() {
        if (locationHidden) locationHidden.value = '';
        renderLocations(providerSelect?.value || '');
      });

      locationInput?.addEventListener('focus', function() {
        renderLocations(providerSelect?.value || '');
      });

      locationInput?.addEventListener('blur', function() {
        setTimeout(hideResults, 120);
      });

      const initialMethod = document.querySelector('input[name="delivery_method"]:checked')?.value || 'courier';
      // Pre-fill logic
      if (initialMethod === 'locker' && providerSelect?.value) {
        const selected = findLocation(providerSelect.value, locationHidden?.value || '');
        if (!locationInput?.value && selected) {
          locationInput.value = formatLockerLabel(selected);
        }
      }
      
      toggleSections(initialMethod);
      updateTotals(initialMethod);
    })();
  </script>

  <?php renderFooter($pdo); ?>
</body>
</html>
