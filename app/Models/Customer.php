<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'identification_type',
        'identification_number',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'tax_responsibility',
        'status',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
