<?php

namespace DsRestauration\SeedBundle\Core;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Registers the seed commands and the different seeds in the symfony console application
 */
class Registrar extends Container
{
    use ContainerAwareTrait;
    private $prefix;
    private $separator;

    public function __construct($prefix, $separator)
    {
        $this->prefix = $prefix;
        $this->separator = $separator;
    }

    public function registerSeeds(Application $application)
    {
        $seedsDir = $this->container->getParameter('seed.directory');

        //add seed:load and seed:unload commands
        $application->add($this->container->get('seed.load_seeds_command'));
        $application->add($this->container->get('seed.unload_seeds_command'));

        $seedsDirectory = $application->getKernel()->getRootDir().'/'.$seedsDir.'/Bundles';
        $this->registerAllSeedsRecursively($seedsDirectory, $application);
    }

    public function registerAllSeedsRecursively($base_dir, Application $application) {
        foreach($this->getAllSubDirectories($base_dir) as $directory) {
            $this->registerAllSeedsInDirectory($directory, $application);
        }
    }

    public function registerAllSeedsInDirectory($directory, Application $application) {
        //Go through bundles and add *Seeds available in seed.directory
        $finder = new Finder();
        $finder->files()->name('*Seed.php')->in($directory);

        foreach ($finder as $file) {
            $nameSpace = $this->getNameSpaceByToken($file->getPathName());

            $class = $nameSpace.'\\'.$file->getBasename('.php');
            $alias = 'seed.command.'.strtolower(str_replace('\\', '_', $class));

            if ($this->container->has($alias)) {
                continue;
            }

            $r = new \ReflectionClass($class);
            if ($r->isSubclassOf('DsRestauration\\SeedBundle\\Command\\Seed') && !$r->isAbstract() && ($r->hasMethod('load') || $r->hasMethod('unload'))) {
                $application->add(
                    $r->newInstanceArgs([$this->prefix, $this->separator])
                );
            }
        }
    }

    private function getAllSubDirectories($base_dir) {
      $directories = array();

      foreach(scandir($base_dir) as $file) {
            if($file == '.' || $file == '..') continue;
            $dir = $base_dir.DIRECTORY_SEPARATOR.$file;
            if(is_dir($dir)) {
                $directories []= $dir;
                $directories = array_merge($directories, $this->getAllSubDirectories($dir));
            }
      }

      return $directories;
    }

    private function getNameSpaceByToken($fileName) {
        $src = file_get_contents($fileName);

        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;

        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            $i++;
        }

        if (!$namespace_ok) {
            return null;
        } else {
            return $namespace;
        }
    }
}
