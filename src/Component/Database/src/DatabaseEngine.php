<?php

declare(strict_types=1);

namespace WpPack\Component\Database;

enum DatabaseEngine: string
{
    case MySQL = 'mysql';
    case SQLite = 'sqlite';
    case PostgreSQL = 'pgsql';
}
