@props([
    'coverUrl' => null,
    'title',
    'author' => null,
    'seed',
    'size' => 'sm',
    'fit' => 'cover',
])

@php
    $palette = \App\Models\Book::coverPalette($seed);

    $sizes = [
        'xs' => ['title' => 'text-[9px]', 'author' => 'text-[6.5px]', 'authorClamp' => 'line-clamp-1', 'pad' => 'p-2', 'gap' => 'gap-1'],
        'sm' => ['title' => 'text-[13px]', 'author' => 'text-[9px]', 'authorClamp' => 'line-clamp-2', 'pad' => 'p-3.5', 'gap' => 'gap-1.5'],
        'lg' => ['title' => 'text-[18px]', 'author' => 'text-[11px]', 'authorClamp' => 'line-clamp-2', 'pad' => 'p-5', 'gap' => 'gap-2'],
    ][$size];
@endphp

<div class="relative h-full w-full">
    {{-- Always rendered as the base layer: Open Library sometimes returns an
         HTTP 200 with a 1x1 pixel "no cover" image instead of a 404, so a real
         <img> can silently fail to show anything even when cover_url is set. --}}
    <div
        class="cover-ph absolute inset-0 flex flex-col items-start justify-center overflow-hidden {{ $sizes['gap'] }} {{ $sizes['pad'] }}"
        style="background-color: {{ $palette['bg'] }}; color: {{ $palette['fg'] }}"
    >
        @if ($size !== 'xs')
            <div class="absolute bottom-0 left-1 top-0 w-px opacity-20" style="background: {{ $palette['fg'] }}"></div>
        @endif

        <div class="line-clamp-3 font-serif {{ $sizes['title'] }} font-semibold leading-snug" style="color: {{ $palette['fg'] }}">
            {{ $title }}
        </div>

        @if ($author)
            <div class="{{ $sizes['authorClamp'] }} font-sans {{ $sizes['author'] }} font-medium uppercase tracking-[0.06em] opacity-75" style="color: {{ $palette['fg'] }}">
                {{ $author }}
            </div>
        @endif
    </div>

    @if ($coverUrl)
        <img
            x-data="{ broken: false }"
            x-show="!broken"
            x-on:load="broken = $event.target.naturalWidth <= 2"
            x-on:error="broken = true"
            src="{{ $coverUrl }}"
            alt="{{ $title }}"
            {{ $attributes->class(['absolute inset-0 h-full w-full', 'object-contain' => $fit === 'contain', 'object-cover' => $fit === 'cover']) }}
            loading="lazy"
        />
    @endif
</div>
