<?php

namespace App\Models;

use Database\Factories\UserBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['user_id', 'book_id', 'ownership_status_id', 'reading_status_id', 'started_at', 'ended_at'])]
class UserBook extends Pivot
{
    /** @use HasFactory<UserBookFactory> */
    use HasFactory;

    protected $table = 'user_books';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return BelongsTo<OwnershipStatus, $this> */
    public function ownershipStatus(): BelongsTo
    {
        return $this->belongsTo(OwnershipStatus::class);
    }

    /** @return BelongsTo<ReadingStatus, $this> */
    public function readingStatus(): BelongsTo
    {
        return $this->belongsTo(ReadingStatus::class);
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'user_book_id', 'id');
    }
}
