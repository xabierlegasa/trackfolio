<?php

namespace App\Portfolio\Application\Job;

use App\Portfolio\Application\UseCase\RecalculateEvolutionDayUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateEvolutionDayJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly string $date,
        public readonly int $processId,
        public readonly ?string $untilDate = null,
    ) {
        $this->onQueue('queue_one');
    }

    public function handle(RecalculateEvolutionDayUseCase $recalculateEvolutionDayUseCase): void
    {
        $recalculateEvolutionDayUseCase->execute(
            $this->userId,
            $this->date,
            $this->processId,
            $this->untilDate,
        );
    }
}
