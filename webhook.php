<?php

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Mode normal : webhook public
// -----------------------------------------------------------------------------

define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require '../../main.inc.php';

require_once __DIR__.'/class/helloasso.class.php';
require_once __DIR__.'/class/HelloassoItem.php';
require_once __DIR__.'/class/HelloassoMember.php';
require_once __DIR__.'/class/HelloassoMembership.php';
require_once __DIR__.'/class/HelloassoDonation.php';
require_once __DIR__.'/class/HelloassoRegistration.php';
require_once __DIR__.'/class/HelloassoOption.php';
require_once __DIR__.'/lib/helloasso.lib.php';

header('Content-Type: application/json');

if (!empty($_POST['test'])) {
    // -----------------------------------------------------------------------------
    // Mode TEST depuis l'interface d'administration
    // -----------------------------------------------------------------------------

    require_once __DIR__.'/tests/TestParser.php';
    $scenario = GETPOST('test', 'alphanohtml');

    $payload = TestParser::getScenario($scenario);

    // test only : add timestamp on invoice id
    $payload['data']['id'] .= '_'.date('YmdHis');

} else {
    // -------------------------------------------------------------------------
    // Vrai webhook HelloAsso
    // -------------------------------------------------------------------------

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    $secret = $conf->global->HELLOASSO_WEBHOOK_SECRET ?? '';

    if (!empty($secret)) {

        $signature = $_SERVER['HTTP_X_HELLOASSO_SIGNATURE'] ?? '';

        $computed = base64_encode(
            hash_hmac(
                'sha256',
                $raw,
                $secret,
                true
            )
        );

        if (!hash_equals($computed, $signature)) {

            http_response_code(401);

            echo json_encode([
                'status' => 'ERROR',
                'result' => 'Invalid signature'
            ]);

            exit;
        }
    }
}

// -----------------------------------------------------------------------------
// Traitement commun
// -----------------------------------------------------------------------------
try {
    $result = helloasso_process_payload($db, $payload);
    echo json_encode([
        'status' => 'OK',
        'result' => $result
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    helloasso_notify_admin_error($db, $e, $payload);

    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'result' => $e->getMessage(),
        'trace'  => $conf->global->MAIN_FEATURES_LEVEL > 0
            ? explode("\n", $e->getTraceAsString())
            : null
    ], JSON_PRETTY_PRINT);
}
