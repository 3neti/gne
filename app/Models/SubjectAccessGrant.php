<?php

namespace App\Models;

use App\Domain\Authorization\SubjectPermission;
use Database\Factories\SubjectAccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $subject_identifier
 * @property SubjectPermission $permission
 * @property int|null $granted_by_user_id
 * @property Carbon $granted_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['user_id', 'subject_identifier', 'permission', 'granted_by_user_id', 'granted_at', 'expires_at', 'metadata'])]
class SubjectAccessGrant extends Model
{
    /** @use HasFactory<SubjectAccessGrantFactory> */
    use HasFactory;

    protected $table = 'gne_subject_access_grants';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permission' => SubjectPermission::class,
            'granted_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
