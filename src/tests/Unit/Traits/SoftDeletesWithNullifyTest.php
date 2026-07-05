<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Traits\SoftDeletesWithNullify;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SoftDeletesWithNullifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('parents_test', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('children_test', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('value');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('children_test');
        Schema::dropIfExists('parents_test');

        parent::tearDown();
    }

    private function createParent(string $name): Model
    {
        $parent = new ParentTestModel();
        $parent->name = $name;
        $parent->save();

        return $parent;
    }

    private function createChild(int $parentId, string $value): Model
    {
        $child = new ChildTestModel();
        $child->parent_id = $parentId;
        $child->value = $value;
        $child->save();

        return $child;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_soft_deletes_internally(): void
    {
        $parent = $this->createParent('Test Parent');

        $parent->delete();

        $this->assertSoftDeleted('parents_test', ['id' => $parent->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_deleted_at_timestamp_on_soft_delete(): void
    {
        $parent = $this->createParent('Test Parent');

        $parent->delete();

        $parent->refresh();
        $this->assertNotNull($parent->deleted_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_soft_deleted_records_from_standard_queries(): void
    {
        $parent = $this->createParent('Test Parent');
        $this->createParent('Active Parent');

        $parent->delete();

        $results = ParentTestModel::all();
        $this->assertCount(1, $results);
        $this->assertSame('Active Parent', $results->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_nullifies_foreign_keys_on_dependent_records_when_soft_deleted(): void
    {
        $parent = $this->createParent('Test Parent');
        $child1 = $this->createChild($parent->id, 'Child 1');
        $child2 = $this->createChild($parent->id, 'Child 2');

        $parent->delete();

        $child1->refresh();
        $child2->refresh();
        $this->assertNull($child1->parent_id);
        $this->assertNull($child2->parent_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_nullify_foreign_keys_belonging_to_other_parents(): void
    {
        $parent1 = $this->createParent('Parent 1');
        $parent2 = $this->createParent('Parent 2');
        $child1 = $this->createChild($parent1->id, 'Child of Parent 1');
        $child2 = $this->createChild($parent2->id, 'Child of Parent 2');

        $parent1->delete();

        $child1->refresh();
        $child2->refresh();
        $this->assertNull($child1->parent_id);
        $this->assertSame($parent2->id, (int) $child2->parent_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function restore_clears_deleted_at_without_relinking_foreign_keys(): void
    {
        $parent = $this->createParent('Test Parent');
        $child = $this->createChild($parent->id, 'Child');

        $parent->delete();

        $child->refresh();
        $this->assertNull($child->parent_id);

        $parent->restoreRecord();
        $parent->refresh();
        $child->refresh();

        $this->assertNull($parent->deleted_at);
        $this->assertNull($child->parent_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_nullify_when_force_deleting(): void
    {
        $parent = $this->createParent('Test Parent');
        $child = $this->createChild($parent->id, 'Child');

        $parent->forceDelete();

        $child->refresh();
        $this->assertSame($parent->id, (int) $child->parent_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function nullify_on_delete_returns_correct_mappings(): void
    {
        $parent = new ParentTestModel();

        $reflection = new \ReflectionMethod($parent, 'nullifyOnDelete');
        $mappings = $reflection->invoke($parent);

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('children', $mappings);
        $this->assertSame('parent_id', $mappings['children']);
    }
}

/**
 * Test model that uses SoftDeletesWithNullify.
 */
class ParentTestModel extends Model
{
    use SoftDeletesWithNullify;

    protected $table = 'parents_test';
    protected $guarded = [];

    protected function nullifyOnDelete(): array
    {
        return ['children' => 'parent_id'];
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChildTestModel::class, 'parent_id');
    }
}

/**
 * Test model representing dependent records.
 */
class ChildTestModel extends Model
{
    protected $table = 'children_test';
    protected $guarded = [];
}
