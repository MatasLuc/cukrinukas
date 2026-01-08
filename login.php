<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);

// Bandome prisijungti automatiškai (jei yra slapukas)
tryAutoLogin($pdo);

// Jei jau prisijungęs, nukreipiame
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$errors = [];
$message = '';

if (isset($_POST['logout'])) {
    // Išvalome slapuką ir DB įrašą atsijungiant
    clearRememberMe($pdo);
    
    session_unset();
    session_destroy();
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Įveskite el. pašto adresą ir slaptažodį.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_admin FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Neteisingi prisijungimo duomenys.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['is_admin'] = (int) $user['is_admin'];
                
                // Jei pažymėta "Prisiminti mane", nustatome slapuką
                if (!empty($_POST['remember'])) {
                    setRememberMe($pdo, $user['id']);
                }
                
                header('Location: /');
                exit;
            }
        } catch (Throwable $e) {
            logError('Login failed', $e);
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
  <title>Prisijungimas | Cukrinukas.lt</title>
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

    .auth-links { margin-top: 24px; display: flex; justify-content: space-between; font-size: 14px; }
    .auth-links a { color: var(--text-muted); font-weight: 500; text-decoration: none; transition: color .2s; }
    .auth-links a:hover { color: var(--accent); }
    .link-primary { color: var(--accent) !important; font-weight: 600 !important; }

    .notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; display: flex; gap: 10px; line-height: 1.4; }
    .notice.error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }
    .notice.success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }

    @media (max-width: 800px) {
        .auth-container { grid-template-columns: 1fr; }
        .auth-info { padding: 32px; display: none; }
        .auth-form-box { padding: 32px 24px; }
    }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'login'); ?>
  
  <div class="auth-wrapper">
    <div class="auth-container">
        <div class="auth-info">
            <h1>Sveiki sugrįžę!</h1>
            <p>Prisijunkite prie savo paskyros ir tęskite apsipirkimą, valdykite užsakymus bei matykite išsaugotus produktus.</p>
            
            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-icon">✓</div>
                    <span>Greitas užsakymų valdymas</span>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">✓</div>
                    <span>Išsaugoti receptai ir prekės</span>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">✓</div>
                    <span>Personalizuoti pasiūlymai</span>
                </li>
            </ul>
        </div>

        <div class="auth-form-box">
            <div class="auth-header">
                <h2>Prisijungimas</h2>
                <p>Įveskite savo prisijungimo duomenis</p>
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
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="email">El. paštas</label>
                    <input class="form-input" id="email" name="email" type="email" placeholder="pvz. vardas@pastas.lt" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Slaptažodis</label>
                    <input class="form-input" id="password" name="password" type="password" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-bottom: 24px;">
                    <input type="checkbox" id="remember" name="remember" value="1" style="width: 16px; height: 16px; cursor: pointer;">
                    <label for="remember" style="margin: 0; cursor: pointer; color: var(--text-muted); font-weight: 500;">Prisiminti mane</label>
                </div>

                <button type="submit" class="btn-submit">Prisijungti</button>
            </form>

            <div class="auth-links">
                <a href="/forgot_password.php">Pamiršote slaptažodį?</a>
                <span>Neturite paskyros? <a href="/register.php" class="link-primary">Registruokitės</a></span>
            </div>
        </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
