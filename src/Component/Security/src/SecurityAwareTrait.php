<?php

declare(strict_types=1);

namespace WpPack\Component\Security;

trait SecurityAwareTrait
{
    private ?Security $security = null;

    /** @internal */
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function getUser(): ?\WP_User
    {
        if ($this->security === null) {
            throw new \LogicException('Security is not available. Register SecurityServiceProvider to use getUser().');
        }

        return $this->security->getUser();
    }

    protected function isGranted(string $attribute, mixed $subject = null): bool
    {
        if ($this->security === null) {
            throw new \LogicException('Security is not available. Register SecurityServiceProvider to use isGranted().');
        }

        return $this->security->isGranted($attribute, $subject);
    }

    protected function denyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if ($this->security === null) {
            throw new \LogicException('Security is not available. Register SecurityServiceProvider to use denyAccessUnlessGranted().');
        }

        $this->security->denyAccessUnlessGranted($attribute, $subject, $message);
    }
}
