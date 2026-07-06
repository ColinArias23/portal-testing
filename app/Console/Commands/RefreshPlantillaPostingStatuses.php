<?php

namespace App\Console\Commands;

use App\Models\PlantillaPosting;
use Illuminate\Console\Command;

class RefreshPlantillaPostingStatuses extends Command
{
    protected $signature = 'plantilla-postings:refresh-status';
    protected $description = 'Auto-update plantilla posting statuses based on vacancies and closing date proximity';

    public function handle(): void
    {
        PlantillaPosting::where('status', '!=', 'Closed')->each(function (PlantillaPosting $posting) {
            $posting->applyAutoStatus();
        });

        $this->info('Plantilla posting statuses refreshed.');
    }
}
