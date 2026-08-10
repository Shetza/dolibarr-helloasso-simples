<?php

declare(strict_types=1);

function helloasso_process_payload($db, $payload)
{
    $h = new HelloassoHandler($db);
    $h->log('Received payload: ' . json_encode($payload));

    if (empty($payload)) throw new Exception("Can't parse payload");

    // Get Event / only consider Order events.
    if (@$payload['eventType'] !== 'Order') return true;

    // Do not consider 0-total order
    if ((int)@$payload['data']['amount']['total'] <= 0) return true;

    $data = $payload['data'];

    // Déjà importé ?
    $exist = $h->getDolibarrInvoice((string)$data['id']);
    if (!empty($exist)) {
        return [
            'invoice' => [
                'id'     => $exist['id'],
                'ref'    => $exist['ref'],
                'amount' => $exist['total_ttc'],
            ],
        ];
    }

    $member = new HelloassoMember($data['payer']);
    $mid    = $h->findOrMakeDolibarrThirdparty($member); // Or findOrMakeDolibarrMember (may be configurable ?)

    if ($mid == null) {
        $mid = "Can't get or create Member: " . $member->toJson();
        $h->log($mid);
    }

    $items = [];

    foreach ($data['items'] as $item)
    {
        switch ($item['type'])
        {
            case 'Membership':
                $helloItem = new HelloassoMembership($item, $data['date'], $member);
                $h->updateDolibarrThirdparty($mid, $helloItem->member, $h->getDolibarrThirdparty($member));
                break;

            case 'Registration':
                $helloItem = new HelloassoRegistration($item, $data['date'], $member);
                break;

            case 'Donation':
                $helloItem = new HelloassoDonation($item, $data['date'], $member);
                break;

            default:
                $h->log("Unknown formType: '" . $item['type'] . "'");
                continue 2;
        }

        $items[] = $helloItem;
    }

    // Une seule facture pour toutes les lignes
    if (!empty($items))
    {
        $invoice = $h->createDolibarrInvoice($mid, $items, (string)$data['id']);

        if ($invoice == null) {
            $invoice = "Can't create invoice for order ". $data['id'];
            $h->log($invoice);
        }

        $invoice = $h->getDolibarrInvoice((string)$data['id']);

        return [
            'member'  => $mid,
            'items'   => $items,
            'invoice' => [
                'id'     => $invoice['id'],
                'ref'    => $invoice['ref'],
                'amount' => $invoice['total_ttc'],
            ],
        ];
    }
}

function helloasso_notify_admin_error($db, Throwable $e, array $payload = []): bool
{
    $h = new HelloassoHandler($db);
    return $h->helloasso_notify_admin_error($e, $payload);
}