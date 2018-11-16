<?php
/**
 * Mollie Recurring Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\MollieRecurring;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use WHMCS\Session;

/**
 * Base class for gateway actions capture, refund, link
 */
abstract class ActionBase
{
    /** @var array $actionParams Action parameters */
    protected $actionParams;

    /** @var array $gatewayParams Gateway parameters */
    protected $gatewayParams;

    /** @var string $whmcsVersion WHMCS version */
    protected $whmcsVersion;

    /** @var bool $sandbox Sandbox mode */
    protected $sandbox;

    /**
     * Action constructor
     * @param array $params Action parameters.
     */
    protected function __construct(array $params)
    {
        $whmcs = \DI::make('app');

        // Parameters.
        $this->actionParams = $params;
        $this->gatewayParams = getGatewayVariables('mollierecurring');

        // WHMCS version.
        $this->whmcsVersion = $whmcs->get_config('Version');

        // Sandbox mode.
        $this->sandbox = $this->gatewayParams['sandbox'] == 'on';
    }

    /**
     * Get single value from database
     *
     * WHMCS v6 uses an older version of Eloquent. In v7 it has been replaced by a newer version which deprecates pluck
     * and causes different behaviour. Instead value is used, which does the same as the old pluck method.
     *
     * @param QueryBuilder $query  Query to execute.
     * @param string       $column Column to get the value from.
     * @return mixed
     */
    protected function pluck(QueryBuilder $query, $column)
    {
        // WHMCS 6.0.
        if (version_compare($this->whmcsVersion, '7.0.0', '<')) {
            return $query->pluck($column);
        }

        return $query->value($column);
    }

    /**
     * Get current session
     * @return WHMCS\Session
     */
    protected function getSession()
    {
        return new Session();
    }

    /**
     * Get current request
     * @return WHMCS\Http\Request
     */
    protected function getRequest()
    {
        return Request::createFromGlobals();
    }

    /**
     * Get Mollie customer ID for WHMCS client
     *
     * @param integer $clientId WHMCS client ID.
     * @return string|bool Mollie customer ID or false if none defined
     */
    protected function getCustomerId($clientId)
    {
        $customerId = $this->pluck(
            Capsule::table('mod_mollie_customers')
                ->where('clientid', $clientId),
            'customerid'
        );

        try {
            $customerId = decrypt($customerId);

            if (empty($customerId)) {
                return false;
            }

            return $customerId;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Set Mollie customer ID for WHMCS client
     *
     * @param integer $clientId   WHMCS client ID.
     * @param string  $customerId Mollie customer ID.
     * @return void
     */
    protected function setCustomerId($clientId, $customerId)
    {
        $exists = Capsule::table('mod_mollie_customers')
                    ->where('clientid', $clientId)
                    ->count();

        // Update customer ID.
        if ($exists) {
            Capsule::table('mod_mollie_customers')
                ->where('clientid', $clientId)
                ->update(array(
                    'customerid' => encrypt($customerId)
                ));

            return;
        }

        // Insert customer ID.
        Capsule::table('mod_mollie_customers')
            ->insert(array(
                'clientid' => $clientId,
                'customerid' => encrypt($customerId)
            ));
    }

    /**
     * Get full URL to callback for use by the Mollie webhookUrl parameter
     * @return string|null
     */
    protected function getWebhookUrl()
    {
        $whmcs = \DI::make('app');

        // Get WHMCS URL.
        $whmcsUrl = $whmcs->isSSLAvailable() ? $whmcs->getSystemSSLURL() : $whmcs->getSystemURL();

        // Don't set callback when developing.
        if (array_key_exists('develop', $this->gatewayParams) && $this->gatewayParams['develop'] == "on") {
            return null;
        }

        // Build URL.
        return "{$whmcsUrl}/modules/gateways/callback/mollie.php";
    }

    /**
     * Get Mollie API key
     * @return string|null
     */
    protected function getApiKey()
    {
        $apiKey = $this->sandbox ? $this->gatewayParams['test_api_key'] : $this->gatewayParams['live_api_key'];

        if (empty($apiKey)) {
            return false;
        }

        return $apiKey;
    }

    /**
     * Check for pending transactions
     *
     * @param integer $invoiceId Invoice ID.
     * @return boolean
     */
    protected function hasPendingTransactions($invoiceId)
    {
        return Capsule::table('mod_mollie_transactions')
                        ->where('invoiceid', $invoiceId)
                        ->where('status', 'pending')
                        ->count() > 0;
    }

    /**
     * Check for failed transactions
     *
     * @param integer $invoiceId Invoice ID.
     * @return boolean
     */
    protected function hasFailedTransactions($invoiceId)
    {
        return Capsule::table('mod_mollie_transactions')
                        ->where('invoiceid', $invoiceId)
                        ->where('status', 'failed')
                        ->count() > 0;
    }

    /**
     * Set transaction status
     *
     * @param integer $invoiceId     Invoice ID.
     * @param string  $status        Status of transaction, failed or pending.
     * @param string  $transactionId Transaction ID when pending.
     * @return void
     */
    protected function updateTransactionStatus($invoiceId, $status, $transactionId = null)
    {
        // Check for existing transaction.
        $exists = Capsule::table('mod_mollie_transactions')
            ->where('invoiceid', $invoiceId)
            ->count();

        // Update transaction status.
        if ($exists) {
            Capsule::table('mod_mollie_transactions')
                ->where('invoiceid', $invoiceId)
                ->update(array(
                    'transid'   => $transactionId,
                    'status'    => $status
                ));

            return;
        }

        Capsule::table('mod_mollie_transactions')
            ->insert(array(
                'invoiceid' => $invoiceId,
                'transid'   => $transactionId,
                'status'    => $status
            ));
    }

    /**
     * Log transaction
     *
     * @param string $description Transaction description.
     * @param string $status      Transaction status.
     * @return void
     */
    protected function logTransaction($description, $status = 'Success')
    {
        if ($this->sandbox) {
            $description = "[SANDBOX] " . $description;
        }

        logTransaction($this->gatewayParams['name'], $description, ucfirst($status));
    }

    /**
     * Initialization
     * @return bool True if initialization complete and license is active
     */
    protected function initialize()
    {
        // Create database.
        if (!Capsule::schema()->hasTable('mod_mollie_transactions')) {
            Capsule::schema()->create('mod_mollie_transactions', function ($table) {
                $table->increments('id');
                $table->integer('invoiceid')->unsigned()->unique();
                $table->string('transid')->unique()->nullable();
                $table->string('status');
            });
        }

        if (!Capsule::schema()->hasTable('mod_mollie_customers')) {
            Capsule::schema()->create('mod_mollie_customers', function ($table) {
                $table->increments('id');
                $table->integer('clientid')->unsigned()->unique();
                $table->string('customerid')->unique();
            });
        }

        // Check API key.
        if (!empty($this->gatewayParams)) {
            $apiKey = $this->sandbox ? $this->gatewayParams['test_api_key'] : $this->gatewayParams['live_api_key'];

            // Return true if API key is entered for current mode.
            return $apiKey !== false && !empty($apiKey);
        }

        return false;
    }

    /**
     * Run action
     * @return void
     */
    abstract public function run();
}
