<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Command;

use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Input\InputOption;
use WpPack\Component\Console\Output\OutputStyle;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

#[AsCommand(
    name: 'media:migrate-storage',
    description: 'Migrate local uploads to object storage',
)]
final class MigrateCommand extends AbstractCommand
{
    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        private readonly StorageConfiguration $config,
    ) {}

    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addOption(new InputOption(
                name: 'dry-run',
                mode: InputOption::VALUE_NONE,
                description: 'Simulate the migration without actually copying files',
            ))
            ->addOption(new InputOption(
                name: 'batch-size',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Number of attachments to process per batch',
                default: 100,
            ));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        if ($dryRun) {
            $output->warning('Dry run mode enabled. No files will be copied.');
        }

        $output->info(sprintf(
            'Migrating uploads to %s://%s/%s',
            $this->config->protocol,
            $this->config->bucket,
            $this->config->prefix,
        ));

        $uploadDir = wp_upload_dir();
        /** @var string $baseDir */
        $baseDir = $uploadDir['basedir'];

        $offset = 0;
        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        do {
            /** @var list<\WP_Post> $attachments */
            $attachments = get_posts([
                'post_type' => 'attachment',
                'posts_per_page' => $batchSize,
                'offset' => $offset,
                'post_status' => 'any',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);

            if ($attachments === []) {
                break;
            }

            $progress = $output->progress(\count($attachments), 'Migrating batch');

            foreach ($attachments as $attachment) {
                $attachedFile = get_post_meta($attachment->ID, '_wp_attached_file', true);
                if (!\is_string($attachedFile) || $attachedFile === '') {
                    $progress->advance();
                    $totalSkipped++;

                    continue;
                }

                $filesToMigrate = $this->collectFiles($attachedFile, $attachment->ID, $baseDir);

                foreach ($filesToMigrate as $localPath => $storageKey) {
                    if (!file_exists($localPath)) {
                        $totalSkipped++;

                        continue;
                    }

                    if ($this->adapter->exists($storageKey)) {
                        $totalSkipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $output->line(sprintf('  [DRY RUN] %s -> %s', $localPath, $storageKey));
                        $totalMigrated++;

                        continue;
                    }

                    try {
                        $stream = fopen($localPath, 'rb');
                        if ($stream === false) {
                            $output->warning(sprintf('  Failed to open: %s', $localPath));
                            $totalFailed++;

                            continue;
                        }

                        $this->adapter->writeStream($storageKey, $stream);
                        fclose($stream);
                        $totalMigrated++;
                    } catch (\Throwable $e) {
                        $output->warning(sprintf('  Failed to migrate %s: %s', $localPath, $e->getMessage()));
                        $totalFailed++;
                    }
                }

                $progress->advance();
            }

            $progress->finish();
            $offset += $batchSize;
        } while (\count($attachments) === $batchSize);

        $output->newLine();
        $output->info(sprintf('Migrated: %d, Skipped: %d, Failed: %d', $totalMigrated, $totalSkipped, $totalFailed));

        if ($totalFailed > 0) {
            $output->warning('Some files failed to migrate. Re-run the command to retry.');

            return self::FAILURE;
        }

        $output->success('Migration completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Collect all files associated with an attachment (original + thumbnails).
     *
     * @return array<string, string> Map of local path => storage key
     */
    private function collectFiles(string $attachedFile, int $attachmentId, string $baseDir): array
    {
        $files = [];

        // Original file
        $localPath = $baseDir . '/' . $attachedFile;
        $storageKey = $this->config->prefix . '/' . $attachedFile;
        $files[$localPath] = $storageKey;

        // Thumbnails
        $metadata = wp_get_attachment_metadata($attachmentId);
        if (\is_array($metadata) && isset($metadata['sizes']) && \is_array($metadata['sizes'])) {
            $directory = \dirname($attachedFile);
            /** @var array<string, mixed> $size */
            foreach ($metadata['sizes'] as $size) {
                if (isset($size['file']) && \is_string($size['file'])) {
                    $thumbPath = $directory . '/' . $size['file'];
                    $files[$baseDir . '/' . $thumbPath] = $this->config->prefix . '/' . $thumbPath;
                }
            }
        }

        return $files;
    }
}
