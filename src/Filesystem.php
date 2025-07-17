<?php

namespace uuf6429\AMPOS;

use ErrorException;
use RuntimeException;

final class Filesystem
{
    /**
     * @throws RuntimeException
     */
    public function readFile(string $fileName): string
    {
        try {
            $result = self::callSafely(static fn() => file_get_contents($fileName));
        } catch (ErrorException $e) {
            throw new RuntimeException(
                sprintf('File "%s" cannot be read: %s', $fileName, $e->getMessage()),
                previous: $e,
            );
        }

        assert($result !== false, 'file_get_contents() should not return false without emitting a PHP warning');

        return $result;
    }

    public function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        try {
            $result = self::callSafely(static fn() => mkdir($path, 0777, true));

            assert($result !== false, 'mkdir() should not return false without emitting a PHP warning');
        } catch (ErrorException $e) {
            // @codeCoverageIgnoreStart
            if (is_dir($path)) {
                // Some other concurrent process created the directory.
                return;
            }
            // @codeCoverageIgnoreEnd

            throw new RuntimeException(
                sprintf('Path at "%s" cannot be created: %s', $path, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @template TResult
     *
     * @param (callable(): TResult) $callback
     *
     * @return TResult
     *
     * @throws ErrorException
     */
    private static function callSafely(callable $callback): mixed
    {
        set_error_handler(
            static fn(int $severity, string $message, string $file, int $line) => throw new ErrorException($message, 0, $severity, $file, $line)
        );

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
