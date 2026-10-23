<?php

it('renders the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('redirects guests away from the admin dashboard', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
