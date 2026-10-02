<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFooterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Free text printed at the bottom of a hotel's bills (bank details,
 * terms, a thank-you note). Each hotel has at most one.
 *
 * @property int $id
 * @property int $hotel_id
 * @property string $content
 * @property int|null $created_by
 * @property int|null $edited_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvoiceFooter extends Model
{
    /** @use HasFactory<InvoiceFooterFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'content',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
