<?php

class HelloassoDonation extends HelloassoItem
{
    public function __construct(array $item, string $date, HelloassoMember $member)
    {
        parent::__construct($item, $date, $member);

        if (empty($this->name)) $this->name = getDolGlobalString('HELLOASSO_DEFAULT_DONATION_LABEL');
    }
}
