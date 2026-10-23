<?php

use App\Models\Setting;

it('falls back to the config default when nothing is saved', function () {
    expect(Setting::get('preorder_slot_limit'))->toBe(100)
        ->and(Setting::get('pilot_duration_days'))->toBe(30);
});

it('returns the saved value over the default', function () {
    Setting::get('preorder_slot_limit'); // warm the cache first

    Setting::set('preorder_slot_limit', 150);

    expect(Setting::get('preorder_slot_limit'))->toBe(150);
});

it('stores arrays such as notification e-mails', function () {
    Setting::set('admin_notification_emails', ['a@example.com', 'b@example.com']);

    expect(Setting::get('admin_notification_emails'))->toBe(['a@example.com', 'b@example.com']);
});

it('rejects unknown setting keys', function () {
    Setting::set('no_such_setting', 1);
})->throws(InvalidArgumentException::class);
