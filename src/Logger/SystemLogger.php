<?php

namespace uuf6429\AMPOS\Logger;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Console\Style\SymfonyStyle;

class SystemLogger implements LoggerInterface
{
    use LoggerTrait;

    private Logger $monolog;

    public function __construct(SymfonyStyle $console)
    {
        $this->monolog = new Logger(
            'system',
            [
                new ConsoleHandler($console),
                new RotatingFileHandler('/log/system.log', 90),
            ],
            [
                new PsrLogMessageProcessor(),
                new IntrospectionProcessor(),
                new ProcessIdProcessor(),
            ]
        );
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->monolog->log($level, $message, $context);
    }
}
