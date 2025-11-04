<?php

namespace App\DegiroTransaction\Domain\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\User\Domain\Entity\User;

class DegiroTransaction extends Model
{
    use HasFactory;

    protected $table = 'degiro_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'time',
        'product',
        'isin',
        'reference',
        'venue',
        'quantity',
        'price_min_unit',
        'price_currency',
        'local_value_min_unit',
        'local_value_currency',
        'value_min_unit',
        'value_currency',
        'exchange_rate',
        'transaction_and_or_third',
        'transaction_currency',
        'total_min_unit',
        'total_currency',
        'order_id',
        'custom_content_hash',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

