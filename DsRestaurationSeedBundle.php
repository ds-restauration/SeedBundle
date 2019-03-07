<?php

namespace DsRestauration\SeedBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Console\Application;
use DsRestauration\SeedBundle\DependencyInjection\Compiler\ExtensionPass;

class DsRestaurationSeedBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $this->container = $container;

        parent::build($container);

        $container->addCompilerPass(new ExtensionPass());
    }

    public function registerCommands(Application $application)
    {
        $seeds = $this->container->get('seed.loader');
        $seeds->loadSeeds($application);
    }
}
