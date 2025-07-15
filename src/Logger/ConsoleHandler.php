<?php

namespace uuf6429\AMPOS\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly SymfonyStyle $output,
        int|string|Level              $level = Level::Debug,
        bool                          $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        match ($record->level) {
            Level::Emergency, Level::Alert, Level::Critical => $this->output->caution($record->message),
            Level::Error => $this->output->error($record->message),
            Level::Warning => $this->output->warning($record->message),
            Level::Notice => $this->output->note($record->message),
            Level::Info => $this->output->info($record->message),
            Level::Debug => $this->output->writeln($record->message),
        };
    }
}
