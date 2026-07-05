<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

final class DependentModelStub extends Model
{
    protected $table = 'dependent_model_stubs';
    protected $guarded = [];
}
