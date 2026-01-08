<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureUsersTable($pdo);
ensureAdminAccount($pdo);
tryAutoLogin($pdo);

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id, name, email, profile_photo, birthdate, gender, city, country FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $birthdate = $_POST['birthdate'] !== '' ? $_POST['birthdate'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $profilePhoto = $user['profile_photo'] ?? null;

    if (!empty($_FILES['profile_photo']['name'])) {
        $allowedMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $uploaded = saveUploadedFile($_FILES['profile_photo'], $allowedMimeMap, 'profile_');
        if ($uploaded) {
            $profilePhoto = $uploaded;
        }
    }

    if ($name === '' || $email === '') {
        $errors[] = 'Įveskite vardą ir el. paštą.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET name = ?, email = ?, birthdate = ?, gender = ?, city = ?, country = ?, profile_photo = ? WHERE id = ?')
            ->execute([$name, $email, $birthdate, $gender ?: null, $city ?: null, $country ?: null, $profilePhoto, $userId]);
        $_SESSION['user_name'] = $name;
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
        }
        $success = 'Paskyra atnaujinta sėkmingai';
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Paskyra | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text-main: #0f172a;
      --text-muted: #475467;
      --accent: #2563eb;
      --accent-hover: #1d4ed8;
      --focus-ring: rgba(37, 99, 235, 0.2);
    }
    * { box-sizing:border-box; }
    body { margin:0; background: var(--bg); color: var(--text-main); font-family:'Inter', sans-serif; }
    a { color:inherit; text-decoration:none; }
    
    /* Pakeistas plotis į 1200px ir tarpai pagal news.php */
    .page { max-width: 1200px; margin:0 auto; padding:32px 20px 72px; display:grid; gap:28px; }

    /* Hero Section - Matching Login/Register Left Side Style */
    .hero { 
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border:1px solid #dbeafe; 
        border-radius:24px; 
        padding:32px; 
        display:flex; 
        align-items:center; 
        justify-content:space-between; 
        gap:24px; 
        flex-wrap:wrap; 
    }
    .hero h1 { margin:0 0 8px; font-size:28px; color:#1e3a8a; letter-spacing:-0.5px; }
    .hero p { margin:0; color:#1e40af; line-height:1.5; max-width:520px; font-size:15px; }
    
    .pill { 
        display:inline-flex; align-items:center; gap:8px; 
        padding:6px 12px; border-radius:999px; 
        background:#fff; border:1px solid #bfdbfe; 
        font-weight:600; font-size:13px; color:#1e40af; 
        margin-bottom: 12px;
    }
    
    .stat-card { 
        background:#fff; border:1px solid rgba(255,255,255,0.6); 
        padding:16px 20px; border-radius:16px; 
        min-width:160px; text-align:right;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
    }
    .stat-card strong { display:block; font-size:20px; color:#1e3a8a; margin-bottom: 4px; }
    .stat-card span { color: #64748b; font-size:13px; font-weight: 500; }

    /* Layout */
    .layout { display:grid; grid-template-columns: 1fr 320px; gap:24px; align-items:start; }
    @media(max-width: 850px){ .layout { grid-template-columns:1fr; } }

    /* Cards */
    .card { 
        background:var(--card); 
        border:1px solid var(--border); 
        border-radius:20px; 
        padding:32px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .card h2 { margin:0 0 8px; font-size:20px; color: var(--text-main); }
    .card-desc { margin:0 0 24px; color: var(--text-muted); font-size:14px; line-height: 1.5; }

    /* Form Elements - Matching Login.php */
    label { display:block; margin:0 0 6px; font-weight:600; font-size:14px; color:#344054; }
    
    .form-control { 
        width:100%; padding:12px 14px; 
        border-radius:10px; border:1px solid var(--border); 
        background:#fff; font-family:inherit; font-size:15px; color: var(--text-main);
        transition: all .2s;
    }
    .form-control:focus { outline:none; border-color:var(--accent); box-shadow: 0 0 0 4px var(--focus-ring); }
    
    .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .form-group { margin-bottom: 20px; }
    
    button.btn-primary { 
        padding:12px 16px; border-radius:10px; border:none; 
        background: #0f172a; color:#fff; font-weight:600; font-size:15px;
        cursor:pointer; width:100%; 
        transition: all .2s;
        display: flex; align-items: center; justify-content: center;
    }
    button.btn-primary:hover { background: #1e293b; transform: translateY(-1px); }

    /* Messages */
    .notice { padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; display:flex; gap:10px; align-items:flex-start; line-height:1.4; }
    .error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }
    .success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }

    /* Profile Photo */
    .profile-row { display:flex; align-items:center; gap:20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
    .avatar { 
        width:80px; height:80px; border-radius:20px; 
        background:#eff6ff; border:1px solid #dbeafe; 
        display:flex; align-items:center; justify-content:center; 
        font-weight:700; font-size: 24px; color:var(--accent); 
        overflow:hidden; flex-shrink: 0;
    }
    .avatar img { width:100%; height:100%; object-fit:cover; }
    .file-input-wrapper { flex: 1; }
    input[type="file"] { font-size: 14px; color: var(--text-muted); }
    input[type="file"]::file-selector-button {
        margin-right: 12px;
        padding: 8px 12px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid var(--border);
        cursor: pointer;
        font-weight: 500;
        font-family: inherit;
        transition: all .2s;
    }
    input[type="file"]::file-selector-button:hover { background: #f8fafc; border-color: #cbd5e1; }

    /* Recommendations List */
    .rec-list { list-style:none; padding:0; margin:0; }
    .rec-list li { 
        position: relative; padding-left: 24px; margin-bottom: 12px; 
        font-size: 14px; color: var(--text-muted); line-height: 1.5; 
    }
    .rec-list li::before {
        content: "✓"; position: absolute; left: 0; top: 2px;
        color: var(--accent); font-weight: bold;
    }
    
    @media(max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; gap:0; }
        .hero { padding: 24px; }
        .card { padding: 24px; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'account'); ?>
  
  <div class="page">
    <section class="hero">
      <div>
        <div class="pill">👤 Paskyros nustatymai</div>
        <h1>Jūsų profilis</h1>
        <p>Atnaujinkite savo asmeninę informaciją, valdykite pristatymo adresus ir keiskite prisijungimo duomenis.</p>
      </div>
      <div class="stat-card">
        <strong><?php echo htmlspecialchars($user['name'] ?? 'Vartotojas'); ?></strong>
        <span>Prisijungęs vartotojas</span>
      </div>
    </section>

    <div class="layout">
      <div class="card">
        <h2>Profilio duomenys</h2>
        <p class="card-desc">Redaguokite informaciją, kurią mato kiti bendruomenės nariai bei kuri naudojama užsakymams.</p>
        
        <?php foreach ($errors as $err): ?>
            <div class="notice error">
                <svg style="width:20px;height:20px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?php echo htmlspecialchars($err); ?></span>
            </div>
        <?php endforeach; ?>
        
        <?php if ($success): ?>
            <div class="notice success">
                <svg style="width:20px;height:20px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <?php echo csrfField(); ?>
          
          <div class="profile-row">
            <div class="avatar">
              <?php if (!empty($user['profile_photo'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profilis">
              <?php else: ?>
                <?php echo strtoupper(mb_substr($user['name'] ?? 'V', 0, 1)); ?>
              <?php endif; ?>
            </div>
            <div class="file-input-wrapper">
              <label for="profile_photo">Keisti nuotrauką</label>
              <input id="profile_photo" name="profile_photo" type="file" accept="image/png, image/jpeg, image/webp">
              <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Rekomenduojama: PNG, JPG iki 5MB.</div>
            </div>
          </div>

          <div class="form-group">
              <label for="name">Vardas</label>
              <input class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
          </div>

          <div class="form-group">
              <label for="email">El. paštas</label>
              <input class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" type="email" required>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label for="birthdate">Gimimo data</label>
              <input class="form-control" id="birthdate" name="birthdate" type="date" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="gender">Lytis</label>
              <select class="form-control" id="gender" name="gender">
                <option value="">Nepasirinkta</option>
                <?php foreach (['moteris' => 'Moteris','vyras' => 'Vyras','kita' => 'Kita'] as $val => $label): ?>
                  <option value="<?php echo $val; ?>" <?php echo ($user['gender'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label for="city">Miestas</label>
              <input class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="Pvz. Vilnius">
            </div>
            <div class="form-group">
              <label for="country">Šalis</label>
              <input class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" placeholder="Pvz. Lietuva">
            </div>
          </div>

          <div class="form-group" style="margin-top:10px; padding-top:20px; border-top:1px solid var(--border);">
            <label for="password">Keisti slaptažodį</label>
            <input class="form-control" id="password" name="password" type="password" placeholder="Naujas slaptažodis (palikite tuščią, jei nekeičiate)">
          </div>

          <button type="submit" class="btn-primary">Išsaugoti pakeitimus</button>
        </form>
      </div>

      <div class="card" style="background: #f8fafc; border: 1px solid #e2e8f0;">
        <h2>Saugumo patarimai</h2>
        <p class="card-desc">Keletas patarimų, kaip apsaugoti savo paskyrą.</p>
        <ul class="rec-list">
          <li>Naudokite unikalų slaptažodį, sudarytą iš raidžių ir skaičių.</li>
          <li>Periodiškai atnaujinkite savo el. paštą, kad gautumėte pranešimus apie užsakymus.</li>
          <li>Įkelkite tikrą profilio nuotrauką bendruomenės pasitikėjimui didinti.</li>
        </ul>
        <div style="margin-top:24px; padding-top:20px; border-top:1px solid #e2e8f0;">
             <a href="/orders.php" style="display:block; font-weight:600; font-size:14px; margin-bottom:12px; color:var(--text-main);">📦 Mano užsakymai →</a>
             <a href="/saved.php" style="display:block; font-weight:600; font-size:14px; color:var(--text-main);">❤️ Išsaugoti produktai →</a>
        </div>
      </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
