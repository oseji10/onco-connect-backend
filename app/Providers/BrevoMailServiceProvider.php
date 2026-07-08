<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class BrevoMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registers a "brevo" mail transport that talks to Brevo's REST API
        // directly (https://api.brevo.com), bypassing SMTP entirely — the
        // scheme is "brevo+api", not "brevo+smtp".
        Mail::extend('brevo', function (array $config = []) {
            $factory = new BrevoTransportFactory();

            $dsn = new Dsn(
                'brevo+api',
                'default',
                $config['key'] ?? null
            );

            return $factory->create($dsn);
        });
    }
}