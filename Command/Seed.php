<?php

namespace DsRestauration\SeedBundle\Command;

use DsRestauration\SeedBundle\Core\Seed as SeedCompatibility;
use DsRestauration\SeedBundle\Model\SeedOrderInterface;
use DsRestauration\SeedBundle\Model\SeedInterface;

/**
 * @codeCoverageIgnore
 */
abstract class Seed extends SeedCompatibility implements SeedOrderInterface, SeedInterface
{
    public function getOrder(): int
    {
        return 0;
    }
}
