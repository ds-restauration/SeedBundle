# Symfony/Doctrine Seed Bundle

[![Build Status](https://travis-ci.org/ds-restauration/SeedBundle.svg?branch=master)](https://travis-ci.org/ds-restauration/SeedBundle)
## Description

Generates and persists seed data.
Derived from Soyuka's seed bundle: https://github.com/DsRestauration/SeedBundle

## Configuration

```yaml
ds_restauration_seed:
  prefix: 'seed' #command prefix "seed:yourseedname"
  directory: 'Seeds' #default seed path: Bundle/Seeds
  separator: ':'
```

## Building a Seed

The `Seed` class is a `Command` and :

- Must extend `DsRestauration\SeedBundle\Command\Seed`
- Must have a class name that ends by `Seed`
- Must call `setSeedName` in the configure method

```php
<?php

namespace AcmeBundle\ISOBundle\Seeds;

use DsRestauration\SeedBundle\Command\Seed;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Parser;
use AcmeBundle\ISOBundle\Entity\Country;

class CountrySeed extends Seed
{

    protected function configure()
    {
        //The seed won't load if this is not set
        //The resulting command will be {prefix}:country
        $this
            ->setSeedName('country');

        parent::configure();
    }

    public function load(InputInterface $input, OutputInterface $output){

        //Doctrine logging eats a lot of memory, this is a wrapper to disable logging
        $this->disableDoctrineLogging();

        //Access doctrine through $this->doctrine
        $countryRepository = $this->doctrine->getRepository('AcmeISOBundle:Country');

        $yaml = new Parser();

        //for example, using umpirsky/country-list (lazy yaml)
        $countries = $yaml->parse(file_get_contents('vendor/umpirsky/country-list/country/cldr/fr/country.yaml'));

        foreach ($countries as $id => $country) {

            if($countryRepository->findOneById($id)) {
                continue;
            }

            $e = new Country();

            $e->setId($id);
            $e->setName($country);

            //Doctrine manager is also available
            $this->manager->persist($e);

            $this->manager->flush();
        }

        $this->manager->clear();
    }

    public function getOrder() {
      return 0;
    }
}
```

## Loading a seed

The SeedBundle gives you two default commands and one for each seed you made. With the previous example, I'd have:

```
app/console seed:load #calls the load method of every seed
app/console seed:unload #calls the unload method of every seed
app/console seed:country
```

The global `seed:load` and `seed:unload` allow you to run multiple seeds in one command. You can of course skip seeds `app/console seed:load --skip Town` but also name the one you want `app/console seed:load Country`. For more informations, please use `app/console seed:load --help`.

## Seed order

Every seed has a `getOrder` method that is used to sort them. The default value is `0`.

## Licence

```
The MIT License (MIT)

Copyright (c) 2019 Ds Restauration
Copyright for the original project (Soyuka\SeedBundle) is held by Antoine Bluchet, 2015

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```
