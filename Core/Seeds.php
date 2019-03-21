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
            ->addOption('bundle', '-bn', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY); # load only the seeds in the bundle/s specified
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

        $app = $this->getApplication();
        $commands = $this->getSeedCommands($app, $bundle);
        $seedOrder = $this->getContainer()->getParameter('seed.order');

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
            $order = 0;

            if (isset($seedOrder[$commandName])) {
                $order = $seedOrder[$commandName];
            }

            $tstart = microtime(true);

            if (false === $startFrom) {
              if ($from !== $commandName) {
                continue;
              } else {
                $startFrom = true;
              }
            }

            $output->writeln(sprintf(
                '<info>[%d] Starting %s</info>',
                $order, $commandName
            ));

            if ($debug) {
                $code = 0;
            } else {
                $code = $command->run($arguments, $output);
            }

            $time = microtime(true) - $tstart;

            if ($code === 0) {
                $output->writeln(sprintf(
                    '<info>[%d] Seed %s done (+%d seconds)</info>',
                    $order, $commandName, $time
                ));

                continue;
            }

            $output->writeln(sprintf(
                '<error>[%d] Seed %s failed (+%d seconds)</error>',
                $order, $commandName, $time
            ));

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
    private function getSeedCommands($app, $bundle): array
    {
        $commands = [];

        // Get every command, if no seeds argument we take all available seeds
        foreach ($app->all() as $key => $command) {
            // Test if it's a Seed and if it's in a bundle supplied by the optional bundle parameter.
            if ($command instanceof Seed && (empty($bundle) || in_array($command->getBundleName(), $bundle))) {
                $commands[] = $command;
                continue;
            }
        }

        return $this->orderSeedCommands($commands);
    }

    /**
     * Order the seeds (lowest runs first, default is 0)
     */
    private function orderSeedCommands($commands) {
        usort($commands, function ($commandA, $commandB) {
            return $this->getSeedOrder($commandA) - $this->getSeedOrder($commandB);
        });

        return $commands;
    }

    private function getSeedOrder($command) {
        $seedOrder = $this->getContainer()->getParameter('seed.order');
        $commandName = $command->getName();
        $commandName = substr($commandName, strrpos($commandName, ':') + 1);
        $order = 0;

        if (isset($seedOrder[$commandName])) {
            $order = $seedOrder[$commandName];
        }

        return $order;
    }
}
