<?php

namespace App\User\Domain\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLeverage extends Model
{
    protected $table = 'user_leverages';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'amount_eur_min_unit',
        'recorded_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_eur_min_unit' => 'integer',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
