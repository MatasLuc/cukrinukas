<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

// Įkeliame Paysera bibliotekas tik jei failai egzistuoja
if (file_exists(__DIR__ . '/libwebtopay/WebToPay.php')) {
    require_once __DIR__ . '/libwebtopay/WebToPay.php';
}
if (file_exists(__DIR__ . '/libwebtopay/helpers.php')) {
    require_once __DIR__ . '/libwebtopay/helpers.php';
}

// Pagalbinė funkcija klaidų registravimui, jei 'logError' neegzistuoja
if (!function_exists('safeLogError')) {
    function safeLogError($msg, $e = null) {
        if (function_exists('logError')) {
            logError($msg, $e);
        } else {
            $context = $e ? $e->getMessage() : '';
            error_log("Cukrinukas Checkout Error: $msg. $context");
        }
    }
}

try {
    $pdo = getPdo();
} catch (Throwable $e) {
    http_response_code(500);
    safeLogError('DB connection failed on checkout', $e);
    echo 'Įvyko klaida prisijungiant prie duomenų bazės. Bandykite vėliau.';
    exit;
}

// Inicijuojame lenteles
$schemaReady = true;
try {
    // Patikriname ir iškviečiame funkcijas tik jei jos egzistuoja
    if (function_exists('ensureProductsTable')) ensureProductsTable($pdo);
    if (function_exists('ensureOrdersTables')) ensureOrdersTables($pdo);
    if (function_exists('ensureCartTables')) ensureCartTables($pdo);
    if (function_exists('ensureAdminAccount')) ensureAdminAccount($pdo);
    if (function_exists('ensureLockerTables')) ensureLockerTables($pdo);
    if (function_exists('ensureShippingSettings')) ensureShippingSettings($pdo);
    if (function_exists('ensureDiscountTables')) ensureDiscountTables($pdo);
    
    // Auto login tik jei funkcija yra
    if (function_exists('tryAutoLogin')) {
        tryAutoLogin($pdo);
    }
} catch (Throwable $e) {
    $schemaReady = false;
    safeLogError('Checkout schema init failed', $e);
}

// Gauname krepšelio duomenis
$cartItemsRaw = $_SESSION['cart'] ?? [];
$cartVariations = $_SESSION['cart_variations'] ?? [];
$cartData = ['items' => [], 'total' => 0];

if ($schemaReady && function_exists('getCartData')) {
    $cartData = getCartData($pdo, $cartItemsRaw, $cartVariations);
}

$items = $cartData['items'] ?? [];
$subtotal = $cartData['total'] ?? 0;

// Pristatymo nustatymai
$shippingSettings = [];
if ($schemaReady && function_exists('getShippingSettings')) {
    $shippingSettings = getShippingSettings($pdo);
}
$courierPrice = isset($shippingSettings['courier_price']) ? (float)$shippingSettings['courier_price'] : 3.99;
$lockerPrice = isset($shippingSettings['locker_price']) ? (float)$shippingSettings['locker_price'] : 2.49;
$freeOver = isset($shippingSettings['free_over']) ? (float)$shippingSettings['free_over'] : null;

// Paštomatų tinklai
$lockerNetworks = [];
if ($schemaReady && function_exists('getLockerNetworks')) {
    $lockerNetworks = getLockerNetworks($pdo);
}

// Nemokamas pristatymas
$globalDiscount = ($schemaReady && function_exists('getGlobalDiscount')) ? getGlobalDiscount($pdo) : ['free_shipping' => 0];
$categoryDiscounts = ($schemaReady && function_exists('getCategoryDiscounts')) ? getCategoryDiscounts($pdo) : [];
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

// Formos kintamieji
$name = '';
$email = '';
$phone = '';
$address = '';
$lockerRequest = '';
$deliveryMethod = 'courier';
$lockerProvider = array_key_first($lockerNetworks) ?: '';
$lockerLocation = '';
$errors = [];

// Jei vartotojas prisijungęs, užpildome info
if (empty($_POST) && !empty($_SESSION['user_id'])) {
    $userStmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
    $userStmt->execute([$_SESSION['user_id']]);
    $uData = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($uData) {
        $name = $uData['name'];
        $email = $uData['email'];
        // Pastaba: 'phone' ir 'address' gali nebūti users lentelėje pagal db.php,
        // todėl jų čia netraukiame, kad išvengtume klaidų.
    }
}

