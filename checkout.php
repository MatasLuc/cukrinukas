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
        // Nepaliekame seno pasirinkimo, jei pirkėjas grįžta prie kurjerio.
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
  <title>Apmokėjimas</title>
  <?php echo headerStyles(); ?>
  <style>
    body { background:#f7f7fb; }
    .container { max-width: 1000px; margin: 20px auto 60px; padding: 0 18px; display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; }
    .card { background: #fff; border-radius: 18px; border:1px solid #e5e7eb; padding: 20px; box-shadow: 0 20px 50px rgba(15,23,42,0.08); }
    .section-title { margin:0 0 10px; font-size:22px; }
    label { display:block; margin: 12px 0 6px; font-weight: 700; }
    input, textarea, select { width:100%; padding: 12px; border-radius: 12px; border:1px solid #e5e7eb; background:#fbfbff; box-sizing: border-box; max-width:100%; }
    textarea { resize: vertical; min-height: 80px; }
    .delivery-choice { display:flex; gap:10px; flex-wrap:wrap; margin: 6px 0 4px; }
    .chip { border:1px solid #e5e7eb; padding: 10px 12px; border-radius: 14px; display:flex; align-items:center; gap:8px; background:#f9f9ff; cursor:pointer; }
    .chip input { margin:0; }
    .notice { padding: 12px; border-radius: 12px; border:1px solid #f5c2c7; background:#fff1f2; color:#991b1b; }
    .btn { width:100%; padding: 14px 16px; border-radius: 14px; border:none; background: linear-gradient(135deg, #6f4ef2, #2f9aff); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 16px 40px rgba(47,154,255,0.25); }
    .summary-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e5e7eb; }
    .muted { color:#6b7280; }
    .locker-box { border:1px dashed #e5e7eb; padding:12px; border-radius:12px; background:#fafaff; }
    .checkout-form { max-width: 560px; width: 100%; }
    .locker-combobox { position: relative; }
    .locker-results { display:none; position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 12px 30px rgba(15,23,42,0.12); margin-top:6px; max-height:230px; overflow-y:auto; z-index:40; }
    .locker-result { padding:10px 12px; cursor:pointer; }
    .locker-result:hover { background:#f3f4f6; }
    .locker-empty { padding:10px 12px; color:#6b7280; }
    @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'checkout'); ?>
  <div class="container">
    <div class="card">
      <h1 class="section-title">Pristatymo pasirinkimas</h1>
      <p class="muted">Pasirinkite kurjerį arba paštomatą ir suveskite kontaktus.</p>

      <?php if (!$schemaReady): ?>
        <div class="notice" style="margin-top:10px;">
          Duomenų bazės struktūra negali būti automatiškai atnaujinta. Prašome kreiptis į administratorių.
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="notice" style="margin-top:10px;">
          <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="checkout-form" style="margin-top:12px; display:grid; gap:8px;">
        <?php echo csrfField(); ?>
<label for="name">Vardas ir pavardė</label>
        <input id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>

        <label for="email">El. paštas</label>
        <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

        <label for="phone">Telefono numeris</label>
        <input id="phone" type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>

        <label>Pristatymo būdas</label>
        <div class="delivery-choice">
          <label class="chip">
            <input type="radio" name="delivery_method" value="courier" <?php echo $deliveryMethod === 'courier' ? 'checked' : ''; ?>>
            <span>Kurjeris (<?php echo number_format($courierPrice, 2); ?> €)</span>
          </label>
          <label class="chip">
            <input type="radio" name="delivery_method" value="locker" <?php echo $deliveryMethod === 'locker' ? 'checked' : ''; ?>>
            <span>Paštomatas (<?php echo number_format($lockerPrice, 2); ?> €)</span>
          </label>
        </div>

        <div id="courier-fields" style="display: <?php echo $deliveryMethod === 'courier' ? 'block' : 'none'; ?>;">
          <label for="address">Adresas kurjeriui</label>
          <textarea id="address" name="address" placeholder="Gatvė, namo nr., miestas" <?php echo $deliveryMethod === 'courier' ? 'required' : ''; ?>><?php echo htmlspecialchars($address); ?></textarea>
        </div>

        <div id="locker-fields" class="locker-box" style="display: <?php echo $deliveryMethod === 'locker' ? 'block' : 'none'; ?>;">
          <label for="locker-provider">Paštomatų tinklas</label>
          <select id="locker-provider" name="locker_provider">
            <option value="">Pasirinkite tinklą</option>
            <option value="omniva" <?php echo $lockerProvider === 'omniva' ? 'selected' : ''; ?>>Omniva</option>
            <option value="lpexpress" <?php echo $lockerProvider === 'lpexpress' ? 'selected' : ''; ?>>LP Express</option>
          </select>

          <label for="locker-location">Pasirinkite paštomatą</label>
          <div class="locker-combobox">
            <input id="locker-location-input" type="search" placeholder="Ieškokite pagal miestą, gatvę ar pavadinimą" value="<?php echo htmlspecialchars($lockerDisplay); ?>" autocomplete="off">
            <input type="hidden" id="locker-location" name="locker_location" value="<?php echo htmlspecialchars($lockerLocation); ?>">
            <div id="locker-location-results" class="locker-results"></div>
          </div>

          <p class="muted" style="margin:10px 0 6px;">Jeigu sąraše nerandate savo pageidaujamo paštomato, įveskite jo pavadinimą arba aprašymą į žemiau esantį langelį ir pasistengsime siuntą jums ten pristatyti.</p>
          <label for="locker-request">Pageidaujamas paštomatas (pasirinktinai)</label>
          <input id="locker-request" name="locker_request" value="<?php echo htmlspecialchars($lockerRequest); ?>" placeholder="Pvz. „Omniva prekybos centre“ ar tikslesnis aprašymas">
        </div>

        <button class="btn" type="submit">Apmokėti</button>
      </form>
    </div>

    <div class="card" style="position:sticky; top:20px;">
      <h2 class="section-title">Suvestinė</h2>
      <?php if (!$items): ?>
        <p class="muted">Krepšelis tuščias.</p>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <div class="summary-row">
            <div>
              <strong><?php echo htmlspecialchars($item['title']); ?></strong>
              <div class="muted" style="font-size:13px;">Kiekis: <?php echo (int)$item['quantity']; ?></div>
            </div>
            <div><?php echo number_format($item['line_total'], 2); ?> €</div>
          </div>
        <?php endforeach; ?>
        <div class="summary-row"><span>Tarpinė suma</span><strong><?php echo number_format($subtotal, 2); ?> €</strong></div>
        <div class="summary-row"><span>Pristatymas</span><strong id="shipping-summary"><?php echo number_format($shippingAmount, 2); ?> €</strong></div>
        <div class="summary-row" style="border-bottom:none;">
          <span style="font-weight:800;">Mokėtina</span>
          <span style="font-weight:800; font-size:18px;" id="payable-total"><?php echo number_format($payable, 2); ?> €</span>
        </div>
        <p class="muted" style="margin:8px 0 0;">Kainos perskaičiuojamos pagal pasirinktą pristatymą.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function() {
      const courierFields = document.getElementById('courier-fields');
      const lockerFields = document.getElementById('locker-fields');
      const methodRadios = document.querySelectorAll('input[name="delivery_method"]');
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
          empty.textContent = provider ? 'Nėra galimų paštomatų pagal paiešką' : 'Pasirinkite tinklą';
          resultsBox.appendChild(empty);
          resultsBox.style.display = provider ? 'block' : 'none';
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
          if (providerSelect) providerSelect.value = '';
          if (locationInput) locationInput.value = '';
          if (locationHidden) locationHidden.value = '';
          hideResults();
        } else if (providerSelect && providerSelect.value) {
          renderLocations(providerSelect.value);
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
      if (initialMethod === 'locker' && providerSelect?.value) {
        const selected = findLocation(providerSelect.value, locationHidden?.value || '');
        if (!locationInput?.value && selected) {
          locationInput.value = formatLockerLabel(selected);
        }
        renderLocations(providerSelect.value);
      }
      toggleSections(initialMethod);
      updateTotals(initialMethod);
    })();
  </script>

  <?php renderFooter($pdo); ?>
</body>
</html>
