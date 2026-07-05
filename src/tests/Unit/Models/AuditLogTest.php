<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createAuditLog(array $overrides = []): AuditLog
    {
        $defaults = [
            'user_id' => $this->user->id,
            'auditable_type' => 'App\\Models\\Transaction',
            'auditable_id' => 1,
            'event' => AuditEvent::Created,
            'old_values' => null,
            'new_values' => ['amount_centavos' => 50000],
        ];

        return AuditLog::create(array_merge($defaults, $overrides));
    }

    #[Test]
    public function it_can_be_created_with_valid_attributes(): void
    {
        $log = $this->createAuditLog();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $this->user->id,
            'auditable_type' => 'App\\Models\\Transaction',
            'auditable_id' => 1,
            'event' => 'created',
        ]);
    }

    #[Test]
    public function it_auto_sets_created_at_in_utc_when_not_provided(): void
    {
        $log = $this->createAuditLog();

        $this->assertNotNull($log->created_at);
        $this->assertSame('UTC', $log->created_at->timezone->getName());
    }

    #[Test]
    public function it_preserves_created_at_when_explicitly_provided(): void
    {
        $timestamp = '2024-06-15 10:30:00';

        $log = $this->createAuditLog(['created_at' => $timestamp]);

        $this->assertSame('2024-06-15 10:30:00', $log->created_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_disables_eloquent_timestamps(): void
    {
        $log = new AuditLog();

        $this->assertFalse($log->timestamps);
    }

    #[Test]
    public function it_casts_event_to_audit_event_enum(): void
    {
        $log = $this->createAuditLog(['event' => AuditEvent::Updated]);

        $this->assertInstanceOf(AuditEvent::class, $log->event);
        $this->assertSame(AuditEvent::Updated, $log->event);
    }

    #[Test]
    public function it_casts_old_values_as_array(): void
    {
        $oldValues = ['amount_centavos' => 30000, 'description' => 'old'];

        $log = $this->createAuditLog([
            'event' => AuditEvent::Updated,
            'old_values' => $oldValues,
            'new_values' => ['amount_centavos' => 50000, 'description' => 'new'],
        ]);

        $log->refresh();

        $this->assertIsArray($log->old_values);
        $this->assertSame(30000, $log->old_values['amount_centavos']);
        $this->assertSame('old', $log->old_values['description']);
    }

    #[Test]
    public function it_casts_new_values_as_array(): void
    {
        $newValues = ['amount_centavos' => 50000, 'description' => 'test'];

        $log = $this->createAuditLog(['new_values' => $newValues]);

        $log->refresh();

        $this->assertIsArray($log->new_values);
        $this->assertSame(50000, $log->new_values['amount_centavos']);
        $this->assertSame('test', $log->new_values['description']);
    }

    #[Test]
    public function it_casts_created_at_as_datetime(): void
    {
        $log = $this->createAuditLog();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->created_at);
    }

    #[Test]
    public function it_throws_exception_when_update_is_called(): void
    {
        $log = $this->createAuditLog();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit log records are immutable.');

        $log->update(['user_id' => 999]);
    }

    #[Test]
    public function it_throws_exception_when_save_is_called_on_existing_record(): void
    {
        $log = $this->createAuditLog();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit log records are immutable.');

        $log->user_id = 999;
        $log->save();
    }

    #[Test]
    public function it_throws_exception_when_delete_is_called(): void
    {
        $log = $this->createAuditLog();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit log records cannot be deleted.');

        $log->delete();
    }

    #[Test]
    public function it_throws_exception_when_force_delete_is_called(): void
    {
        $log = $this->createAuditLog();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit log records cannot be deleted.');

        $log->forceDelete();
    }

    #[Test]
    public function it_allows_null_old_values_for_created_events(): void
    {
        $log = $this->createAuditLog([
            'event' => AuditEvent::Created,
            'old_values' => null,
            'new_values' => ['amount_centavos' => 50000],
        ]);

        $log->refresh();

        $this->assertNull($log->old_values);
        $this->assertNotNull($log->new_values);
    }

    #[Test]
    public function it_allows_null_new_values_for_deleted_events(): void
    {
        $log = $this->createAuditLog([
            'event' => AuditEvent::Deleted,
            'old_values' => ['amount_centavos' => 50000],
            'new_values' => null,
        ]);

        $log->refresh();

        $this->assertNotNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    #[Test]
    public function it_defines_expected_fillable_attributes(): void
    {
        $log = new AuditLog();

        $expected = [
            'user_id',
            'auditable_type',
            'auditable_id',
            'event',
            'old_values',
            'new_values',
            'created_at',
        ];

        $this->assertSame($expected, $log->getFillable());
    }
}
