<?php

use App\Jobs\CacheCoverImage;
use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use App\Models\Review;
use App\Models\UserBook;
use App\Services\OpenLibraryService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('My Shelf')] class extends Component {
    // ── Shelf filters ──────────────────────────────────────────────────────────
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

    #[Url(as: 'sort')]
    public string $sortBy = 'recent';

    // ── Book detail panel ──────────────────────────────────────────────────────
    public ?int $selectedUserBookId = null;

    public bool $panelOpen = false;

    public string $panelOwnershipStatusId = '';

    public string $panelReadingStatusId = '';

    public string $panelStartedAt = '';

    public string $panelEndedAt = '';

    public int $panelRating = 0;

    public string $panelReviewBody = '';

    // ── Add-book modal ─────────────────────────────────────────────────────────
    public string $addQuery = '';

    public ?string $addSelectedOpenLibraryId = null;

    public int $addOwnershipStatusId = 0;

    // ── Computed: shelf ────────────────────────────────────────────────────────

    #[Computed]
    public function counts(): array
    {
        $user       = Auth::user();
        $ownedId    = OwnershipStatus::where('name', 'owned')->value('id');
        $wishlistId = OwnershipStatus::where('name', 'wishlist')->value('id');

        return [
            'all'      => $user->userBooks()->count(),
            'owned'    => $user->userBooks()->where('ownership_status_id', $ownedId)->count(),
            'wishlist' => $user->userBooks()->where('ownership_status_id', $wishlistId)->count(),
        ];
    }

    #[Computed]
    public function userBooks()
    {
        $books = Auth::user()
            ->userBooks()
            ->with(['book', 'ownershipStatus', 'readingStatus', 'review'])
            ->when(filled($this->search), fn ($q) => $q->whereHas('book', fn ($q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('author', 'like', "%{$this->search}%")))
            ->when(filled($this->ownershipFilter), fn ($q) => $q->where('ownership_status_id', $this->ownershipFilter))
            ->when(filled($this->readingFilter), fn ($q) => $q->where('reading_status_id', $this->readingFilter))
            ->when($this->authorFilter, fn ($q) => $q->whereHas('book', fn ($q) => $q->where('author', $this->authorFilter)))
            ->when($this->genreFilter, fn ($q) => $q->whereHas('book', fn ($q) => $q->whereJsonContains('genres', $this->genreFilter)))
            ->latest()
            ->get();

        return match ($this->sortBy) {
            'title'  => $books->sortBy('book.title')->values(),
            'author' => $books->sortBy('book.author')->values(),
            'rating' => $books->sortByDesc(fn ($ub) => $ub->review?->rating ?? 0)->values(),
            default  => $books,
        };
    }

    #[Computed]
    public function ownershipStatuses()
    {
        return OwnershipStatus::all();
    }

    #[Computed]
    public function readingStatuses()
    {
        $order = ['in_progress' => 0, 'completed' => 1, 'abandoned' => 2];

        return ReadingStatus::all()->sortBy(fn ($rs) => $order[$rs->name] ?? 99)->values();
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
            ->flatMap(fn ($g) => collect($g)->all())
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
        $this->sortBy          = 'recent';
    }

    // ── Panel: open / close ────────────────────────────────────────────────────

    public function openBook(int $userBookId): void
    {
        $userBook = UserBook::with(['review'])->findOrFail($userBookId);

        abort_if($userBook->user_id !== Auth::id(), 403);

        $this->selectedUserBookId     = $userBookId;
        $this->panelOwnershipStatusId = (string) $userBook->ownership_status_id;
        $this->panelReadingStatusId   = (string) ($userBook->reading_status_id ?? '');
        $this->panelStartedAt         = $userBook->started_at?->toDateString() ?? '';
        $this->panelEndedAt           = $userBook->ended_at?->toDateString() ?? '';
        $this->panelRating            = $userBook->review?->rating ?? 0;
        $this->panelReviewBody        = $userBook->review?->body ?? '';
        $this->panelOpen              = true;
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
    }

    // ── Panel: computed ────────────────────────────────────────────────────────

    #[Computed]
    public function selectedUserBook(): ?UserBook
    {
        if (! $this->selectedUserBookId) {
            return null;
        }

        return UserBook::with(['book', 'ownershipStatus', 'readingStatus', 'review'])
            ->find($this->selectedUserBookId);
    }

    #[Computed]
    public function panelShowEndDate(): bool
    {
        $status = ReadingStatus::find($this->panelReadingStatusId);

        return in_array($status?->name, ['completed', 'abandoned']);
    }

    // ── Panel: save actions ────────────────────────────────────────────────────

    public function updatedPanelReadingStatusId(): void
    {
        $status = ReadingStatus::find($this->panelReadingStatusId);

        if ($status?->name === 'in_progress' && blank($this->panelStartedAt)) {
            $this->panelStartedAt = now()->toDateString();
        }

        if (in_array($status?->name, ['completed', 'abandoned'])) {
            if (blank($this->panelStartedAt)) {
                $this->panelStartedAt = now()->toDateString();
            }
            if (blank($this->panelEndedAt)) {
                $this->panelEndedAt = now()->toDateString();
            }
        }

        $this->savePanelShelfEntry();
    }

    public function updatedPanelOwnershipStatusId(): void
    {
        $this->savePanelShelfEntry();
    }

    public function updatedPanelStartedAt(): void
    {
        $this->savePanelShelfEntry();
    }

    public function updatedPanelEndedAt(): void
    {
        $this->savePanelShelfEntry();
    }

    public function setPanelRating(int $rating): void
    {
        $this->panelRating = $this->panelRating === $rating ? 0 : $rating;

        Review::updateOrCreate(
            ['user_book_id' => $this->selectedUserBookId],
            ['rating' => $this->panelRating ?: null, 'body' => $this->panelReviewBody ?: null],
        );

        unset($this->userBooks);
    }

    public function savePanelReview(): void
    {
        Review::updateOrCreate(
            ['user_book_id' => $this->selectedUserBookId],
            ['rating' => $this->panelRating ?: null, 'body' => $this->panelReviewBody ?: null],
        );

        unset($this->userBooks);
        Flux::toast(variant: 'success', text: __('Review saved.'));
    }

    private function savePanelShelfEntry(): void
    {
        UserBook::where('id', $this->selectedUserBookId)->update([
            'ownership_status_id' => $this->panelOwnershipStatusId ?: null,
            'reading_status_id'   => $this->panelReadingStatusId ?: null,
            'started_at'          => $this->panelStartedAt ?: null,
            'ended_at'            => $this->panelEndedAt ?: null,
        ]);

        unset($this->userBooks, $this->selectedUserBook);
    }

    public function removePanelBookFromShelf(): void
    {
        $userBook = UserBook::where('id', $this->selectedUserBookId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $userBook->delete();

        $this->panelOpen = false;
        unset($this->userBooks, $this->selectedUserBook, $this->counts);
        Flux::modal('remove-book')->close();
    }

    // ── Add-book modal ─────────────────────────────────────────────────────────

    #[Computed]
    public function addResults(): array
    {
        $query = trim($this->addQuery);

        if (strlen($query) < 2) {
            return [];
        }

        $service = app(OpenLibraryService::class);

        if ($this->looksLikeIsbn($query)) {
            $book = $service->findByIsbn(preg_replace('/[\s\-]/', '', $query));

            return $book ? [$book] : [];
        }

        return $service->search($query);
    }

    #[Computed]
    public function addUserBookIds(): array
    {
        return Auth::user()
            ->userBooks()
            ->with('book:id,open_library_id')
            ->get()
            ->pluck('book.open_library_id')
            ->filter()
            ->all();
    }

    public function selectBookToAdd(string $openLibraryId): void
    {
        $this->addSelectedOpenLibraryId = $openLibraryId;
        $this->addOwnershipStatusId     = OwnershipStatus::where('name', 'owned')->value('id') ?? 0;
        Flux::modal('shelf-add-confirm')->show();
    }

    public function addToShelf(): void
    {
        $this->validate(['addOwnershipStatusId' => 'required|exists:ownership_statuses,id']);

        $bookData = collect($this->addResults)
            ->firstWhere('open_library_id', $this->addSelectedOpenLibraryId);

        if (! $bookData) {
            return;
        }

        $service = app(OpenLibraryService::class);

        $book = Book::firstOrCreate(
            ['open_library_id' => $bookData['open_library_id']],
            collect($bookData)->except('open_library_id')->all(),
        );

        if ($book->wasRecentlyCreated) {
            if (blank($book->description)) {
                $details = $service->fetchDetails($bookData['open_library_id']);
                if ($details) {
                    $book->update($details);
                }
            }
            if ($book->cover_url) {
                CacheCoverImage::dispatch($book);
            }
        }

        UserBook::firstOrCreate(
            ['user_id' => Auth::id(), 'book_id' => $book->id],
            ['ownership_status_id' => $this->addOwnershipStatusId],
        );

        Flux::modal('shelf-add-confirm')->close();
        $this->addQuery                 = '';
        $this->addSelectedOpenLibraryId = null;
        unset($this->addResults, $this->addUserBookIds, $this->userBooks, $this->counts);
        Flux::toast(variant: 'success', text: __('Book added to your shelf.'));
    }

    private function looksLikeIsbn(string $query): bool
    {
        $clean = preg_replace('/[\s\-]/', '', $query);

        return (bool) preg_match('/^\d{9}[\dX]$/i', $clean) || (bool) preg_match('/^\d{13}$/', $clean);
    }
}; ?>

@php
    $coverPalettes = [
        ['bg' => '#1a1a1a', 'fg' => '#f2c14e'],
        ['bg' => '#8b2e1f', 'fg' => '#f5e6c8'],
        ['bg' => '#2d4a2b', 'fg' => '#e8d9a8'],
        ['bg' => '#1f3a5f', 'fg' => '#f0e4c4'],
        ['bg' => '#c76a3a', 'fg' => '#1a1a1a'],
        ['bg' => '#4a2c5a', 'fg' => '#f3d77c'],
        ['bg' => '#3d2817', 'fg' => '#e8c887'],
        ['bg' => '#7a1f2b', 'fg' => '#f5e6c8'],
        ['bg' => '#0f3a3a', 'fg' => '#d4a574'],
        ['bg' => '#d9b382', 'fg' => '#2a1810'],
        ['bg' => '#2b2b44', 'fg' => '#e5c85c'],
        ['bg' => '#5a3a1f', 'fg' => '#f0dba0'],
    ];

    $ratingLabels = ['', "Didn't land", 'Fine enough', 'Worth reading', 'Excellent', 'A keeper'];
@endphp

<div class="relative flex h-full w-full flex-1 flex-col overflow-hidden">

    {{-- ── Topbar ─────────────────────────────────────────────────────────── --}}
    <div class="flex shrink-0 items-center gap-3 border-b border-line bg-bg px-4 py-3 sm:gap-4 sm:px-8 sm:py-[18px]">
        <flux:button
            @click="$flux.modal('shelf-add-search').show()"
            variant="primary"
            icon="plus"
            class="shrink-0"
        >
            <span class="hidden sm:inline">{{ __('Add a book') }}</span>
            <span class="sm:hidden">{{ __('Add') }}</span>
        </flux:button>

        <div class="relative ml-auto w-full max-w-xs sm:max-w-80">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search your books…') }}"
                clearable
            />
        </div>
    </div>

    {{-- ── Scrollable content ─────────────────────────────────────────────── --}}
    <div class="flex-1 overflow-y-auto px-4 pb-10 pt-5 sm:px-8 sm:pb-16 sm:pt-7">

        {{-- Page head --}}
        <div class="mb-5 flex flex-wrap items-end gap-x-3 gap-y-2 sm:mb-6">
            <h1 class="font-serif text-[26px] font-medium leading-tight tracking-[0.01em] text-ink sm:text-[38px]">
                @if ($ownershipFilter)
                    @php $ownershipLabel = $this->ownershipStatuses->firstWhere('id', $ownershipFilter)?->name; @endphp
                    {{ ucfirst($ownershipLabel ?? 'Books') }}
                @else
                    {{ __('All Books') }}
                @endif
            </h1>

            <span class="pb-1 font-sans text-[13.5px] text-muted">
                <strong class="font-medium text-ink">{{ count($this->userBooks) }}</strong>
                {{ count($this->userBooks) === 1 ? __('book') : __('books') }}
                @if (count($this->userBooks) !== $this->counts['all'] && !$ownershipFilter)
                    {{ __('of') }} {{ $this->counts['all'] }}
                @endif
            </span>

            {{-- Filter + sort pills --}}
            <div class="flex w-full flex-wrap gap-2 pb-0.5 sm:ml-auto sm:w-auto">

                {{-- Genre filter --}}
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-[7px] font-sans text-[12.5px] font-medium transition
                            {{ $genreFilter ? 'border-accent bg-accent-soft text-accent-ink' : 'border-line bg-card text-ink-2 hover:border-line-2' }}"
                    >
                        <flux:icon.funnel class="size-3.5 {{ $genreFilter ? 'text-accent-ink' : 'text-muted' }}" />
                        {{ $genreFilter ?: __('Genre') }}
                    </button>
                    <div x-show="open" x-transition class="absolute left-0 top-[calc(100%+6px)] z-20 max-h-80 min-w-50 overflow-y-auto rounded-[10px] border border-line-2 bg-card p-1.5 shadow-[0_10px_30px_-12px_rgba(30,20,10,0.2)] sm:left-auto sm:right-0">
                        <button wire:click="$set('genreFilter', '')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ !$genreFilter ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ __('All genres') }}</button>
                        @foreach ($this->genres as $genre)
                            <button wire:click="$set('genreFilter', '{{ $genre }}')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ $genreFilter === $genre ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ $genre }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Author filter --}}
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-[7px] font-sans text-[12.5px] font-medium transition
                            {{ $authorFilter ? 'border-accent bg-accent-soft text-accent-ink' : 'border-line bg-card text-ink-2 hover:border-line-2' }}"
                    >
                        <flux:icon.funnel class="size-3.5 {{ $authorFilter ? 'text-accent-ink' : 'text-muted' }}" />
                        {{ $authorFilter ?: __('Author') }}
                    </button>
                    <div x-show="open" x-transition class="absolute left-0 top-[calc(100%+6px)] z-20 max-h-80 min-w-55 overflow-y-auto rounded-[10px] border border-line-2 bg-card p-1.5 shadow-[0_10px_30px_-12px_rgba(30,20,10,0.2)] sm:left-auto sm:right-0">
                        <button wire:click="$set('authorFilter', '')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ !$authorFilter ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ __('All authors') }}</button>
                        @foreach ($this->authors as $author)
                            <button wire:click="$set('authorFilter', '{{ addslashes($author) }}')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ $authorFilter === $author ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ $author }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Status filter --}}
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-[7px] font-sans text-[12.5px] font-medium transition
                            {{ $readingFilter ? 'border-accent bg-accent-soft text-accent-ink' : 'border-line bg-card text-ink-2 hover:border-line-2' }}"
                    >
                        <flux:icon.funnel class="size-3.5 {{ $readingFilter ? 'text-accent-ink' : 'text-muted' }}" />
                        @if ($readingFilter)
                            {{ ucfirst(str_replace('_', ' ', $this->readingStatuses->firstWhere('id', $readingFilter)?->name ?? 'Status')) }}
                        @else
                            {{ __('Status') }}
                        @endif
                    </button>
                    <div x-show="open" x-transition class="absolute left-0 top-[calc(100%+6px)] z-20 min-w-45 rounded-[10px] border border-line-2 bg-card p-1.5 shadow-[0_10px_30px_-12px_rgba(30,20,10,0.2)] sm:left-auto sm:right-0">
                        <button wire:click="$set('readingFilter', '')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ !$readingFilter ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ __('All statuses') }}</button>
                        @foreach ($this->readingStatuses as $status)
                            <button wire:click="$set('readingFilter', '{{ $status->id }}')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ $readingFilter == $status->id ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ ucfirst(str_replace('_', ' ', $status->name)) }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Sort --}}
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-1.5 rounded-full border border-line bg-card px-3 py-[7px] font-sans text-[12.5px] font-medium text-ink-2 transition hover:border-line-2"
                    >
                        <flux:icon.bars-3-bottom-left class="size-3.5 text-muted" />
                        {{ __('Sort:') }} {{ match($sortBy) { 'title' => 'Title', 'author' => 'Author', 'rating' => 'Rating', default => 'Recent' } }}
                    </button>
                    <div x-show="open" x-transition class="absolute left-0 top-[calc(100%+6px)] z-20 min-w-37.5 rounded-[10px] border border-line-2 bg-card p-1.5 shadow-[0_10px_30px_-12px_rgba(30,20,10,0.2)] sm:left-auto sm:right-0">
                        @foreach ([['recent','Recent'],['title','Title'],['author','Author'],['rating','Rating']] as [$val, $label])
                            <button wire:click="$set('sortBy', '{{ $val }}')" @click="open = false" class="block w-full rounded-[7px] px-2.5 py-[7px] text-left font-sans text-[12.5px] transition hover:bg-bg-2 {{ $sortBy === $val ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-ink-2' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Book grid ───────────────────────────────────────────────────── --}}
        @if (count($this->userBooks) > 0)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-5.5 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                @foreach ($this->userBooks as $userBook)
                    @php
                        $palette  = $coverPalettes[$userBook->book->id % count($coverPalettes)];
                        $genres   = (array) ($userBook->book->genres ?? []);
                        $firstGenre = $genres[0] ?? null;
                        $started  = $userBook->started_at?->format('M j, Y');
                        $finished = $userBook->ended_at?->format('M j, Y');
                        $statusName = $userBook->readingStatus?->name;
                    @endphp

                    <article
                        wire:click="openBook({{ $userBook->id }})"
                        wire:key="book-{{ $userBook->id }}"
                        class="group flex cursor-pointer flex-row items-start gap-3 rounded-[14px] border border-line bg-card p-3 transition duration-150 hover:border-line-2 hover:shadow-[0_4px_16px_-8px_rgba(40,30,10,0.15)] sm:flex-col sm:gap-3 sm:p-4.5 sm:hover:-translate-y-px sm:hover:shadow-[0_10px_24px_-18px_rgba(40,30,10,0.25)]"
                    >
                        {{-- Cover --}}
                        <div class="aspect-[2/3] w-14 shrink-0 overflow-hidden rounded-sm shadow-[0_1px_0_rgba(0,0,0,0.15),inset_2px_0_0_rgba(255,255,255,0.1),inset_-2px_0_0_rgba(0,0,0,0.15),inset_0_0_0_1px_rgba(0,0,0,0.08)] sm:w-full">
                            @if ($userBook->book->cover_url)
                                <img
                                    src="{{ $userBook->book->cover_url }}"
                                    alt="{{ $userBook->book->title }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            @else
                                <div
                                    class="cover-ph relative flex h-full flex-col overflow-hidden p-[10px_9px]"
                                    style="background-color: {{ $palette['bg'] }}; color: {{ $palette['fg'] }}"
                                >
                                    {{-- Spine rule --}}
                                    <div class="absolute bottom-0 left-1 top-0 w-px opacity-20" style="background: {{ $palette['fg'] }}"></div>
                                    <div class="font-serif text-[13px] font-semibold leading-tight" style="color: {{ $palette['fg'] }}">
                                        {{ $userBook->book->title }}
                                    </div>
                                    <div class="mt-auto flex justify-between font-sans text-[8.5px] font-medium uppercase tracking-[0.04em] opacity-75" style="color: {{ $palette['fg'] }}">
                                        <span>{{ last(explode(' ', $userBook->book->author ?? '')) }}</span>
                                        <span>—</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Body + footer wrapper --}}
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex flex-col gap-1">
                                <h3 class="font-serif text-[15px] font-semibold leading-snug tracking-[0.005em] text-ink sm:text-[17px]" style="text-wrap: balance">
                                    {{ $userBook->book->title }}
                                </h3>
                                @if ($userBook->book->author)
                                    <p class="font-sans text-[12px] text-muted sm:text-[13px]">{{ $userBook->book->author }}</p>
                                @endif

                                @if ($firstGenre)
                                    <div class="mt-1 sm:mt-2">
                                        <span class="rounded-full border border-line bg-bg-2 px-2 py-0.5 font-sans text-[11px] font-medium text-ink-2">
                                            {{ $firstGenre }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            {{-- Footer --}}
                            <div class="mt-auto flex items-center justify-between gap-2 border-t border-line pt-2 sm:pt-2.5">
                                @if ($userBook->review?->rating)
                                    <div class="flex gap-0.5">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <flux:icon.star
                                                variant="solid"
                                                class="size-3 {{ $i <= $userBook->review->rating ? 'text-accent' : 'text-muted-2' }}"
                                            />
                                        @endfor
                                    </div>
                                @else
                                    <span class="font-sans text-[11px] italic text-muted-2 sm:text-[11.5px]">Unrated</span>
                                @endif

                                <span class="font-sans text-[11px] text-muted">
                                    @if ($statusName === 'in_progress' && $started)
                                        Began {{ $started }}
                                    @elseif ($statusName === 'completed' && $finished)
                                        Finished {{ $finished }}
                                    @elseif ($statusName === 'abandoned')
                                        Set aside
                                    @endif
                                </span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

        @else
            {{-- Empty state --}}
            <div class="mx-auto mt-12 max-w-[460px] rounded-[18px] border border-dashed border-line-2 bg-card px-8 py-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-[14px] bg-accent-soft text-accent-ink">
                    <flux:icon.book-open class="size-8" />
                </div>
                <h2 class="font-serif text-[26px] font-medium text-ink">
                    {{ $search || $ownershipFilter || $readingFilter || $authorFilter || $genreFilter
                        ? __('No books match your filters.')
                        : __('Nothing on this shelf yet.') }}
                </h2>
                <p class="mt-1.5 font-sans text-[13.5px] text-muted">
                    {{ __('Search the catalog to find a book by title, author, or ISBN — then it\'ll live here on your shelf.') }}
                </p>
                <button
                    @click="$flux.modal('shelf-add-search').show()"
                    class="mt-5 inline-flex items-center gap-1.5 rounded-[10px] bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-card shadow-[0_1px_0_rgba(0,0,0,0.08)] transition hover:brightness-110"
                >
                    <flux:icon.plus class="size-3.5" />
                    {{ __('Add your first book') }}
                </button>
            </div>
        @endif
    </div>

    {{-- ── Detail panel ──────────────────────────────────────────────────────── --}}
    <div
        x-show="$wire.panelOpen"
        x-transition:enter="transition duration-250 ease-[cubic-bezier(0.2,0.9,0.3,1)]"
        x-transition:enter-start="max-lg:translate-y-full lg:translate-x-full opacity-0"
        x-transition:enter-end="translate-y-0 translate-x-0 opacity-100"
        x-transition:leave="transition duration-200 ease-in"
        x-transition:leave-start="translate-y-0 translate-x-0 opacity-100"
        x-transition:leave-end="max-lg:translate-y-full lg:translate-x-full opacity-0"
        class="fixed bottom-0 right-0 top-0 z-50 flex flex-col overflow-y-auto bg-bg
               max-lg:left-0 max-lg:w-full max-lg:border-t max-lg:border-line-2 max-lg:shadow-[0_-8px_30px_-8px_rgba(30,20,10,0.2)]
               lg:w-[min(540px,96vw)] lg:border-l lg:border-line-2 lg:shadow-[-24px_0_60px_-24px_rgba(30,20,10,0.3)]"
    >
        @if ($selectedUserBookId && $this->selectedUserBook)
            @php
                $sb      = $this->selectedUserBook;
                $sbBook  = $sb->book;
                $palette = $coverPalettes[$sbBook->id % count($coverPalettes)];
                $days    = $sb->started_at
                    ? max(1, (int) $sb->started_at->diffInDays($sb->ended_at ?? now()))
                    : null;
            @endphp

            {{-- Panel header --}}
            <div class="flex shrink-0 items-center justify-between border-b border-line px-5 py-3.5">
                <span class="font-sans text-[11.5px] text-muted">
                    Book{{ ($sbBook->isbn_13 ?? $sbBook->isbn_10) ? ' · ISBN ' . ($sbBook->isbn_13 ?? $sbBook->isbn_10) : '' }}
                </span>
                <div class="flex gap-1.5">
                    <button
                        @click="$flux.modal('remove-book').show()"
                        class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] text-muted transition hover:bg-bg-2 hover:text-danger"
                        title="{{ __('Remove from shelf') }}"
                    >
                        <flux:icon.trash class="size-4" />
                    </button>
                    <button
                        wire:click="closePanel"
                        class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] text-muted transition hover:bg-bg-2 hover:text-ink"
                        title="{{ __('Close') }}"
                    >
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>
            </div>

            {{-- Panel body --}}
            <div class="flex-1 overflow-y-auto px-4 pb-8 pt-5 sm:px-8 sm:pb-10 sm:pt-7">

                {{-- Hero --}}
                <div class="mb-6 flex gap-6">
                    {{-- Cover --}}
                    <div class="aspect-[2/3] w-32 shrink-0 overflow-hidden rounded-[4px] shadow-[0_1px_0_rgba(0,0,0,0.15),inset_2px_0_0_rgba(255,255,255,0.1),inset_-2px_0_0_rgba(0,0,0,0.15),inset_0_0_0_1px_rgba(0,0,0,0.08),6px_8px_20px_-8px_rgba(30,20,10,0.3)]">
                        @if ($sbBook->cover_url)
                            <img src="{{ $sbBook->cover_url }}" alt="{{ $sbBook->title }}" class="h-full w-full object-cover" />
                        @else
                            <div class="cover-ph relative flex h-full flex-col overflow-hidden p-[14px_12px]" style="background-color: {{ $palette['bg'] }}; color: {{ $palette['fg'] }}">
                                <div class="absolute bottom-0 left-1 top-0 w-px opacity-20" style="background: {{ $palette['fg'] }}"></div>
                                <div class="font-serif text-[17px] font-semibold leading-tight" style="color: {{ $palette['fg'] }}">{{ $sbBook->title }}</div>
                            </div>
                        @endif
                    </div>

                    {{-- Meta --}}
                    <div class="min-w-0">
                        <h1 class="font-serif text-[28px] font-medium leading-snug tracking-[0.01em] text-ink">
                            {{ $sbBook->title }}
                        </h1>
                        @if ($sbBook->author)
                            <p class="mt-1 font-sans text-[14px] text-muted">by {{ $sbBook->author }}</p>
                        @endif
                        <div class="mt-4 flex flex-col gap-3">
                            {{-- Row 1: numeric / date stats --}}
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                @if ($sbBook->page_count)
                                    <div>
                                        <p class="font-sans text-[11px] text-muted">Length</p>
                                        <p class="font-sans text-[13px] font-medium text-ink-2">{{ $sbBook->page_count }} pages</p>
                                    </div>
                                @endif
                                @if ($sb->created_at)
                                    <div>
                                        <p class="font-sans text-[11px] text-muted">Added</p>
                                        <p class="font-sans text-[13px] font-medium text-ink-2">{{ $sb->created_at->format('F Y') }}</p>
                                    </div>
                                @endif
                                @if ($days)
                                    <div>
                                        <p class="font-sans text-[11px] text-muted">{{ $sb->ended_at ? 'Read in' : 'Reading for' }}</p>
                                        <p class="font-sans text-[13px] font-medium text-ink-2">{{ $days }} {{ $days === 1 ? 'day' : 'days' }}</p>
                                    </div>
                                @endif
                            </div>
                            {{-- Row 2: genres --}}
                            @php $sbGenres = collect($sbBook->genres)->all(); @endphp
                            @if (count($sbGenres))
                                <div>
                                    <p class="font-sans text-[11px] text-muted">Genre</p>
                                    <p class="font-sans text-[13px] font-medium text-ink-2">{{ implode(', ', $sbGenres) }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Status --}}
                <div class="border-t border-line pt-5">
                    <p class="mb-3 font-sans text-[11.5px] text-muted">Status</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="mb-1.5 font-sans text-[12px] text-muted">Ownership</p>
                            <div class="flex overflow-hidden rounded-[10px] border border-line bg-card">
                                @foreach ($this->ownershipStatuses as $os)
                                    <button
                                        wire:click="$set('panelOwnershipStatusId', '{{ $os->id }}')"
                                        class="flex-1 py-2 text-center font-sans text-[12px] transition {{ $panelOwnershipStatusId == $os->id ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-muted hover:bg-bg-2' }} {{ !$loop->last ? 'border-r border-line' : '' }}"
                                    >{{ ucfirst($os->name) }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="mb-1.5 font-sans text-[12px] text-muted">Reading</p>
                            <div class="flex overflow-hidden rounded-[10px] border border-line bg-card">
                                @foreach ($this->readingStatuses as $rs)
                                    <button
                                        wire:click="$set('panelReadingStatusId', '{{ $rs->id }}')"
                                        class="flex-1 py-2 text-center font-sans text-[11px] transition {{ $panelReadingStatusId == $rs->id ? 'bg-accent-soft font-semibold text-accent-ink' : 'text-muted hover:bg-bg-2' }} {{ !$loop->last ? 'border-r border-line' : '' }}"
                                    >{{ ucfirst(str_replace('_', ' ', $rs->name)) }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Dates --}}
                @if (filled($panelReadingStatusId))
                    <div class="mt-6 border-t border-line pt-5">
                        <p class="mb-3 font-sans text-[11.5px] text-muted">Dates</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block font-sans text-[12px] text-muted">Started</label>
                                <flux:input wire:model.live="panelStartedAt" type="date" />
                            </div>
                            @if ($this->panelShowEndDate)
                                <div>
                                    <label class="mb-1.5 block font-sans text-[12px] text-muted">Finished</label>
                                    <flux:input wire:model.live="panelEndedAt" type="date" />
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Rating --}}
                <div class="mt-6 border-t border-line pt-5">
                    <p class="mb-3 font-sans text-[11.5px] text-muted">Your rating</p>
                    <div class="flex items-center gap-2" x-data="{ hovered: 0 }">
                        @for ($i = 1; $i <= 5; $i++)
                            <button
                                type="button"
                                @mouseenter="hovered = {{ $i }}"
                                @mouseleave="hovered = 0"
                                wire:click="setPanelRating({{ $i }})"
                                class="transition-transform hover:scale-110 focus:outline-none"
                            >
                                <flux:icon.star
                                    variant="solid"
                                    x-bind:class="({{ $i }} <= hovered || (hovered === 0 && {{ $i }} <= {{ $panelRating }})) ? 'text-accent' : 'text-muted-2'"
                                    class="size-6"
                                />
                            </button>
                        @endfor
                        @if ($panelRating > 0)
                            <span class="ml-1 font-serif text-[15px] text-ink-2">{{ $ratingLabels[$panelRating] ?? '' }}</span>
                        @else
                            <span class="ml-1 font-serif text-[15px] italic text-muted-2">Not yet rated</span>
                        @endif
                    </div>
                </div>

                {{-- Review --}}
                <div class="mt-6 border-t border-line pt-5">
                    <p class="mb-3 font-sans text-[11.5px] text-muted">Review</p>
                    <flux:textarea
                        wire:model="panelReviewBody"
                        placeholder="{{ __('Write a few lines about what stuck with you…') }}"
                        rows="4"
                        class="font-serif text-[15px]"
                    />
                    <div class="mt-2 flex items-center justify-between">
                        <span class="font-sans text-[11.5px] text-muted">{{ strlen($panelReviewBody) }} chars · saved manually</span>
                        <flux:button wire:click="savePanelReview" size="sm" variant="primary">
                            {{ __('Save review') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Panel backdrop --}}
    <div
        x-show="$wire.panelOpen"
        x-transition:enter="transition duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        wire:click="closePanel"
        class="fixed inset-0 z-40 bg-ink/20 backdrop-blur-[2px]"
    ></div>

    {{-- ── Add-book search modal ─────────────────────────────────────────────── --}}
    <flux:modal name="shelf-add-search" class="p-0" style="width: 780px; max-width: 100%">
        <div class="px-5 py-4">
            <flux:input
                wire:model.live.debounce.400ms="addQuery"
                icon="magnifying-glass"
                placeholder="{{ __('Search by title, author, or ISBN…') }}"
                clearable
                autofocus
                class="font-serif text-[18px]"
            />
        </div>

        <div class="{{ strlen(trim($addQuery)) >= 2 && count($this->addResults) > 0 ? 'border-t border-line' : '' }} max-h-120 overflow-y-auto p-2">
            @if (strlen(trim($addQuery)) >= 2)
                @if (count($this->addResults) > 0)
                    @foreach ($this->addResults as $addBook)
                        @php $onShelf = in_array($addBook['open_library_id'], $this->addUserBookIds); @endphp
                        @php $ap = $coverPalettes[crc32($addBook['open_library_id'] ?? '') % count($coverPalettes)]; @endphp
                        <div class="flex items-center gap-3.5 rounded-[10px] px-3 py-2.5 transition hover:bg-bg-2">
                            <div class="aspect-2/3 w-11 shrink-0 overflow-hidden rounded-sm shadow-sm">
                                @if ($addBook['cover_url'])
                                    <img src="{{ $addBook['cover_url'] }}" alt="{{ $addBook['title'] }}" class="h-full w-full object-cover" loading="lazy" />
                                @else
                                    <div class="cover-ph flex h-full flex-col p-1.5" style="background-color: {{ $ap['bg'] }}; color: {{ $ap['fg'] }}">
                                        <span class="font-serif text-[8px] font-semibold leading-tight" style="color: {{ $ap['fg'] }}">{{ $addBook['title'] }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-serif text-[15px] font-semibold text-ink">{{ $addBook['title'] }}</p>
                                <p class="font-sans text-[12px] text-muted">
                                    {{ $addBook['author'] }}
                                    {{ $addBook['genres'] ? '· ' . implode(', ', array_slice((array) $addBook['genres'], 0, 1)) : '' }}{{ $addBook['page_count'] ? ' · ' . $addBook['page_count'] . ' pp' : '' }}
                                </p>
                            </div>
                            @if ($onShelf)
                                <span class="shrink-0 rounded-lg border border-line px-2.5 py-1.5 font-sans text-[12px] font-semibold text-muted">On shelf</span>
                            @else
                                <button
                                    wire:click="selectBookToAdd('{{ $addBook['open_library_id'] }}')"
                                    class="shrink-0 flex items-center gap-1 rounded-lg border border-line-2 bg-card px-2.5 py-1.5 font-sans text-[12px] font-semibold text-ink-2 transition hover:border-accent hover:bg-accent-soft hover:text-accent-ink"
                                >
                                    <flux:icon.plus class="size-3" /> Add
                                </button>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="py-9 text-center font-sans text-[13px] text-muted">
                        No results for "<em class="font-serif text-ink-2">{{ trim($addQuery) }}</em>"
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>

    {{-- Add-book ownership confirm modal --}}
    <flux:modal name="shelf-add-confirm" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add to shelf') }}</flux:heading>
                <flux:subheading>{{ __('Choose how you want to shelve this book.') }}</flux:subheading>
            </div>
            <flux:radio.group wire:model="addOwnershipStatusId" :label="__('Shelf')">
                @foreach ($this->ownershipStatuses as $status)
                    <flux:radio :value="$status->id" :label="ucfirst($status->name)" />
                @endforeach
            </flux:radio.group>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="addToShelf">{{ __('Add') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Remove book confirm modal --}}
    <flux:modal name="remove-book" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove from shelf?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will remove') }}
                    @if ($this->selectedUserBook)
                        <strong>{{ $this->selectedUserBook->book->title }}</strong>
                    @endif
                    {{ __('and any review you\'ve written. This cannot be undone.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removePanelBookFromShelf">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
