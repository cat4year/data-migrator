<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class PintFileCommand extends Command
{
    /** @var string */
    protected $signature = 'app:pint {--file= : The path to file for pint}';

    /** @var string */
    protected $description = 'Pint php file';

    public function handle(): void
    {
        $process = new Process(
            command: ['composer', 'pint', '-q',  $this->input->getOption('file')],
        );
        $process->setTimeout(null);

        try {
            $process->mustRun();
            $this->info($process->getOutput());
        } catch (ProcessFailedException $e) {
            $this->error($e->getMessage());
        }
    }
}
