<?php

namespace App\AccountStatement\Domain\Entity;

use App\User\Domain\Entity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountStatement extends Model
{
    protected $table = 'account_statements';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'time',
        'value_date',
        'product',
        'isin',
        'description',
        'fx',
        'change_currency',
        'change_min_unit',
        'balance_currency',
        'balance_min_unit',
        'order_id',
        'custom_content_hash',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'value_date' => 'date',
        'change_min_unit' => 'integer',
        'balance_min_unit' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
