<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Exception;

class NoHandlerForMessageException extends \LogicException implements ExceptionInterface {}
