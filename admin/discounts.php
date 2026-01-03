<?php
// admin/discounts.php

$globalDiscount = getGlobalDiscount($pdo);
$discountCodes = getAllDiscountCodes($pdo);
$allCategoriesSimple = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
?>

<div class="grid" style="margin-top:12px; grid-template-columns: repeat(auto-fit, minmax(320px,1fr));">
  <div class="card">
    <h3>Bendra nuolaida</h3>
    <form method="post">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="save_global_discount">
      <label>Tipas</label>
      <select name="discount_type">
        <option value="none" <?php echo $globalDiscount['type'] === 'none' ? 'selected' : ''; ?>>Išjungta</option>
        <option value="percent" <?php echo $globalDiscount['type'] === 'percent' ? 'selected' : ''; ?>>Procentai (%)</option>
        <option value="amount" <?php echo $globalDiscount['type'] === 'amount' ? 'selected' : ''; ?>>Suma (€)</option>
        <option value="free_shipping" <?php echo $globalDiscount['type'] === 'free_shipping' ? 'selected' : ''; ?>>Nemokamas pristatymas</option>
      </select>
      <label>Reikšmė</label>
      <input class="discount-value" data-toggle-select="discount_type" type="number" step="0.01" name="discount_value" value="<?php echo htmlspecialchars($globalDiscount['value']); ?>">
      <button class="btn" type="submit">Išsaugoti</button>
    </form>
  </div>
  <div class="card">
    <h3>Naujas nuolaidos kodas</h3>
    <form method="post" class="input-row" style="flex-direction:column;">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="save_discount_code">
      <label>Kodas</label>
      <input name="code" placeholder="BLACKFRIDAY" required>
      <div class="input-row">
        <div style="flex:1; min-width:140px;">
          <label>Tipas</label>
          <select name="type">
            <option value="percent">Procentai (%)</option>
            <option value="amount">Suma (€)</option>
            <option value="free_shipping">Nemokamas pristatymas</option>
          </select>
        </div>
        <div style="flex:1; min-width:140px;">
          <label>Reikšmė</label>
          <input class="discount-value" data-toggle-select="type" name="value" type="number" step="0.01" min="0" required>
        </div>
      </div>
      <div class="input-row">
        <div style="flex:1; min-width:140px;">
          <label>Panaudojimų limitas (0 – neribota)</label>
          <input name="usage_limit" type="number" min="0" value="0">
        </div>
        <div style="flex:1; min-width:140px; display:flex; flex-direction:column; gap:8px;">
          <label class="checkbox-row"><input type="checkbox" id="code_active_new" name="active" checked> Aktyvus</label>
        </div>
      </div>
      <button class="btn" type="submit">Sukurti kodą</button>
    </form>
  </div>
  <div class="card">
    <h3>Kategorijų nuolaidos</h3>
    <form method="post" class="input-row" style="flex-direction:column;">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="save_category_discount">
      <label>Kategorija</label>
      <select name="category_id" required>
        <option value="">Pasirinkti</option>
        <?php foreach ($allCategoriesSimple as $cat): ?>
          <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <div class="input-row">
        <div style="flex:1;">
          <label>Tipas</label>
          <select name="category_type">
            <option value="none">Išjungta</option>
            <option value="percent">Procentai (%)</option>
            <option value="amount">Suma (€)</option>
            <option value="free_shipping">Nemokamas pristatymas</option>
          </select>
        </div>
        <div style="flex:1;">
          <label>Reikšmė</label>
          <input class="discount-value" data-toggle-select="category_type" name="category_value" type="number" step="0.01" min="0" value="0">
        </div>
      </div>
      <label class="checkbox-row"><input type="checkbox" name="category_active" checked> Aktyvuota</label>
      <button class="btn" type="submit">Išsaugoti</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>Nuolaidų kodai</h3>
  <p class="table-note">Kiekviena eilutė redaguojama vietoje – vertės automatiškai pritaikomos.</p>
  <table class="table-form">
    <thead><tr><th>Kodas</th><th>Tipas</th><th>Reikšmė</th><th>Panaudojimų limitas</th><th>Panaudota</th><th>Aktyvus</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($discountCodes as $code): ?>
        <?php $formId = 'codeform' . (int)$code['id']; ?>
        <form id="<?php echo $formId; ?>" method="post"></form>
        <tr>
          <td>
            <input type="hidden" form="<?php echo $formId; ?>" name="action" value="save_discount_code">
            <input type="hidden" form="<?php echo $formId; ?>" name="id" value="<?php echo (int)$code['id']; ?>">
            <input form="<?php echo $formId; ?>" name="code" value="<?php echo htmlspecialchars($code['code']); ?>">
          </td>
          <td>
            <select form="<?php echo $formId; ?>" name="type">
              <option value="percent" <?php echo $code['type'] === 'percent' ? 'selected' : ''; ?>>%</option>
              <option value="amount" <?php echo $code['type'] === 'amount' ? 'selected' : ''; ?>>€</option>
              <option value="free_shipping" <?php echo $code['type'] === 'free_shipping' ? 'selected' : ''; ?>>Nemokamas pristatymas</option>
            </select>
          </td>
          <td><input form="<?php echo $formId; ?>" data-toggle-select="type" name="value" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($code['value']); ?>"></td>
          <td><input form="<?php echo $formId; ?>" name="usage_limit" type="number" min="0" value="<?php echo (int)$code['usage_limit']; ?>"></td>
          <td class="muted" style="min-width:80px;"><?php echo (int)$code['used_count']; ?></td>
          <td style="text-align:center; min-width:140px;">
            <label class="checkbox-row" style="justify-content:center;"><input form="<?php echo $formId; ?>" type="checkbox" name="active" <?php echo (int)$code['active'] ? 'checked' : ''; ?>> Aktyvus</label>
          </td>
          <td class="inline-actions">
            <button class="btn" form="<?php echo $formId; ?>" type="submit" style="padding:8px 12px;">Išsaugoti</button>
            <form method="post" style="margin:0;">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="delete_discount_code">
              <input type="hidden" name="id" value="<?php echo (int)$code['id']; ?>">
              <button class="btn" type="submit" style="background:#f1f1f5; color:#0b0b0b; border-color:#e0e0ea; padding:8px 12px;">Šalinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$discountCodes): ?>
        <tr><td colspan="7" class="muted">Kodu dar nėra.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
