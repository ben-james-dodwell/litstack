@props(['sidebar' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    {{-- Brand mark --}}
    <div class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-[9px] bg-accent shadow-[0_1px_0_rgba(0,0,0,0.06),inset_0_-2px_0_rgba(0,0,0,0.08)]">
        {{-- Stacked-books lines --}}
        <div class="flex flex-col gap-1">
            <div class="h-0.75 w-4 rounded-full bg-card opacity-90"></div>
            <div class="h-0.75 w-3 rounded-full bg-card opacity-90"></div>
            <div class="h-0.75 w-3.5 rounded-full bg-card opacity-90"></div>
        </div>
    </div>

    {{-- Wordmark --}}
    <div class="flex items-baseline leading-none">
        <span class="font-serif text-[21px] font-medium italic tracking-[0.015em] text-ink">Lit</span>
        <span class="ml-1 font-sans text-[11px] font-semibold uppercase tracking-[0.18em] text-accent-ink" style="position:relative;top:-1px">stack</span>
    </div>
</div>
