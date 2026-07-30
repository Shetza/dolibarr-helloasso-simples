#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/TestParser.php';

const GREEN="\033[32m";
const RED="\033[31m";
const CYAN="\033[36m";
const YELLOW="\033[33m";
const RESET="\033[0m";

$file = $argv[1] ?? TestParser::FILEPATH;
$wanted = $argv[2] ?? null;

if ($file !== TestParser::FILEPATH) {
    class_alias(TestParser::class, '_TP');
    _TP::FILEPATH;
}

$exit = 0;

foreach (TestParser::getRequests() as $request) {

    if ($wanted && stripos($request['title'], $wanted) === false) {
        continue;
    }

    // test only : add timestamp on invoice id
    $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
    $payload['data']['id'] .= '_'.date('YmdHis');
    $request['body'] = json_encode($payload);

    echo PHP_EOL;
    echo CYAN."========================================================".RESET.PHP_EOL;
    echo CYAN.$request['title'].RESET.PHP_EOL;
    echo CYAN."========================================================".RESET.PHP_EOL;

    echo $request['method'].' '.$request['url'].PHP_EOL;

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
    echo "   ".number_format((microtime(true)-$start)*1000,1)." ms".PHP_EOL.PHP_EOL;

    echo YELLOW."Response headers".RESET.PHP_EOL;
    echo "------------------------------".PHP_EOL;
    echo trim($headers).PHP_EOL.PHP_EOL;

    echo YELLOW."Response body".RESET.PHP_EOL;
    echo "------------------------------".PHP_EOL;

    $json = json_decode($body, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    } else {
        echo trim($body).PHP_EOL;
    }

    if ($status >= 400) {
        $exit = 1;
    }

    $totalInvoice = $json['result']['invoice']['amount'];
    $totalItems = array_sum(array_column(
        $payload['data']['items'],
        'amount'
    ));

    if ((int)$totalItems === ((int)$totalInvoice*100)) {
        echo GREEN."✓ Invoice total".RESET.PHP_EOL;
    } else {
        echo RED."✗ Invoice total".RESET.PHP_EOL;
        echo "Expected : $totalItems".PHP_EOL;
        echo "Actual   : ".$totalInvoice.PHP_EOL;
    }
}

exit($exit);