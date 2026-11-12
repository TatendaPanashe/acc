<?php

namespace App\Services\Billing\Gateways;

use Codevirtus\Payments\Pesepay;

/** Builds the Pesepay SDK client. Bound in the container so tests can swap in a fake API. */
class PesepayClientFactory
{
    public function make(string $integrationKey, string $encryptionKey, bool $sandbox): Pesepay
    {
        return new Pesepay($integrationKey, $encryptionKey, $sandbox);
    }
}
