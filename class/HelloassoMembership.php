<?php

class HelloassoMembership
{
    public $id;
    public $name;
    public $amount;
    public $date;
    public $method;
    public $member;

    public function __construct(array $item, array $payment, HelloassoMember $member)
    {
        $this->id     = $item['id'];
        $this->name   = "HelloAsso - ". $item['name'];
        $this->amount = intval($item['amount']) / 100.0; // HelloAsso amounts are in cents
        $this->date   = !empty($payment['date']) ? substr($payment['date'],0,10) : date('Y-m-d');
        $this->method = 'CB';
        $this->member = $member;
    }
}
