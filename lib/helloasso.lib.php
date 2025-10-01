<?php

function helloasso_process_payload($db, $payload)
{
    $h = new HelloassoHandler($db);
    $h->log('Received payload: ' . json_encode($payload));

    // Get Event / only consider Order events.
    $event = $payload['eventType'];
    if ($event != 'Order') return true;

    $data   = $payload['data'];
    $member = new HelloassoMember($data['payer']);
    $mid    = $h->findOrMakeDolibarrMember($member);

    if ($mid == null) {
        $mid = "Can't get of create Member: " . $member->toJson();
        $h->log($mid);
    }

    $subscriptions = [];

    foreach ($data['items'] as $item)
    {
        if ($item['type'] == 'Membership')
        {
            $membership = new HelloassoMembership($item, $data['payments'][0] ?? [], $member);
            $sid = $h->createDolibarrSubscription($mid, $membership);

            if ($sid == null) {
                $sid = "Can't create Membership: ". $membership->toJson();
                $h->log($sid);
                $subscriptions[] = $sid;
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
