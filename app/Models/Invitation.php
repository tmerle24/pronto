<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Personal link to join the team, sent by WhatsApp. Only the token's hash is stored.
 *
 * @property int $id
 * @property string $token_hash
 * @property string $name
 * @property UserRole $role
 * @property ?int $created_by_user_id
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $accepted_at
 * @property ?int $accepted_user_id
 */
#[Fillable(['token_hash', 'name', 'role', 'created_by_user_id', 'expires_at', 'accepted_at', 'accepted_user_id'])]
class Invitation extends Model
{
    public const VALID_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return array{0: self, 1: string} the invitation and the plain token for the link
     */
    public static function issue(string $name, UserRole $role, ?User $by): array
    {
        $token = Str::random(40);
        $invitation = static::query()->create([
            'token_hash' => hash('sha256', $token),
            'name' => $name,
            'role' => $role,
            'created_by_user_id' => $by?->id,
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        return [$invitation, $token];
    }

    public static function findOpen(string $token): ?self
    {
        return static::query()->open()->where('token_hash', hash('sha256', $token))->first();
    }

    /** @param  Builder<Invitation>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
