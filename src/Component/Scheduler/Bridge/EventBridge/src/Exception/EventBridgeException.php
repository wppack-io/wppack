<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Exception;

use WpPack\Component\Scheduler\Exception\ExceptionInterface;

class EventBridgeException extends \RuntimeException implements ExceptionInterface {}
