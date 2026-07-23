<?php

it('renders the landing page', function () {
    $this->get(route('home'))->assertOk();
});
