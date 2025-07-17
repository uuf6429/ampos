<?php

namespace uuf6429\AMPOS;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Kernel
{
    private static Kernel $instance;
    public private(set) bool $shuttingDown = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SymfonyStyle    $console,
        private readonly LinuxFFI        $ffi = new LinuxFFI(),
        private readonly Filesystem      $fs = new Filesystem(),
    ) {
        if (isset(self::$instance)) {
            $this->panic('Kernel already initialized');
        }

        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function run(): void
    {
        $this->setUpMountPoints();
        $this->setUpSignalHandlers();

        $this->logger->info('Supervisor running as PID ' . posix_getpid());

        $this->startShell(); // TODO do kernel bootstrap

        while (true) {
            // TODO do kernel loop stuff
            sleep(1);
//            echo '.';
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

        $this->ffi->powerOff();
    }

    /**
     * Immediately power-off the system, without any cleaning up.
     */
    public function abort(): never
    {
        $this->logger->emergency('Terminating...');
        $this->ffi->powerOff();
    }

    private function setUpMountPoints(): void
    {
        $this->fs->ensureDirectoryExists('/proc');
        $this->ffi->mount('proc', '/proc', 'proc');

        $this->fs->ensureDirectoryExists('/sys');
        $this->ffi->mount('sysfs', '/sys', 'sysfs');

        $this->fs->ensureDirectoryExists('/dev');
        $this->ffi->mount('devtmpfs', '/dev', 'devtmpfs');

        $this->fs->ensureDirectoryExists('/dev/pts');
        $this->ffi->mount('devpts', '/dev/pts', 'devpts');

        $this->fs->ensureDirectoryExists('/dev/shm');
        $this->ffi->mount('tmpfs', '/dev/shm', 'tmpfs');
    }

    private function setUpSignalHandlers(): void
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
    }

    private function startShell(): void
    {
        $this->ffi->switchToVirtualTerminal(2);
        $this->ffi->exec('/bin/php', ['/bin/psysh']); // TODO check why phar won't work ("file not found") eventhough we have "env" now
    }
}
