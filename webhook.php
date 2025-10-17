<?php

// On définit NOLOGIN avant d’inclure Dolibarr pour ne pas exiger d’authentification
define('NOLOGIN', 1);       // Pas besoin d’être connecté
define('NOCSRFCHECK', 1);   // Les webhooks externes n’ont pas de token CSRF
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require '../../main.inc.php'; // chemin depuis ton dossier custom/helloasso/

// Charge ton code de traitement
require_once __DIR__ . '/class/helloasso.class.php';
require_once __DIR__ . '/class/HelloassoMember.php';
require_once __DIR__ . '/class/HelloassoMembership.php';
require_once __DIR__ . '/lib/helloasso.lib.php';

if (!empty($_POST['test'])) {
    dol_syslog("HelloAsso Test Webhook triggered");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'OK', 'result' => "Webhook test received successfully."]);
    exit;
}

// Récupération du payload brut
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

// Vérif HMAC (optionnelle si configurée)
$secret = $conf->global->HELLOASSO_WEBHOOK_SECRET ?? '';
if (!empty($secret)) {
    $signature = $_SERVER['HTTP_X_HELLOASSO_SIGNATURE'] ?? '';
    $computed = base64_encode(hash_hmac('sha256', $raw, $secret, true));
    if ($signature !== $computed) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

$status = '';

// Traitement du webhook
try {
    $result = helloasso_process_payload($db, $payload);
    $status = 'OK';
} catch(Exception $e) {
    $result = $e->getMessage();
    $status = 'ERROR';
}

// Réponse
header('Content-Type: application/json');
echo json_encode(['status' => $status, 'result' => $result]);