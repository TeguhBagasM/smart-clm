<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Route::get('api/dcs/_validation', fn () => throw ValidationException::withMessages([
        'title' => ['Judul wajib diisi.'],
        'department' => ['Departemen wajib diisi.'],
    ]));
    Route::get('api/dcs/_forbidden', fn () => throw new AuthorizationException('Hanya penyusun yang boleh.'));
    Route::get('api/dcs/_hidden', fn () => throw (new AuthorizationException)->withStatus(404));
    Route::get('api/dcs/_missing', fn () => User::query()->findOrFail(0));
    Route::get('api/dcs/_conflict', fn () => abort(409, 'Terbitan 2 Revisi 0 sudah dipakai.'));
    Route::get('api/dcs/_boom', fn () => throw new RuntimeException('SQLSTATE secret details'));
    Route::get('api/other/_boom', fn () => throw new RuntimeException('plain'));
});

it('renders validation failures as 400 with the first message and the field errors', function () {
    $this->getJson('/api/dcs/_validation')
        ->assertStatus(400)
        ->assertExactJson([
            'error' => 'Judul wajib diisi.',
            'errors' => ['title' => ['Judul wajib diisi.'], 'department' => ['Departemen wajib diisi.']],
        ]);
});

it('renders authorization failures keeping the status', function (string $path, int $status, string $message) {
    $this->getJson($path)->assertStatus($status)->assertExactJson(['error' => $message]);
})->with([
    'forbidden with message' => ['/api/dcs/_forbidden', 403, 'Hanya penyusun yang boleh.'],
    'denied as not found' => ['/api/dcs/_hidden', 404, 'Data tidak ditemukan.'],
]);

it('renders missing models and http exceptions', function (string $path, int $status, string $message) {
    $this->getJson($path)->assertStatus($status)->assertExactJson(['error' => $message]);
})->with([
    'model not found' => ['/api/dcs/_missing', 404, 'Data tidak ditemukan.'],
    'conflict keeps its message' => ['/api/dcs/_conflict', 409, 'Terbitan 2 Revisi 0 sudah dipakai.'],
    'unknown route' => ['/api/dcs/_nope', 404, 'Data tidak ditemukan.'],
]);

it('hides internal details of unexpected failures', function () {
    $this->getJson('/api/dcs/_boom')->assertStatus(500)->assertExactJson(['error' => 'Terjadi kesalahan pada server.']);
});

it('leaves other API routes with the default rendering', function () {
    $this->getJson('/api/other/_boom')->assertStatus(500)->assertJsonMissingPath('error')->assertJsonPath('message', fn ($message) => $message !== 'Terjadi kesalahan pada server.');
});
