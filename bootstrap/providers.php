<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationsServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationsServiceProvider::class,
];
