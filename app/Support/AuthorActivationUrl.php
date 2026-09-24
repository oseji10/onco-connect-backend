<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class AuthorActivationUrl
{
    public static function for(int $userId, int $days = 14): string
    {
        /*
         * Generate the REAL Laravel signed API URL.
         *
         * Example:
         *
         * http://localhost:8007/api/author/activate/772
         * ?expires=1791466616
         * &signature=abc123
         */
        $backendUrl = URL::temporarySignedRoute(
            'author.activate',
            now()->addDays($days),
            [
                'user' => $userId,
            ]
        );

        /*
         * Extract only the signed query parameters.
         */
        $parts = parse_url($backendUrl);

        $query = $parts['query'] ?? '';

        /*
         * The generated query contains:
         *
         * expires=...
         * signature=...
         */
        parse_str($query, $params);

        if (
            empty($params['expires']) ||
            empty($params['signature'])
        ) {
            throw new \RuntimeException(
                'Unable to generate author activation signature.'
            );
        }

        /*
         * Build the FRONTEND URL.
         *
         * The frontend is static, so the user ID must NOT be
         * part of the Next.js route.
         *
         * Instead:
         *
         * /author-activation-page/
         * ?user=772
         * &expires=...
         * &signature=...
         */
        $frontend = rtrim(
            config('app.frontend_url', env('FRONTEND_URL')),
            '/'
        );

        return $frontend . '/author-activation-page/?' . http_build_query([
            'user' => $userId,
            'expires' => $params['expires'],
            'signature' => $params['signature'],
        ]);
    }
}