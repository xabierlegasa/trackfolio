<?php

namespace App\Portfolio\Domain\Entity;

use App\User\Domain\Entity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SnapshotCalculationProcess extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'snapshot_calculation_processes';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'started_from',
        'deleted_snapshots',
        'finished_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'started_from' => 'date',
        'deleted_snapshots' => 'integer',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            SnapshotCalculationProcessLog::class,
            'snapshot_calculation_process_id'
        );
    }
}
