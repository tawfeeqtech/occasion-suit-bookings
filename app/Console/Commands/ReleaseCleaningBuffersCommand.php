<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemMaintenance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseCleaningBuffersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buffer:release-clean-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired cleaning buffer items to available status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        $records = ItemMaintenance::withoutGlobalScopes()
            ->where('status', 'cleaning')
            ->where('expected_ready_at', '<=', $now)
            ->get();

        if ($records->isEmpty()) {
            $this->info('No cleaning items due for release.');

            return Command::SUCCESS;
        }

        $count = 0;
        DB::transaction(function () use ($records, $now, &$count) {
            foreach ($records as $record) {
                $record->update([
                    'status' => 'completed',
                    'actual_ready_at' => $now,
                ]);

                Item::withoutGlobalScopes()
                    ->where('id', $record->item_id)
                    ->update(['status' => 'available']);

                $count++;
            }
        });

        $this->info("Released {$count} items from cleaning buffer to available status.");

        return Command::SUCCESS;
    }
}
