<?php

declare(strict_types=1);

class HelloassoHandler
{
    private $db;
    private $apiKey;
    private $apiEmail;
    private $apiUrl = DOL_MAIN_URL_ROOT .'/api/index.php/'; // @see $dolibarr_main_url_root
    private $uid    = 1;
    private $bid    = 1; // @TODO Put this in config (Bank Account Id)

    public function __construct($db)
    {
        $this->db = $db;

        if ($default_user_id = getDolGlobalInt('HELLOASSO_DEFAULT_USER_ID')) {
            $this->uid = $default_user_id;
        }

        $userObj = new User($db);

        if ($userObj->fetch($this->uid) > 0) {
            $this->apiKey = $userObj->api_key;
            $this->apiEmail = $userObj->email;
            if (empty($this->apiKey)) {
                $this->log('Apikey introuvable pour le super admin (id='. $this->uid .')');
                die;
            }
        } else {
            $this->log('Utilisateur introuvable (id='. $this->uid .')');
            die;
        }

        // $this->log('Utilisateur valide (id='. $this->uid .')');
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
    public function findOrMakeDolibarrThirdparty(HelloassoMember $member): int|null
    {
        if ($result = $this->getDolibarrThirdparty($member)) {
            // $this->updateDolibarrThirdparty($result['id'], $member, $result);
            return (int) $result['id'];
        }
        
        if ($this->createDolibarrThirdparty($member)) {
            $result = $this->getDolibarrThirdparty($member);
            return (int) $result['id'];
        }

        return null;
    }

    /**
     * @see http://dolibarr/api/index.php/explorer/#!/thirdparties/listThirdparties
     */
    public function getDolibarrThirdparty(HelloassoMember $member): array|null
    {
        $params = [
            'sqlfilters' => "(t.email:=:'". $member->email ."') or (ef.emailperso:=:'". $member->email ."')",
            'limit' => 1,
            'sortfield' => "rowid",
        ];
        $result = $this->callApi('GET', 'thirdparties', $params);

        if (isset($result["error"]) && $result["error"]["code"] >= "300") {
            if ($result["error"]["code"] == "404") {
                return null;
            } else {
                $this->log('('. $member->email .'): '. json_encode($result));
                return null;
            }
        }

        return $result[0];
    }

    /**
     * Create Dolibarr Thirdparty from specified member.
     * @see http://dolibarr/api/index.php/explorer/#!/thirdparties/createThirdparties
     */
    public function createDolibarrThirdparty(HelloassoMember $member): void
    {
        $data = [
            'entity'        => '1',
            'email'         => $member->email,
            'name'          => $member->fullName,
            'client'        => 1, // @TODO Put this in config (Prospect / Client : Client)
            'code_client'   => "auto",
            'array_options' => [
                'options_statut'    => $member->status,
                'options_massif'    => $member->massif,
                'options_cotis'     => $member->period,
            ],
        ];
        if ($address = $member->address) $data['address'] = $address;
        if ($zipCode = $member->zipCode) $data['zip'] = $zipCode;
        if ($city = $member->city) $data['town'] = $city;
        if ($country = $member->country) $data['country_code'] = $country;
        if ($phone = $member->phone) $data['phone'] = $phone;

        // Hook/extension point here ?
        $result = $this->callApi('POST', 'thirdparties', json_encode($data));

        if (isset($result["error"]) && $result["error"]["code"] >= "300") {
            $this->log('('. $member->email .'): '. json_encode($result));
        }
    }

    /**
     * Update Dolibarr Thirdparty from specified member.
     * @see http://dolibarr/api/index.php/explorer/#!/thirdparties/updateThirdparties
     */
    public function updateDolibarrThirdparty(int $mid, HelloassoMember $member, array $thirdparty): void
    {
        $status = $member->status ?: ($thirdparty['array_options']['options_statut'] ?? '');
        $massif = $member->massif ?: ($thirdparty['array_options']['options_massif'] ?? '');

        // Concat periods (year after year)
        $periods = $thirdparty['array_options']['options_cotis'] ?? '';
        if(strpos($periods, $member->period) === false) {
            $periods .= ','. $member->period;
        }

        $data = [
            'array_options' => [
                'options_statut' => $status,
                'options_massif' => $massif,
                'options_cotis'  => $periods,
            ],
        ];
        if ($address = $member->address) $data['address'] = $address;
        if ($zipCode = $member->zipCode) $data['zip'] = $zipCode;
        if ($city = $member->city) $data['town'] = $city;
        if ($country = $member->country) $data['country_code'] = $country;
        if ($phone = $member->phone) $data['phone'] = $phone;

        // Hook/extension point here ?
        $this->log('('. $mid .'/'. $member->email .') >> '. json_encode($data));

        if (!empty($data)) {
            $result = $this->callApi('PUT', "thirdparties/$mid", json_encode($data));

            if (isset($result["error"]) && $result["error"]["code"] >= "300") {
                $this->log('('. $member->email .'): '. json_encode($result));
            }
        }
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
            'array_options' => [],
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
    public function createDolibarrSubscription(int $mid, HelloassoItem $item): int
    {
        $subscription = [
            'start_date' => strtotime($item->date),
            'end_date'   => strtotime(date('Y-12-31', strtotime($item->date))),
            'amount'     => $item->amount,
            // 'fk_bank'    => $bankLineId, // Is not set into database, update subscription below...
            'label'      => "Helloasso - ". $item->name .' - '. $item->id,
        ];
        
        $subscriptionId = $this->callApi('POST', "members/$mid/subscriptions", json_encode($subscription));
        $this->log("Adhésion validée: $subscriptionId");

        $bankLine = [
            'date'   => strtotime($item->date),
            'type'   => $item->method,
            'amount' => $item->amount,
            'label'  => "Helloasso - ". $item->name .' - '. $item->id,
        ];

        $bankLineId = $this->callApi('POST', "bankaccounts/$this->bid/lines", json_encode($bankLine));
        $this->log("Ligne ajoutée au compte bancaire: $bankLineId");
        
        $bankLink = [
            'type'   => 'member',
            'url'    => $this->apiUrl .'adherents/card.php?rowid=',
            'url_id' => $mid,
            'label'  => $item->member->fullName,
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

    /**
     * @see http://dolibarr/api/index.php/explorer/#!/products/listProducts
     */
    public function getDolibarrProduct(?string $label = null): array|null
    {
        $params = [
            'sqlfilters' => "(t.label:=:'$label')",
            'limit' => 1,
            'sortfield' => "t.ref",
        ];
        $result = $this->callApi('GET', 'products', $params);

        if (isset($result["error"]) && $result["error"]["code"] >= "300") {
            if ($result["error"]["code"] == "404") {
                return null;
            } else {
                $this->log('('. $label .'): '. json_encode($result));
                return null;
            }
        }

        return $result[0];
    }

    /**
     * Get Invoice by reference
     * 
     * @see http://dolibarr/api/index.php/explorer/#!/invoices/listInvoices
     */
    public function getDolibarrInvoice(string $reference): array|null
    {
        $params = [
            'sqlfilters' => "(t.note_private:like:'%". $reference ."')",
            'limit' => 1,
            'sortfield' => "rowid",
        ];
        $result = $this->callApi('GET', 'invoices', $params);

        if (isset($result["error"]) && $result["error"]["code"] >= "300") {
            if ($result["error"]["code"] == "404") {
                return null;
            } else {
                $this->log('('. $reference .'): '. json_encode($result));
                return null;
            }
        }

        return $result[0] ?? null;
    }

    /**
     * Create Invoice, and Payment from current Membership.
     * 
     * @see http://dolibarr/api/index.php/explorer/#!/invoices/createInvoices
     * @see http://dolibarr/api/index.php/explorer/#!/invoices/invoicesAddPayment
     * @see http://dolibarr/api/index.php/explorer/#!/invoices/invoicesValidate
     */
    public function createDolibarrInvoice(int $mid, array $items, string $oid): string|null
    {
        $lines = [];
        $rang = 1;
        $emailTemplate = null;

        foreach ($items as $item)
        {
            // Produit Dolibarr associé
            $product = $this->getDolibarrProduct($item->name);
            if (empty($product)) {
                throw new Exception("Can't find product by label '$item->name'");
            }

            // Modèle d'email associé au produit
            $emailTemplate = $emailTemplate ?: $product['array_options']['options_status'];

            $lines[] = [
                'rang'       => (string) $rang++,
                'qty'        => 1,
                'fk_product' => $product['id'],
                'subprice'   => $item->amount,
                'total_ht'   => $item->amount,
                'total_ttc'  => $item->amount,
                'marque_tx'  => 100,
            ];
        }

        $invoice = [
            'socid'             => $mid,
            'cond_reglement_id' => "1", // @TODO Put this in config ("A RECEPTION")
            'mode_reglement_id' => "6", // @TODO Put this in config ("CB")
            'fk_account'        => "2", // @TODO Put this in config ("COMPTE")
            'ref_client'        => "Helloasso Order ". $items[0]->member->period,
            'note_private'      => "Helloasso ID: $oid",
            'lines'             => $lines,
        ];

        $result = $this->callApi('POST', 'invoices', json_encode($invoice));

        if (isset($result["error"]) && $result["error"]["code"] >= "300" || empty($result)) {
            $this->log('('. $items[0]->member->email .'): '. json_encode($result));
            return null;
        }

        $invoiceId = $result;
        $this->log("Facture créée: $invoiceId avec ".count($lines)." lignes");
        
        $validate = $this->callApi('POST', "invoices/$invoiceId/validate", json_encode($invoice));
        $this->log('Facture validée: '. json_encode($validate));

        $payment = [
            'datepaye'          => $validate['date_validation'],
            'closepaidinvoices' => "yes",
            'paymentid'         => "6", // @TODO Put this in config ("CB")
            'accountid'         => "2", // @TODO Put this in config ("COMPTE")
        ];
        $paymentId = $this->callApi('POST', "invoices/$invoiceId/payments", json_encode($payment));
        $this->log("Paiement crée: $paymentId");

        // Get and send pdf invoice from Dolibarr.
        $emailTemplate = $emailTemplate ?: getDolGlobalString('HELLOASSO_DEFAULT_EMAIL_TEMPLATE');
        $sql = "SELECT *
                FROM ".MAIN_DB_PREFIX."c_email_templates
                WHERE type_template = 'facture_send'
                AND label = '".$db->escape($emailTemplate)."'
                LIMIT 1";

        $resql = $db->query($sql);

        if (!$resql || !($template = $db->fetch_object($resql))) {
            $this->log("Modèle d'email facture introuvable: $emailTemplate");
            return $validate['ref'];
        }

        global $conf;
        $pdfFile = $conf->facture->dir_output.'/'.$validate['ref'].'/'.$validate['ref'].'.pdf';
        $this->log("Facture prête à envoi: $pdfFile");

        $email = $items[0]->member->email;
        if ($send_all_emails_to = getDolGlobalString('HELLOASSO_SEND_ALL_EMAILS_TO')) {
            $email = $send_all_emails_to;
        }

        if ($this->helloasso_send_mail($template->topic, $email, $template->content, $pdfFile, 'application/pdf', $validate['ref'].'.pdf')) {
            $this->log("Facture envoyée par email à: $email");
        } else {
            $this->log("Échec de l'envoi de la facture par email à: $email");
        }

        return $validate['ref'];
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
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->log(json_encode(['callApi', $method, $this->apiUrl . $url, "HTTP $status", $result]));
        curl_close($curl);

        if ($status >= 400) {
            throw new Exception("HTTP $status: $result");
        }

        if ($result === false) {
            throw new Exception("HTTP $status: $result", curl_errno($curl));
        }

        return json_decode($result, true);
    }

    /**
     * Envoie un email HTML avec pièce jointe optionnelle, via Dolibarr CMailFile.
     *
     * @param string $subject
     * @param string $recipientEmail
     * @param string $htmlBody
     * @param string|null $attachmentPath  chemin complet du fichier à joindre
     * @param string|null $attachmentMime  type MIME de la pièce jointe
     * @param string|null $attachmentName  nom affiché de la pièce jointe
     * @return bool true si envoyé, false sinon (voir dol_syslog pour le détail)
     */
    function helloasso_send_mail(
        string $subject,
        string $recipientEmail,
        string $htmlBody,
        ?string $attachmentPath = null,
        ?string $attachmentMime = null,
        ?string $attachmentName = null
    ): bool {
        require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

        $filepaths = $attachmentPath ? [$attachmentPath] : [];
        $filemimes = $attachmentMime ? [$attachmentMime] : [];
        $filenames = $attachmentName ? [$attachmentName] : [];

        $mail = new CMailFile(
            $subject,
            $recipientEmail,
            getDolGlobalString('MAIN_MAIL_EMAIL_FROM'),
            $htmlBody,
            $filepaths,
            $filemimes,
            $filenames,
            '',                         // cc
            '',                         // bcc
            0,                          // delivery receipt
            1                           // message déjà HTML
        );

        if (!$mail->sendfile()) {
            $this->log('Impossible d\'envoyer un email à: '. $recipientEmail .' - '. $mail->error, LOG_ERR);
            return false;
        }

        return true;
    }

    /**
     * Notifie les admins d'une exception survenue dans le traitement du webhook.
     *
     * @param Throwable $e
     * @param array $context éléments additionnels utiles au diagnostic (ex: payload brut)
     * @return bool
     */
    function helloasso_notify_admin_error(Throwable $e, array $context = []): bool
    {
        if (empty($this->apiEmail)) {
            $this->log('Aucune adresse admin configurée, notification d\'erreur non envoyée', LOG_ERR);
            return false;
        }

        $body = '<h3>Erreur webhook HelloAsso</h3>';
        $body .= '<p><strong>Message :</strong> '. htmlspecialchars($e->getMessage()) .'</p>';
        $body .= '<p><strong>Fichier :</strong> '. htmlspecialchars($e->getFile()) .':'. $e->getLine() .'</p>';

        if (!empty($context)) {
            $body .= '<p><strong>Contexte :</strong></p><pre>'. htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) .'</pre>';
        }

        $body .= '<p><strong>Trace :</strong></p><pre>'. htmlspecialchars($e->getTraceAsString()) .'</pre>';

        return $this->helloasso_send_mail(
            'Erreur webhook: '. $e->getMessage(),
            $this->apiEmail,
            $body
        );
    }
}