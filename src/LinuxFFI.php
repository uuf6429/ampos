<?php

namespace uuf6429\AMPOS;

use FFI;
use RuntimeException;

class LinuxFFI
{
    private const LINUX_REBOOT_CMD_POWER_OFF = 0x4321fedc;
    private const VT_ACTIVATE = 0x5606;
    private const VT_WAITACTIVE = 0x5607;
    private const O_RDWR = 2;
    private const TIOCSCTTY = 0x540E;

    private readonly FFI $ffi;

    public function __construct()
    {
        $this->ffi = FFI::cdef(
            <<<C
                typedef struct _IO_FILE FILE;
                int reboot(int);
                int fileno(FILE *stream);
                int dup2(int oldfd, int newfd);
                int mount(const char *source, const char *target,
                          const char *filesystemtype, unsigned long mountflags,
                          const void *data);
                extern int errno;
                int open(const char *pathname, int flags);
                int ioctl(int fd, unsigned long request, ...);
                int close(int fd);
                int setsid(void);
                int fork(void);
                int execl(const char *path, const char *arg, ...);
            C
        );
    }

    public function powerOff(): never
    {
        $this->ffi->reboot(self::LINUX_REBOOT_CMD_POWER_OFF);
        exit;
    }

    /**
     * @todo Wrap in higher level function.
     */
    public function fileno($resource): int
    {
        return $this->ffi->fileno(FFI::addr(FFI::cast("FILE *", $resource)));
    }

    /**
     * @todo Wrap in higher level function.
     */
    public function dup2(int $oldDescriptor, int $newDescriptor): void
    {
        $this->ffi->dup2($oldDescriptor, $newDescriptor);
    }

    public function mount(?string $source, string $target, string $fstype, int $flags = 0, ?string $data = null): void
    {
        if ($this->ffi->mount($source, $target, $fstype, $flags, $data) !== 0) {
            throw new RuntimeException(sprintf(
                'Mounting %s to %s failed (error %d): %s',
                $source,
                $target,
                $this->ffi->errno,
                posix_strerror($this->ffi->errno)
            ));
        }
    }

    public function switchToVirtualTerminal(int $vtIndex): void
    {
        $fd = $this->ffi->open("/dev/console", self::O_RDWR);
        if ($fd < 0) {
            throw new RuntimeException('Failed to open stream to controlling terminal /dev/console.');
        }

        $ret = $this->ffi->ioctl($fd, self::VT_ACTIVATE, $vtIndex);
        if ($ret !== 0) {
            throw new RuntimeException(sprintf(
                'Failed to switch VT %s (error %d): %s.',
                $vtIndex,
                $this->ffi->errno,
                posix_strerror($this->ffi->errno)
            ));
        }

        $ret = $this->ffi->ioctl($fd, self::VT_WAITACTIVE, $vtIndex);
        if ($ret !== 0) {
            throw new RuntimeException(sprintf(
                'Failed to wait for switching VT %s (error %d): %s.',
                $vtIndex,
                $this->ffi->errno,
                posix_strerror($this->ffi->errno)
            ));
        }

        $this->ffi->close($fd);
    }

    public function exec(string $path, array $args = [], bool $async = true): ?int
    {
        $fd = $this->ffi->open('/dev/tty2', self::O_RDWR);

        $pid = $this->ffi->fork();
        if ($pid === 0) {
            $this->ffi->setsid();
            $this->ffi->ioctl($fd, self::TIOCSCTTY, 0);
            $this->ffi->dup2($fd, 0);
            $this->ffi->dup2($fd, 1);
            $this->ffi->dup2($fd, 2);
            $this->ffi->execl($path, ...[basename($path), ...$args, null]);

            echo sprintf(
                'Execution of `%s %s` failed (error %d): %s.',
                $path,
                implode(' ', $args),
                $this->ffi->errno,
                posix_strerror($this->ffi->errno)
            );
            exit(127);
        }

        if ($pid === -1) {
            throw new RuntimeException(sprintf(
                'Failed to execute `%s %s` as subprocess.',
                $path,
                implode(' ', $args)
            ));
        }

        if (!$async) {
            return null;
        }

        pcntl_waitpid($pid, $status);
        return pcntl_wexitstatus($status);
    }
}
