<?php

use App\Models\User;

test('demo login redirects to shelf when enabled', function () {
    Config::set('demo.enabled', true);
    Config::set('demo.email', 'demo@litstack.app');

    User::factory()->create(['email' => 'demo@litstack.app']);

    $this->get(route('demo.login'))
        ->assertRedirect(route('books.shelf'));

    $this->assertAuthenticated();
});

test('demo login returns 404 when disabled', function () {
    Config::set('demo.enabled', false);

    $this->get(route('demo.login'))->assertNotFound();
});

test('demo login returns 404 when demo user does not exist', function () {
    Config::set('demo.enabled', true);
    Config::set('demo.email', 'demo@litstack.app');

    $this->get(route('demo.login'))->assertNotFound();
});

test('demo login regenerates session', function () {
    Config::set('demo.enabled', true);
    Config::set('demo.email', 'demo@litstack.app');

    User::factory()->create(['email' => 'demo@litstack.app']);

    $sessionBefore = $this->withSession(['_token' => 'old'])->get(route('demo.login'));

    $this->assertAuthenticated();
});
