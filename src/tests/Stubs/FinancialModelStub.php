<?php

declare(strict_types=1);

namespace Tests\Stubs;

use App\Traits\BelongsToUser;
use App\Traits\HasAuditTrail;
use App\Traits\HasMonetaryFields;
use App\Traits\SoftDeletesWithNullify;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinancialModelStub extends Model
{
    use BelongsToUser;
    use HasAuditTrail;
    use SoftDeletesWithNullify;
    use HasMonetaryFields;

    protected $table = 'financial_model_stubs';
    protected $guarded = [];

    protected array $auditableFields = ['name', 'amount_centavos', 'category'];

    protected function monetaryFields(): array
    {
        return ['amount_centavos'];
    }

    protected function nullifyOnDelete(): array
    {
        return ['dependents' => 'parent_stub_id'];
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(DependentModelStub::class, 'parent_stub_id');
    }
}
