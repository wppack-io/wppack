<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

interface NormalizerAwareInterface
{
    public function setNormalizer(NormalizerInterface $normalizer): void;
}
