<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Patikriname tokeną
$stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$token]);
$resetRequest = $stmt->fetch();

if (!$resetRequest) {
    die('Netinkama arba pasibaigusi nuoroda. <a href="/forgot_password.php">Bandykite dar kartą</a>.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if (strlen($pass1) < 6) {
        $error = 'Slaptažodis per trumpas.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Slaptažodžiai nesutampa.';
    } else {
        // Atnaujiname vartotoją
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$hash, $resetRequest['email']]);
        
        // Ištriname panaudotą tokeną
        $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$resetRequest['email']]);
        
        $success = 'Slaptažodis pakeistas! <a href="/login.php">Prisijungti</a>';
    }
}
?>
<!doctype html>
<html lang="lt">
<head><title>Naujas slaptažodis</title><?php echo headerStyles(); ?></head>
<body>
<?php renderHeader($pdo, 'login'); ?>
<div style="max-width:400px; margin:40px auto; padding:20px; background:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <h2>Naujas slaptažodis</h2>
    <?php if ($success): ?><div style="color:green; margin-bottom:10px;"><?php echo $success; ?></div>
    <?php else: ?>
        <?php if ($error): ?><div style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post">
            <?php echo csrfField(); ?>
            <label style="display:block; margin-bottom:8px;">Naujas slaptažodis</label>
            <input type="password" name="pass1" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; margin-bottom:10px;">
            <label style="display:block; margin-bottom:8px;">Pakartokite slaptažodį</label>
            <input type="password" name="pass2" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc;">
            <button type="submit" style="margin-top:12px; width:100%; padding:10px; background:#0b0b0b; color:#fff; border:none; border-radius:8px; cursor:pointer;">Išsaugoti</button>
        </form>
    <?php endif; ?>
</div>
<?php renderFooter($pdo); ?>
</body>
</html>