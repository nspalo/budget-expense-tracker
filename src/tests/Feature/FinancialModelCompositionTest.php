<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Scopes\UserScope;
use App\Traits\BelongsToUser;
use App\Traits\HasAuditTrail;
use App\Traits\HasMonetaryFields;
use App\Traits\SoftDeletesWithNullify;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\DependentModelStub;
use Tests\Stubs\FinancialModelStub;
use Tests\TestCase;

final class FinancialModelCompositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('financial_model_stubs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->bigInteger('amount_centavos')->default(0);
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dependent_model_stubs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_stub_id')->nullable();
            $table->string('label');
            $table->timestamps();
        });

        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dependent_model_stubs');
        Schema::dropIfExists('financial_model_stubs');

        parent::tearDown();
    }

    #[Test]
    public function model_uses_all_four_traits(): void
    {
        $traits = class_uses_recursive(FinancialModelStub::class);

        $this->assertArrayHasKey(BelongsToUser::class, $traits);
        $this->assertArrayHasKey(HasAuditTrail::class, $traits);
        $this->assertArrayHasKey(SoftDeletesWithNullify::class, $traits);
        $this->assertArrayHasKey(HasMonetaryFields::class, $traits);
    }

    #[Test]
    public function all_traits_boot_without_conflicts(): void
    {
        $model = new FinancialModelStub();

        // Verify global scopes registered (BelongsToUser)
        $scopes = $model->getGlobalScopes();
        $this->assertArrayHasKey(UserScope::class, $scopes);

        // Verify monetary fields cast is registered (HasMonetaryFields)
        $casts = $model->getCasts();
        $this->assertArrayHasKey('amount_centavos', $casts);

        // Verify soft deletes column recognized (SoftDeletesWithNullify)
        $this->assertSame('deleted_at', $model->getDeletedAtColumn());
    }

    #[Test]
    public function model_creates_with_user_scope_and_monetary_field(): void
    {
        $stub = FinancialModelStub::create([
            'name' => 'Test Entry',
            'amount_centavos' => 150000,
            'category' => 'utilities',
        ]);

        $this->assertSame($this->user->id, $stub->user_id);
        $this->assertInstanceOf(Money::class, $stub->amount_centavos);
        $this->assertSame(150000, $stub->amount_centavos->toCentavos());
    }

    #[Test]
    public function soft_delete_nullifies_dependents_without_conflict(): void
    {
        $stub = FinancialModelStub::create([
            'name' => 'Parent Record',
            'amount_centavos' => 50000,
            'category' => 'food',
        ]);

        $dependent = DependentModelStub::create([
            'parent_stub_id' => $stub->id,
            'label' => 'Dependent 1',
        ]);

        $stub->delete();

        $dependent->refresh();
        $this->assertNull($dependent->parent_stub_id);
        $this->assertSoftDeleted('financial_model_stubs', ['id' => $stub->id]);
    }

    #[Test]
    public function restore_does_not_relink_nullified_foreign_keys(): void
    {
        $stub = FinancialModelStub::create([
            'name' => 'Restorable',
            'amount_centavos' => 25000,
            'category' => 'transport',
        ]);

        $dependent = DependentModelStub::create([
            'parent_stub_id' => $stub->id,
            'label' => 'Child',
        ]);

        $stub->delete();
        $stub->restore();
        $stub->refresh();
        $dependent->refresh();

        $this->assertNull($stub->deleted_at);
        $this->assertNull($dependent->parent_stub_id);
    }
}
