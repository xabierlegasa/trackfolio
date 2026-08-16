<?php

namespace App\Portfolio\Domain\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnapshotCalculationProcessLog extends Model
{
    protected $table = 'snapshot_calculation_process_logs';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'snapshot_calculation_process_id',
        'description',
        'date_processed',
        'isin',
        'symbol',
        'provider_request_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'snapshot_calculation_process_id' => 'integer',
        'date_processed' => 'date',
        'provider_request_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(
            SnapshotCalculationProcess::class,
            'snapshot_calculation_process_id'
        );
    }
}
