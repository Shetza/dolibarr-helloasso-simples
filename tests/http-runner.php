#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/TestParser.php';

const GREEN="\033[32m";
const RED="\033[31m";
const CYAN="\033[36m";
const YELLOW="\033[33m";
const RESET="\033[0m";

$file = TestParser::FILEPATH;
$wanted = $argv[1] ?? null;

if ($file !== TestParser::FILEPATH) {
    class_alias(TestParser::class, '_TP');
    _TP::FILEPATH;
}

$exit = 0;

foreach (TestParser::getRequests() as $request)
{
    if ($wanted && stripos($request['title'], $wanted) === false) {
        continue;
    }

    // Test only: add timestamp on invoice id
    $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
    $payload['data']['id'] .= '_'.date('YmdHis');
    $request['body'] = json_encode($payload);

    echo PHP_EOL;
    echo CYAN."========================================================".RESET.PHP_EOL;
    echo CYAN.$request['title'].RESET.PHP_EOL;
    echo CYAN."========================================================".RESET.PHP_EOL;

    echo $request['method'].' '.$request['url'];

    $ch = curl_init($request['url']);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $request['method'],
        CURLOPT_HTTPHEADER     => $request['headers'],
        CURLOPT_POSTFIELDS     => $request['body'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
    ]);

    $start = microtime(true);

    $response = curl_exec($ch);

    if ($response === false) {
        echo RED.curl_error($ch).RESET.PHP_EOL;
        curl_close($ch);
        $exit = 1;
        continue;
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    echo PHP_EOL;
    echo ($status < 400 ? GREEN : RED)."HTTP ".$status.RESET;
    echo "   ".number_format((microtime(true)-$start)*1000, 1)." ms".PHP_EOL.PHP_EOL;

    // echo YELLOW."Response headers".RESET.PHP_EOL;
    // echo "------------------------------".PHP_EOL;
    // echo trim($headers).PHP_EOL.PHP_EOL;

    echo YELLOW."Response body".RESET.PHP_EOL;
    echo "------------------------------".PHP_EOL;

    $json = json_decode($body, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode(
            $json,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ).PHP_EOL;
    } else {
        echo trim($body).PHP_EOL;
    }

    if ($status >= 400) {
        $exit = 1;
    }

    /*
     * ========================================================
     * Vérification du montant de la commande
     * ========================================================
     *
     * HelloAsso :
     *
     * data.amount.total
     *     = somme des inscriptions
     *     + somme des options
     *
     * Exemple :
     *
     * item.amount = 2100
     * option.amount = 4000
     * ----------------
     * total = 6100
     */

    $totalItems = 0;
    $totalOptions = 0;

    foreach ($payload['data']['items'] ?? [] as $item) {

        // Montant de l'inscription
        $totalItems += (int) ($item['amount'] ?? 0);

        // Montant des options sélectionnées
        foreach ($item['options'] ?? [] as $option) {
            $totalOptions += (int) ($option['amount'] ?? 0);
        }
    }

    $expectedTotal = $totalItems + $totalOptions;

    // Total annoncé par HelloAsso dans le payload
    $helloAssoTotal = (int) ($payload['data']['amount']['total'] ?? 0);

    // Total de la facture retournée par ton webhook
    $invoiceAmount = (float) ($json['result']['invoice']['amount'] ?? 0);

    // La facture semble être exprimée en euros,
    // tandis que les montants HelloAsso sont en centimes.
    $invoiceTotal = (int) round($invoiceAmount * 100);

    echo PHP_EOL;
    echo YELLOW."Amount details".RESET.PHP_EOL;
    echo "------------------------------".PHP_EOL;
    echo "Items    : ".$totalItems." centimes".PHP_EOL;
    echo "Options  : ".$totalOptions." centimes".PHP_EOL;
    echo "Expected : ".$expectedTotal." centimes".PHP_EOL;
    echo "HA total : ".$helloAssoTotal." centimes".PHP_EOL;
    echo "Invoice  : ".$invoiceTotal." centimes".PHP_EOL;

    /*
     * Vérification 1 :
     * items + options = total HelloAsso
     */
    if ($expectedTotal === $helloAssoTotal) {
        echo GREEN."✓ HelloAsso total (items + options)".RESET.PHP_EOL;
    } else {
        echo RED."✗ HelloAsso total (items + options)".RESET.PHP_EOL;
        echo "Expected : ".$expectedTotal.PHP_EOL;
        echo "Actual   : ".$helloAssoTotal.PHP_EOL;
        $exit = 1;
    }

    /*
     * Vérification 2 :
     * items + options = montant de la facture
     */
    if ($expectedTotal === $invoiceTotal) {
        echo GREEN."✓ Invoice total".RESET.PHP_EOL;
    } else {
        echo RED."✗ Invoice total".RESET.PHP_EOL;
        echo "Expected : ".$expectedTotal.PHP_EOL;
        echo "Actual   : ".$invoiceTotal.PHP_EOL;
        $exit = 1;
    }
}

exit($exit);