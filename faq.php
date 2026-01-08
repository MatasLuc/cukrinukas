<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
$pdo = getPdo();
ensureNavigationTable($pdo);
tryAutoLogin($pdo);
$siteContent = getSiteContent($pdo);
$faqs = [
    ['Kokias diabetui skirtas prekes siūlote?', 'Gliukometrai, sensoriai, lancetai, juostelės, mitybos produktai ir kita reikalinga priežiūrai.'],
    ['Ar produktai turi galiojimo laiką?', 'Taip, prie kiekvienos prekės nurodome tinkamumo laiką ir sandėliavimo rekomendacijas.'],
    ['Kaip vykdomas pristatymas?', 'Siunčiame per kurjerius ir paštomatus visoje Lietuvoje per 1–3 darbo dienas.'],
    ['Ar galima grąžinti prekes?', 'Jeigu pakuotė nepažeista ir laikomasi higienos reikalavimų, grąžinimus priimame per 14 dienų.'],
    ['Kaip susisiekti dėl konsultacijos?', 'Parašykite el. paštu e.kolekcija@gmail.com arba skambinkite +37060093880 – padėsime parinkti reikalingas priemones.'],
];
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dažniausiai užduodami klausimai | Cukrinukas</title>
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
  <?php renderHeader($pdo, 'faq'); ?>
  <main class="page">
    <section class="hero">
      <div class="hero__pill"><?php echo htmlspecialchars($siteContent['faq_hero_pill'] ?? '💡 Pagalba ir gairės'); ?></div>
      <h1><?php echo htmlspecialchars($siteContent['faq_hero_title'] ?? 'Dažniausiai užduodami klausimai'); ?></h1>
      <p><?php echo htmlspecialchars($siteContent['faq_hero_body'] ?? 'Trumpi atsakymai apie pristatymą, grąžinimus ir kaip išsirinkti tinkamus produktus diabetui prižiūrėti.'); ?></p>
    </section>
    <?php foreach ($faqs as $item): ?>
      <article class="card">
        <h3><?php echo htmlspecialchars($item[0]); ?></h3>
        <p><?php echo htmlspecialchars($item[1]); ?></p>
      </article>
    <?php endforeach; ?>
  </main>

  <?php renderFooter($pdo); ?>
</body>
</html>
