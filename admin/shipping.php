<?php
// admin/shipping.php

$shipping = getShippingSettings($pdo);
$lockerNetworks = getLockerNetworks($pdo);
$products = $pdo->query('SELECT id, title FROM products ORDER BY title')->fetchAll(); // Reikalinga parinkimui
$freeShippingProductIds = getFreeShippingProductIds($pdo);
?>

<div class="card" style="max-width:640px;">
  <h3>Pristatymo kainos</h3>
  <p class="muted" style="margin-top:-4px;">Nustatykite atskiras kainas kurjeriui ir paštomatams bei ribą, nuo kurios pristatymas nemokamas.</p>
  <form method="post" class="input-row" style="flex-direction:column; gap:10px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="shipping_save">
    <label>Kurjerio kaina (€)</label>
    <input name="shipping_courier" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($shipping['courier_price'] ?? $shipping['base_price'] ?? 3.99); ?>">
    <label>Paštomato kaina (€)</label>
    <input name="shipping_locker" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($shipping['locker_price'] ?? 2.49); ?>">
    <label>Nemokamas pristatymas nuo sumos (€) (pasirinktinai)</label>
    <input name="shipping_free_over" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($shipping['free_over'] ?? ''); ?>" placeholder="Palikite tuščią jei netaikoma">
    <button class="btn" type="submit">Išsaugoti</button>
  </form>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Paštomatų tinklai</h3>
  <p class="muted" style="margin-top:-4px;">Pridėkite Omniva arba LP Express paštomatus rankiniu būdu arba importuokite iš .xlsx.</p>
  <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
    <form method="post" class="card" style="box-shadow:none; border:1px solid #ebeaf5;" enctype="multipart/form-data">
      <?php echo csrfField(); ?>
      <h4 style="margin-top:0;">Rankinis pridėjimas</h4>
      <input type="hidden" name="action" value="locker_new">
      <label>Tinklas</label>
      <select name="locker_provider" required>
        <option value="">Pasirinkite</option>
        <option value="omniva">Omniva</option>
        <option value="lpexpress">LP Express</option>
      </select>
      <label>Pavadinimas</label>
      <input name="locker_title" placeholder="Pvz. Vilnius Akropolis" required>
      <label>Adresas</label>
      <input name="locker_address" placeholder="Pvz. Ozo g. 25, Vilnius" required>
      <label>Pastabos (pasirinktinai)</label>
      <textarea name="locker_note" rows="2" placeholder="Papildoma informacija pirkėjui."></textarea>
      <button class="btn" type="submit">Pridėti paštomatą</button>
    </form>

    <form method="post" class="card" style="box-shadow:none; border:1px solid #ebeaf5;" enctype="multipart/form-data">
      <?php echo csrfField(); ?>
      <h4 style="margin-top:0;">Importas iš .xlsx</h4>
      <input type="hidden" name="action" value="locker_import">
      <label>Tinklas</label>
      <select name="locker_provider" required>
        <option value="">Pasirinkite</option>
        <option value="omniva">Omniva</option>
        <option value="lpexpress">LP Express</option>
      </select>
      <label>.xlsx failas</label>
      <input type="file" name="locker_file" accept=".xlsx" required>
      <p class="muted" style="font-size:13px;">Omniva stulpeliai: Pašto kodas, Pavadinimas, Šalis, Apskritis, Savivaldybė, Miestas, Gatvė, Namo nr, X, Y, Papildomai. LP Express stulpeliai: Miestas, ID, Pavadinimas, Adresas, Pašto kodas, Platuma, Ilguma, Pastabos. Išsaugomi tik pavadinimai, adresai ir pastabos.</p>
      <button class="btn" type="submit">Importuoti paštomatus</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Nemokamo pristatymo pasiūlymai</h3>
  <p class="muted" style="margin-top:-4px;">Pasirinkite iki 4 prekių, kurių įsigijus pirkėjui automatiškai suteikiamas nemokamas pristatymas.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="shipping_free_products">
    <?php for ($i = 0; $i < 4; $i++): $current = $freeShippingProductIds[$i] ?? ''; ?>
      <label style="display:flex; flex-direction:column; gap:8px;">
        <span style="font-weight:600; color:#0f172a;">Prekė #<?php echo $i + 1; ?></span>
        <select name="promo_products[]" style="padding:10px 12px; border-radius:12px; border:1px solid #e6e6ef;">
          <option value="">— Nepasirinkta —</option>
          <?php foreach ($products as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$current === (int)$p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['title']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endfor; ?>
    <div style="grid-column: 1/-1; display:flex; justify-content: flex-end;">
      <button class="btn" type="submit">Išsaugoti pasiūlymus</button>
    </div>
  </form>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Esami paštomatai</h3>
  <?php if (!$lockerNetworks): ?>
    <p class="muted">Paštomatų dar nėra. Įkelkite failą arba pridėkite rankiniu būdu.</p>
  <?php else: ?>
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
      <?php foreach ($lockerNetworks as $providerKey => $list): ?>
        <div class="card" style="box-shadow:none; border:1px solid #ebeaf5;">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <strong style="text-transform:uppercase; font-size:13px; letter-spacing:0.04em; color:#4b5563;">
              <?php echo htmlspecialchars($providerKey === 'omniva' ? 'Omniva' : 'LP Express'); ?>
            </strong>
            <span class="muted" style="font-size:12px;">Iš viso: <?php echo count($list); ?></span>
          </div>
          <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:6px; max-height:260px; overflow:auto;">
            <?php foreach ($list as $loc): ?>
              <li style="padding:8px; border:1px solid #f0f0f5; border-radius:10px;">
                <form method="post" style="display:flex; flex-direction:column; gap:6px;">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="action" value="locker_update">
                  <input type="hidden" name="locker_id" value="<?php echo (int)$loc['id']; ?>">
                  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:6px; align-items:center;">
                    <select name="locker_provider" style="padding:8px 10px; border-radius:8px; border:1px solid #e6e6ef; background:#fff;">
                      <option value="omniva" <?php echo $loc['provider'] === 'omniva' ? 'selected' : ''; ?>>Omniva</option>
                      <option value="lpexpress" <?php echo $loc['provider'] === 'lpexpress' ? 'selected' : ''; ?>>LP Express</option>
                    </select>
                    <input name="locker_title" value="<?php echo htmlspecialchars($loc['title']); ?>" placeholder="Pavadinimas" style="padding:8px 10px; border-radius:8px; border:1px solid #e6e6ef;">
                  </div>
                  <input name="locker_address" value="<?php echo htmlspecialchars($loc['address']); ?>" placeholder="Adresas" style="padding:8px 10px; border-radius:8px; border:1px solid #e6e6ef;">
                  <textarea name="locker_note" rows="2" placeholder="Pastabos (pasirinktinai)" style="padding:8px 10px; border-radius:8px; border:1px solid #e6e6ef; resize:vertical;"><?php echo htmlspecialchars($loc['note'] ?? ''); ?></textarea>
                  <div style="display:flex; justify-content:flex-end;">
                    <button class="btn" type="submit" style="width:auto; padding:8px 14px; font-size:13px;">Atnaujinti</button>
                  </div>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
