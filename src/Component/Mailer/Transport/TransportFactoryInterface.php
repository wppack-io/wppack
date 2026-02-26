<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

interface TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface;

    public function supports(Dsn $dsn): bool;
}
