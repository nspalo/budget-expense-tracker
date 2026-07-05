<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Services\AuditService;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A spy replacement for the final AuditService class.
 * Records all method calls for assertion in tests.
 */
class AuditServiceSpy
{
    /** @var array<int, array{method: string, args: array}> */
    public array $calls = [];

    public function logCreation(Model $model): void
    {
        $this->calls[] = ['method' => 'logCreation', 'args' => [$model]];
    }

    public function logUpdate(Model $model, array $oldValues, array $newValues): void
    {
        $this->calls[] = ['method' => 'logUpdate', 'args' => [$model, $oldValues, $newValues]];
    }

    public function logDeletion(Model $model): void
    {
        $this->calls[] = ['method' => 'logDeletion', 'args' => [$model]];
    }

    public function getCallsFor(string $method): array
    {
        return array_values(array_filter($this->calls, fn (array $call) => $call['method'] === $method));
    }

    public function callCount(string $method): int
    {
        return count($this->getCallsFor($method));
    }
}

final class HasAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private AuditServiceSpy $auditSpy;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('auditable_test_models', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('amount')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('auditable_custom_fields_models', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('internal_note')->nullable();
            $table->integer('amount')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->auditSpy = new AuditServiceSpy();
        $this->app->instance(AuditService::class, $this->auditSpy);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('auditable_custom_fields_models');
        Schema::dropIfExists('auditable_test_models');

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calls_log_creation_on_created_event(): void
    {
        $model = new AuditableTestModel();
        $model->name = 'Test Record';
        $model->save();

        $this->assertSame(1, $this->auditSpy->callCount('logCreation'));
        $call = $this->auditSpy->getCallsFor('logCreation')[0];
        $this->assertSame('Test Record', $call['args'][0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calls_log_update_with_changed_auditable_fields(): void
    {
        $model = new AuditableTestModel();
        $model->name = 'Original';
        $model->save();

        $model->name = 'Updated';
        $model->save();

        $this->assertSame(1, $this->auditSpy->callCount('logUpdate'));
        $call = $this->auditSpy->getCallsFor('logUpdate')[0];
        $this->assertSame(['name' => 'Original'], $call['args'][1]);
        $this->assertSame(['name' => 'Updated'], $call['args'][2]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_audit_when_no_auditable_fields_changed(): void
    {
        $model = new AuditableTestModel();
        $model->name = 'Same';
        $model->save();

        // Trigger update with only timestamp change — not an auditable field
        $model->updated_at = now()->addMinute();
        $model->save();

        $this->assertSame(0, $this->auditSpy->callCount('logUpdate'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calls_log_deletion_on_deleting_event(): void
    {
        $model = new AuditableTestModel();
        $model->name = 'To Delete';
        $model->save();

        $model->delete();

        $this->assertSame(1, $this->auditSpy->callCount('logDeletion'));
        $call = $this->auditSpy->getCallsFor('logDeletion')[0];
        $this->assertSame('To Delete', $call['args'][0]->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_auditable_fields_property_when_defined(): void
    {
        $model = new AuditableCustomFieldsModel();
        $model->name = 'Original';
        $model->internal_note = 'Secret';
        $model->save();

        $model->name = 'Updated';
        $model->internal_note = 'New Secret';
        $model->save();

        $this->assertSame(1, $this->auditSpy->callCount('logUpdate'));
        $call = $this->auditSpy->getCallsFor('logUpdate')[0];
        // Only 'name' should be tracked since internal_note is not in $auditableFields
        $this->assertSame(['name' => 'Original'], $call['args'][1]);
        $this->assertSame(['name' => 'Updated'], $call['args'][2]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_audit_when_only_non_auditable_fields_change(): void
    {
        $model = new AuditableCustomFieldsModel();
        $model->name = 'Test';
        $model->internal_note = 'Note 1';
        $model->save();

        // Only change internal_note which is NOT in $auditableFields
        $model->internal_note = 'Note 2';
        $model->save();

        $this->assertSame(0, $this->auditSpy->callCount('logUpdate'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_auditable_fields_excludes_timestamps_and_soft_delete_by_default(): void
    {
        $model = new AuditableTestModel();
        $model->name = 'Test';
        $model->description = 'Desc';
        $model->amount = 100;
        $model->save();

        $reflection = new \ReflectionMethod($model, 'getAuditableFields');
        $fields = $reflection->invoke($model);

        $this->assertContains('name', $fields);
        $this->assertContains('description', $fields);
        $this->assertContains('amount', $fields);
        $this->assertNotContains('created_at', $fields);
        $this->assertNotContains('updated_at', $fields);
        $this->assertNotContains('deleted_at', $fields);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_original_audit_values_returns_original_values_for_auditable_fields(): void
    {
        $model = new AuditableCustomFieldsModel();
        $model->name = 'Original Name';
        $model->amount = 500;
        $model->internal_note = 'Note';
        $model->save();

        $model->name = 'New Name';
        $model->amount = 1000;

        $reflection = new \ReflectionMethod($model, 'getOriginalAuditValues');
        $values = $reflection->invoke($model);

        $this->assertSame('Original Name', $values['name']);
        $this->assertSame(500, $values['amount']);
        $this->assertArrayNotHasKey('internal_note', $values);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_multiple_field_changes_in_single_update(): void
    {
        $model = new AuditableTestModel();
        $model->name = 'Original';
        $model->amount = 100;
        $model->save();

        $model->name = 'Updated';
        $model->amount = 200;
        $model->save();

        $this->assertSame(1, $this->auditSpy->callCount('logUpdate'));
        $call = $this->auditSpy->getCallsFor('logUpdate')[0];
        $this->assertArrayHasKey('name', $call['args'][1]);
        $this->assertArrayHasKey('amount', $call['args'][1]);
        $this->assertSame('Original', $call['args'][1]['name']);
        $this->assertSame(100, $call['args'][1]['amount']);
        $this->assertSame('Updated', $call['args'][2]['name']);
        $this->assertSame(200, $call['args'][2]['amount']);
    }
}

/**
 * Test model with default auditable fields (all attributes except timestamps/soft-delete).
 */
class AuditableTestModel extends Model
{
    use HasAuditTrail;
    use SoftDeletes;

    protected $table = 'auditable_test_models';
    protected $guarded = [];
}

/**
 * Test model with explicit $auditableFields property.
 */
class AuditableCustomFieldsModel extends Model
{
    use HasAuditTrail;
    use SoftDeletes;

    protected $table = 'auditable_custom_fields_models';
    protected $guarded = [];

    protected array $auditableFields = ['name', 'amount'];
}
