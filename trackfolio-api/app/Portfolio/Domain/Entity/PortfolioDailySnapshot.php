<?php

namespace App\Portfolio\Domain\Entity;

use App\User\Domain\Entity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioDailySnapshot extends Model
{
    protected $table = 'portfolio_daily_snapshots';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'snapshot_date',
        'balance_eur_min_unit',
        'portfolio_eur_min_unit',
        'leverage_eur_min_unit',
        'day_change_eur_min_unit',
        'total_gain_loss_eur_min_unit',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'snapshot_date' => 'date',
        'balance_eur_min_unit' => 'integer',
        'portfolio_eur_min_unit' => 'integer',
        'leverage_eur_min_unit' => 'integer',
        'day_change_eur_min_unit' => 'integer',
        'total_gain_loss_eur_min_unit' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
