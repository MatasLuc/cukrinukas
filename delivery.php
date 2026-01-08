<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureNavigationTable($pdo);
tryAutoLogin($pdo);

// Pristatymo būdų informacija
$deliveryMethods = [
    [
        'title' => 'LP Express / Omniva / DPD paštomatai',
        'desc' => 'Patogiausias būdas atsiimti prekes. Pristatymas per 1–3 darbo dienas į jūsų pasirinktą paštomatą visoje Lietuvoje. Kaina: 2.99 € (nemokamai nuo 50 €).'
    ],
    [
        'title' => 'DPD Kurjeris į namus',
        'desc' => 'Siuntą kurjeris pristatys tiesiai jūsų nurodytu adresu (į namus ar darbovietę). Prieš atvykdamas kurjeris informuos SMS žinute. Pristatymas per 1–2 darbo dienas. Kaina: 4.99 €.'
    ],
    [
        'title' => 'Autobusų siuntos',
        'desc' => 'Skubus pristatymas tą pačią arba kitą dieną į didžiųjų miestų autobusų stotis. Siuntą reikia atsiimti siuntų skyriuje. Kaina: 5.50 €.'
    ],
    [
        'title' => 'Tarptautinis pristatymas',
        'desc' => 'Siunčiame prekes į ES šalis registruotu paštu. Pristatymo terminas priklauso nuo šalies, paprastai trunka 5–10 darbo dienų. Kaina skaičiuojama krepšelyje.'
    ]
];
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pristatymas | Cukrinukas</title>
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
    .page { max-width: 900px; margin:0 auto; padding:32px 20px 64px; display:grid; gap:18px; }
    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border-radius: 24px; padding: 24px 24px 26px; border:1px solid #e5e7eb; box-shadow:0 16px 42px rgba(0,0,0,0.08); }
    .hero__pill { display:inline-flex; align-items:center; gap:8px; padding:9px 14px; background:#fff; border:1px solid #e4e7ec; border-radius:999px; font-weight:700; box-shadow:0 10px 26px rgba(0,0,0,0.08); }
    .hero h1 { margin:10px 0 8px; font-size: clamp(26px, 4vw, 32px); letter-spacing:-0.02em; }
    .hero p { margin:0; color: var(--muted); line-height:1.6; }
    .card { background:#fff; border-radius:16px; padding:18px; border:1px solid var(--border); box-shadow:0 12px 30px rgba(0,0,0,0.08); }
    .card h3 { margin:0 0 8px; letter-spacing:-0.01em; }
    .card p { margin:0; line-height:1.6; color: var(--muted); }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'delivery'); ?>
  
  <main class="page">
    <section class="hero">
      <div class="hero__pill">🚚 Greitas ir patogus</div>
      <h1>Pristatymo informacija</h1>
      <p>Pasirūpiname, kad diabeto priežiūros priemonės jus pasiektų saugiai ir greitai. Žemiau rasite visus galimus pristatymo būdus.</p>
    </section>

    <?php foreach ($deliveryMethods as $item): ?>
      <article class="card">
        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
        <p><?php echo htmlspecialchars($item['desc']); ?></p>
      </article>
    <?php endforeach; ?>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
