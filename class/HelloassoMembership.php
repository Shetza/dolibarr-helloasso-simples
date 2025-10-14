<?php

class HelloassoMembership
{
    public $id;
    public $name;
    public $amount;
    public $date;
    public $method;
    public $member;

    /**
     * @deprecated Get associated product from Dolibarr service name
     */
    public $analytic;

    public function __construct(array $item, array $payment, HelloassoMember $member)
    {
        $this->id     = $item['id'];
        $this->name   = $item['name'];
        $this->amount = intval($item['amount']) / 100.0; // Helloasso amounts are in cents
        $this->date   = !empty($payment['date']) ? substr($payment['date'],0,10) : date('Y-m-d');
        $this->method = 'CB';
        $this->member = $member;

        // $this->setAnalytic($item['name']); // @deprecated Get associated product from Dolibarr service name

        // $this->member->setStatus($item['name']); // @deprecated Get associated status from Dolibarr service
        $this->member->setPeriod($this->date);   // Update members period (from payment date)

        // Update members address (from item custom fields)
        if (isset($item['customFields'])) foreach ($item['customFields'] as $field)
        {
            if (strpos($field['name'], 'massif') !== false) {
                $this->member->setMassif(trim($field['answer']));
            }
            if (strpos($field['name'], 'Adresse') !== false) {
                $this->member->address = trim($field['answer']);
            }
            if (strpos($field['name'], 'Ville') !== false) {
                $this->member->city = trim($field['answer']);
            }
            if (strpos($field['name'], 'Postal') !== false) {
                $this->member->zipCode = trim($field['answer']);
            }
            if (strpos($field['name'], 'phone') !== false) {
                $this->member->phone = trim($field['answer']);
            }
        }
    }

    /**
     * @deprecated Get associated product from Dolibarr service name
     */
    public function setAnalytic(string $name): int
    {
        throw new Exception("@deprecated Get associated product from Dolibarr service name");

        // @TODO Get this from Dolibarr ?
        if (strpos($name, 'Symptahisant') !== false) {
            return $this->analytic = 110;
        }
        if (strpos($name, 'PPAM') !== false) {
            if (strpos($name, 'installation') !== false) {
                return $this->analytic = 173;
            }
            return $this->analytic = 109;
        }

        return $this->analytic = 9; // @TODO Put this in config (Default don-product id)
    }

    /**
     * @deprecated Status doesn't exist in HelloassoMembership
     */
    public function setStatus(string $name): void
    {
        throw new Exception("@deprecated Status doesn't exist in HelloassoMembership");

        if (strpos($name, 'PPAM') !== false) {
            $this->status = "adherentspro";
        } else {
            $this->status = "adherentsymp";
        }
    }

    public function toJson(): string
    {
        return json_encode([
            'id'        => $this->id,
            'name'      => $this->name,
            'amount'    => $this->amount,
            'date'      => $this->date,
            'method'    => $this->method,
            'member'    => $this->member ? $this->member->email : null,
            // 'analytic'  => $this->analytic, // @deprecated Get associated product from Dolibarr service name
        ]);
    }
}
