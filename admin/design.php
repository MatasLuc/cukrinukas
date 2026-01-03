<?php
// admin/design.php

// Užkrauname dizaino nustatymus ir nuorodas
$siteContent = getSiteContent($pdo);
$footerLinks = getFooterLinks($pdo);
?>

<div class="card" style="margin-bottom:18px;">
  <h3>Pagrindinio hero tekstai</h3>
  <p class="muted" style="margin-top:-4px;">Atnaujinkite titulinio puslapio antraštę, aprašymą ir mygtuko nuorodą.</p>
  <form method="post">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="hero_copy">
    <input name="hero_title" value="<?php echo htmlspecialchars($siteContent['hero_title'] ?? ''); ?>" placeholder="Antraštė">
    <textarea name="hero_body" rows="3" placeholder="Aprašymas"><?php echo htmlspecialchars($siteContent['hero_body'] ?? ''); ?></textarea>
    <div class="input-row">
      <input name="hero_cta_label" style="flex:1; min-width:200px;" value="<?php echo htmlspecialchars($siteContent['hero_cta_label'] ?? ''); ?>" placeholder="Mygtuko tekstas">
      <input name="hero_cta_url" style="flex:1; min-width:200px;" value="<?php echo htmlspecialchars($siteContent['hero_cta_url'] ?? ''); ?>" placeholder="Mygtuko nuoroda">
    </div>
    <button class="btn" type="submit">Išsaugoti</button>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Hero fonas ir media</h3>
  <p class="muted" style="margin-top:-4px;">Pasirinkite ar hero naudos spalvą, nuotrauką ar video. Įkeltos bylos saugomos /uploads aplanke.</p>
  <form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="hero_media_update">
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Fono tipas</label>
      <select name="hero_media_type">
        <?php $selectedType = $siteContent['hero_media_type'] ?? 'image'; ?>
        <option value="color" <?php echo $selectedType === 'color' ? 'selected' : ''; ?>>Spalva</option>
        <option value="image" <?php echo $selectedType === 'image' ? 'selected' : ''; ?>>Nuotrauka</option>
        <option value="video" <?php echo $selectedType === 'video' ? 'selected' : ''; ?>>Video</option>
      </select>
      <label>Spalva</label>
      <input name="hero_media_color" type="color" value="<?php echo htmlspecialchars($siteContent['hero_media_color'] ?? '#829ed6'); ?>">
      <label>Overlay (šešėlis) intensyvumas</label>
      <input name="hero_shadow_intensity" type="range" min="0" max="100" value="<?php echo (int)($siteContent['hero_shadow_intensity'] ?? 70); ?>" oninput="this.nextElementSibling.value=this.value">
      <output style="display:block; margin-top:4px; font-weight:600;"><?php echo (int)($siteContent['hero_shadow_intensity'] ?? 70); ?></output>
      <label>Alternatyvus tekstas</label>
      <input name="hero_media_alt" value="<?php echo htmlspecialchars($siteContent['hero_media_alt'] ?? ''); ?>" placeholder="Trumpas aprašymas">
    </div>

    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <p style="margin-top:0; font-weight:600;">Nuotrauka</p>
      <input type="hidden" name="hero_media_image_existing" value="<?php echo htmlspecialchars($siteContent['hero_media_image'] ?? ''); ?>">
      <input type="file" name="hero_media_image" accept="image/*">
      <?php if (!empty($siteContent['hero_media_image'])): ?>
        <p class="muted" style="margin:6px 0 0;">Dabartinis kelias: <?php echo htmlspecialchars($siteContent['hero_media_image']); ?></p>
      <?php endif; ?>
    </div>

    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <p style="margin-top:0; font-weight:600;">Video</p>
      <input type="hidden" name="hero_media_video_existing" value="<?php echo htmlspecialchars($siteContent['hero_media_video'] ?? ''); ?>">
      <input type="file" name="hero_media_video" accept="video/mp4,video/webm,video/quicktime">
      <?php if (!empty($siteContent['hero_media_video'])): ?>
        <p class="muted" style="margin:6px 0 0;">Dabartinis kelias: <?php echo htmlspecialchars($siteContent['hero_media_video']); ?></p>
      <?php endif; ?>
      <label style="margin-top:12px;">Plakatas (poster)</label>
      <input type="hidden" name="hero_media_poster_existing" value="<?php echo htmlspecialchars($siteContent['hero_media_poster'] ?? ''); ?>">
      <input type="file" name="hero_media_poster" accept="image/*">
      <?php if (!empty($siteContent['hero_media_poster'])): ?>
        <p class="muted" style="margin:6px 0 0;">Dabartinis plakatas: <?php echo htmlspecialchars($siteContent['hero_media_poster']); ?></p>
      <?php endif; ?>
    </div>

    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti hero foną</button>
    </div>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Hero sekcijos</h3>
  <p class="muted" style="margin-top:-4px;">Tvarkykite kiekvieno puslapio hero tekstus ir, jei yra, kortelę.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="page_hero_update">

    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <h4 style="margin:0 0 6px;">Naujienos</h4>
      <input name="news_hero_pill" value="<?php echo htmlspecialchars($siteContent['news_hero_pill'] ?? ''); ?>" placeholder="Piliulė">
      <input name="news_hero_title" value="<?php echo htmlspecialchars($siteContent['news_hero_title'] ?? ''); ?>" placeholder="Antraštė" style="margin-top:8px;">
      <textarea name="news_hero_body" rows="3" placeholder="Aprašymas" style="margin-top:8px;"><?php echo htmlspecialchars($siteContent['news_hero_body'] ?? ''); ?></textarea>
      <div class="input-row">
        <input name="news_hero_cta_label" value="<?php echo htmlspecialchars($siteContent['news_hero_cta_label'] ?? ''); ?>" placeholder="Mygtuko tekstas">
        <input name="news_hero_cta_url" value="<?php echo htmlspecialchars($siteContent['news_hero_cta_url'] ?? ''); ?>" placeholder="Nuoroda">
      </div>
      <label style="margin-top:10px;">Kortelė</label>
      <input name="news_hero_card_meta" value="<?php echo htmlspecialchars($siteContent['news_hero_card_meta'] ?? ''); ?>" placeholder="Meta">
      <input name="news_hero_card_title" value="<?php echo htmlspecialchars($siteContent['news_hero_card_title'] ?? ''); ?>" placeholder="Kortelės antraštė" style="margin-top:6px;">
      <textarea name="news_hero_card_body" rows="2" placeholder="Kortelės tekstas" style="margin-top:6px;"><?php echo htmlspecialchars($siteContent['news_hero_card_body'] ?? ''); ?></textarea>
    </div>

    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <h4 style="margin:0 0 6px;">Receptai</h4>
      <input name="recipes_hero_pill" value="<?php echo htmlspecialchars($siteContent['recipes_hero_pill'] ?? ''); ?>" placeholder="Piliulė">
      <input name="recipes_hero_title" value="<?php echo htmlspecialchars($siteContent['recipes_hero_title'] ?? ''); ?>" placeholder="Antraštė" style="margin-top:8px;">
      <textarea name="recipes_hero_body" rows="3" placeholder="Aprašymas" style="margin-top:8px;"><?php echo htmlspecialchars($siteContent['recipes_hero_body'] ?? ''); ?></textarea>
      <div class="input-row">
        <input name="recipes_hero_cta_label" value="<?php echo htmlspecialchars($siteContent['recipes_hero_cta_label'] ?? ''); ?>" placeholder="Mygtuko tekstas">
        <input name="recipes_hero_cta_url" value="<?php echo htmlspecialchars($siteContent['recipes_hero_cta_url'] ?? ''); ?>" placeholder="Nuoroda">
      </div>
      <label style="margin-top:10px;">Kortelė</label>
      <input name="recipes_hero_card_meta" value="<?php echo htmlspecialchars($siteContent['recipes_hero_card_meta'] ?? ''); ?>" placeholder="Meta">
      <input name="recipes_hero_card_title" value="<?php echo htmlspecialchars($siteContent['recipes_hero_card_title'] ?? ''); ?>" placeholder="Kortelės antraštė" style="margin-top:6px;">
      <textarea name="recipes_hero_card_body" rows="2" placeholder="Kortelės tekstas" style="margin-top:6px;"><?php echo htmlspecialchars($siteContent['recipes_hero_card_body'] ?? ''); ?></textarea>
    </div>

    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <h4 style="margin:0 0 6px;">DUK</h4>
      <input name="faq_hero_pill" value="<?php echo htmlspecialchars($siteContent['faq_hero_pill'] ?? ''); ?>" placeholder="Piliulė">
      <input name="faq_hero_title" value="<?php echo htmlspecialchars($siteContent['faq_hero_title'] ?? ''); ?>" placeholder="Antraštė" style="margin-top:8px;">
      <textarea name="faq_hero_body" rows="4" placeholder="Aprašymas" style="margin-top:8px;"><?php echo htmlspecialchars($siteContent['faq_hero_body'] ?? ''); ?></textarea>
    </div>

    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <h4 style="margin:0 0 6px;">Kontaktai</h4>
      <input name="contact_hero_pill" value="<?php echo htmlspecialchars($siteContent['contact_hero_pill'] ?? ''); ?>" placeholder="Piliulė">
      <input name="contact_hero_title" value="<?php echo htmlspecialchars($siteContent['contact_hero_title'] ?? ''); ?>" placeholder="Antraštė" style="margin-top:8px;">
      <textarea name="contact_hero_body" rows="3" placeholder="Aprašymas" style="margin-top:8px;"><?php echo htmlspecialchars($siteContent['contact_hero_body'] ?? ''); ?></textarea>
      <div class="input-row">
        <input name="contact_cta_primary_label" value="<?php echo htmlspecialchars($siteContent['contact_cta_primary_label'] ?? ''); ?>" placeholder="Pirmo mygtuko tekstas">
        <input name="contact_cta_primary_url" value="<?php echo htmlspecialchars($siteContent['contact_cta_primary_url'] ?? ''); ?>" placeholder="Nuoroda">
      </div>
      <div class="input-row" style="margin-top:6px;">
        <input name="contact_cta_secondary_label" value="<?php echo htmlspecialchars($siteContent['contact_cta_secondary_label'] ?? ''); ?>" placeholder="Antro mygtuko tekstas">
        <input name="contact_cta_secondary_url" value="<?php echo htmlspecialchars($siteContent['contact_cta_secondary_url'] ?? ''); ?>" placeholder="Nuoroda">
      </div>
      <label style="margin-top:10px;">Kortelė</label>
      <input name="contact_card_pill" value="<?php echo htmlspecialchars($siteContent['contact_card_pill'] ?? ''); ?>" placeholder="Piliulė">
      <input name="contact_card_title" value="<?php echo htmlspecialchars($siteContent['contact_card_title'] ?? ''); ?>" placeholder="Kortelės antraštė" style="margin-top:6px;">
      <textarea name="contact_card_body" rows="2" placeholder="Kortelės tekstas" style="margin-top:6px;"><?php echo htmlspecialchars($siteContent['contact_card_body'] ?? ''); ?></textarea>
    </div>

    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti hero sekcijas</button>
    </div>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Promo kortelės</h3>
  <p class="muted" style="margin-top:-4px;">Redaguokite tris akcentus po hero: ikoną, pavadinimą ir tekstą.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="promo_update">
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
        <label style="margin-top:0;">Ikona #<?php echo $i; ?></label>
        <input name="promo_<?php echo $i; ?>_icon" value="<?php echo htmlspecialchars($siteContent['promo_' . $i . '_icon'] ?? ''); ?>" placeholder="Pvz. 24/7 arba ★">
        <label>Pavadinimas</label>
        <input name="promo_<?php echo $i; ?>_title" value="<?php echo htmlspecialchars($siteContent['promo_' . $i . '_title'] ?? ''); ?>" placeholder="Antraštė">
        <label>Aprašymas</label>
        <textarea name="promo_<?php echo $i; ?>_body" rows="3" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['promo_' . $i . '_body'] ?? ''); ?></textarea>
      </div>
    <?php endfor; ?>
    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti promo korteles</button>
    </div>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Storyband</h3>
  <p class="muted" style="margin-top:-4px;">Tvarkykite titulinės juostos ženkliuką, tekstus, mygtuką ir tris metrinius rodiklius.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="storyband_update">
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Ženkliukas</label>
      <input name="storyband_badge" value="<?php echo htmlspecialchars($siteContent['storyband_badge'] ?? ''); ?>" placeholder="Storyband ženkliukas">
      <label>Antraštė</label>
      <input name="storyband_title" value="<?php echo htmlspecialchars($siteContent['storyband_title'] ?? ''); ?>" placeholder="Antraštė">
      <label>Aprašymas</label>
      <textarea name="storyband_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['storyband_body'] ?? ''); ?></textarea>
      <div class="input-row">
        <input name="storyband_cta_label" value="<?php echo htmlspecialchars($siteContent['storyband_cta_label'] ?? ''); ?>" placeholder="Mygtuko tekstas">
        <input name="storyband_cta_url" value="<?php echo htmlspecialchars($siteContent['storyband_cta_url'] ?? ''); ?>" placeholder="Mygtuko nuoroda">
      </div>
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Kortelės meta</label>
      <input name="storyband_card_eyebrow" value="<?php echo htmlspecialchars($siteContent['storyband_card_eyebrow'] ?? ''); ?>" placeholder="Reklaminis akcentas">
      <label>Kortelės antraštė</label>
      <input name="storyband_card_title" value="<?php echo htmlspecialchars($siteContent['storyband_card_title'] ?? ''); ?>" placeholder="„Cukrinukas“ rinkiniai">
      <label>Kortelės tekstas</label>
      <textarea name="storyband_card_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['storyband_card_body'] ?? ''); ?></textarea>
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Metrika #1</label>
      <div class="input-row">
        <input name="storyband_metric_1_value" style="flex:1;" value="<?php echo htmlspecialchars($siteContent['storyband_metric_1_value'] ?? ''); ?>" placeholder="Reikšmė">
        <input name="storyband_metric_1_label" style="flex:1;" value="<?php echo htmlspecialchars($siteContent['storyband_metric_1_label'] ?? ''); ?>" placeholder="Pavadinimas">
      </div>
      <label>Metrika #2</label>
      <div class="input-row">
        <input name="storyband_metric_2_value" style="flex:1;" value="<?php echo htmlspecialchars($siteContent['storyband_metric_2_value'] ?? ''); ?>" placeholder="Reikšmė">
        <input name="storyband_metric_2_label" style="flex:1;" value="<?php echo htmlspecialchars($siteContent['storyband_metric_2_label'] ?? ''); ?>" placeholder="Pavadinimas">
      </div>
      <label>Metrika #3</label>
      <div class="input-row">
        <input name="storyband_metric_3_value" style="flex:1;" value="<?php echo htmlspecialchars($siteContent['storyband_metric_3_value'] ?? ''); ?>" placeholder="Reikšmė">
        <input name="storyband_metric_3_label" style="flex:1;" value="<?php echo htmlspecialchars($siteContent['storyband_metric_3_label'] ?? ''); ?>" placeholder="Pavadinimas">
      </div>
    </div>
    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti storyband</button>
    </div>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Story-row</h3>
  <p class="muted" style="margin-top:-4px;">Valdykite eilutės turinį: tekstus, tris piliules ir abi dešinės pusės korteles.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="storyrow_update">
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Antantraštė</label>
      <input name="storyrow_eyebrow" value="<?php echo htmlspecialchars($siteContent['storyrow_eyebrow'] ?? ''); ?>" placeholder="Dienos rutina">
      <label>Antraštė</label>
      <input name="storyrow_title" value="<?php echo htmlspecialchars($siteContent['storyrow_title'] ?? ''); ?>" placeholder="Stebėjimas, užkandžiai ir ramybė">
      <label>Aprašymas</label>
      <textarea name="storyrow_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['storyrow_body'] ?? ''); ?></textarea>
      <label>Piliulės</label>
      <input name="storyrow_pill_1" value="<?php echo htmlspecialchars($siteContent['storyrow_pill_1'] ?? ''); ?>" placeholder="Pirmas punktas">
      <input name="storyrow_pill_2" value="<?php echo htmlspecialchars($siteContent['storyrow_pill_2'] ?? ''); ?>" placeholder="Antras punktas" style="margin-top:6px;">
      <input name="storyrow_pill_3" value="<?php echo htmlspecialchars($siteContent['storyrow_pill_3'] ?? ''); ?>" placeholder="Trečias punktas" style="margin-top:6px;">
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Dešinės kortelės meta</label>
      <input name="storyrow_bubble_meta" value="<?php echo htmlspecialchars($siteContent['storyrow_bubble_meta'] ?? ''); ?>" placeholder="Rekomendacija">
      <label>Kortelės antraštė</label>
      <input name="storyrow_bubble_title" value="<?php echo htmlspecialchars($siteContent['storyrow_bubble_title'] ?? ''); ?>" placeholder="„Cukrinukas“ specialistai">
      <label>Kortelės tekstas</label>
      <textarea name="storyrow_bubble_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['storyrow_bubble_body'] ?? ''); ?></textarea>
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Plūduriuojančios kortelės meta</label>
      <input name="storyrow_floating_meta" value="<?php echo htmlspecialchars($siteContent['storyrow_floating_meta'] ?? ''); ?>" placeholder="Greitas pristatymas">
      <label>Kortelės antraštė</label>
      <input name="storyrow_floating_title" value="<?php echo htmlspecialchars($siteContent['storyrow_floating_title'] ?? ''); ?>" placeholder="1-2 d.d.">
      <label>Kortelės tekstas</label>
      <textarea name="storyrow_floating_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['storyrow_floating_body'] ?? ''); ?></textarea>
    </div>
    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti story-row</button>
    </div>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Support band</h3>
  <p class="muted" style="margin-top:-4px;">Atnaujinkite bendruomenės juostos tekstus, ženkliukus ir veiksmo mygtuką.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="support_update">
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Meta</label>
      <input name="support_meta" value="<?php echo htmlspecialchars($siteContent['support_meta'] ?? ''); ?>" placeholder="Bendruomenė">
      <label>Antraštė</label>
      <input name="support_title" value="<?php echo htmlspecialchars($siteContent['support_title'] ?? ''); ?>" placeholder="Pagalba jums ir šeimai">
      <label>Aprašymas</label>
      <textarea name="support_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['support_body'] ?? ''); ?></textarea>
      <label>Temos</label>
      <input name="support_chip_1" value="<?php echo htmlspecialchars($siteContent['support_chip_1'] ?? ''); ?>" placeholder="Pirmas akcentas">
      <input name="support_chip_2" value="<?php echo htmlspecialchars($siteContent['support_chip_2'] ?? ''); ?>" placeholder="Antras akcentas" style="margin-top:6px;">
      <input name="support_chip_3" value="<?php echo htmlspecialchars($siteContent['support_chip_3'] ?? ''); ?>" placeholder="Trečias akcentas" style="margin-top:6px;">
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label style="margin-top:0;">Kortelės meta</label>
      <input name="support_card_meta" value="<?php echo htmlspecialchars($siteContent['support_card_meta'] ?? ''); ?>" placeholder="Gyva konsultacija">
      <label>Kortelės antraštė</label>
      <input name="support_card_title" value="<?php echo htmlspecialchars($siteContent['support_card_title'] ?? ''); ?>" placeholder="5 d. per savaitę">
      <label>Kortelės tekstas</label>
      <textarea name="support_card_body" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['support_card_body'] ?? ''); ?></textarea>
      <div class="input-row" style="margin-top:6px;">
        <input name="support_card_cta_label" value="<?php echo htmlspecialchars($siteContent['support_card_cta_label'] ?? ''); ?>" placeholder="Mygtuko tekstas">
        <input name="support_card_cta_url" value="<?php echo htmlspecialchars($siteContent['support_card_cta_url'] ?? ''); ?>" placeholder="Mygtuko nuoroda">
      </div>
    </div>
    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti support band</button>
    </div>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Reklamjuostė</h3>
  <p class="muted" style="margin-top:-4px;">Įjunkite viršutinę juostą ir suredaguokite tekstą, spalvą bei nuorodą.</p>
  <form method="post" class="input-row" style="flex-direction:column; gap:10px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="banner_update">
    <label style="display:flex; align-items:center; gap:8px;">
      <input type="checkbox" name="banner_enabled" <?php echo !empty($siteContent['banner_enabled']) && $siteContent['banner_enabled'] !== '0' ? 'checked' : ''; ?>>
      Rodyti reklamjuostę
    </label>
    <input name="banner_text" value="<?php echo htmlspecialchars($siteContent['banner_text'] ?? ''); ?>" placeholder="Tekstas">
    <input name="banner_link" value="<?php echo htmlspecialchars($siteContent['banner_link'] ?? ''); ?>" placeholder="Nuoroda (neprivaloma)">
    <label>Fono spalva</label>
    <input type="color" name="banner_background" value="<?php echo htmlspecialchars($siteContent['banner_background'] ?? '#829ed6'); ?>" style="width:120px; height:42px; padding:0;">
    <button class="btn" type="submit">Išsaugoti</button>
  </form>
