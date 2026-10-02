<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A travel agent / tour operator that books rooms on behalf of guests.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int|null $agent_qualification_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $tin
 * @property string|null $vrn
 * @property string|null $address
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'agent_qualification_id',
        'name',
        'phone',
        'email',
        'tin',
        'vrn',
        'address',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AgentQualification, $this>
     */
    public function qualification(): BelongsTo
    {
        return $this->belongsTo(AgentQualification::class, 'agent_qualification_id');
    }
}
