<?php

test('redirects the home page to the dashboard', function () {
    $this->get(route('home'))->assertRedirect('/dashboard');
});
