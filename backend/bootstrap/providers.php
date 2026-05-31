<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\PrintingServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    RepositoryServiceProvider::class,
    PrintingServiceProvider::class,
];
