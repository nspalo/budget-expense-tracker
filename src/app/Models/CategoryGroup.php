<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToUser;
use App\Traits\HasAuditTrail;
use App\Traits\SoftDeletesWithNullify;
use Database\Factories\CategoryGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryGroup extends Model
{
    /** @use HasFactory<CategoryGroupFactory> */
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
        'sort_order',
    ];

    /**
     * Get the fields that should be tracked for audit trail.
     *
     * @return list<string>
     */
    protected function getAuditableFields(): array
    {
        return ['name', 'sort_order'];
    }

    /**
     * Define relationship→column mappings for nullification on soft delete.
     *
     * @return array<string, string>
     */
    protected function nullifyOnDelete(): array
    {
        return ['categories' => 'category_group_id'];
    }

    /**
     * Get the categories that belong to this group.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
