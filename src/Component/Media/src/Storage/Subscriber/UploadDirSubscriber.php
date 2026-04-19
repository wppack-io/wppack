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

namespace WPPack\Component\Media\Storage\Subscriber;

use WPPack\Component\EventDispatcher\Attribute\AsEventListener;
use WPPack\Component\EventDispatcher\WordPressEvent;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Media\Storage\UrlResolver;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;

final readonly class UploadDirSubscriber
{
    public function __construct(
        private StorageConfiguration $config,
        private UrlResolver $urlResolver,
        private BlogContextInterface $blogContext = new BlogContext(),
    ) {}

    /**
     * Rewrite upload directory paths and URLs to use object storage.
     */
    #[AsEventListener(event: 'upload_dir')]
    public function filterUploadDir(WordPressEvent $event): void
    {
        /** @var array<string, string> $dirs */
        $dirs = $event->filterValue;

        $basePath = sprintf('%s://%s/%s', $this->config->protocol, $this->config->bucket, $this->config->prefix);
        $baseUrl = $this->urlResolver->resolve($this->config->prefix);

        // Preserve multisite subdirectory (e.g., /sites/2/)
        $siteSubdir = '';
        if ($this->blogContext->isMultisite()) {
            $blogId = $this->blogContext->getCurrentBlogId();
            if ($blogId !== $this->blogContext->getMainSiteId()) {
                $siteSubdir = '/sites/' . $blogId;
            }
        }

        // subdir contains date-based subdirectory (e.g., /2024/01)
        $subdir = $dirs['subdir'] ?? '';

        $dirs['path'] = $basePath . $siteSubdir . $subdir;
        $dirs['url'] = $baseUrl . $siteSubdir . $subdir;
        $dirs['basedir'] = $basePath . $siteSubdir;
        $dirs['baseurl'] = $baseUrl . $siteSubdir;

        $event->filterValue = $dirs;
    }
}
