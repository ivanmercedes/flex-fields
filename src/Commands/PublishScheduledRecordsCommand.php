<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use IvanMercedes\FlexFields\Services\RecordDomainService;

class PublishScheduledRecordsCommand extends Command
{
    protected $signature = 'flex:publish-scheduled';

    protected $description = 'Publish the scheduled FlexFields records whose publish date has passed';

    public function handle(RecordDomainService $records): int
    {
        $published = $records->publishDue();

        $this->components->info("Published {$published} scheduled record(s).");

        return self::SUCCESS;
    }
}