</div>

<div class="card" style="margin-bottom:18px;">
  <h3>Atsiliepimai</h3>
  <p class="muted" style="margin-top:-4px;">Redaguokite 3 klientų istorijas, kurios rodomos tituliniame puslapyje.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="testimonial_update">
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
        <label style="margin-top:0;">Vardas/pareigos</label>
        <input name="testimonial_<?php echo $i; ?>_name" value="<?php echo htmlspecialchars($siteContent['testimonial_' . $i . '_name'] ?? ''); ?>" placeholder="Vardas">
        <label>Pozicija</label>
        <input name="testimonial_<?php echo $i; ?>_role" value="<?php echo htmlspecialchars($siteContent['testimonial_' . $i . '_role'] ?? ''); ?>" placeholder="Rolė">
        <label>Atsiliepimas</label>
        <textarea name="testimonial_<?php echo $i; ?>_text" rows="4" placeholder="Tekstas"><?php echo htmlspecialchars($siteContent['testimonial_' . $i . '_text'] ?? ''); ?></textarea>
      </div>
    <?php endfor; ?>
    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti atsiliepimus</button>
    </div>
  </form>
</div>

<div class="card" style="grid-column: span 3;">
  <h3>Poraštės tekstas</h3>
  <p class="muted" style="margin-top:-4px;">Atnaujinkite trumpą aprašą, skilties pavadinimus ir kontaktinę informaciją.</p>
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="footer_content">
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label>Pavadinimas</label>
      <input name="footer_brand_title" value="<?php echo htmlspecialchars($siteContent['footer_brand_title'] ?? ''); ?>" placeholder="Cukrinukas.lt">
      <label>Aprašas</label>
      <textarea name="footer_brand_body" rows="3" placeholder="Trumpas tekstas apie parduotuvę."><?php echo htmlspecialchars($siteContent['footer_brand_body'] ?? ''); ?></textarea>
      <label>Ženkliukas</label>
      <input name="footer_brand_pill" value="<?php echo htmlspecialchars($siteContent['footer_brand_pill'] ?? ''); ?>" placeholder="Kasdienė priežiūra">
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label>Greitų nuorodų pavadinimas</label>
      <input name="footer_quick_title" value="<?php echo htmlspecialchars($siteContent['footer_quick_title'] ?? ''); ?>">
      <label>Pagalbos pavadinimas</label>
      <input name="footer_help_title" value="<?php echo htmlspecialchars($siteContent['footer_help_title'] ?? ''); ?>">
      <label>Kontaktų pavadinimas</label>
      <input name="footer_contact_title" value="<?php echo htmlspecialchars($siteContent['footer_contact_title'] ?? ''); ?>">
    </div>
    <div class="card" style="box-shadow:none; border:1px solid #e9e9f3;">
      <label>El. paštas</label>
      <input name="footer_contact_email" value="<?php echo htmlspecialchars($siteContent['footer_contact_email'] ?? ''); ?>" placeholder="info@cukrinukas.lt">
      <label>Tel.</label>
      <input name="footer_contact_phone" value="<?php echo htmlspecialchars($siteContent['footer_contact_phone'] ?? ''); ?>" placeholder="+370...">
      <label>Darbo laikas</label>
      <input name="footer_contact_hours" value="<?php echo htmlspecialchars($siteContent['footer_contact_hours'] ?? ''); ?>" placeholder="I–V 09:00–18:00">
    </div>
    <div style="grid-column:1/-1;">
      <button class="btn" type="submit">Išsaugoti poraštę</button>
    </div>
  </form>
