<?php

namespace uuf6429\AMPOS;

use FFI;

final class Kernel
{
    private static Kernel $instance;
    public private(set) bool $shuttingDown = false;

    private function __construct()
    {

    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @todo Eventually make it into an infinite loop
     */
    public function run(): void
    {
        for ($i = 5; $i > 0; $i--) {
            $this->writeMsg("Shutting down in $i...");
            sleep(1);
        }

        readline('Press enter to power off...');

        $this->exit();
    }

    public function panic(string $message): void
    {
        $this->writeErr("PANIC: $message");
        $this->abort();
    }

    /**
     * Shuts down the system gracefully - closing (and waiting for) any running processes, cleaning up resources etc.
     */
    public function exit(): void
    {
        if ($this->shuttingDown) {
            $this->panic('Already shutting down!');
        }

        $this->shuttingDown = true;
        $this->writeMsg('Shutting down...');

        // TODO here trigger events and wait for services to close

        $this->powerOff();
    }

    /**
     * Immediately power-off the system, without any cleaning up.
     */
    public function abort(): void
    {
        $this->writeMsg('Terminating...');
        $this->powerOff();
    }

    private function powerOff(): void
    {
        $ffi = FFI::cdef('int reboot(int);');
        $ffi->reboot(0x4321fedc); // LINUX_REBOOT_CMD_POWER_OFF
    }

    private function writeMsg(string $message): void
    {
        file_put_contents('php://stdout', "$message\n");
    }

    private function writeErr(string $message): void
    {
        file_put_contents('php://stderr', "$message\n");
    }
}
