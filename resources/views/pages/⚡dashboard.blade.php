<?php

use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
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
    public function recentlyAdded()
    {
        return Auth::user()
            ->userBooks()
            ->with(['book', 'ownershipStatus', 'readingStatus'])
            ->latest()
            ->take(6)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8 p-6">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</flux:subheading>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="xl" class="text-3xl font-bold">{{ $this->stats['total'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('Books on shelf') }}</flux:text>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="xl" class="text-3xl font-bold text-blue-500">{{ $this->stats['reading'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('Currently reading') }}</flux:text>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="xl" class="text-3xl font-bold text-green-500">{{ $this->stats['completed'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('Completed') }}</flux:text>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="xl" class="text-3xl font-bold text-zinc-400">{{ $this->stats['wishlist'] }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('On wishlist') }}</flux:text>
        </div>
    </div>

    {{-- Currently reading --}}
    @if ($this->stats['reading'] > 0)
        <div class="flex flex-col gap-4">
            <flux:heading>{{ __('Currently Reading') }}</flux:heading>
            <div class="flex gap-4 overflow-x-auto pb-2">
                @foreach ($this->currentlyReading as $userBook)
                    <a href="{{ route('books.show', $userBook) }}" wire:navigate class="group flex w-32 shrink-0 flex-col gap-2">
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
                            <flux:text class="line-clamp-2 text-sm font-medium">{{ $userBook->book->title }}</flux:text>
                            @if ($userBook->started_at)
                                <flux:text class="text-xs text-zinc-500">
                                    {{ __('Day :n', ['n' => $userBook->started_at->diffInDays(now()) + 1]) }}
                                </flux:text>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recently added --}}
    @if ($this->stats['total'] > 0)
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Recently Added') }}</flux:heading>
                <flux:link :href="route('books.shelf')" wire:navigate class="text-sm">{{ __('View all') }}</flux:link>
            </div>
            <div class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
                @foreach ($this->recentlyAdded as $userBook)
                    <a href="{{ route('books.show', $userBook) }}" wire:navigate class="group flex flex-col gap-2">
                        <div class="aspect-[2/3] overflow-hidden rounded-lg bg-zinc-100 transition-transform group-hover:scale-[1.02] dark:bg-zinc-800">
                            @if ($userBook->book->cover_url)
                                <img
                                    src="{{ $userBook->book->cover_url }}"
                                    alt="{{ $userBook->book->title }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex h-full items-center justify-center p-2 text-center">
                                    <flux:text class="text-xs text-zinc-400">{{ $userBook->book->title }}</flux:text>
                                </div>
                            @endif
                        </div>
                        <div>
                            <flux:text class="line-clamp-2 text-xs font-medium group-hover:text-zinc-600 dark:group-hover:text-zinc-300">
                                {{ $userBook->book->title }}
                            </flux:text>
                            @if ($userBook->readingStatus)
                                <flux:badge
                                    size="sm"
                                    :color="match($userBook->readingStatus->name) {
                                        'in_progress' => 'blue',
                                        'completed'   => 'green',
                                        'abandoned'   => 'red',
                                        default       => 'zinc',
                                    }"
                                    class="mt-1"
                                >
                                    {{ ucfirst(str_replace('_', ' ', $userBook->readingStatus->name)) }}
                                </flux:badge>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading>{{ __('Your shelf is empty') }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('Search for books to get started.') }}</flux:subheading>
                <flux:button :href="route('books.search')" wire:navigate class="mt-4" variant="primary">
                    {{ __('Search books') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
