<?php

declare(strict_types=1);

class HelloassoItem implements JsonSerializable
{
    public $id;
    public $name;
    public $amount;
    public $date;
    public $method;
    public $member;

    public function __construct(array $item, string $date, HelloassoMember $member)
    {
        $this->id     = $item['id'];
        $this->name   = $item['name'] ?? '';
        $this->amount = intval($item['amount']) / 100.0; // Helloasso amounts are in cents
        $this->date   = !empty($date) ? substr($date,0,10) : date('Y-m-d');
        $this->method = 'CB';
        $this->member = $member;

        // Update members address (from item custom fields)
        if (isset($item['customFields'])) foreach ($item['customFields'] as $field)
        {
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

    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'amount'    => $this->amount,
            'date'      => $this->date,
            'method'    => $this->method,
            'member'    => $this->member?->email,
        ];
    }
}
