<?php

return [

    'signatures' => [
        'allowed_mimes' => ['png'],
        'max_size_kb' => 2048,
        'disk' => 'signatures',
    ],

    'profile' => [
        'mobile_no_regex' => '/^[0-9+\-\s()]{7,20}$/',
    ],

    'activity_log' => [
        'default_limit' => 20,
        'max_limit' => 50,
    ],

];
