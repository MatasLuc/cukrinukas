<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/WebToPay.php';
require_once __DIR__ . '/../mailer.php'; // Pataisytas kelias, nes callback.php yra sub-aplanke
require_once __DIR__ . '/../env.php'; // Jei reikia

$pdo = getPdo();
ensureOrdersTables($pdo);
$config = require __DIR__ . '/config.php';

try {
    $response = WebToPay::parseCallback($_REQUEST, $config['sign_password']);
    $orderId = isset($response['orderid']) ? (int)$response['orderid'] : 0;
    $status = $response['status'] ?? '';
    $isTest = isset($response['test']) && (string)$response['test'] !== '';
    if ($orderId) {
        $paidStatuses = ['1', '2', '3', 'paid', 'completed', 'paid_ok', 'test'];
        $isPaid = in_array($status, $paidStatuses, true) || ($isTest && in_array($status, ['0', 'pending'], true));
        if ($isPaid) 
        {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute(['apmokėta', $orderId]);
            $oStmt = $pdo->prepare('SELECT customer_email, customer_name, total FROM orders WHERE id = ?');
            $oStmt->execute([$orderId]);
            $orderInfo = $oStmt->fetch();
            
            // Nauja logikos dalis: Kiekio atnaujinimas
            $itemsStmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $itemsStmt->execute([$orderId]);
            
            $updateProductStmt = $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?');
            
            foreach ($itemsStmt->fetchAll() as $item) {
                // Sumažiname produkto likutį
                $updateProductStmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            }
            // Pabaiga: Kiekio atnaujinimas

            if ($orderInfo) {
                // Pirkėjui
                $content = "<p>Sveiki, <strong>{$orderInfo['customer_name']}</strong>,</p>
                            <p>Jūsų užsakymas <strong>#{$orderId}</strong> sėkmingai apmokėtas ir priimtas vykdyti.</p>
                            <p>Bendra suma: <strong>{$orderInfo['total']} EUR</strong></p>
                            <p>Informuosime jus, kai siunta bus išsiųsta.</p>";
                
                // Galime pridėti nuorodą į užsakymų istoriją (jei vartotojas prisijungęs)
                $html = getEmailTemplate('Užsakymas patvirtintas! ✅', $content, 'https://cukrinukas.lt/orders.php', 'Mano užsakymai');
                // Adresas gali būti neprieinamas be pilno domeno, todėl palieku klaidų registravimą
                try {
                    sendEmail($orderInfo['customer_email'], "Užsakymo patvirtinimas #{$orderId}", $html);
                } catch (Throwable $e) {
                    logError('Failed to send customer email on successful payment for order: ' . $orderId, $e);
                }
                
                // Adminui (galima palikti paprastesnį arba irgi gražų)
                $adminContent = "<p>Gautas naujas užsakymas #{$orderId}.</p><p>Klientas: {$orderInfo['customer_name']}</p><p>Suma: {$orderInfo['total']} EUR</p>";
                $adminHtml = getEmailTemplate('Naujas užsakymas 💰', $adminContent);
                // Admino el. pašto adresą reikėtų paimti iš konfigūracijos (jei neįvestas) arba tiesiogiai įrašyti
                $adminEmail = getenv('ADMIN_EMAIL') ?: 'labas@cukrinukas.lt'; 
                try {
                    sendEmail($adminEmail, "Naujas užsakymas #{$orderId}", $adminHtml);
                } catch (Throwable $e) {
                    logError('Failed to send admin email on successful payment for order: ' . $orderId, $e);
                }
            }
        } 
        elseif ($status === '0' || $status === 'pending') 
        {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute(['laukiama apmokėjimo', $orderId]);
        } 
        else 
        {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute(['atmesta', $orderId]);
        }
    }
    echo 'OK';
} catch (Exception $e) {
    http_response_code(400);
    logError('Paysera callback validation failed', $e);
    echo 'ERROR';
}
