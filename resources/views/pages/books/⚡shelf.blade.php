<?php

use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('My Shelf')] class extends Component {
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'ownership')]
    public string $ownershipFilter = '';

    #[Url(as: 'status')]
    public string $readingFilter = '';

    #[Url(as: 'author')]
    public string $authorFilter = '';

    #[Url(as: 'genre')]
    public string $genreFilter = '';

    #[Computed]
    public function stats(): array
    {
        $user = Auth::user();

        $inProgressId = ReadingStatus::where('name', 'in_progress')->value('id');
        $completedId  = ReadingStatus::where('name', 'completed')->value('id');
        $wishlistId   = OwnershipStatus::where('name', 'wishlist')->value('id');

        return [
            'total'     => $user->userBooks()->count(),
            'reading'   => $user->userBooks()->where('reading_status_id', $inProgressId)->count(),
            'completed' => $user->userBooks()->where('reading_status_id', $completedId)->count(),
            'wishlist'  => $user->userBooks()->where('ownership_status_id', $wishlistId)->count(),
        ];
    }

    #[Computed]
    public function currentlyReading()
    {
        $inProgressId = ReadingStatus::where('name', 'in_progress')->value('id');

        return Auth::user()
            ->userBooks()
            ->with('book')
            ->where('reading_status_id', $inProgressId)
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function userBooks()
    {
        return Auth::user()
            ->userBooks()
            ->with(['book', 'ownershipStatus', 'readingStatus', 'review'])
            ->when(filled($this->search), fn ($q) => $q->whereHas('book', fn ($q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('author', 'like', "%{$this->search}%")))
            ->when(filled($this->ownershipFilter), fn ($q) => $q->where('ownership_status_id', $this->ownershipFilter))
            ->when(filled($this->readingFilter), fn ($q) => $q->where('reading_status_id', $this->readingFilter))
            ->when($this->authorFilter, fn ($q) => $q->whereHas('book', fn ($q) => $q->where('author', $this->authorFilter)))
            ->when($this->genreFilter, fn ($q) => $q->whereHas('book', fn ($q) => $q->whereJsonContains('genres', $this->genreFilter)))
            ->latest()
            ->get();
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
    public function authors(): array
    {
        return Auth::user()
            ->userBooks()
            ->join('books', 'user_books.book_id', '=', 'books.id')
            ->whereNotNull('books.author')
            ->orderBy('books.author')
            ->distinct()
            ->pluck('books.author')
            ->all();
    }

    #[Computed]
    public function genres(): array
    {
        return Auth::user()
            ->userBooks()
            ->with('book:id,genres')
            ->get()
            ->pluck('book.genres')
            ->filter()
            ->flatMap(fn ($g) => (array) $g)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function clearFilters(): void
    {
        $this->search          = '';
        $this->ownershipFilter = '';
        $this->readingFilter   = '';
        $this->authorFilter    = '';
        $this->genreFilter     = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('My Shelf') }}</flux:heading>
            <flux:subheading>{{ __(':count book', ['count' => $this->stats['total']]) }}{{ $this->stats['total'] === 1 ? '' : 's' }}</flux:subheading>
        </div>
        <flux:button :href="route('books.search')" icon="magnifying-glass" wire:navigate>
            {{ __('Add books') }}
        </flux:button>
    </div>

    @if ($this->stats['total'] > 0)
        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading class="text-2xl font-bold">{{ $this->stats['total'] }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Total') }}</flux:text>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading class="text-2xl font-bold text-blue-500">{{ $this->stats['reading'] }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Reading') }}</flux:text>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading class="text-2xl font-bold text-green-500">{{ $this->stats['completed'] }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Completed') }}</flux:text>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading class="text-2xl font-bold text-zinc-400">{{ $this->stats['wishlist'] }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Wishlist') }}</flux:text>
            </div>
        </div>

    @endif

    {{-- Search + Filters --}}
    <div class="flex flex-col gap-3">
    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="{{ __('Search by title or author…') }}"
        clearable
        class="max-w-sm"
    />

    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="ownershipFilter" class="w-40">
            <flux:select.option value="">{{ __('All shelves') }}</flux:select.option>
            @foreach ($this->ownershipStatuses as $status)
                <flux:select.option :value="$status->id">{{ ucfirst($status->name) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="readingFilter" class="w-44">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach ($this->readingStatuses as $status)
                <flux:select.option :value="$status->id">{{ ucfirst(str_replace('_', ' ', $status->name)) }}</flux:select.option>
            @endforeach
        </flux:select>

        @if (count($this->authors) > 0)
            <flux:select wire:model.live="authorFilter" class="w-48">
                <flux:select.option value="">{{ __('Any author') }}</flux:select.option>
                @foreach ($this->authors as $author)
                    <flux:select.option :value="$author">{{ $author }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if (count($this->genres) > 0)
            <flux:select wire:model.live="genreFilter" class="w-48">
                <flux:select.option value="">{{ __('Any genre') }}</flux:select.option>
                @foreach ($this->genres as $genre)
                    <flux:select.option :value="$genre">{{ $genre }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if ($search || $ownershipFilter || $readingFilter || $authorFilter || $genreFilter)
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>
    </div>

    {{-- Currently reading --}}
    @if ($this->stats['total'] > 0 && $this->stats['reading'] > 0 && blank($this->readingFilter))
        <div class="flex flex-col gap-3">
            <flux:heading>{{ __('Currently Reading') }}</flux:heading>
            <div class="flex gap-4 overflow-x-auto pb-1">
                @foreach ($this->currentlyReading as $userBook)
                    <a href="{{ route('books.show', $userBook) }}" wire:navigate class="group flex w-28 shrink-0 flex-col gap-2">
                        <div class="aspect-[2/3] overflow-hidden rounded-lg bg-zinc-100 transition-transform group-hover:scale-[1.02] dark:bg-zinc-800">
                            @if ($userBook->book->cover_url)
                                <img
                                    src="{{ $userBook->book->cover_url }}"
                                    alt="{{ $userBook->book->title }}"
                                    class="h-full w-full object-cover"
                                />
                            @else
                                <div class="flex h-full items-center justify-center p-2 text-center">
                                    <flux:text class="text-xs text-zinc-400">{{ $userBook->book->title }}</flux:text>
                                </div>
                            @endif
                        </div>
                        <div>
                            <flux:text class="line-clamp-2 text-xs font-medium">{{ $userBook->book->title }}</flux:text>
                            @if ($userBook->started_at)
                                <flux:text class="text-xs text-zinc-500">
                                    {{ __('Day :n', ['n' => (int) $userBook->started_at->diffInDays(now()) + 1]) }}
                                </flux:text>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Book grid --}}
    @if (count($this->userBooks) > 0)
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($this->userBooks as $userBook)
                <a href="{{ route('books.show', $userBook) }}" wire:navigate class="group flex flex-col gap-2">
                    <div class="relative aspect-[2/3] overflow-hidden rounded-lg bg-zinc-100 transition-transform group-hover:scale-[1.02] dark:bg-zinc-800">
                        @if ($userBook->book->cover_url)
                            <img
                                src="{{ $userBook->book->cover_url }}"
                                alt="{{ $userBook->book->title }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        @else
                            <div class="flex h-full items-center justify-center p-3 text-center">
                                <flux:text class="text-xs text-zinc-400">{{ $userBook->book->title }}</flux:text>
                            </div>
                        @endif

                        @if ($userBook->readingStatus)
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 px-2 pb-2 pt-4">
                                <flux:badge
                                    size="sm"
                                    :color="match($userBook->readingStatus->name) {
                                        'in_progress' => 'blue',
                                        'completed'   => 'green',
                                        'abandoned'   => 'red',
                                        default       => 'zinc',
                                    }"
                                >
                                    {{ ucfirst(str_replace('_', ' ', $userBook->readingStatus->name)) }}
                                </flux:badge>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <flux:text class="line-clamp-2 text-sm font-medium group-hover:text-zinc-600 dark:group-hover:text-zinc-300">
                            {{ $userBook->book->title }}
                        </flux:text>
                        @if ($userBook->book->author)
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $userBook->book->author }}</flux:text>
                        @endif
                        @if ($userBook->review?->rating)
                            <div class="flex gap-0.5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <flux:icon.star
                                        class="size-3 {{ $i <= $userBook->review->rating ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}"
                                        variant="solid"
                                    />
                                @endfor
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading>{{ __('No books yet') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ $ownershipFilter || $readingFilter || $authorFilter || $genreFilter
                        ? __('No books match your current filters.')
                        : __('Start by searching for books to add to your shelf.') }}
                </flux:subheading>
                @unless ($ownershipFilter || $readingFilter || $authorFilter || $genreFilter)
                    <flux:button :href="route('books.search')" wire:navigate class="mt-4" variant="primary">
                        {{ __('Search books') }}
                    </flux:button>
                @endunless
            </div>
        </div>
    @endif
</div>
