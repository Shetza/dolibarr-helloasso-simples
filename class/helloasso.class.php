<?php

class HelloassoHandler
{
    private $db;
    private $apiKey;
    private $apiUrl = DOL_MAIN_URL_ROOT .'/api/index.php/'; // @see $dolibarr_main_url_root
    private $uid    = 1; // @TODO Put this in config (Super Admin Id)
    private $bid    = 1; // @TODO Put this in config (Bank Account Id)

    public function __construct($db)
    {
        $this->db = $db;

        $userObj = new User($db);

        if ($userObj->fetch($this->uid) > 0) {
            $this->apiKey = $userObj->api_key;
            if (empty($this->apiKey)) {
                $this->log('Apikey introuvable pour le super admin (id='. $this->uid .')');
                die;
            }
        } else {
            $this->log('Utilisateur introuvable (id='. $this->uid .')');
            die;
        }
    }

    public function log($msg)
    {
        // write to dolibarr log (if available) or to a file
        if (function_exists('dol_syslog')) dol_syslog('[helloasso] ' . $msg);
        // file_put_contents(DOL_DATA_ROOT . '/helloasso/webhook.log', date('c') . ' ' . $msg . "\n", FILE_APPEND);
    }

    /**
     * 
     */
    public function findOrMakeDolibarrMember(HelloassoMember $member): int|null
    {
        if ($result = $this->getDolibarrMember($member)) {
            $this->updateDolibarrMember($result, $member);
            return $result;
        }
        
        if ($this->createDolibarrMember($member)) {
            $result = $this->getDolibarrMember($member);
            return $result;
        }

        return null;
    }

    /**
     * @see http://dolibarr/api/index.php/explorer/#!/members/listMembers
     */
    public function getDolibarrMember(HelloassoMember $member): int|null
    {
        $params = [
            'sqlfilters' => "(t.email:=:'". $member->email ."')",
            'limit' => 1,
            'sortfield' => "rowid",
        ];
        $result = $this->callApi('GET', 'members', $params);

        if (isset($result["error"]) && $result["error"]["code"] >= "300") {
            if ($result["error"]["code"] == "404") {
                return null;
            } else {
                $this->log('('. $member->email .'): '. json_encode($result));
                return null;
            }
        }

        return $result[0]['id'];
    }

    /**
     * Create Dolibarr Member from specified member.
     * @see http://dolibarr/api/index.php/explorer/#!/members/createMembers
     */
    public function createDolibarrMember(HelloassoMember $member): void
    {
        $data = [
            'morphy' => 'phy',
            'typeid' => 1,
            'email'  => $member->email,
            'login'  => $member->fullName,
            'firstname' => $member->firstName,
            'lastname'  => $member->lastName,
            'array_options' => array(),
        ];
        if ($address = $member->address) $data['address'] = $address;
        if ($zipCode = $member->zipCode) $data['zip'] = $zipCode;
        if ($city = $member->city) $data['town'] = $city;
        if ($country = $member->country) $data['country_code'] = $country;
        if ($phone = $member->phone) $data['phone'] = $phone;

        // Hook/extension point here ?
        $result = $this->callApi('POST', 'members', json_encode($data));

        if (isset($result["error"]) && $result["error"]["code"] >= "300") {
            $this->log('('. $member->email .'): '. json_encode($result));
        }
    }

    /**
     * Update Dolibarr Member from specified member.
     * @see http://dolibarr/api/index.php/explorer/#!/members/updateMembers
     */
    public function updateDolibarrMember(int $mid, HelloassoMember $member): void
    {
        $data = [];
        if ($address = $member->address) $data['address'] = $address;
        if ($zipCode = $member->zipCode) $data['zip'] = $zipCode;
        if ($city = $member->city) $data['town'] = $city;
        if ($country = $member->country) $data['country_code'] = $country;
        if ($phone = $member->phone) $data['phone'] = $phone;

        // Hook/extension point here ?
        $this->log('('. $mid .'/'. $member->email .') >> '. json_encode($data));

        if (!empty($data)) {
            $result = $this->callApi('PUT', "members/$mid", json_encode($data));

            if (isset($result["error"]) && $result["error"]["code"] >= "300") {
                $this->log('('. $member->email .'): '. json_encode($result));
            }
        }
    }

    /**
     * Create Subscription, Bank line and Bank link from current Membership.
     * 
     * @see http://dolibarr/api/index.php/explorer/#!/members/membersCreateSubscription
     * @see http://dolibarr/api/index.php/explorer/#!/bankaccounts/bankaccountsAddLine
     * @see http://dolibarr/api/index.php/explorer/#!/bankaccounts/bankaccountsAddLink
     */
    public function createDolibarrSubscription(int $mid, HelloassoMembership $membership): int
    {
        $subscription = [
            'start_date' => strtotime($membership->date),
            'end_date'   => strtotime(date('Y-12-31', strtotime($membership->date))),
            'amount'     => $membership->amount,
            // 'fk_bank'    => $bankLineId, // Is not set into database, update subscription below...
            'label'      => $membership->name .' - '. $membership->id,
        ];
        
        $subscriptionId = $this->callApi('POST', "members/$mid/subscriptions", json_encode($subscription));
        $this->log("Adhésion validée: $subscriptionId");

        $bankLine = [
            'date'   => strtotime($membership->date),
            'type'   => $membership->method,
            'amount' => $membership->amount,
            'label'  => $membership->name .' - '. $membership->id,
        ];

        $bankLineId = $this->callApi('POST', "bankaccounts/$this->bid/lines", json_encode($bankLine));
        $this->log("Ligne ajoutée au compte bancaire: $bankLineId");
        
        $bankLink = [
            'type'   => 'member',
            'url'    => $this->apiUrl .'adherents/card.php?rowid=',
            'url_id' => $mid,
            'label'  => $membership->member->fullName,
        ];

        $bankLinkId = $this->callApi('POST', "bankaccounts/$this->bid/lines/$bankLineId/links", json_encode($bankLink));
        $this->log("Lien ajouté à la ligne du compte bancaire: $bankLinkId");

        // Update subscription with Bank line Id, as it doesn't work in creation.
        $subscription = [
            'fk_bank' => $bankLineId,
        ];
        
        $this->callApi('PUT', "subscriptions/$subscriptionId", json_encode($subscription));

        return $subscriptionId;
    }

    private function callApi($method, $url, $data = false)
    {
        $this->log(__FUNCTION__ .': '. json_encode([$method, $url, $data]));

        $curl = curl_init();
        $httpheader = [
            'DOLAPIKEY: '. $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        switch ($method)
        {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;

            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;

            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        //    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        //    curl_setopt($curl, CURLOPT_USERPWD, "username:password");

        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);

        $result = curl_exec($curl);
        if ($result === false) {
            $this->log(json_encode(['callApi', $method, $this->apiUrl . $url, json_encode([curl_error($curl), curl_errno($curl)])]));
            throw new Exception(curl_error($curl), curl_errno($curl));
        }

        $this->log(json_encode(['callApi', $method, $this->apiUrl . $url, json_encode($result)]));

        $result = json_decode($result, true);
        curl_close($curl);

        return $result;
    }
}