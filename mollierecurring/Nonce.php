<?php
/**
 * Mollie Recurring Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\MollieRecurring;

/**
 * CSRF Protection Nonce
 */
class Nonce extends \Wukka\Nonce
{
    /** @var \Wukka\Nonce $nonce Nonce instance */
    protected $nonce = null;

    /**
     * Nonce
     *
     * @param string  $secret Nonce token secret.
     * @param integer $length Nonce token length.
     */
    public function __construct($secret = null, $length = 40)
    {
        // Generate a random string to use as secret if none is provided.
        if (empty($secret)) {
            $secret = $this->randomString(40);
        }

        parent::__construct($secret, $length);
    }

    /**
     * Generate random string
     *
     * @param integer $length Output string length.
     * @return string
     */
    protected function randomString($length)
    {
        $length = $length / 2;

        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }
        if (function_exists('mcrypt_create_iv')) {
            return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
        }
    }
}
