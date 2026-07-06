<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Traits\BelongsToUser;
use App\Traits\HasAuditTrail;
use App\Traits\HasMonetaryFields;
use App\Traits\SoftDeletesWithNullify;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use BelongsToUser;
    use HasAuditTrail;
    use HasFactory;
    use HasMonetaryFields;
    use SoftDeletesWithNullify;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'balance_centavos',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
        ];
    }

    /**
     * Declare the monetary fields for automatic MoneyCast.
     *
     * @return array<int, string>
     */
    protected function monetaryFields(): array
    {
        return ['balance_centavos'];
    }

    /**
     * Declare the fields tracked by the audit trail.
     *
     * @return array<int, string>
     */
    protected function getAuditableFields(): array
    {
        return ['name', 'type', 'balance_centavos'];
    }

    /**
     * Define relationship→column mappings for nullification on soft delete.
     *
     * @return array<string, string>
     */
    protected function nullifyOnDelete(): array
    {
        return ['transactions' => 'account_id'];
    }
}
