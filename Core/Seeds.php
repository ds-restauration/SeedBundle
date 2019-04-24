<?php

namespace DsRestauration\SeedBundle\Core;

use DesRestauration\SeedBundle\Core\Tools;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use DsRestauration\SeedBundle\Model\AlterationExtensionInterface;
use DsRestauration\SeedBundle\Model\ConfigurableExtensionInterface;

abstract class Seeds extends ContainerAwareCommand
{
    private $separator = ':';
    private $prefix;

    /**
     * __construct.
     *
     * @param string $prefix - prefix can be changed through configuration
     *                       Note: Prefix is in the contructor because we need it in the "configure()" method
     *                       to build the seed name. The container is not available in the configure state.
     */
    public function __construct($prefix, $separator, array $extensions = [])
    {
        $this->prefix = $prefix;
        $this->separator = $separator;
        $this->extensions = $extensions;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName($this->prefix.$this->separator.$this->method)
            ->setDescription('Load requested seeds')
            ->addOption('break', '-b', InputOption::VALUE_NONE)
            ->addOption('debug', '-d', InputOption::VALUE_NONE)
            ->addOption('from', '-f', InputOption::VALUE_REQUIRED)
            ->addOption('bundle', '-bn', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY) # load only the seeds in the bundle/s specified
            ->addOption('skip-bundle', '-sb', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY) # don't load the seeds in the bundle/s specified
            ->addOption('seed', '-s', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY); # load only the seed/s specified
        $help = <<<EOT

This command loads/unloads a list of seeds

If you want to break on a bad exit code use -b

Want to debug seeds ordering? You can launch a simulation by using the -d option:

  <info>php app/console seeds:load -d</info>
EOT;

        foreach ($this->extensions as $extension) {
            if ($extension instanceof ConfigurableExtensionInterface) {
                $extension->configure($this);
                $help .= $extension->getHelp();
            }
        }

        $this->setHelp($help);
    }

    /**
     * This is wrapping every seed in a single command based on $this->method
     * it's also handling arguments and options to launch multiple seeds.
     * {@inheritdoc}
     *
     * @see LoadSeedsCommand
     * @see UnloadSeedsCommand
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $break = $input->getOption('break');
        $debug = $input->getOption('debug');
        $from = $input->getOption('from');
        $bundle = $input->getOption('bundle');
        $seed = $input->getOption('seed');
        $bundlesToSkip = $input->getOption('skip-bundle');

        $app = $this->getApplication();
        $commands = $this->getSeedCommands($app, $bundle, $bundlesToSkip, $seed);
        $seedOrder = $this->getContainer()->getParameter('seed.seed_order');
        $bundleOrder = $this->getContainer()->getParameter('seed.bundle_order');

        foreach ($this->extensions as $extension) {
            if ($extension instanceof AlterationExtensionInterface) {
                $extension->apply($commands, $input);
            }
        }

        $l = count($commands);

        //No seeds? Stop.
        if ($l == 0) {
            $output->writeln('<info>No seeds</info>');

            return 1;
        }

        foreach ($this->extensions as $extension) {
            if (!($extension instanceof AlterationExtensionInterface)) {
                $extension->apply($commands, $input);
            }
        }

        //Prepare arguments
        $arguments = new ArrayInput(['method' => $this->method]);
        $returnCode = 0;
        $startFrom = true;

        if ($from) {
          $startFrom = false;
        }

        //Loop and execute every seed by printing tstart/tend
        for ($i = 0; $i < $l; ++$i) {
            $command = $commands[$i];

            $commandName = $command->getName();
            $commandName = substr($commandName, strrpos($commandName, ':') + 1);
            $seedOrder = 0;
            $bundleOrder = 0;

            if (isset($seedOrder[$commandName])) {
                $seedOrder = $seedOrder[$commandName];
            }

            if (isset($bundleOrder[$commandName])) {
                $bundleOrder = $bundleOrder[$commandName];
            }

            $tstart = microtime(true);

            if (false === $startFrom) {
              if ($from !== $commandName) {
                continue;
              } else {
                $startFrom = true;
              }
            }

            $orderText = "B$bundleOrder S$seedOrder";
            $output->writeln("<info>[$orderText] Starting $commandName</info>");

            if ($debug) {
                $code = 0;
            } else {
                $code = $command->run($arguments, $output);
            }

            $time = microtime(true) - $tstart;

            if ($code === 0) {
                $output->writeln("<info>[$orderText] Seed $commandName done (+$time seconds)</info>");

                continue;
            }

            $output->writeln("<error>[$orderText] Seed $commandName failed (+$time seconds)</error>");

            if ($break === true) {
                $returnCode = 1;
                break;
            }
        }

        return $returnCode;
    }

    /**
     * Get seeds from app commands.
     *
     * @param array $seeds Input Option
     *
     * @return array commands
     */
    private function getSeedCommands($app, $bundle, $bundlesToSkip, $seed): array
    {
        $commands = [];

        // Get every command, if no seeds argument we take all available seeds
        foreach ($app->all() as $key => $command) {

            if ($command instanceof Seed) {
                // if the optional bundle parameter is specified, only load seeds in those bundles
                $loadBundle = empty($bundle) || in_array($command->getBundleName(), $bundle);
                // if the optional seed parameter is specified, only load seeds of that name
                $loadSeed = empty($seed) || in_array($command->getSeedName(), $seed);
                $skipBundle = !empty($bundlesToSkip) && in_array($command->getBundleName(), $bundlesToSkip);

                if ($loadBundle && $loadSeed && !$skipBundle) {
                    $commands[] = $command;
                }
            }

        }

        return $this->orderSeedCommands($commands);
    }

    /**
     * Order the seeds (lowest runs first, default is 0, also considers bundle order)
     */
    private function orderSeedCommands($commands) {
        usort($commands, function ($commandA, $commandB) {
            $seedOrder = $this->getSeedOrder($commandA) - $this->getSeedOrder($commandB);
            $bundleOrder = $this->getBundleOrder($commandA) - $this->getBundleOrder($commandB);
            return ($bundleOrder * 1e10) + $seedOrder;
        });

        return $commands;
    }

    private function getSeedOrder($command) {
        $commandName = $command->getName();
        $commandName = substr($commandName, strrpos($commandName, ':') + 1);
        return $this->getOrder($commandName, 'seed.seed_order');
    }

    private function getBundleOrder($command) {
        return $this->getOrder($command->getBundleName(), 'seed.bundle_order');;
    }

    private function getOrder($commandName, $param) {
        $orders = $this->getContainer()->getParameter($param);
        $order = 0;

        if (isset($orders[$commandName])) {
            $order = $orders[$commandName];
        }

        return $order;
    }
}
