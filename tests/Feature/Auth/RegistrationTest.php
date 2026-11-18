<?php

use App\Models\User;

test('does not expose a public registration page', function () {
    $this->get('/register')->assertNotFound();
});

test('rejects public registration submissions', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});
