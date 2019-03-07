<?php

namespace DsRestauration\SeedBundle\Extensions;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use DsRestauration\SeedBundle\Model\SeedExtensionInterface;
use DsRestauration\SeedBundle\Model\AlterationExtensionInterface;
use DsRestauration\SeedBundle\Model\ConfigurableExtensionInterface;
use DsRestauration\SeedBundle\Core\Seed;

class Skip implements SeedExtensionInterface, AlterationExtensionInterface, ConfigurableExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(array &$commands, InputInterface $input)
    {
        $skip = $input->getOption('skip');

        if (!$skip) {
            return;
        }

        $skip = is_array($skip) ? $skip : [$skip];

        $skip = array_map(function ($v) {
            return strtolower($v);
        }, $skip);

        //array_filter keeps keys
        $commands = array_values(array_filter($commands, function (Seed $command) use ($skip) {
            return !in_array($command->getSeedName(), $skip);
        }));
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Command $command)
    {
        $command->addOption('skip', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY);
    }

    /**
     * {@inheritdoc}
     */
    public function getHelp()
    {
        return <<<EOT

You can skip some seeds:

  <info>php app/console seeds:load --skip=Country --skip=Town</info>
EOT;
    }
}
