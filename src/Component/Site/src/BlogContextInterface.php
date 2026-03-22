<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

interface BlogContextInterface
{
    public function getCurrentBlogId(): int;

    public function isMultisite(): bool;

    public function getMainSiteId(): int;

    public function isSwitched(): bool;
}
