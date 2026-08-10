<?php

class HelloassoRegistration extends HelloassoItem
{
    public function __construct(array $item, string $date, HelloassoMember $member)
    {
        parent::__construct($item, $date, $member);
    }
}
