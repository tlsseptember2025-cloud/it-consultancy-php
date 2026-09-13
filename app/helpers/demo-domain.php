<?php

/*
|--------------------------------------------------------------------------
| Demo Domain Helpers
|--------------------------------------------------------------------------
|
| Shared helpers for Demo request validation.
| LOCAL / DEV / DEMO compatible.
|
*/

/**
 * Return the Demo environment based on the current host.
 *
 * LOCAL:
 *   null
 *
 * DEV:
 *   dev
 *
 * DEMO:
 *   demo
 */
function getDemoEnvironment(): ?string
{
    $host = strtolower(
        trim(
            explode(
                ':',
                $_SERVER['HTTP_HOST'] ?? ''
            )[0]
        )
    );

    if ($host === 'demo.wahbibconsultancy.com') {
        return 'demo';
    }

    if ($host === 'dev.wahbibconsultancy.com') {
        return 'dev';
    }

    return null;
}


/**
 * Extract and normalize the domain from an email address.
 */
function getDemoEmailDomain(string $email): ?string
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $atPosition = strrpos($email, '@');

    if ($atPosition === false) {
        return null;
    }

    $domain = substr($email, $atPosition + 1);

    if ($domain === '') {
        return null;
    }

    return strtolower(trim($domain));
}


/**
 * Free / consumer email providers that are not accepted
 * for company Demo requests.
 */
function isFreeDemoEmailDomain(string $domain): bool
{
    $domain = strtolower(trim($domain));

    $freeDomains = [
        'gmail.com',
        'googlemail.com',

        'yahoo.com',
        'yahoo.co.uk',
        'yahoo.ca',
        'yahoo.com.au',
        'yahoo.co.in',

        'hotmail.com',
        'hotmail.co.uk',
        'hotmail.fr',
        'hotmail.de',

        'outlook.com',
        'outlook.co.uk',
        'outlook.fr',
        'outlook.de',

        'live.com',
        'live.co.uk',
        'live.ca',
        'live.com.au',

        'msn.com',

        'icloud.com',
        'me.com',
        'mac.com',

        'aol.com',
        'aim.com',

        'proton.me',
        'protonmail.com',

        'gmx.com',
        'gmx.net',

        'mail.com',

        'yandex.com',
        'yandex.ru',

        'zoho.com',
        'zohomail.com',

        'fastmail.com',

        'tutanota.com',
        'tuta.com',

        'mail.ru',
        'bk.ru',
        'inbox.ru',
        'list.ru'
    ];

    return in_array($domain, $freeDomains, true);
}
