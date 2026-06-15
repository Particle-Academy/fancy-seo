<?php

use FancySeo\FancySeo;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('good-page', fn () => '')->name('good');
    Route::get('dup-a', fn () => '')->name('dup.a');
    Route::get('dup-b', fn () => '')->name('dup.b');

    app(FancySeo::class)
        ->route('good', ['title' => 'A Perfectly Good Page Title', 'description' => str_repeat('useful copy ', 8)])
        ->route('dup.a', ['title' => 'Identical Title'])
        ->route('dup.b', ['title' => 'Identical Title']);
});

it('passes a clean route and flags duplicate titles', function () {
    $this->artisan('fancy-seo:validate')
        ->assertExitCode(1); // the duplicate <title> is an error
});

it('emits machine-readable json with a summary', function () {
    $this->artisan('fancy-seo:validate', ['--format' => 'json'])
        ->expectsOutputToContain('"errors": 2') // both dup routes flagged
        ->assertExitCode(1);
});

it('treats thin titles as warnings under --strict', function () {
    Route::get('thin', fn () => '')->name('thin');
    app(FancySeo::class)->route('thin', ['title' => 'Hi']); // 2 chars → under minimum

    $this->artisan('fancy-seo:validate', ['--strict' => true])
        ->assertExitCode(1);
});

it('skips noindex routes entirely (no false-positive defects)', function () {
    // Two NOINDEX routes that share a title — if they were linted they'd add two
    // more "duplicate title" errors on top of the beforeEach dup.a/dup.b pair
    // (→ 4). Skipping noindex keeps the count at 2, proving they were excluded.
    Route::get('admin-a', fn () => '')->name('admin.a');
    Route::get('admin-b', fn () => '')->name('admin.b');

    app(FancySeo::class)
        ->route('admin.a', ['title' => 'Dashboard', 'noindex' => true])
        ->route('admin.b', ['title' => 'Dashboard', 'noindex' => true]);

    $this->artisan('fancy-seo:validate', ['--format' => 'json'])
        ->expectsOutputToContain('"errors": 2'); // only the two indexable dupes
});