// POST apdorojimas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF patikra
    if (function_exists('validateCsrfToken')) {
        validateCsrfToken();
    } elseif (function_exists('checkCsrf')) {
        checkCsrf();
    }
    
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
            $errors[] = 'Pasirinkite paštomatų tinklą.';
        }
        $lockerId = (int)$lockerLocation;
        if ($lockerId > 0 && function_exists('getLockerById')) {
            $selectedLocker = getLockerById($pdo, $lockerId);
            if (!$selectedLocker) {
                $errors[] = 'Pasirinktas paštomatas neegzistuoja.';
            } elseif (($selectedLocker['provider'] ?? '') !== $lockerProvider) {
                $errors[] = 'Pasirinktas paštomatas nepriklauso pasirinktam tinklui.';
            }
        } elseif ($lockerId <= 0 && empty($lockerRequest)) {
             $errors[] = 'Pasirinkite paštomatą iš sąrašo arba įrašykite pageidavimą.';
        }
    }

    if (!$items) {
        $errors[] = 'Krepšelis tuščias.';
    }

    $shippingAmount = $freeShippingFlag ? 0 : ($deliveryMethod === 'locker' ? $lockerPrice : $courierPrice);
    $totalPayable = max(0, $subtotal + $shippingAmount);

    if (!$errors) {
        $cartBackup = $_SESSION['cart'] ?? [];
        try {
            $deliveryDetails = [
                'method' => $deliveryMethod,
                'provider' => $deliveryMethod === 'locker' ? $lockerProvider : null,
                'locker_id' => $deliveryMethod === 'locker' ? ($selectedLocker['id'] ?? null) : null,
                'locker_note' => $deliveryMethod === 'locker' ? ($selectedLocker['note'] ?? null) : null,
                'locker_request' => $deliveryMethod === 'locker' ? ($lockerRequest ?: null) : null,
                'phone' => $phone,
            ];

            $finalAddress = $deliveryMethod === 'locker'
                ? trim(($selectedLocker['title'] ?? '') . ' — ' . ($selectedLocker['address'] ?? ''))
                : $address;

            if ($deliveryMethod === 'locker' && !$finalAddress && $lockerRequest) {
                $finalAddress = "Kliento pageidavimas: $lockerRequest";
            }

            $pdo->beginTransaction();
            
            // Įterpiame užsakymą
            $sqlOrder = 'INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, customer_address, discount_amount, shipping_amount, total, status, delivery_method, delivery_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $orderStmt = $pdo->prepare($sqlOrder);
            $orderStmt->execute([
                $_SESSION['user_id'] ?? null,
                $name,
                $email,
                $phone,
                $finalAddress,
                0, // discount_amount
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

            // Apmokėjimas
            if (function_exists('buildPayseraParams') && class_exists('WebToPay')) {
                $config = [];
                if (file_exists(__DIR__ . '/libwebtopay/config.php')) {
                    $config = require __DIR__ . '/libwebtopay/config.php';
                }
                // Fallback jei config failo nėra
                if (empty($config)) {
                     $config = [
                        'projectid' => 0,
                        'sign_password' => '',
                        'test' => 1
                     ];
                }

                $paymentParams = buildPayseraParams(['id' => $orderId, 'total' => $totalPayable], $config);
                
                // Išvalome krepšelį
                $_SESSION['cart'] = [];
                $_SESSION['cart_variations'] = [];
                if (!empty($_SESSION['user_id']) && function_exists('clearUserCart')) {
                    clearUserCart($pdo, (int)$_SESSION['user_id']);
                }

                WebToPay::redirectToPayment($paymentParams);
                exit;
            } else {
                // Jei nėra apmokėjimo integracijos, tiesiog nukreipiame į užsakymų sąrašą
                $_SESSION['cart'] = [];
                $_SESSION['cart_variations'] = [];
                 if (!empty($_SESSION['user_id']) && function_exists('clearUserCart')) {
                    clearUserCart($pdo, (int)$_SESSION['user_id']);
                }
                header('Location: /orders.php');
                exit;
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['cart'] = $cartBackup;
            $errors[] = 'Įvyko klaida išsaugant užsakymą. Bandykite dar kartą.';
            safeLogError('Checkout order save failed', $e);
        }
    }
}

// Perskaičiuojame sumas atvaizdavimui
$shippingAmount = $freeShippingFlag ? 0 : ($deliveryMethod === 'locker' ? $lockerPrice : $courierPrice);
$payable = max(0, $subtotal + $shippingAmount);

// Paštomato logikos atstatymas (pre-fill)
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

    .checkout-grid { display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 2px 4px -2px rgba(0, 0, 0, 0.05); padding: 24px; }
    .card-title { font-size: 18px; font-weight: 700; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }

    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: var(--text-main); }
    
    input[type="text"], input[type="email"], input[type="tel"], input[type="search"], textarea, select {
        width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; background: #fff; color: var(--text-main); transition: all .2s; outline: none;
    }
    input:focus, textarea:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--focus-ring); }
    textarea { min-height: 80px; resize: vertical; }

    .delivery-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .radio-chip { position: relative; display: flex; align-items: center; gap: 10px; padding: 14px; border: 1px solid var(--border); border-radius: 12px; cursor: pointer; transition: all .2s; background: #fff; }
    .radio-chip:hover { border-color: #cbd5e1; background: #f8fafc; }
    .radio-chip.checked { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }
    .radio-chip input { margin: 0; accent-color: var(--accent); width: 16px; height: 16px; }
    .radio-chip span { font-weight: 600; font-size: 14px; }

    .locker-container { background: #f8fafc; border: 1px dashed var(--border); border-radius: 12px; padding: 16px; margin-top: 16px; }
    .locker-combobox { position: relative; }
    .locker-results { display: none; position: absolute; top: 100%; left: 0; right: 0; margin-top: 6px; background: #fff; border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-height: 240px; overflow-y: auto; z-index: 50; }
    .locker-result { padding: 10px 14px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .locker-result:hover { background: #f1f5f9; }
    .locker-empty { padding: 12px; text-align: center; color: var(--text-muted); font-size: 13px; }

    .alert { padding: 12px 16px; border-radius: 12px; background: var(--danger-bg); border: 1px solid #fecaca; color: var(--danger-text); margin-bottom: 20px; font-size: 14px; }
    
    .summary-item { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .totals-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: var(--text-muted); }
    .totals-row.final { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); color: var(--text-main); font-weight: 700; font-size: 18px; align-items: center; }

    .btn-pay { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 14px; margin-top: 24px; border-radius: 12px; font-weight: 600; font-size: 16px; cursor: pointer; border: none; background: #0f172a; color: #fff; transition: all .2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .btn-pay:hover { background: #1e293b; transform: translateY(-1px); }

    @media (max-width: 900px) { .checkout-grid { grid-template-columns: 1fr; } .card.sticky { position: static !important; } }
    @media (max-width: 600px) { .delivery-options { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'checkout'); ?>
  
  <div class="page">
    <h1 class="page-title">Užsakymo apmokėjimas</h1>

    <div class="checkout-grid">
      <div class="main-column">
        <?php if (!$schemaReady): ?>
          <div class="alert">Techninė klaida: nepavyko inicijuoti duomenų bazės.</div>
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
                <p style="color:var(--text-muted);">Krepšelis tuščias.</p>
            <?php else: ?>
                <div style="margin-bottom: 20px;">
                    <?php foreach ($items as $item): ?>
                        <div class="summary-item">
                            <div>
                                <div style="font-weight:500; margin-bottom:2px;"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div style="font-size:12px; color:var(--text-muted);"><?php echo $item['quantity']; ?> vnt. × <?php echo number_format((float)$item['price'], 2); ?> €</div>
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

                <button type="submit" form="checkout-form" class="btn-pay">Apmokėti</button>
                
                <p style="margin:16px 0 0; font-size:12px; color:var(--text-muted); text-align:center; line-height:1.5;">
                    Paspausdami „Apmokėti“, jūs sutinkate su pirkimo taisyklėmis.
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
          return [loc.title, loc.address, loc.note].filter(Boolean).some(function(field) { return String(field).toLowerCase().includes(query); });
        });
      }

      function formatLockerLabel(loc) {
        return (loc.title || '') + ' — ' + (loc.address || '') + (loc.note ? ' (' + loc.note + ')' : '');
      }

      function renderLocations(provider) {
        if (!resultsBox) return;
        const locations = provider ? getFilteredLocations(provider) : [];
        resultsBox.innerHTML = '';
        if (!locations.length) {
          const empty = document.createElement('div');
          empty.className = 'locker-empty';
          empty.textContent = provider ? 'Nėra paštomatų' : 'Pasirinkite tinklą';
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

      function hideResults() { if (resultsBox) resultsBox.style.display = 'none'; }

      function toggleSections(method) {
        if (courierFields) courierFields.style.display = method === 'courier' ? 'block' : 'none';
        if (lockerFields) lockerFields.style.display = method === 'locker' ? 'block' : 'none';
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

      locationInput?.addEventListener('focus', function() { renderLocations(providerSelect?.value || ''); });
      locationInput?.addEventListener('blur', function() { setTimeout(hideResults, 120); });

      const initialMethod = document.querySelector('input[name="delivery_method"]:checked')?.value || 'courier';
      toggleSections(initialMethod);
      updateTotals(initialMethod);
    })();
  </script>

  <?php renderFooter($pdo); ?>
</body>
</html>
