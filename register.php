<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/mailer.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'Užpildykite visus laukus.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Slaptažodis turi būti bent 6 simbolių.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Toks el. paštas jau registruotas.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
                $insert->execute([$name, $email, $hash]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                $_SESSION['is_admin'] = 0;
                $content = "<p>Dėkojame, kad prisiregistravote. Dabar galite prisijungti, išsisaugoti mėgstamus receptus ir greičiau apsipirkti.</p>";
                $html = getEmailTemplate('Sveiki atvykę į bendruomenę! 👋', $content, 'https://nauja.apdaras.lt/login.php', 'Prisijungti');
                sendEmail($email, 'Sveiki atvykę į Cukrinuką!', $html);
                header('Location: /');
                exit;
            }
        } catch (Throwable $e) {
            logError('Registration failed', $e);
            $errors[] = friendlyErrorMessage();
        }
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registracija | E-kolekcija</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --surface: #ffffff;
      --ink: #0f172a;
      --muted: #5b5f6a;
      --accent: #6f4ef2;
      --accent-2: #2f9aff;
      --border: #e4e6f0;
    }
    * { box-sizing: border-box; }
    body { background: var(--bg); color: var(--ink); }
    .wrapper { min-height: 100vh; display: grid; grid-template-columns: 1.05fr 1fr; align-items: center; gap: 24px; padding: 32px 24px; max-width: 1100px; margin: 0 auto; }
    .hero { background: linear-gradient(135deg, rgba(111,78,242,0.26), rgba(47,154,255,0.18)); border-radius: 28px; padding: 32px; border: 1px solid rgba(255,255,255,0.5); box-shadow: 0 18px 48px rgba(15,23,42,0.12); backdrop-filter: blur(5px); position: relative; overflow: hidden; }
    .hero::after { content: ""; position: absolute; left: -60px; bottom: -60px; width: 220px; height: 220px; background: radial-gradient(circle at center, rgba(255,255,255,0.6), transparent 60%); filter: blur(18px); }
    .hero h1 { margin: 0 0 10px; font-size: 32px; letter-spacing: -0.4px; }
    .hero p { margin: 0 0 16px; color: #161e33; max-width: 520px; }
    .pill-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,0.85); color: #111827; font-weight: 700; border: 1px solid rgba(255,255,255,0.6); }
    .hero-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 18px; }
    .hero-card { background: rgba(255,255,255,0.9); border-radius: 16px; padding: 14px; border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 14px 30px rgba(17,24,39,0.12); }
    .hero-card strong { display: block; font-size: 22px; margin-bottom: 4px; }
    .hero-card span { color: var(--muted); font-size: 14px; }
    .card { background: var(--surface); padding: 30px; border-radius: 20px; box-shadow: 0 16px 38px rgba(15,23,42,0.08); border: 1px solid var(--border); width: min(460px, 100%); margin-left: auto; }
    .card h2 { margin: 0 0 8px; font-size: 26px; letter-spacing: -0.2px; }
    .card p { margin: 0 0 18px; color: var(--muted); }
    label { display: block; margin-bottom: 6px; font-weight: 700; color: #121826; }
    input { width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: #fbfbff; font-size: 15px; transition: all .2s ease; }
    input:focus { outline: 2px solid rgba(111,78,242,0.3); box-shadow: 0 8px 20px rgba(111,78,242,0.12); }
    button { width: 100%; padding: 14px; border-radius: 14px; border: none; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; font-weight: 700; cursor: pointer; margin-top: 12px; box-shadow: 0 16px 40px rgba(47,154,255,0.25); }
    .link-row { display: flex; justify-content: space-between; margin-top: 14px; font-size: 14px; }
    .notice { padding: 14px; border-radius: 14px; margin-bottom: 12px; border: 1px solid; }
    .notice.error { background: #fff1f1; border: 1px solid #f3b7b7; color: #991b1b; box-shadow: 0 10px 24px rgba(244, 63, 94, 0.12); }
    .notice.success { background: #edf9f0; border: 1px solid #b8e2c4; color: #0f5132; box-shadow: 0 10px 24px rgba(16, 185, 129, 0.12); }
    .brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; color: var(--ink); text-decoration: none; margin-bottom: 16px; font-size: 21px; letter-spacing: -0.2px; }
    @media (max-width: 900px) { .wrapper { grid-template-columns: 1fr; } .card { margin: 0 auto; } }

  </style>
</head>
<body>
  <?php renderHeader($pdo, 'register'); ?>
  <div class="wrapper">
    <div class="hero">
      <h1>Kurkite paskyrą ir saugokite savo kolekciją</h1>
      <p>Greitesnis atsiskaitymas, personalizuotos rekomendacijos ir išsaugotos receptų/produktų kolekcijos vienoje vietoje.</p>
      <div class="pill-row">
        <span class="pill">✨ Moderni, intuityvi sąsaja</span>
        <span class="pill">🛡️ AES šifravimas</span>
        <span class="pill">🎁 Premijos naujiems nariams</span>
      </div>
      <div class="hero-grid">
        <div class="hero-card"><strong>2 min.</strong><span>Vidutinis registracijos laikas</span></div>
        <div class="hero-card"><strong>0€</strong><span>Narystė be mokesčių</span></div>
        <div class="hero-card"><strong>VIP</strong><span>Prieiga prie išankstinių pasiūlymų</span></div>
      </div>
    </div>

    <div class="card">
      <a class="brand" href="/">Cukrinukas.lt</a>
      <h2>Registracija</h2>
      <p>Sukurkite paskyrą, kad galėtumėte stebėti užsakymus ir greičiau pirkti.</p>

      <?php if ($errors): ?>
        <div class="notice error">
          <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="notice success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <form method="post">
        <?php echo csrfField(); ?>
        <label for="name">Vardas</label>
        <input id="name" name="name" type="text" required>

        <label for="email">El. paštas</label>
        <input id="email" name="email" type="email" required autocomplete="email">

        <label for="password">Slaptažodis</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" minlength="6">

        <button type="submit">Registruotis</button>
      </form>

      <div class="link-row">
        <a href="/login.php">Jau turite paskyrą? Prisijunkite</a>
        <a href="/">↩ Pagrindinis</a>
      </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
