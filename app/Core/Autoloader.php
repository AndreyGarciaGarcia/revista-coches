<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoload PSR-4 sin dependencias externas.
 *   App\Core\Router  ->  app/Core/Router.php
 */
final class Autoloader
{
    private const PREFIX = 'App\\';

    private readonly string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/\\');
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): void
    {
        if (!str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
}
