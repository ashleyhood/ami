<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$rules = [
    '@Symfony' => true
];

return Config::create()
    ->finder(Finder::create()->in(__DIR__))
    ->setRules($rules)
    ->setUsingCache(true);
