<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/mailer.php';

$pdo = getPdo();
ensurePasswordResetsTable($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $email = trim($_POST['email'] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Tikriname, ar vartotojas egzistuoja
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Išsaugome tokeną
            $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)')
                ->execute([$email, $token, $expires]);

            // Paruošiame laišką
            $link = "https://nauja.apdaras.lt/reset_password.php?token=$token";
            $content = "<p>Gavome prašymą atkurti jūsų paskyros slaptažodį.</p>
                        <p>Paspauskite žemiau esantį mygtuką, kad sukurtumėte naują slaptažodį. Nuoroda galioja 1 valandą.</p>
                        <p>Jei to neprašėte, tiesiog ignoruokite šį laišką.</p>";
            
            $html = getEmailTemplate('Slaptažodžio atkūrimas 🔒', $content, $link, 'Atkurti slaptažodį');

            // Siunčiame (naudojame $html kintamąjį)
            if (sendEmail($email, 'Slaptažodžio atkūrimas', $html)) {
                $message = 'Instrukcijos išsiųstos į jūsų el. paštą.';
            } else {
                $error = 'Nepavyko išsiųsti laiško. Bandykite vėliau.';
            }
        } else {
            // Saugumo sumetimais rodome tą patį pranešimą
            $message = 'Jei toks el. paštas egzistuoja, instrukcijos išsiųstos.';
        }
    } else {
        $error = 'Neteisingas el. pašto formatas.';
    }
}
?>
<!doctype html>
<html lang="lt">
<head><title>Atkurti slaptažodį</title><?php echo headerStyles(); ?></head>
<body>
<?php renderHeader($pdo, 'login'); ?>
<div style="max-width:400px; margin:40px auto; padding:20px; background:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <h2>Pamiršote slaptažodį?</h2>
    <?php if ($message): ?><div style="color:green; margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
        <?php echo csrfField(); ?>
        <label style="display:block; margin-bottom:8px;">Įveskite el. paštą</label>
        <input type="email" name="email" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc;">
        <button type="submit" style="margin-top:12px; width:100%; padding:10px; background:#0b0b0b; color:#fff; border:none; border-radius:8px; cursor:pointer;">Siųsti</button>
    </form>
</div>
<?php renderFooter($pdo); ?>
</body>
</html>