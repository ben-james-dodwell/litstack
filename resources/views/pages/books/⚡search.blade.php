<?php

use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\UserBook;
use App\Services\OpenLibraryService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Search Books')] class extends Component {
    public string $query = '';

    public ?string $selectedOpenLibraryId = null;

    public ?int $ownershipStatusId = null;

    #[Computed]
    public function results(): array
    {
        if (strlen(trim($this->query)) < 2) {
            return [];
        }

        return app(OpenLibraryService::class)->search($this->query);
    }

    #[Computed]
    public function ownershipStatuses()
    {
        return OwnershipStatus::all();
    }

    #[Computed]
    public function userBookIds(): array
    {
        return Auth::user()
            ->userBooks()
            ->with('book:id,open_library_id')
            ->get()
            ->pluck('book.open_library_id')
            ->filter()
            ->all();
    }

    public function selectBook(string $openLibraryId): void
    {
        $this->selectedOpenLibraryId = $openLibraryId;
        $this->ownershipStatusId = OwnershipStatus::where('name', 'owned')->value('id');
        Flux::modal('add-to-shelf')->show();
    }

    public function addToShelf(): void
    {
        $this->validate(['ownershipStatusId' => 'required|exists:ownership_statuses,id']);

        $bookData = collect($this->results)
            ->firstWhere('open_library_id', $this->selectedOpenLibraryId);

        if (! $bookData) {
            return;
        }

        $book = Book::firstOrCreate(
            ['open_library_id' => $bookData['open_library_id']],
            collect($bookData)->except('open_library_id')->all(),
        );

        UserBook::firstOrCreate(
            ['user_id' => Auth::id(), 'book_id' => $book->id],
            ['ownership_status_id' => $this->ownershipStatusId],
        );

        Flux::modal('add-to-shelf')->close();
        Flux::toast(variant: 'success', text: __('Book added to your shelf.'));

        unset($this->userBookIds);
    }
}; ?>

<x-layouts::app :title="__('Search Books')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div class="max-w-xl">
            <flux:heading size="xl">{{ __('Search Books') }}</flux:heading>
            <flux:subheading>{{ __('Search the Open Library catalogue to add books to your shelf.') }}</flux:subheading>
        </div>

        <flux:input
            wire:model.live.debounce.400ms="query"
            icon="magnifying-glass"
            placeholder="{{ __('Search by title, author, or ISBN…') }}"
            clearable
            class="max-w-xl"
        />

        @if (strlen(trim($query)) >= 2)
            @if (count($this->results) > 0)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($this->results as $book)
                        @php $onShelf = in_array($book['open_library_id'], $this->userBookIds); @endphp
                        <div class="flex flex-col gap-2">
                            <div class="relative aspect-[2/3] overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                @if ($book['cover_url'])
                                    <img
                                        src="{{ $book['cover_url'] }}"
                                        alt="{{ $book['title'] }}"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                @else
                                    <div class="flex h-full items-center justify-center p-4 text-center">
                                        <flux:text class="text-xs text-zinc-400">{{ __('No cover') }}</flux:text>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col gap-1">
                                <flux:text class="line-clamp-2 text-sm font-medium">{{ $book['title'] }}</flux:text>
                                @if ($book['author'])
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $book['author'] }}</flux:text>
                                @endif
                                @if ($book['published_year'])
                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ $book['published_year'] }}</flux:text>
                                @endif
                            </div>

                            @if ($onShelf)
                                <flux:badge color="green" size="sm" class="w-fit">{{ __('On shelf') }}</flux:badge>
                            @else
                                <flux:button
                                    size="sm"
                                    wire:click="selectBook('{{ $book['open_library_id'] }}')"
                                >
                                    {{ __('Add to shelf') }}
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-zinc-500">{{ __('No results found for') }} "{{ $query }}".</flux:text>
            @endif
        @endif
    </div>

    <flux:modal name="add-to-shelf" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add to shelf') }}</flux:heading>
                <flux:subheading>{{ __('Choose how you want to shelve this book.') }}</flux:subheading>
            </div>

            <flux:radio.group wire:model="ownershipStatusId" :label="__('Shelf')">
                @foreach ($this->ownershipStatuses as $status)
                    <flux:radio :value="$status->id" :label="ucfirst($status->name)" />
                @endforeach
            </flux:radio.group>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="addToShelf">
                    {{ __('Add') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-layouts::app>
