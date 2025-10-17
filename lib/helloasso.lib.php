<?php

function helloasso_process_payload($db, $payload)
{
    $h = new HelloassoHandler($db);
    $h->log('Received payload: ' . json_encode($payload));

    if (empty($payload)) throw new Exception("Can't parse payload");

    // Get Event / only consider Order events.
    $event = @$payload['eventType'];
    if ($event != 'Order') return true;

    $data   = $payload['data'];
    $member = new HelloassoMember($data['payer']);
    $mid    = $h->findOrMakeDolibarrThirdparty($member); // Or findOrMakeDolibarrMember (may be configurable ?)

    if ($mid == null) {
        $mid = "Can't get or create Member: " . $member->toJson();
        $h->log($mid);
    }

    $subscriptions = [];

    foreach ($data['items'] as $item)
    {
        if ($item['type'] == 'Membership')
        {
            $exist = $h->getDolibarrInvoice($item['id']);
            if (!empty($exist)) continue;

            $membership = new HelloassoMembership($item, $data['payments'][0] ?? [], $member);
            $sid = $h->createDolibarrInvoice($mid, $membership); // Or createDolibarrSubscription (may be configurable ?)

            if ($sid != null) {
                $h->updateDolibarrThirdparty($mid, $membership->member, $h->getDolibarrThirdparty($member)); // As new HelloassoMembership may update members attributes
            } else {
                $sid = "Can't create Membership: ". $membership->toJson();
                $h->log($sid);
            }

            $subscriptions[] = $sid;
        } else {
            $h->log("Unknown formType: '". $item['type'] ."'");
        }
    }

    return [
        'member' => $mid,
        'subscriptions' => $subscriptions,
    ];
}
