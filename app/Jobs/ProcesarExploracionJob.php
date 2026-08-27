<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExploracionActiva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Exploraciones\App\ProcesarExploracionService;

class ProcesarExploracionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $exploracionId,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(ProcesarExploracionService $service): void
    {
        $exploracion = ExploracionActiva::with('team.members', 'habitat')
            ->find($this->exploracionId);

        if ($exploracion !== null) {
            $service->procesar($exploracion);
        }
    }
}
