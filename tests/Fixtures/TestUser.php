<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

class TestUser extends User
{
    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }
}
