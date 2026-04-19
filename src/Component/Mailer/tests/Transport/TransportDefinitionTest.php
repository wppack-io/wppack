<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Transport\TransportDefinition;
use WPPack\Component\Mailer\Transport\TransportField;

#[CoversClass(TransportDefinition::class)]
final class TransportDefinitionTest extends TestCase
{
    #[Test]
    public function buildDsnWithUserAndPassword(): void
    {
        $def = new TransportDefinition('smtp', 'SMTP', [
            new TransportField('user', 'User', dsnPart: 'user'),
            new TransportField('pass', 'Pass', type: 'password', dsnPart: 'password'),
            new TransportField('host', 'Host', dsnPart: 'host'),
            new TransportField('port', 'Port', dsnPart: 'port'),
        ]);
        $dsn = $def->buildDsn(['user' => 'admin', 'pass' => 'secret', 'host' => 'mail.example.com', 'port' => '587']);
        self::assertSame('smtp://admin:secret@mail.example.com:587', $dsn);
    }

    #[Test]
    public function buildDsnWithQueryOptions(): void
    {
        $def = new TransportDefinition('ses+api', 'SES', [
            new TransportField('region', 'Region', dsnPart: 'option:region'),
            new TransportField('configSet', 'Config Set', dsnPart: 'option:configuration_set'),
        ]);
        $dsn = $def->buildDsn(['region' => 'us-east-1', 'configSet' => 'my-set']);
        self::assertSame('ses+api://default?region=us-east-1&configuration_set=my-set', $dsn);
    }

    #[Test]
    public function buildDsnSkipsEmptyValues(): void
    {
        $def = new TransportDefinition('native', 'Native', [
            new TransportField('host', 'Host', dsnPart: 'host'),
        ]);
        self::assertSame('native://default', $def->buildDsn(['host' => '']));
        self::assertSame('native://default', $def->buildDsn([]));
    }

    #[Test]
    public function buildDsnWithOnlyUser(): void
    {
        $def = new TransportDefinition('ses+api', 'SES', [
            new TransportField('key', 'Key', dsnPart: 'user'),
        ]);
        self::assertSame('ses+api://AKID@default', $def->buildDsn(['key' => 'AKID']));
    }

    #[Test]
    public function buildDsnUsesFieldDefault(): void
    {
        $def = new TransportDefinition('smtp', 'SMTP', [
            new TransportField('host', 'Host', default: 'localhost', dsnPart: 'host'),
        ]);
        self::assertSame('smtp://localhost', $def->buildDsn([]));
    }

    #[Test]
    public function schemeAndLabelProperties(): void
    {
        $def = new TransportDefinition('ses+api', 'Amazon SES (API)');
        self::assertSame('ses+api', $def->scheme);
        self::assertSame('Amazon SES (API)', $def->label);
        self::assertSame([], $def->fields);
    }
}
