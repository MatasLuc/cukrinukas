<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureNavigationTable($pdo);
ensureAdminAccount($pdo);
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Apie mus | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #52606d;
      --accent: #4338ca;
      --accent-2: #22c55e;
    }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    * { box-sizing:border-box; }

    .page { max-width:1200px; margin:0 auto; padding:32px 20px 70px; display:flex; flex-direction:column; gap:22px; }

    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border:1px solid #e5e7eb; border-radius:32px; padding:28px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.08); display:grid; grid-template-columns:1.2fr 0.8fr; gap:24px; align-items:center; }
    @media(max-width: 920px){ .hero { grid-template-columns:1fr; } }
    .hero h1 { margin:0; font-size:clamp(28px, 5vw, 40px); letter-spacing:-0.02em; color:#0b1224; }
    .hero p { margin:10px 0 0; color: var(--muted); line-height:1.7; }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; background:#fff; border:1px solid #e4e7ec; font-weight:700; color:#0b1224; box-shadow:0 12px 26px rgba(0,0,0,0.08); }
    .cta { display:flex; gap:10px; margin-top:16px; flex-wrap:wrap; }
    .btn { padding:12px 16px; border-radius:12px; border:1px solid transparent; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 18px 44px rgba(124,58,237,0.25); transition: transform .18s ease, box-shadow .18s ease; }
    .btn:hover { transform: translateY(-1px); box-shadow:0 22px 60px rgba(67,56,202,0.35); }
    .ghost { background:transparent; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none; }

    .stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:12px; }
    .stat-card { background:#fff; border:1px solid #e4e7ec; border-radius:16px; padding:14px 16px; box-shadow:0 12px 28px rgba(0,0,0,0.06); }
    .stat-card strong { display:block; font-size:24px; color:#0b1224; }
    .stat-card span { color: var(--muted); font-size:13px; }

    .section { display:grid; gap:14px; }
    .section h2 { margin:0; font-size:24px; }
    .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:14px; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:16px; box-shadow:0 14px 32px rgba(0,0,0,0.06); display:flex; flex-direction:column; gap:8px; }
    .card h3 { margin:0; font-size:18px; }
    .muted { color: var(--muted); line-height:1.6; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'about'); ?>
  <div class="page">
    <section class="hero">
      <div>
        <div class="pill">✨ Apie Cukrinuką</div>
        <h1>Mėgstamiausi skanėstai, atrinkti su meile</h1>
        <p>Sujungiame kruopščiai atrinktus desertus, sezoninius receptus ir bendruomenės istorijas vienoje vietoje. Mūsų komanda tiki, kad gera patirtis prasideda nuo šiltos aplinkos, todėl viską kuriame ant šviesaus #f7f7fb pagrindo.</p>
        <div class="cta">
          <a class="btn" href="/products.php">Peržiūrėti produktus</a>
          <a class="btn ghost" href="/recipes.php">Įkvėpimo receptai</a>
        </div>
      </div>
      <div class="stats">
        <div class="stat-card">
          <strong>1500+</strong>
          <span>Patikrintų skanėstų</span>
        </div>
        <div class="stat-card">
          <strong>240</strong>
          <span>Lojalūs partneriai</span>
        </div>
        <div class="stat-card">
          <strong>24/7</strong>
          <span>Greita pagalba</span>
        </div>
        <div class="stat-card">
          <strong>98%</strong>
          <span>Klientų pasitenkinimas</span>
        </div>
      </div>
    </section>

    <section class="section">
      <h2>Mūsų filosofija</h2>
      <div class="cards">
        <div class="card">
          <h3>Kokybė ir skaidrumas</h3>
          <p class="muted">Kiekvienas produktas pereina kokybės patikras, o sudėtis pateikiama aiškiai, kad galėtumėte rinktis sąmoningai.</p>
        </div>
        <div class="card">
          <h3>Bendruomenės galia</h3>
          <p class="muted">Skatiname dalintis atsiliepimais, receptais ir patarimais – taip kuriame erdvę, kurioje gera sugrįžti.</p>
        </div>
        <div class="card">
          <h3>Tvarūs sprendimai</h3>
          <p class="muted">Renkamės atsakingus tiekėjus, optimizuojame pakuotes ir nuolat ieškome būdų mažinti poveikį aplinkai.</p>
        </div>
      </div>
    </section>

    <section class="section">
      <h2>Komanda ir palaikymas</h2>
      <div class="cards">
        <div class="card">
          <h3>Klientų sėkmė</h3>
          <p class="muted">Atsakome į jūsų klausimus, padedame išsirinkti ir pasirūpiname, kad užsakymai būtų pristatyti laiku.</p>
        </div>
        <div class="card">
          <h3>Kūrybinė virtuvė</h3>
          <p class="muted">Mūsų konditeriai kuria naujus desertus, testuoja receptus ir dalijasi jais su bendruomene.</p>
        </div>
        <div class="card">
          <h3>Technologijos</h3>
          <p class="muted">Modernizuojame patirtį: nuo personalizuotų rekomendacijų iki sklandaus užsakymo ir sekimo proceso.</p>
        </div>
      </div>
    </section>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
