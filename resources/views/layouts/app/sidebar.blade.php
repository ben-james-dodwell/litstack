<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg font-sans text-ink antialiased">
        @php
            $ownedId    = \App\Models\OwnershipStatus::where('name', 'owned')->value('id');
            $wishlistId = \App\Models\OwnershipStatus::where('name', 'wishlist')->value('id');
            $ownedParam = request()->query('ownership');

            $allCount      = auth()->check() ? auth()->user()->userBooks()->count() : 0;
            $ownedCount    = auth()->check() ? auth()->user()->userBooks()->where('ownership_status_id', $ownedId)->count() : 0;
            $wishlistCount = auth()->check() ? auth()->user()->userBooks()->where('ownership_status_id', $wishlistId)->count() : 0;
        @endphp

        {{-- Desktop sidebar (hidden on mobile — mobile nav is in flux:header below) --}}
        <flux:sidebar sticky class="max-lg:hidden border-e border-line bg-bg-2">

            <flux:sidebar.header class="pb-4">
                <a href="{{ route('books.shelf') }}" wire:navigate>
                    <x-app-logo class="px-1 py-0.5" />
                </a>
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Library" class="grid">
                    <flux:sidebar.item
                        icon="book-open"
                        :href="route('books.shelf')"
                        :current="request()->routeIs('books.shelf') && !$ownedParam"
                        wire:navigate
                    >
                        <span class="flex-1">{{ __('All Books') }}</span>
                        <span class="ml-auto font-sans text-[13px] tabular-nums {{ request()->routeIs('books.shelf') && !$ownedParam ? 'font-semibold text-accent-ink' : 'text-muted' }}">
                            {{ $allCount }}
                        </span>
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="check"
                        :href="route('books.shelf') . '?ownership=' . $ownedId"
                        :current="request()->routeIs('books.shelf') && $ownedParam == $ownedId"
                        wire:navigate
                    >
                        <span class="flex-1">{{ __('Owned') }}</span>
                        <span class="ml-auto font-sans text-[13px] tabular-nums {{ request()->routeIs('books.shelf') && $ownedParam == $ownedId ? 'font-semibold text-accent-ink' : 'text-muted' }}">
                            {{ $ownedCount }}
                        </span>
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="bookmark"
                        :href="route('books.shelf') . '?ownership=' . $wishlistId"
                        :current="request()->routeIs('books.shelf') && $ownedParam == $wishlistId"
                        wire:navigate
                    >
                        <span class="flex-1">{{ __('Wishlist') }}</span>
                        <span class="ml-auto font-sans text-[13px] tabular-nums {{ request()->routeIs('books.shelf') && $ownedParam == $wishlistId ? 'font-semibold text-accent-ink' : 'text-muted' }}">
                            {{ $wishlistCount }}
                        </span>
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- User menu --}}
            @auth
                <flux:dropdown position="top" align="start" class="w-full">
                    <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-bg-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-ink font-sans text-[12.5px] font-semibold tracking-wide text-card">
                            {{ auth()->user()->initials() }}
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <div class="truncate font-sans text-[13px] font-medium text-ink">{{ auth()->user()->name }}</div>
                            <div class="truncate font-sans text-[11px] text-muted">{{ auth()->user()->email }}</div>
                        </div>
                        <flux:icon.chevron-up-down class="size-4 shrink-0 text-muted-2" />
                    </button>

                    <flux:menu>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endauth
        </flux:sidebar>

        {{-- Mobile header + dropdown nav (flux:header must be present for Flux's CSS grid to activate) --}}
        <flux:header
            class="lg:hidden relative border-b border-line bg-bg-2"
            x-data="{ open: false }"
            @click.outside="open = false"
            @keydown.escape.window="open = false"
        >
            {{-- Hamburger --}}
            <button
                @click="open = !open"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-ink-2 transition hover:bg-bg-3"
                :aria-expanded="open"
                aria-label="{{ __('Toggle navigation') }}"
            >
                <flux:icon.bars-2 x-show="!open" class="size-5" />
                <flux:icon.x-mark x-show="open" class="size-5" />
            </button>

            {{-- Centred logo --}}
            <a href="{{ route('books.shelf') }}" wire:navigate @click="open = false" class="absolute left-1/2 -translate-x-1/2">
                <x-app-logo />
            </a>

            {{-- Profile initials → settings --}}
            @auth
                <a href="{{ route('profile.edit') }}" wire:navigate
                   class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-ink font-sans text-[12px] font-semibold tracking-wide text-card transition hover:opacity-80">
                    {{ auth()->user()->initials() }}
                </a>
            @endauth

            {{-- Dropdown nav panel --}}
            <div
                x-show="open"
                x-transition:enter="transition duration-200 ease-out"
                x-transition:enter-start="-translate-y-1 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition duration-150 ease-in"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="-translate-y-1 opacity-0"
                class="absolute left-0 right-0 top-full z-30 border-b border-line bg-bg-2 px-3 py-2 shadow-[0_8px_24px_-8px_rgba(30,20,10,0.15)]"
            >
                <p class="px-3 pb-1.5 pt-0.5 font-sans text-[10.5px] font-semibold uppercase tracking-[0.07em] text-muted">Library</p>

                <a href="{{ route('books.shelf') }}" wire:navigate @click="open = false"
                   class="flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 font-sans text-[14px] transition hover:bg-bg-3
                          {{ request()->routeIs('books.shelf') && !$ownedParam ? 'border border-line-2 bg-card font-semibold text-accent-ink' : 'text-ink' }}">
                    <flux:icon.book-open class="size-4 shrink-0 {{ request()->routeIs('books.shelf') && !$ownedParam ? 'text-accent-ink' : 'text-muted' }}" />
                    {{ __('All Books') }}
                    <span class="ml-auto font-sans text-[12.5px] tabular-nums {{ request()->routeIs('books.shelf') && !$ownedParam ? 'text-accent-ink' : 'text-muted' }}">{{ $allCount }}</span>
                </a>

                <a href="{{ route('books.shelf') . '?ownership=' . $ownedId }}" wire:navigate @click="open = false"
                   class="flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 font-sans text-[14px] transition hover:bg-bg-3
                          {{ request()->routeIs('books.shelf') && $ownedParam == $ownedId ? 'border border-line-2 bg-card font-semibold text-accent-ink' : 'text-ink' }}">
                    <flux:icon.check class="size-4 shrink-0 {{ request()->routeIs('books.shelf') && $ownedParam == $ownedId ? 'text-accent-ink' : 'text-muted' }}" />
                    {{ __('Owned') }}
                    <span class="ml-auto font-sans text-[12.5px] tabular-nums {{ request()->routeIs('books.shelf') && $ownedParam == $ownedId ? 'text-accent-ink' : 'text-muted' }}">{{ $ownedCount }}</span>
                </a>

                <a href="{{ route('books.shelf') . '?ownership=' . $wishlistId }}" wire:navigate @click="open = false"
                   class="flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 font-sans text-[14px] transition hover:bg-bg-3
                          {{ request()->routeIs('books.shelf') && $ownedParam == $wishlistId ? 'border border-line-2 bg-card font-semibold text-accent-ink' : 'text-ink' }}">
                    <flux:icon.bookmark class="size-4 shrink-0 {{ request()->routeIs('books.shelf') && $ownedParam == $wishlistId ? 'text-accent-ink' : 'text-muted' }}" />
                    {{ __('Wishlist') }}
                    <span class="ml-auto font-sans text-[12.5px] tabular-nums {{ request()->routeIs('books.shelf') && $ownedParam == $wishlistId ? 'text-accent-ink' : 'text-muted' }}">{{ $wishlistCount }}</span>
                </a>

            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
