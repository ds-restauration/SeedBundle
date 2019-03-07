<?php

namespace DsRestauration\SeedBundle\Command;

use DsRestauration\SeedBundle\Core\Seeds;

final class LoadSeedsCommand extends Seeds
{
    protected function configure()
    {
        $this->method = 'load';
        parent::configure();
    }
}
