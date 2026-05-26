<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            SocialiteWasCalled::class,
            MicrosoftExtendSocialite::class . '@handle'
        );
    }
}
