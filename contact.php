<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
$pdo = getPdo();
ensureNavigationTable($pdo);
$siteContent = getSiteContent($pdo);
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kontaktai | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #475467;
      --accent: #7c3aed;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    .page { max-width: 1100px; margin:0 auto; padding:32px 20px 64px; display:grid; gap:22px; }
    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border-radius: 26px; padding: 24px 26px; border:1px solid #e5e7eb; box-shadow:0 16px 44px rgba(0,0,0,0.08); display:grid; grid-template-columns: 1.3fr 0.7fr; gap:18px; align-items:center; }
    .hero__pill { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; background:#fff; border:1px solid #e4e7ec; border-radius:999px; font-weight:700; box-shadow:0 12px 30px rgba(0,0,0,0.08); }
    .hero h1 { margin:10px 0 8px; font-size: clamp(26px, 4vw, 34px); letter-spacing:-0.02em; }
    .hero p { margin:0; color: var(--muted); line-height:1.6; }
    .cta { display:inline-flex; align-items:center; gap:8px; padding:12px 16px; border-radius:12px; border:none; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; box-shadow:0 14px 36px rgba(124,58,237,0.25); cursor:pointer; }
    .cta.secondary { background:#fff; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none; }
    .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap:16px; align-items:start; }
    .card { background:var(--card); border-radius:18px; padding:18px 20px; border:1px solid var(--border); box-shadow:0 12px 28px rgba(0,0,0,0.08); }
    .card h2 { margin:0 0 8px; letter-spacing:-0.01em; }
    .muted { color: var(--muted); margin:0 0 8px; }
    .list { margin:0; padding:0; list-style:none; display:grid; gap:10px; }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; background:#eef2ff; color:#4338ca; font-weight:700; font-size:13px; }
    iframe { width:100%; border:0; border-radius:16px; min-height:320px; box-shadow:0 12px 30px rgba(0,0,0,0.08); }
  </style>

</head>
<body>
  <?php renderHeader($pdo, 'contact'); ?>
  <main class="page">
    <section class="hero">
      <div>
        <div class="hero__pill"><?php echo htmlspecialchars($siteContent['contact_hero_pill'] ?? '🤝 Esame šalia'); ?></div>
        <h1><?php echo htmlspecialchars($siteContent['contact_hero_title'] ?? 'Susisiekime ir aptarkime, kaip galime padėti'); ?></h1>
        <p><?php echo htmlspecialchars($siteContent['contact_hero_body'] ?? 'Greiti atsakymai, nuoširdūs patarimai ir pagalba parenkant reikiamus produktus – parašykite mums.'); ?></p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
          <a class="cta" href="<?php echo htmlspecialchars($siteContent['contact_cta_primary_url'] ?? 'mailto:e.kolekcija@gmail.com'); ?>"><?php echo htmlspecialchars($siteContent['contact_cta_primary_label'] ?? 'Rašyti el. laišką'); ?></a>
          <a class="cta secondary" href="<?php echo htmlspecialchars($siteContent['contact_cta_secondary_url'] ?? 'tel:+37060093880'); ?>"><?php echo htmlspecialchars($siteContent['contact_cta_secondary_label'] ?? 'Skambinti +37060093880'); ?></a>
        </div>
      </div>
      <div style="text-align:right;">
        <div class="card" style="display:inline-grid; gap:6px; text-align:left; min-width:260px;">
          <span class="pill" style="justify-content:flex-start; background:#ecfdf3; color:#15803d;"><?php echo htmlspecialchars($siteContent['contact_card_pill'] ?? 'Greita reakcija'); ?></span>
          <strong style="font-size:24px; letter-spacing:-0.02em;"><?php echo htmlspecialchars($siteContent['contact_card_title'] ?? 'Iki 1 darbo dienos'); ?></strong>
          <p class="muted" style="margin:0;"><?php echo htmlspecialchars($siteContent['contact_card_body'] ?? 'Į užklausas atsakome kuo greičiau, kad galėtumėte pasirūpinti savo poreikiais.'); ?></p>
        </div>
      </div>
    </section>

    <section class="cards">
      <div class="card">
        <p class="muted">Susisiekite</p>
        <h2>Kontaktai</h2>
        <ul class="list">
          <li><strong>Telefono numeris:</strong> +37060093880</li>
          <li><strong>El. paštas:</strong> e.kolekcija@gmail.com</li>
          <li><strong>Adresas:</strong> Vaičaičio g. 10, Marijampolė</li>
          <li><strong>Facebook:</strong> <a href="https://www.facebook.com/ekolekcija.ekolekcija" target="_blank">fb.com/ekolekcija.ekolekcija</a></li>
        </ul>
      </div>
      <div class="card">
        <p class="muted">Darbo laikas</p>
        <h2>Ruošiame užsakymus</h2>
        <ul class="list">
          <li>I–V: 09:00 – 18:00</li>
          <li>VI: 10:00 – 14:00</li>
          <li>VII: nedirbame</li>
        </ul>
      </div>
      <div class="card">
        <p class="muted">Papildoma informacija</p>
        <h2>Mūsų įsipareigojimas</h2>
        <ul class="list">
          <li>Asmeninis konsultavimas diabetui skirtomis prekėmis.</li>
          <li>Nuolatinės akcijos ir individualūs pasiūlymai.</li>
          <li>Saugus ir greitas prekių pristatymas visoje Lietuvoje.</li>
        </ul>
      </div>
    </section>

    <div class="card" style="grid-column:1/-1;">
      <p class="muted">Atvykite</p>
      <h2>Kaip mus rasti</h2>
      <iframe src="https://maps.google.com/maps?q=Vai%C4%8Dai%C4%8Dio%20G.%2010%20Marijampol%C4%97&t=m&z=13&ie=UTF8&output=embed" loading="lazy"></iframe>
    </div>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
