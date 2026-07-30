<?php

class HelloassoMembership extends HelloassoItem
{
    public function __construct(array $item, string $date, HelloassoMember $member)
    {
        parent::__construct($item, $date, $member);

        $this->member->setPeriod($this->date); // Update members period (from payment date)

        // Update members address (from item custom fields)
        if (isset($item['customFields'])) foreach ($item['customFields'] as $field)
        {
            if (strpos($field['name'], 'massif') !== false) {
                $this->member->setMassif(trim($field['answer']));
            }
        }
    }
}
