<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Exception;

use WpPack\Component\Role\Exception\AccessDeniedException as RoleAccessDeniedException;

class AccessDeniedException extends RoleAccessDeniedException implements ExceptionInterface {}
