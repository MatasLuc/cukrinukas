<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureNavigationTable($pdo);

// Grąžinimo sąlygų informacija
$returnRules = [
    [
        'title' => '14 dienų grąžinimo garantija',
        'desc' => 'Netikusias kokybiškas prekes galite grąžinti per 14 kalendorinių dienų nuo pristatymo dienos. Prekė turi būti nenaudota, nepraradusi prekinės išvaizdos ir originalioje pakuotėje.'
    ],
    [
        'title' => 'Higienos prekių išimtis',
        'desc' => 'Dėmesio: vadovaujantis teisės aktais, kokybiškos medicininės paskirties prekės, kurios buvo išpakuotos (pvz., gliukomačių juostelės, lancetai, insulino adatos), nėra grąžinamos dėl higienos ir sveikatos apsaugos priežasčių.'
    ],
    [
        'title' => 'Kaip inicijuoti grąžinimą?',
        'desc' => 'Norėdami grąžinti prekę, parašykite mums el. paštu e.kolekcija@gmail.com nurodydami užsakymo numerį ir grąžinimo priežastį. Mes atsiųsime jums grąžinimo lipduką arba instrukciją.'
    ],
    [
        'title' => 'Brokuotos prekės',
        'desc' => 'Jei gavote nekokybišką prekę ar ji neveikia (pvz., sugedęs gliukometras), nedelsiant susisiekite. Pakeisime prekę nauja arba grąžinsime pinigus, taip pat padengsime siuntimo išlaidas.'
    ],
    [
        'title' => 'Pinigų grąžinimo terminas',
        'desc' => 'Pinigai už grąžintas prekes pervedami į jūsų nurodytą banko sąskaitą per 5–10 darbo dienų nuo prekės grįžimo į mūsų sandėlį ir jos patikrinimo.'
    ]
];
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Grąžinimas ir garantija | Cukrinukas</title>
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
  <?php renderHeader($pdo, 'returns'); ?>
  
  <main class="page">
    <section class="hero">
      <div class="hero__pill">🛡️ Garantija ir grąžinimai</div>
      <h1>Prekių grąžinimas</h1>
      <p>Skaidrios ir sąžiningos grąžinimo sąlygos. Sužinokite, kaip elgtis, jei prekė netiko ar gavote brokuotą produktą.</p>
    </section>

    <?php foreach ($returnRules as $item): ?>
      <article class="card">
        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
        <p><?php echo htmlspecialchars($item['desc']); ?></p>
      </article>
    <?php endforeach; ?>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
