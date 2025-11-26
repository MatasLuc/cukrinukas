<?php
session_start();
require __DIR__ . '/../db.php';
require __DIR__ . '/WebToPay.php';

$config = require __DIR__ . '/config.php';
$pdo = getPdo();
ensureOrdersTables($pdo);

try {
    $response = WebToPay::parseCallback($_REQUEST, $config['sign_password']);
    $orderId = isset($response['orderid']) ? (int)$response['orderid'] : 0;
    $status = $response['status'] ?? '';
    $isTest = isset($response['test']) && (string)$response['test'] !== '';
    if ($orderId) {
        $paidStatuses = ['1', '2', '3', 'paid', 'completed', 'paid_ok', 'test'];
        $isPaid = in_array($status, $paidStatuses, true) || ($isTest && in_array($status, ['0', 'pending'], true));
        $newStatus = $isPaid
            ? 'apmokėta'
            : ($status === '0' || $status === 'pending' ? 'laukiama apmokėjimo' : 'atmesta');
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $orderId]);
    }
    $_SESSION['flash_success'] = 'Apmokėjimas patvirtintas. Ačiū!';
} catch (Exception $e) {
    logError('Payment confirmation failed', $e);
    $_SESSION['flash_error'] = 'Nepavyko patvirtinti mokėjimo. Bandykite dar kartą arba susisiekite su mumis.';
}

header('Location: /orders.php');
exit;
