<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use App\Traits\BelongsToUser;
use App\Traits\HasAuditTrail;
use App\Traits\SoftDeletesWithNullify;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToUser;
    use HasAuditTrail;
    use HasFactory;
    use SoftDeletesWithNullify;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'icon',
        'color',
        'sort_order',
        'category_group_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
        ];
    }

    /**
     * Get the fields that should be tracked for audit trail.
     *
     * @return list<string>
     */
    protected function getAuditableFields(): array
    {
        return ['name', 'type', 'icon', 'color', 'sort_order', 'category_group_id'];
    }

    /**
     * Define relationship→column mappings for nullification on soft delete.
     *
     * @return array<string, string>
     */
    protected function nullifyOnDelete(): array
    {
        return ['transactions' => 'category_id'];
    }

    /**
     * Get the category group that this category belongs to.
     */
    public function categoryGroup(): BelongsTo
    {
        return $this->belongsTo(CategoryGroup::class);
    }
}
