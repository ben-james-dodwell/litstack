@props([
    'coverUrl' => null,
    'title',
    'author' => null,
    'seed',
    'size' => 'sm',
    'showAuthor' => false,
    'fit' => 'cover',
])

@php
    $palette = \App\Models\Book::coverPalette($seed);

    $sizes = [
        'xs' => ['text' => 'text-[8px]', 'pad' => 'p-1.5'],
        'sm' => ['text' => 'text-[13px]', 'pad' => 'p-[10px_9px]'],
        'lg' => ['text' => 'text-[17px]', 'pad' => 'p-[14px_12px]'],
    ][$size];
@endphp

@if ($coverUrl)
    <img
        src="{{ $coverUrl }}"
        alt="{{ $title }}"
        {{ $attributes->class(['h-full w-full', 'object-contain' => $fit === 'contain', 'object-cover' => $fit === 'cover']) }}
        loading="lazy"
    />
@else
    <div
        class="cover-ph relative flex h-full flex-col overflow-hidden {{ $sizes['pad'] }}"
        style="background-color: {{ $palette['bg'] }}; color: {{ $palette['fg'] }}"
    >
        @if ($size !== 'xs')
            <div class="absolute bottom-0 left-1 top-0 w-px opacity-20" style="background: {{ $palette['fg'] }}"></div>
        @endif

        <div class="font-serif {{ $sizes['text'] }} font-semibold leading-tight" style="color: {{ $palette['fg'] }}">
            {{ $title }}
        </div>

        @if ($showAuthor)
            <div class="mt-auto flex justify-between font-sans text-[8.5px] font-medium uppercase tracking-[0.04em] opacity-75" style="color: {{ $palette['fg'] }}">
                <span>{{ last(explode(' ', $author ?? '')) }}</span>
                <span>—</span>
            </div>
        @endif
    </div>
@endif
