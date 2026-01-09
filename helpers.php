<?php

function approveOrder($pdo, $orderId)
{
    // 1. Gauname užsakymo informaciją
    $stmt = $pdo->prepare("SELECT status, customer_email, customer_name, total FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return false; // Užsakymas nerastas
    }

    // Apsauga: Jei jau apmokėta, nieko nedarome, kad nenurašytume likučio du kartus
    // (nebent norite leisti, tada pašalinkite šį patikrinimą)
    if (in_array($order['status'], ['apmokėta', 'įvykdyta', 'completed', 'paid'])) {
        return true;
    }

    // 2. Atnaujiname statusą į 'apmokėta'
    $pdo->prepare("UPDATE orders SET status = 'apmokėta' WHERE id = ?")->execute([$orderId]);

    // 3. Likučių atnaujinimas (Prekės + Variacijos)
    // Pastaba: darome prielaidą, kad order_items lentelėje turite stulpelį `variation_id`
    $itemsStmt = $pdo->prepare("SELECT product_id, variation_id, quantity FROM order_items WHERE order_id = ?");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $updateProductSql = "UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?";
    $updateVarSql = "UPDATE product_variations SET quantity = quantity - ? WHERE id = ? AND track_stock = 1 AND quantity >= ?";

    foreach ($items as $item) {
        $qty = $item['quantity'];
        $pid = $item['product_id'];
        $vid = $item['variation_id'] ?? null; // Jei variation_id stulpelis egzistuoja

        // Sumažiname pagrindinės prekės likutį
        $pdo->prepare($updateProductSql)->execute([$qty, $pid, $qty]);

        // Jei tai variacija, sumažiname ir variacijos likutį
        if ($vid) {
            $pdo->prepare($updateVarSql)->execute([$qty, $vid, $qty]);
        }
    }

    // 4. Laiškų siuntimas
    // Užtikriname, kad turime mailer funkcijas
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/mailer.php';
    }

    // Pirkėjui
    $content = "<p>Sveiki, <strong>{$order['customer_name']}</strong>,</p>
                <p>Jūsų užsakymas <strong>#{$orderId}</strong> sėkmingai apmokėtas ir patvirtintas.</p>
                <p>Bendra suma: <strong>{$order['total']} EUR</strong></p>
                <p>Informuosime jus, kai siunta bus išsiųsta.</p>";
    
    $html = getEmailTemplate('Užsakymas patvirtintas! ✅', $content, 'https://cukrinukas.lt/orders.php', 'Mano užsakymai');
    
    try {
        sendEmail($order['customer_email'], "Užsakymo patvirtinimas #{$orderId}", $html);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('Failed to send customer email for order: ' . $orderId, $e);
        }
    }

    // Adminui
    $adminContent = "<p>Gautas naujas užsakymas #{$orderId}.</p><p>Klientas: {$order['customer_name']}</p><p>Suma: {$order['total']} EUR</p>";
    $adminHtml = getEmailTemplate('Naujas užsakymas 💰', $adminContent);
    $adminEmail = getenv('ADMIN_EMAIL') ?: 'labas@cukrinukas.lt';
    
    try {
        sendEmail($adminEmail, "Naujas užsakymas #{$orderId}", $adminHtml);
    } catch (Throwable $e) {
         if (function_exists('logError')) {
            logError('Failed to send admin email for order: ' . $orderId, $e);
        }
    }

    return true;
}

function imageMimeMap(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
}

function uploadImageWithValidation(array $file, string $prefix, array &$errors, ?string $missingMessage = null, bool $collectErrors = true): ?string
{
    $hasFile = !empty($file['name']);
    if (!$hasFile) {
        if ($missingMessage !== null) {
            $errors[] = $missingMessage;
        }
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if ($collectErrors) {
            $errors[] = 'Nepavyko įkelti nuotraukos.';
        }
        return null;
    }

    $uploaded = saveUploadedFile($file, imageMimeMap(), $prefix);
    if ($uploaded !== null) {
        return $uploaded;
    }

    if ($collectErrors) {
        $errors[] = 'Leidžiami formatai: jpg, jpeg, png, webp, gif.';
    }

    return null;
}

/**
 * Paverčia tekstą į URL draugišką formatą (slug).
 * Pvz.: "Skanus pyragas!" -> "skanus-pyragas"
 */
function slugify(string $text): string
{
    // Lietuviškų raidžių žemėlapis
    $map = [
        'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z'
    ];
    
    // Pakeičiame lietuviškas raides
    $text = strtr($text, $map);
    
    // Paliekame tik raides, skaičius ir tarpus
    // (Naudojame paprastesnį regex, kad veiktų daugelyje serverių)
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    
    // Pakeičiame tarpus brūkšneliais
    $text = preg_replace('/\s+/', '-', $text);
    
    // Konvertuojame į mažąsias raides
    $text = strtolower($text);
    
    // Panaikiname brūkšnelius pradžioje ir pabaigoje
    $text = trim($text, '-');

    return $text ?: 'item';
}
