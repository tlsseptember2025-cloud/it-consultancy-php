<?php

/**
 * Extract and normalize a company domain from an email address.
 */
function getCompanyDomain(string $email): ?string
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $parts = explode('@', $email);

    if (count($parts) !== 2) {
        return null;
    }

    $domain = trim($parts[1]);

    if ($domain === '') {
        return null;
    }

    return $domain;
}


/**
 * Check whether an email domain is a personal/free email provider.
 */
function isPersonalEmailDomain(string $domain): bool
{
    $freeDomains = [
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'yahoo.co.uk',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'proton.me',
        'protonmail.com',
        'mail.com',
        'gmx.com',
        'yandex.com',
    ];

    return in_array(
        strtolower($domain),
        $freeDomains,
        true
    );
}

function companyDomainExists(string $domain): bool
{
    $domain = strtolower(trim($domain));

    if ($domain === '') {
        return false;
    }

    /*
     * Prefer MX records because they indicate that the domain
     * has mail infrastructure.
     */
    if (function_exists('checkdnsrr')) {
        if (checkdnsrr($domain, 'MX')) {
            return true;
        }

        /*
         * Some valid domains do not publish MX records but still
         * exist and may have A/AAAA records.
         */
        if (checkdnsrr($domain, 'A')) {
            return true;
        }

        if (checkdnsrr($domain, 'AAAA')) {
            return true;
        }

        return false;
    }

    /*
     * Fallback for environments where checkdnsrr() is unavailable.
     */
    if (function_exists('dns_get_record')) {
        $mxRecords = dns_get_record($domain, DNS_MX);

        if (!empty($mxRecords)) {
            return true;
        }

        $aRecords = dns_get_record($domain, DNS_A);

        if (!empty($aRecords)) {
            return true;
        }

        $aaaaRecords = dns_get_record($domain, DNS_AAAA);

        if (!empty($aaaaRecords)) {
            return true;
        }
    }

    return false;
}