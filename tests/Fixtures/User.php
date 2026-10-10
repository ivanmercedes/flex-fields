<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @var array<int, string>|null Simulated Sanctum token abilities (null = no token). */
    public ?array $tokenAbilities = null;

    protected $table = 'users';

    protected $guarded = [];

    public function currentAccessToken(): ?object
    {
        return $this->tokenAbilities === null ? null : (object) ['abilities' => $this->tokenAbilities];
    }

    public function tokenCan(string $ability): bool
    {
        return in_array('*', (array) $this->tokenAbilities, true) || in_array($ability, (array) $this->tokenAbilities, true);
    }
}
