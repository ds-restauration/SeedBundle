<?php

namespace DsRestauration\SeedBundle\Extensions;

use Symfony\Component\Console\Input\InputInterface;
use DsRestauration\SeedBundle\Model\SeedExtensionInterface;
use DsRestauration\SeedBundle\Core\Seed;

class Order implements SeedExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(array &$commands, InputInterface $input)
    {
        //Sort through getOrder
        usort($commands, function (Seed $a, Seed $b) {
            return $a->getOrder() - $b->getOrder();
        });
    }
}
