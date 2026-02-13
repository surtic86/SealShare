<?php

use App\Models\Setting;

test('database seeder sets default site title', function () {
    $this->seed();

    expect(Setting::get('site_title'))->toBe('SealShare');
});

test('database seeder sets default site description', function () {
    $this->seed();

    expect(Setting::get('site_description'))->toBe('Simple, secure file sharing');
});
