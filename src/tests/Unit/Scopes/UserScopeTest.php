<?php

declare(strict_types=1);

namespace Tests\Unit\Scopes;

use App\Scopes\UserScope;
use Illuminate\Auth\AuthenticationException;
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

        $this->scope = new UserScope;
    }

    public function test_apply_adds_where_user_id_constraint_for_authenticated_user(): void
    {
        Auth::shouldReceive('id')->once()->andReturn(42);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('where')
            ->with('user_id', '=', 42)
            ->willReturnSelf();

        $model = $this->createMock(Model::class);

        $this->scope->apply($builder, $model);
    }

    public function test_apply_throws_authentication_exception_when_no_user(): void
    {
        Auth::shouldReceive('id')->once()->andReturn(null);

        $builder = $this->createMock(Builder::class);
        $model = $this->createMock(Model::class);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No authenticated user');

        $this->scope->apply($builder, $model);
    }

    public function test_apply_uses_correct_user_id_from_auth(): void
    {
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
