<?php

namespace DsRestauration\SeedBundle\Command;

use DsRestauration\SeedBundle\Core\Seeds;

final class UnloadSeedsCommand extends Seeds
{
    protected function configure()
    {
        $this->method = 'unload';
        parent::configure();
    }
}
