<?php

use App\Models\PuzzleBook;
use App\Models\PuzzleGame;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = PuzzleBook::factory()->create(['title' => 'The Great Gatsby']);
});

test('guests are redirected to login', function () {
    $this->get(route('game'))->assertRedirect(route('login'));
});

test('page loads and creates a game for today', function () {
    Livewire::actingAs($this->user)->test('pages::game.index')
        ->assertOk();

    expect(PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->exists())->toBeTrue();
});

test('shows no-puzzle message when puzzle_books is empty', function () {
    PuzzleBook::query()->delete();

    Livewire::actingAs($this->user)->test('pages::game.index')
        ->assertSee('No puzzle available');
});

test('shows first clue on load', function () {
    PuzzleBook::factory()->create(['page_count' => 350]);

    Livewire::actingAs($this->user)->test('pages::game.index')
        ->assertSee('350');
});

test('correct guess wins the game', function () {
    $component = Livewire::actingAs($this->user)->test('pages::game.index');

    $component->call('submitGuess', 'The Great Gatsby')
        ->assertSee('You got it!');

    $game = PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->first();
    expect($game->won)->toBeTrue();
    expect($game->completed)->toBeTrue();
    expect($game->guesses)->toHaveCount(1);
    expect($game->guesses[0]['correct'])->toBeTrue();
});

test('wrong guess advances the round and records the guess', function () {
    $component = Livewire::actingAs($this->user)->test('pages::game.index');

    $component->call('submitGuess', 'Wrong Title');

    $game = PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->first();
    expect($game->won)->toBeFalse();
    expect($game->completed)->toBeFalse();
    expect($game->current_round)->toBe(2);
    expect($game->guesses[0]['correct'])->toBeFalse();
});

test('guess matching is case-insensitive', function () {
    $component = Livewire::actingAs($this->user)->test('pages::game.index');

    $component->call('submitGuess', 'the great gatsby')
        ->assertSee('You got it!');

    $game = PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->first();
    expect($game->won)->toBeTrue();
});

test('skipping a round advances the round without recording a guess', function () {
    $component = Livewire::actingAs($this->user)->test('pages::game.index');

    $component->call('skipRound');

    $game = PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->first();
    expect($game->current_round)->toBe(2);
    expect($game->guesses)->toBeEmpty();
});

test('exhausting all rounds marks game as completed and lost', function () {
    $component = Livewire::actingAs($this->user)->test('pages::game.index');

    for ($i = 0; $i < 7; $i++) {
        $component->call('skipRound');
    }

    $game = PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->first();
    expect($game->completed)->toBeTrue();
    expect($game->won)->toBeFalse();
});

test('cannot guess after game is completed', function () {
    PuzzleGame::create([
        'user_id' => $this->user->id,
        'puzzle_book_id' => $this->book->id,
        'puzzle_date' => today(),
        'guesses' => [],
        'current_round' => 10,
        'won' => false,
        'completed' => true,
        'completed_at' => now(),
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::game.index');
    $component->call('submitGuess', 'The Great Gatsby');

    $game = PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->first();
    expect($game->won)->toBeFalse();
});

test('visiting the page twice does not create a duplicate game', function () {
    Livewire::actingAs($this->user)->test('pages::game.index');
    Livewire::actingAs($this->user)->test('pages::game.index');

    expect(PuzzleGame::where('user_id', $this->user->id)->where('puzzle_date', today())->count())->toBe(1);
});

test('suggestions search puzzle books by title', function () {
    PuzzleBook::factory()->create(['title' => 'Moby Dick']);
    PuzzleBook::factory()->create(['title' => 'Moby Dick Revisited']);
    PuzzleBook::factory()->create(['title' => 'Unrelated Book']);

    $component = Livewire::actingAs($this->user)->test('pages::game.index');
    $component->set('guessQuery', 'Moby');

    expect($component->get('suggestions'))->toHaveCount(2);
});
