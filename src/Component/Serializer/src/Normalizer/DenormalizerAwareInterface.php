<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

interface DenormalizerAwareInterface
{
    public function setDenormalizer(DenormalizerInterface $denormalizer): void;
}
