<?php

namespace Goodport\AiJournal\Console;

use Goodport\AiJournal\Journal\Pruning\PruneJournal;
use Illuminate\Console\Command;

final class PruneJournalCommand extends Command
{
    protected $signature = 'ai-journal:prune
        {--days= : Retention period in days}
        {--dry-run : Only count records}';

    protected $description = 'Delete old AI journal records';

    public function handle(PruneJournal $pruneJournal): int
    {
        $days = $this->option('days');

        if ($days !== null) {
            if (! is_string($days) || preg_match('/^\d+$/', $days) !== 1 || (int) $days <= 0) {
                $this->error('The --days option must be an integer greater than 0.');

                return self::FAILURE;
            }

            $days = (int) $days;
        }

        $count = $pruneJournal->prune($days, (bool) $this->option('dry-run'));

        $this->info($this->option('dry-run')
            ? "Would delete: {$count}"
            : "Deleted: {$count}");

        return self::SUCCESS;
    }
}
