<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureNavigationTable($pdo);
tryAutoLogin($pdo);

// Apmokėjimo būdų informacija
$paymentMethods = [
    [
        'title' => 'Elektroninė bankininkystė (Paysera)',
        'desc' => 'Saugus ir greitas atsiskaitymas per jūsų banką (Swedbank, SEB, Luminor, Šiaulių bankas ir kt.). Mokėjimas įskaitomas akimirksniu, todėl užsakymą pradedame vykdyti iš karto.'
    ],
    [
        'title' => 'Banko kortelės (VISA / MasterCard)',
        'desc' => 'Galite atsiskaityti bet kuria galiojančia debetine ar kreditine kortele. Duomenų saugumą užtikrina sertifikuoti mokėjimų partneriai.'
    ],
    [
        'title' => 'Bankinis pavedimas',
        'desc' => 'Pasirinkus šį būdą, gausite sąskaitą faktūrą su rekvizitais el. paštu. Užsakymas pradedamas vykdyti tik gavus lėšas į mūsų banko sąskaitą (gali užtrukti 1 d.d.).'
    ],
    [
        'title' => 'Apmokėjimas atsiimant (COD)',
        'desc' => 'Galimybė atsiskaityti grynaisiais arba kortele kurjeriui pristatymo metu. Taikomas papildomas 1.50 € grynųjų pinigų surinkimo mokestis.'
    ]
];
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Apmokėjimas | Cukrinukas</title>
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
  <?php renderHeader($pdo, 'payment'); ?>
  
  <main class="page">
    <section class="hero">
      <div class="hero__pill">💳 Saugūs atsiskaitymai</div>
      <h1>Apmokėjimo būdai</h1>
      <p>Mes užtikriname maksimalų duomenų saugumą. Pasirinkite jums patogiausią atsiskaitymo būdą už prekes.</p>
    </section>

    <?php foreach ($paymentMethods as $item): ?>
      <article class="card">
        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
        <p><?php echo htmlspecialchars($item['desc']); ?></p>
      </article>
    <?php endforeach; ?>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
