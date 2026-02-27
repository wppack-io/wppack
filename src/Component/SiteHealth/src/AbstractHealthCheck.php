<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth;

abstract class AbstractHealthCheck
{
    abstract public function run(): Result;
}
