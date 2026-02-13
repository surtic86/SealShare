<?php

test('home redirects to upload', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('upload'));
});
