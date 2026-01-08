<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/mailer.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);
tryAutoLogin($pdo);

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
  <title>Registracija | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --surface: #ffffff;
      --border: #e4e7ec;
      --input-bg: #ffffff;
      --text-main: #0f172a;
      --text-muted: #475467;
      --accent: #2563eb;
      --focus-ring: rgba(37, 99, 235, 0.2);
    }
    body { background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; }
    
    .auth-wrapper {
        min-height: calc(100vh - 160px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    
    .auth-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        max-width: 1000px;
        width: 100%;
        background: var(--surface);
        border-radius: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    /* Left Side - Hero/Info (Identical style to Login) */
    .auth-info {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        padding: 48px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border-right: 1px solid var(--border);
    }
    .auth-info h1 { margin: 0 0 16px; font-size: 32px; color: #1e3a8a; letter-spacing: -0.5px; }
    .auth-info p { margin: 0 0 32px; color: #1e40af; line-height: 1.6; font-size: 16px; }
    
    .feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 16px; }
    .feature-item { display: flex; align-items: center; gap: 12px; color: #1e3a8a; font-weight: 500; }
    .feature-icon { 
        width: 24px; height: 24px; 
        background: #2563eb; color: #fff; 
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 14px; flex-shrink: 0;
    }

    /* Right Side - Form */
    .auth-form-box { padding: 48px; }
    .auth-header { margin-bottom: 32px; }
    .auth-header h2 { margin: 0 0 8px; font-size: 24px; color: var(--text-main); }
    .auth-header p { margin: 0; color: var(--text-muted); font-size: 14px; }

    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #344054; }
    .form-input { 
        width: 100%; padding: 12px 14px; 
        border: 1px solid var(--border); border-radius: 10px; 
        background: var(--input-bg); color: var(--text-main);
        font-size: 15px; transition: all .2s;
    }
    .form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--focus-ring); }
    
    .btn-submit {
        width: 100%; padding: 12px;
        border-radius: 10px; border: none;
        background: #0f172a; color: #fff;
        font-weight: 600; font-size: 15px;
        cursor: pointer; transition: all .2s;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-submit:hover { background: #1e293b; transform: translateY(-1px); }

    .auth-links { margin-top: 24px; display: flex; justify-content: center; font-size: 14px; color: var(--text-muted); }
    .auth-links a { color: var(--accent); font-weight: 600; text-decoration: none; margin-left: 6px; transition: color .2s; }
    .auth-links a:hover { color: #1d4ed8; }

    /* Messages */
    .notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; display: flex; gap: 10px; line-height: 1.4; }
    .notice.error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }
    .notice.success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }

    @media (max-width: 800px) {
        .auth-container { grid-template-columns: 1fr; }
        .auth-info { display: none; }
        .auth-form-box { padding: 32px 24px; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'register'); ?>

  <div class="auth-wrapper">
    <div class="auth-container">
        <div class="auth-info">
            <h1>Kurkite paskyrą</h1>
            <p>Tapkite bendruomenės dalimi ir mėgaukitės patogesniu apsipirkimu, receptų išsaugojimu bei specialiais pasiūlymais.</p>
            
            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-icon">✨</div>
                    <span>Nemokama narystė</span>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">🚚</div>
                    <span>Greitesnis atsiskaitymas</span>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">🎁</div>
                    <span>Kaupiamoji nuolaidų sistema</span>
                </li>
            </ul>
        </div>

        <div class="auth-form-box">
            <div class="auth-header">
                <h2>Registracija</h2>
                <p>Užpildykite duomenis naujai paskyrai</p>
            </div>

            <?php if ($errors): ?>
                <div class="notice error">
                    <svg style="width:20px;height:20px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="notice success">
                    <svg style="width:20px;height:20px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="name">Vardas</label>
                    <input class="form-input" id="name" name="name" type="text" placeholder="Jūsų vardas" required>
                </div>

                <div class="form-group">
                    <label for="email">El. paštas</label>
                    <input class="form-input" id="email" name="email" type="email" placeholder="vardas@pastas.lt" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Slaptažodis</label>
                    <input class="form-input" id="password" name="password" type="password" placeholder="Mažiausiai 6 simboliai" required autocomplete="new-password" minlength="6">
                </div>

                <button type="submit" class="btn-submit">Registruotis</button>
            </form>

            <div class="auth-links">
                Jau turite paskyrą? <a href="/login.php">Prisijunkite</a>
            </div>
        </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
