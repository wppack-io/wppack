<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;

final readonly class ImageEditorSubscriber
{
    /**
     * @param class-string $imageEditorClass FQCN of StorageImageEditor
     */
    public function __construct(
        private string $imageEditorClass,
    ) {}

    /**
     * Prepend StorageImageEditor to the editors list.
     */
    #[AsEventListener(event: 'wp_image_editors', priority: 9)]
    public function filterEditors(WordPressEvent $event): void
    {
        /** @var list<class-string> $editors */
        $editors = $event->filterValue;
        array_unshift($editors, $this->imageEditorClass);
        $event->filterValue = $editors;
    }
}
