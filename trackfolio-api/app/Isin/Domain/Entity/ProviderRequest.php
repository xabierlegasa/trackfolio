<?php

namespace App\Isin\Domain\Entity;

use Illuminate\Database\Eloquent\Model;

class ProviderRequest extends Model
{
    protected $table = 'provider_requests';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'call_type',
        'method',
        'url',
        'http_status',
        'response_body',
        'duration_ms',
        'success',
        'error_message',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'http_status' => 'integer',
        'duration_ms' => 'integer',
        'success' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