</div>

<div class="card" style="grid-column: span 3;">
  <h3>Poraštės nuorodos</h3>
  <p class="muted" style="margin-top:-4px;">Pridėkite arba redaguokite greitas nuorodas ir pagalbos meniu.</p>
  <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; align-items:flex-end;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="footer_link_save">
    <div style="flex:1 1 160px; min-width:180px;">
      <label style="margin-top:0;">Pavadinimas</label>
      <input name="label" required placeholder="Pvz., Pristatymas">
    </div>
    <div style="flex:2 1 240px; min-width:220px;">
      <label style="margin-top:0;">Nuoroda</label>
      <input name="url" required placeholder="/shipping.php">
    </div>
    <div style="flex:1 1 140px; min-width:140px;">
      <label style="margin-top:0;">Skiltis</label>
      <select name="section">
        <option value="quick">Greitos nuorodos</option>
        <option value="help">Pagalba</option>
      </select>
    </div>
    <div style="flex:0 0 100px;">
      <label style="margin-top:0;">Eilė</label>
      <input name="sort_order" type="number" value="0" style="width:100%;">
    </div>
    <button class="btn" type="submit">Pridėti nuorodą</button>
  </form>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Nuoroda</th><th>Skiltis</th><th>Eilė</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach (['quick' => 'Greitos nuorodos', 'help' => 'Pagalba'] as $sectionKey => $sectionLabel): ?>
        <?php foreach ($footerLinks[$sectionKey] ?? [] as $link): ?>
          <tr>
            <td><?php echo htmlspecialchars($link['label']); ?></td>
            <td><?php echo htmlspecialchars($link['url']); ?></td>
            <td><?php echo htmlspecialchars($sectionLabel); ?></td>
            <td><?php echo (int)$link['sort_order']; ?></td>
            <td style="display:flex; gap:6px; flex-wrap:wrap;">
              <form method="post" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="footer_link_save">
                <input type="hidden" name="id" value="<?php echo (int)$link['id']; ?>">
                <input name="label" value="<?php echo htmlspecialchars($link['label']); ?>" style="width:140px; margin:0;">
                <input name="url" value="<?php echo htmlspecialchars($link['url']); ?>" style="width:200px; margin:0;">
                <select name="section" style="margin:0;">
                  <option value="quick" <?php echo $link['section'] === 'quick' ? 'selected' : ''; ?>>Greitos nuorodos</option>
                  <option value="help" <?php echo $link['section'] === 'help' ? 'selected' : ''; ?>>Pagalba</option>
                </select>
                <input type="number" name="sort_order" value="<?php echo (int)$link['sort_order']; ?>" style="width:80px;">
                <button class="btn" type="submit">Atnaujinti</button>
              </form>
              <form method="post" onsubmit="return confirm('Trinti nuorodą?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="footer_link_delete">
                <input type="hidden" name="id" value="<?php echo (int)$link['id']; ?>">
                <button class="btn" type="submit" style="background:#fff; color:#0b0b0b;">Trinti</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
