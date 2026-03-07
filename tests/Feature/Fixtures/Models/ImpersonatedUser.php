<?php

namespace Tests\Feature\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ImpersonatedUser extends Authenticatable
{
    protected $table = 'users';

    protected $connection = 'tenant';

    public $timestamps = false;

    protected $guarded = [];
}
