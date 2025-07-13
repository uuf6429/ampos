<?php

set_error_handler(static function (int $severity, string $message, ?string $file = null, ?int $line = null): bool {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

set_exception_handler(static function (Throwable $e): void {
    file_put_contents('php://stderr', "PANIC: Unhandled exception: $e\n");

    readline('Press enter to continue...');

    $ffi = FFI::cdef('int reboot(int);');
    $ffi->reboot(0x4321fedc); // LINUX_REBOOT_CMD_POWER_OFF

    exit(1);
});

register_shutdown_function(static function (): void {
    if (($lastError = error_get_last()) !== null) {
        throw new ErrorException($lastError['message'], 0, $lastError['type'], $lastError['file'], $lastError['line']);
    }
});

require __DIR__ . '/vendor/autoload.php';

uuf6429\AMPOS\Kernel::getInstance()->run();
