<?php

namespace App\Dummy\Application\Job;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Minimal job to verify RabbitMQ queue_one + Supervisor worker.
 *
 * Pattern for async use cases:
 * 1. Create a Job with $queue = 'queue_one' (or ->onQueue('queue_one')).
 * 2. In handle(), resolve and call the UseCase.
 *
 * Example:
 *   SomeUseCaseJob::dispatch($dto);
 *   // handle(): app(SomeUseCase::class)->execute($dto);
 */
class PingQueueOneJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $message = 'ping'
    ) {
        $this->onQueue('queue_one');
    }

    public function handle(): void
    {
        Log::info('PingQueueOneJob processed', [
            'message' => $this->message,
            'queue' => 'queue_one',
        ]);
    }
}
