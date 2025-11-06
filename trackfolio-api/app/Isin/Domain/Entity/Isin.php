<?php

namespace App\Isin\Domain\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Isin extends Model
{
    use HasFactory;

    protected $table = 'isins';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'isin',
        'symbol',
        'description',
        'type',
        'display_symbol',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ISIN

    /**
     * Get the ISIN code.
     *
     * @return string
     */
    public function isin(): string
    {
        return $this->isin;
    }

    /**
     * Set the ISIN code.
     *
     * @param string $isin
     * @return $this
     */
    public function setIsin(string $isin): self
    {
        $this->isin = $isin;
        return $this;
    }

    // Symbol

    /**
     * Get the symbol.
     *
     * @return string
     */
    public function symbol(): string
    {
        return $this->symbol;
    }

    /**
     * Set the symbol.
     *
     * @param string $symbol
     * @return $this
     */
    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    // Description

    /**
     * Get the description.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the description.
     *
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // Type

    /**
     * Get the type.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Set the type.
     *
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    // Display Symbol

    /**
     * Get the display symbol.
     *
     * @return string
     */
    public function displaySymbol(): string
    {
        return $this->display_symbol;
    }

    /**
     * Set the display symbol.
     *
     * @param string $displaySymbol
     * @return $this
     */
    public function setDisplaySymbol(string $displaySymbol): self
    {
        $this->display_symbol = $displaySymbol;
        return $this;
    }
}
