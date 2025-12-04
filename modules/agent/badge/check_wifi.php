<?php
// Désactiver l'affichage des erreurs pour cette API
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// --- CONFIGURATION ---
// Préfixe IP local du Wi-Fi de l'entreprise
$COMPANY_WIFI_PREFIX = '192.168.1.'; // tous les appareils connectés auront une IP 192.168.1.X

// --- RÉCUPÉRATION DE L'IP DU CLIENT ---
function getClientIp() {
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Si plusieurs IPs, on prend la première
            if (strpos($ip, ',') !== false) {
                $parts = explode(',', $ip);
                $ip = trim($parts[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return null;
}

$clientIp = getClientIp();

// --- VÉRIFICATION DE LA PLAGE D'IP ---
$isConnected = false;
if ($clientIp) {
    $isConnected = str_starts_with($clientIp, $COMPANY_WIFI_PREFIX);
}

echo json_encode([
    'success' => true,
    'connected' => $isConnected,
    'ip' => $clientIp,
    'timestamp' => time()
]);
?>
