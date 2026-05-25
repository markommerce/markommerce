<?php

declare(strict_types=1);

namespace Markommerce\Config\Proxy;

class ProxyAutoloader
{
    private const string GENERATED_NAMESPACE_PREFIX = 'Markommerce\\Config\\Generated\\';

    public function __construct(
        private readonly string $targetDir,
    ) {}

    /**
     * Registers this autoloader with PHP's SPL autoload stack.
     */
    public function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $this->load($class);
        });
    }

    /**
     * Attempts to load the given class if it is under the Generated namespace.
     *
     * @return bool True if the class was loaded, false otherwise
     */
    public function load(string $class): bool
    {
        if (!str_starts_with($class, self::GENERATED_NAMESPACE_PREFIX)) {
            return false;
        }

        $relative = substr($class, strlen(self::GENERATED_NAMESPACE_PREFIX));
        $filePath = rtrim($this->targetDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relative)
            . '.php';

        if (!file_exists($filePath)) {
            return false;
        }

        require_once $filePath;

        return true;
    }
}
