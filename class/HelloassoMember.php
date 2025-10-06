<?php

class HelloassoMember
{
    public $email;
    public $firstName;
    public $lastName;
    public $fullName;
    public $phone;
    public $address;
    public $city;
    public $zipCode;
    public $country;
    public $company;

    public function __construct(array $data)
    {
        $this->email        = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $this->firstName    = $data['firstName'];
        $this->lastName     = $data['lastName'];
        $this->fullName     = trim($this->firstName .' '. $this->lastName);
        $this->phone        = $data['phone'] ?? '';
        $this->address      = $data['address'];
        $this->city         = $data['city'];
        $this->zipCode      = $data['zipCode'];
        $this->country      = substr($data['country'] ?? 'FR',0,2);
        $this->company      = $data['company'] ?? '';
    }

    public function toJson(): string
    {
        return json_encode([
            'email'     => $this->email,
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
            'fullName'  => $this->fullName,
            'phone'     => $this->phone,
            'address'   => $this->address,
            'city'      => $this->city,
            'zipCode'   => $this->zipCode,
            'country'   => $this->country,
            'company'   => $this->company,
        ]);
    }
}
