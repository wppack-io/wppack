<?php

declare(strict_types=1);

namespace WpPack\Component\Database;

interface TableInterface
{
    public function schema(DatabaseManager $db): string;
}
