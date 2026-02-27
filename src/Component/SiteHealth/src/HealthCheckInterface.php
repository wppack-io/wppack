<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth;

interface HealthCheckInterface
{
    public function run(): Result;
}
