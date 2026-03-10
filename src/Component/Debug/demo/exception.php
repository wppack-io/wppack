<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\FlattenException;

// --- Build a realistic exception chain ---

function simulateDatabaseQuery(string $sql): void
{
    throw new \PDOException(
        'SQLSTATE[HY000] [2002] Connection refused',
        2002,
    );
}

function fetchPostFromDatabase(int $postId): void
{
    try {
        simulateDatabaseQuery("SELECT * FROM wp_posts WHERE ID = {$postId}");
    } catch (\PDOException $e) {
        throw new \RuntimeException(
            "Failed to fetch post #{$postId} from database",
            0,
            $e,
        );
    }
}

function renderSinglePost(int $postId): void
{
    fetchPostFromDatabase($postId);
}

// Capture the exception
$exception = null;
try {
    renderSinglePost(42);
} catch (\Throwable $e) {
    $exception = $e;
}

if ($exception === null) {
    echo 'No exception was thrown.';
    exit(0);
}

// Render the exception page
$flatException = FlattenException::createFromThrowable($exception);
$renderer = new ErrorRenderer();
echo $renderer->render($flatException);
