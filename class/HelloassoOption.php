<?php

class HelloassoOption extends HelloassoItem
{
    public function __construct(array $item, string $date, HelloassoMember $member)
    {
        parent::__construct($item, $date, $member);

        $this->desc = $this->name;
        $this->name = getDolGlobalString('HELLOASSO_DEFAULT_OPTION_LABEL');
    }
}
