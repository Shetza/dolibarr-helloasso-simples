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

    public $status;
    public $massif;
    public $period;

    public function __construct(array $data)
    {
        $this->email        = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $this->firstName    = $data['firstName'];
        $this->lastName     = $data['lastName'];
        $this->fullName     = trim(strtoupper($this->lastName) .' '. ucfirst($this->firstName));
        $this->phone        = $data['phone'] ?? '';
        $this->address      = $data['address'];
        $this->city         = $data['city'];
        $this->zipCode      = $data['zipCode'];
        $this->country      = substr($data['country'] ?? 'FR',0,2);
        $this->company      = $data['company'] ?? '';
    }

    public function setMassif(string $massif): void
    {
        // @TODO Get this from Dolibarr ?
        $list = [
            "Alpes Sud"                  => "alpes-sud",
            "Auvergne"                   => "auvergne",
            "Coeur des Alpes"            => "coeur-des-alpes",
            "Bourgogne"                  => "bourgogne",
            "Bretagne"                   => "bretagne",
            "Cévennes"                   => "cevennes",
            "Grands Causses"             => "grands-causses",
            "Jura - Alpes Nord"          => "juralpes",
            "Limousin"                   => "limousin",
            "Normandie"                  => "normandie",
            "Pays de la Loire"           => "pays-loire",
            "Plaines et bocages du Nord" => "bocages-nord",
            "Pyrénées"                   => "pyrenees",
            "Sud-Ouest"                  => "sud-ouest",
            "Velay-Vivarais"             => "velay-vivarais",
            "Vosges - Ardennes"          => "vosgesardennes",
            "HORS MASSIF"                => "hors-massif",
        ];

        $this->massif = $list[$massif] ?: $massif;
    }

    public function setStatus(string $name): void
    {
        // @TODO Get this from Dolibarr ?
        if (strpos($name, 'PPAM') !== false) {
            $this->status = "adherentspro";
        } else {
            $this->status = "adherentsymp";
        }
    }

    public function setPeriod(string $date, bool $last = false): void
    {
        $year = date('Y', strtotime($date));
        $breakpoint = $year .'-07-01'; // @TODO Put this in config (month-day of breakpoint)

        $year = (int) substr($year, -2);
        if ($last || $date < date($breakpoint)) {
            $this->period = ($year-1) .'-'. $year;
        } else {
            $this->period = $year .'-'. ($year+1);
        }
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
            'status'    => $this->status,
            'massif'    => $this->massif,
            'period'    => $this->period,
        ]);
    }
}
