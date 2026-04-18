<?php

use App\Models\PuzzleBook;
use App\Models\PuzzleGame;
use App\Services\PuzzleService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Daily Book Puzzle')] class extends Component {
    public string $guessQuery = '';

    /** @var array<int, string> */
    public array $suggestions = [];

    #[Computed]
    public function puzzleBook(): ?PuzzleBook
    {
        return app(PuzzleService::class)->todaysBook();
    }

    #[Computed]
    public function game(): ?PuzzleGame
    {
        if (! $this->puzzleBook) {
            return null;
        }

        return PuzzleGame::with('puzzleBook')
            ->where('user_id', Auth::id())
            ->where('puzzle_date', today())
            ->first();
    }

    #[Computed]
    public function clues(): array
    {
        if (! $this->puzzleBook) {
            return [];
        }

        return app(PuzzleService::class)->cluesForBook($this->puzzleBook);
    }

    #[Computed]
    public function currentRound(): int
    {
        return $this->game?->current_round ?? 1;
    }

    #[Computed]
    public function maxRounds(): int
    {
        return app(PuzzleService::class)->maxRounds();
    }

    #[Computed]
    public function isCompleted(): bool
    {
        return $this->game?->completed ?? false;
    }

    #[Computed]
    public function won(): bool
    {
        return $this->game?->won ?? false;
    }

    #[Computed]
    public function guessHistory(): array
    {
        return $this->game?->guesses ?? [];
    }

    public function mount(): void
    {
        if (! $this->puzzleBook) {
            return;
        }

        if (! $this->game) {
            PuzzleGame::create([
                'user_id'        => Auth::id(),
                'puzzle_book_id' => $this->puzzleBook->id,
                'puzzle_date'    => today(),
                'guesses'        => [],
                'current_round'  => 1,
            ]);

            unset($this->game);
        }
    }

    public function updatedGuessQuery(): void
    {
        $query = trim($this->guessQuery);

        if (strlen($query) < 2) {
            $this->suggestions = [];

            return;
        }

        $this->suggestions = PuzzleBook::where('title', 'like', "%{$query}%")
            ->orderBy('title')
            ->limit(8)
            ->pluck('title')
            ->all();
    }

    public function submitGuess(string $title): void
    {
        $game = $this->game;

        if (! $game || $game->completed) {
            return;
        }

        $this->guessQuery = '';
        $this->suggestions = [];

        $correct = strtolower(trim($title)) === strtolower(trim($this->puzzleBook->title));

        $guesses = $game->guesses ?? [];
        $guesses[] = ['title' => $title, 'correct' => $correct];

        if ($correct) {
            $game->update([
                'guesses'      => $guesses,
                'won'          => true,
                'completed'    => true,
                'completed_at' => now(),
            ]);
        } else {
            $nextRound = min($game->current_round + 1, $this->maxRounds + 1);
            $exhausted = $nextRound > $this->maxRounds;

            $game->update([
                'guesses'      => $guesses,
                'current_round' => $nextRound,
                'completed'    => $exhausted,
                'completed_at' => $exhausted ? now() : null,
            ]);
        }

        unset($this->game);
    }

    public function skipRound(): void
    {
        $game = $this->game;

        if (! $game || $game->completed) {
            return;
        }

        $nextRound = min($game->current_round + 1, $this->maxRounds + 1);
        $exhausted = $nextRound > $this->maxRounds;

        $game->update([
            'current_round' => $nextRound,
            'completed'     => $exhausted,
            'completed_at'  => $exhausted ? now() : null,
        ]);

        unset($this->game);
    }

    public function selectSuggestion(string $title): void
    {
        $this->guessQuery = $title;
        $this->suggestions = [];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Daily Book Puzzle') }}</flux:heading>
            <flux:subheading>{{ now()->format('F j, Y') }}</flux:subheading>
        </div>
        @if ($this->game && ! $this->isCompleted)
            <flux:text class="text-sm text-zinc-500">
                {{ __('Round :round of :max', ['round' => $this->currentRound, 'max' => $this->maxRounds]) }}
            </flux:text>
        @endif
    </div>

    @if (! $this->puzzleBook)
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading>{{ __('No puzzle available') }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('Run puzzle:seed to populate the puzzle library.') }}</flux:subheading>
            </div>
        </div>
    @else
        <div class="mx-auto w-full max-w-xl">

            {{-- Clue cards --}}
            <div class="flex flex-col gap-3">
                @foreach ($this->clues as $round => $clue)
                    @if ($round <= $this->currentRound || $this->isCompleted)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                             @if ($round === $this->currentRound && ! $this->isCompleted) x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })" @endif>
                            <flux:text class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">
                                {{ __('Clue :n', ['n' => $round]) }} — {{ $clue['label'] }}
                            </flux:text>

                            @if ($clue['type'] === 'page_count')
                                <flux:heading class="text-lg">
                                    {{ $clue['value'] ? number_format($clue['value']) . ' ' . __('pages') : __('Unknown') }}
                                </flux:heading>

                            @elseif ($clue['type'] === 'published_year')
                                <flux:heading class="text-lg">
                                    {{ $clue['value'] ?? __('Unknown') }}
                                </flux:heading>

                            @elseif ($clue['type'] === 'genres')
                                @if (count($clue['value']) > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($clue['value'] as $genre)
                                            <flux:badge size="sm" color="zinc">{{ $genre }}</flux:badge>
                                        @endforeach
                                    </div>
                                @else
                                    <flux:text class="text-zinc-500">{{ __('Unknown') }}</flux:text>
                                @endif

                            @elseif ($clue['type'] === 'publisher')
                                <flux:heading class="text-lg">
                                    {{ $clue['value'] ?? __('Unknown') }}
                                </flux:heading>

                            @elseif ($clue['type'] === 'cover')
                                @if ($clue['value'])
                                    <div class="w-32 overflow-hidden rounded-lg">
                                        <img
                                            src="{{ $clue['value'] }}"
                                            alt="{{ __('Book cover') }}"
                                            class="h-auto w-full object-cover {{ $clue['blur'] === 'xl' ? 'blur-xl' : 'blur-sm' }}"
                                        />
                                    </div>
                                @else
                                    <flux:text class="text-zinc-500">{{ __('No cover available') }}</flux:text>
                                @endif

                            @elseif (in_array($clue['type'], ['description_excerpt', 'description_full']))
                                <flux:text class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                                    {{ $clue['value'] ?? __('No description available') }}
                                </flux:text>

                            @elseif ($clue['type'] === 'author')
                                <flux:heading class="text-lg">
                                    {{ $clue['value'] ?? __('Unknown') }}
                                </flux:heading>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Previous guesses --}}
            @if (count($this->guessHistory) > 0)
                <div class="mt-4 flex flex-col gap-1">
                    @foreach ($this->guessHistory as $guess)
                        <div class="flex items-center gap-2 text-sm">
                            @if ($guess['correct'])
                                <flux:icon.check-circle class="size-4 text-green-500" />
                                <span class="text-green-600 dark:text-green-400">{{ $guess['title'] }}</span>
                            @else
                                <flux:icon.x-circle class="size-4 text-red-400" />
                                <span class="text-zinc-500 line-through">{{ $guess['title'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Result banner --}}
            @if ($this->isCompleted)
                <div class="mt-6 rounded-xl border p-6 text-center {{ $this->won ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900' }}">
                    @if ($this->won)
                        <flux:heading class="text-green-700 dark:text-green-300">
                            {{ __('You got it!') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                            {{ __('Solved in :n clue', ['n' => count($this->guessHistory)]) }}{{ count($this->guessHistory) === 1 ? '' : 's' }}
                        </flux:text>
                    @else
                        <flux:heading class="text-zinc-700 dark:text-zinc-300">
                            {{ __('Better luck tomorrow!') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                            {{ __('The answer was:') }}
                        </flux:text>
                    @endif
                    <div class="mt-3 flex items-center justify-center gap-3">
                        @if ($this->puzzleBook->cover_url)
                            <img src="{{ $this->puzzleBook->cover_url }}" alt="{{ $this->puzzleBook->title }}"
                                 class="h-16 w-auto rounded shadow" />
                        @endif
                        <div class="text-left">
                            <flux:heading>{{ $this->puzzleBook->title }}</flux:heading>
                            @if ($this->puzzleBook->author)
                                <flux:text class="text-zinc-500">{{ $this->puzzleBook->author }}</flux:text>
                            @endif
                        </div>
                    </div>
                </div>

            @else
                {{-- Guess input --}}
                <div class="relative mt-6" x-data="{ open: false }">
                    <flux:input
                        wire:model.live.debounce.250ms="guessQuery"
                        icon="book-open"
                        placeholder="{{ __('Type a book title to guess…') }}"
                        clearable
                        x-on:focus="open = true"
                        x-on:click.outside="open = false"
                        x-on:keydown.enter.prevent="open = false; $wire.submitGuess($wire.guessQuery)"
                        x-on:keydown.escape="open = false"
                    />

                    @if (count($this->suggestions) > 0)
                        <div
                            x-show="open && {{ count($this->suggestions) }} > 0"
                            class="absolute z-50 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            @foreach ($this->suggestions as $suggestion)
                                <button
                                    type="button"
                                    wire:click="selectSuggestion('{{ addslashes($suggestion) }}')"
                                    x-on:click="open = false"
                                    class="w-full px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 first:rounded-t-lg last:rounded-b-lg"
                                >
                                    {{ $suggestion }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex gap-2">
                        <flux:button
                            wire:click="submitGuess(guessQuery)"
                            variant="primary"
                            :disabled="blank($guessQuery)"
                        >
                            {{ __('Guess') }}
                        </flux:button>
                        @if ($this->currentRound < $this->maxRounds)
                            <flux:button wire:click="skipRound" variant="ghost">
                                {{ __('Skip (reveal next clue)') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    @endif
</div>
