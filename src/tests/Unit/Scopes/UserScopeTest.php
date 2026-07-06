<?php

declare(strict_types=1);

namespace Tests\Unit\Scopes;

use App\Scopes\UserScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class UserScopeTest extends TestCase
{
    private UserScope $scope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scope = new UserScope();
    }

    public function test_apply_adds_where_user_id_constraint_for_authenticated_user(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('id')->once()->andReturn(42);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->with('user_id', '=', 42)
            ->willReturnSelf();

        $model = $this->createMock(Model::class);

        $this->scope->apply($builder, $model);
    }

    public function test_apply_skips_scope_when_no_user_is_authenticated(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(false);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->never())
            ->method('where');

        $model = $this->createMock(Model::class);

        $this->scope->apply($builder, $model);
    }

    public function test_apply_uses_correct_user_id_from_auth(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('id')->once()->andReturn(99);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->with('user_id', '=', 99)
            ->willReturnSelf();

        $model = $this->createMock(Model::class);

        $this->scope->apply($builder, $model);
    }
}
