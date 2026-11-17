<?php

namespace App\Exceptions\Crypto;

use RuntimeException;

/**
 * The keyring is incomplete (missing or malformed key material), so no envelope
 * can be sealed or opened. Surfaced as 503 instead of a generic 500.
 */
class CryptoConfigurationException extends RuntimeException
{
}