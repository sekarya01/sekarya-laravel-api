<?php

use App\Providers\AppServiceProvider;
use App\Providers\AxiomServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    AxiomServiceProvider::class,
    RateLimitServiceProvider::class,
];
