<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

trait DenormalizerAwareTrait
{
    private DenormalizerInterface $denormalizer;

    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->denormalizer = $denormalizer;
    }
}
