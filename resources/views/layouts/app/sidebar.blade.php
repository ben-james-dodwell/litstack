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

        <flux:sidebar sticky collapsible="mobile"
            class="border-e border-line bg-bg-2">

            <flux:sidebar.header class="pb-4">
                <a href="{{ route('books.shelf') }}" wire:navigate>
                    <x-app-logo class="px-1 py-0.5" />
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
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

        {{-- Mobile header --}}
        <flux:header class="lg:hidden border-b border-line bg-bg-2">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <a href="{{ route('books.shelf') }}" wire:navigate class="absolute left-1/2 -translate-x-1/2">
                <x-app-logo />
            </a>
            <flux:spacer />
            @auth
                <flux:dropdown position="bottom" align="end">
                    <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                    <flux:menu>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">{{ __('Log out') }}</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endauth
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
