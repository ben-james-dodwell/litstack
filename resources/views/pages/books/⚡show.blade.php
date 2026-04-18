<?php

use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use App\Models\Review;
use App\Models\UserBook;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Book')] class extends Component {
    #[Locked]
    public int $userBookId;

    public string $ownershipStatusId = '';
    public string $readingStatusId = '';
    public string $startedAt = '';
    public string $endedAt = '';
    public int $rating = 0;
    public string $reviewBody = '';

    public function mount(UserBook $userBook): void
    {
        abort_if($userBook->user_id !== Auth::id(), 403);

        $this->userBookId = $userBook->id;

        $this->ownershipStatusId = (string) $userBook->ownership_status_id;
        $this->readingStatusId   = (string) ($userBook->reading_status_id ?? '');
        $this->startedAt         = $userBook->started_at?->toDateString() ?? '';
        $this->endedAt           = $userBook->ended_at?->toDateString() ?? '';
        $this->rating            = $userBook->review?->rating ?? 0;
        $this->reviewBody        = $userBook->review?->body ?? '';
    }

    #[Computed]
    public function userBook(): UserBook
    {
        return UserBook::with(['book', 'ownershipStatus', 'readingStatus', 'review'])->findOrFail($this->userBookId);
    }

    #[Computed]
    public function ownershipStatuses()
    {
        return OwnershipStatus::all();
    }

    #[Computed]
    public function readingStatuses()
    {
        return ReadingStatus::all();
    }

    #[Computed]
    public function showDates(): bool
    {
        return filled($this->readingStatusId);
    }

    #[Computed]
    public function showEndDate(): bool
    {
        $status = ReadingStatus::find($this->readingStatusId);

        return in_array($status?->name, ['completed', 'abandoned']);
    }

    public function updatedReadingStatusId(): void
    {
        $status = ReadingStatus::find($this->readingStatusId);

        if ($status?->name === 'in_progress' && blank($this->startedAt)) {
            $this->startedAt = now()->toDateString();
        }

        if (in_array($status?->name, ['completed', 'abandoned'])) {
            if (blank($this->startedAt)) {
                $this->startedAt = now()->toDateString();
            }
            if (blank($this->endedAt)) {
                $this->endedAt = now()->toDateString();
            }
        }

        $this->saveShelfEntry();
    }

    public function updatedOwnershipStatusId(): void
    {
        $this->saveShelfEntry();
    }

    public function updatedStartedAt(): void
    {
        $this->saveShelfEntry();
    }

    public function updatedEndedAt(): void
    {
        $this->saveShelfEntry();
    }

    public function setRating(int $rating): void
    {
        $this->rating = $this->rating === $rating ? 0 : $rating;
        $this->saveReview();
    }

    public function saveShelfEntry(): void
    {
        UserBook::where('id', $this->userBookId)->update([
            'ownership_status_id' => $this->ownershipStatusId ?: null,
            'reading_status_id'   => $this->readingStatusId ?: null,
            'started_at'          => $this->startedAt ?: null,
            'ended_at'            => $this->endedAt ?: null,
        ]);

        unset($this->userBook);
    }

    public function saveReview(): void
    {
        Review::updateOrCreate(
            ['user_book_id' => $this->userBookId],
            ['rating' => $this->rating ?: null, 'body' => $this->reviewBody ?: null],
        );

        unset($this->userBook);
        Flux::toast(variant: 'success', text: __('Review saved.'));
    }

    public function removeFromShelf(): void
    {
        UserBook::where('id', $this->userBookId)
            ->where('user_id', Auth::id())
            ->delete();

        $this->redirect(route('books.shelf'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    {{-- Back link --}}
    <div>
        <flux:link :href="route('books.shelf')" wire:navigate icon="arrow-left" icon:leading>
            {{ __('My Shelf') }}
        </flux:link>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:items-start">
        {{-- Book cover + metadata --}}
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <div class="aspect-[2/3] w-48 overflow-hidden rounded-xl bg-zinc-100 shadow-md dark:bg-zinc-800">
                @if ($this->userBook->book->cover_url)
                    <img
                        src="{{ $this->userBook->book->cover_url }}"
                        alt="{{ $this->userBook->book->title }}"
                        class="h-full w-full object-contain"
                    />
                @else
                    <div class="flex h-full items-center justify-center p-4 text-center">
                        <flux:text class="text-sm text-zinc-400">{{ __('No cover') }}</flux:text>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-1">
                <flux:heading size="xl">{{ $this->userBook->book->title }}</flux:heading>
                @if ($this->userBook->book->author)
                    <flux:text class="text-base text-zinc-600 dark:text-zinc-300">{{ $this->userBook->book->author }}</flux:text>
                @endif
                @if ($this->userBook->book->published_year)
                    <flux:text class="text-sm text-zinc-500">{{ $this->userBook->book->published_year }}</flux:text>
                @endif
                @if ($this->userBook->book->page_count)
                    <flux:text class="text-sm text-zinc-500">{{ $this->userBook->book->page_count }} {{ __('pages') }}</flux:text>
                @endif
                @if ($this->userBook->book->publisher)
                    <flux:text class="text-sm text-zinc-500">{{ $this->userBook->book->publisher }}</flux:text>
                @endif
            </div>

            @if ($this->userBook->book->genres && count((array) $this->userBook->book->genres) > 0)
                <div class="flex flex-wrap gap-1">
                    @foreach ((array) $this->userBook->book->genres as $genre)
                        <flux:badge size="sm" color="zinc">{{ $genre }}</flux:badge>
                    @endforeach
                </div>
            @endif

            @if ($this->userBook->book->description)
                <flux:text class="break-words text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                    {{ $this->userBook->book->description }}
                </flux:text>
            @endif
        </div>

        {{-- User shelf controls --}}
        <div class="flex min-w-0 flex-col gap-6">
            {{-- Shelf status --}}
            <flux:fieldset>
                <flux:legend>{{ __('Shelf') }}</flux:legend>
                <div class="flex flex-wrap gap-4">
                    <flux:select wire:model.live="ownershipStatusId" :label="__('Ownership')" class="w-40">
                        @foreach ($this->ownershipStatuses as $status)
                            <flux:select.option :value="$status->id">{{ ucfirst($status->name) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="readingStatusId" :label="__('Reading status')" class="w-44">
                        <flux:select.option value="">{{ __('Not started') }}</flux:select.option>
                        @foreach ($this->readingStatuses as $status)
                            <flux:select.option :value="$status->id">{{ ucfirst(str_replace('_', ' ', $status->name)) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                @if ($this->showDates)
                    <div class="mt-4 flex flex-wrap gap-4">
                        <flux:input
                            wire:model.live="startedAt"
                            type="date"
                            :label="__('Started')"
                            class="w-44"
                        />
                        @if ($this->showEndDate)
                            <flux:input
                                wire:model.live="endedAt"
                                type="date"
                                :label="__('Finished')"
                                class="w-44"
                            />
                        @endif
                    </div>
                @endif
            </flux:fieldset>

            {{-- Rating --}}
            <flux:fieldset>
                <flux:legend>{{ __('Rating') }}</flux:legend>
                <div class="flex gap-1" x-data="{ hovered: 0 }">
                    @for ($i = 1; $i <= 5; $i++)
                        <button
                            type="button"
                            @mouseenter="hovered = {{ $i }}"
                            @mouseleave="hovered = 0"
                            wire:click="setRating({{ $i }})"
                            class="transition-transform hover:scale-110 focus:outline-none"
                        >
                            <flux:icon.star
                                variant="solid"
                                x-bind:class="({{ $i }} <= hovered || (hovered === 0 && {{ $i }} <= {{ $rating }})) ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600'"
                                class="size-7"
                            />
                        </button>
                    @endfor
                    @if ($rating > 0)
                        <flux:text class="ml-2 self-center text-sm text-zinc-500">{{ $rating }}/5</flux:text>
                    @endif
                </div>
            </flux:fieldset>

            {{-- Review --}}
            <flux:fieldset>
                <flux:legend>{{ __('Review') }}</flux:legend>
                <flux:textarea
                    wire:model="reviewBody"
                    :placeholder="__('Write your thoughts about this book…')"
                    rows="5"
                />
                <div class="mt-2">
                    <flux:button wire:click="saveReview" variant="primary">
                        {{ __('Save review') }}
                    </flux:button>
                </div>
            </flux:fieldset>

            {{-- Danger zone --}}
            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button
                    @click="$flux.modal('remove-book').show()"
                    variant="danger"
                    icon="trash"
                >
                    {{ __('Remove from shelf') }}
                </flux:button>
            </div>
        </div>
    </div>

    <flux:modal name="remove-book" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove from shelf?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will remove') }} <strong>{{ $this->userBook->book->title }}</strong> {{ __('and any review you\'ve written. This cannot be undone.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removeFromShelf">
                    {{ __('Remove') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
