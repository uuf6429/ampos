<?php

namespace uuf6429\AMPOS;

use FFI;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Kernel
{
    private static Kernel $instance;
    public private(set) bool $shuttingDown = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SymfonyStyle $console,
    ) {
        self::$instance?->panic('Kernel already initialized');

        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function run(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGCHLD, function (): void {
            while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                $this->logger->debug("Reaped child PID $pid with status $status");
            }
        });

        pcntl_signal(SIGTERM, function () {
            $this->logger->notice('Shutdown requested (SIGTERM)');
            $this->exit();
        });

        $this->logger->info('Supervisor running as PID ' . posix_getpid());

        // TODO do kernel bootstrap

        while (true) {
            // TODO do kernel loop stuff
            sleep(1);
        }
    }

    public function panic(string $message): never
    {
        $this->logger->critical("PANIC: $message");
        $this->abort();
    }

    /**
     * Shuts down the system gracefully - closing (and waiting for) any running processes, cleaning up resources etc.
     */
    public function exit(): never
    {
        if ($this->shuttingDown) {
            $this->panic('Already shutting down!');
        }

        $this->shuttingDown = true;
        $this->logger->notice('Shutting down...');

        // TODO here trigger events and wait for services to close

        $this->console->askHidden('Press [RETURN] to power off...');

        $this->powerOff();
    }

    /**
     * Immediately power-off the system, without any cleaning up.
     */
    public function abort(): never
    {
        $this->logger->emergency('Terminating...');
        $this->powerOff();
    }

    private function powerOff(): never
    {
        $ffi = FFI::cdef('int reboot(int);');
        $ffi->reboot(0x4321fedc); // LINUX_REBOOT_CMD_POWER_OFF
        exit;
    }
}
