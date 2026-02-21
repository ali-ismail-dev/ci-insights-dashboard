<?php

use Illuminate\Support\Str;

return [
    'use' => 'default',
    
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),
    
    'middleware' => ['web'],
    
    'waits' => [
        'redis:default' => 60,
    ],
    
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'failed' => 10080, // 7 days
        'monitored' => 10080,
    ],
    
    'fast_termination' => false,
    
    'memory_limit' => 64,
    
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['high', 'default'],
                'balance' => 'auto',
                'processes' => 5,
                'tries' => 3,
                'timeout' => 180,
            ],
            'supervisor-2' => [
                'connection' => 'redis',
                'queue' => ['low'],
                'balance' => 'auto',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
        
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['high', 'default', 'low'],
                'balance' => 'auto',
                'processes' => 3,
                'tries' => 3,
                'timeout' => 180,
            ],
        ],
    ],
];