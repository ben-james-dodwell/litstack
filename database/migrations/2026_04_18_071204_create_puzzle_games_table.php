<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puzzle_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('puzzle_book_id')->constrained('puzzle_books')->cascadeOnDelete();
            $table->date('puzzle_date');
            $table->json('guesses')->nullable();
            $table->unsignedTinyInteger('current_round')->default(1);
            $table->boolean('won')->default(false);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'puzzle_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puzzle_games');
    }
};
