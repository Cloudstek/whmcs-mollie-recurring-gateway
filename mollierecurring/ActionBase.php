<?php

declare(strict_types=1);

namespace Cloudstek\WHMCS\MollieRecurring;

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for gateway actions capture, refund, link.
 */
abstract class ActionBase
{
    /**
     * Action parameters.
     *
     * @var array
     */
    protected $actionParams;

    /**
     * Gateway parameters.
     *
     * @var array
     */
    protected $gatewayParams;

    /**
     * WHMCS version.
     *
     * @var string
     */
    protected $whmcsVersion;

    /**
     * Sandbox mode.
     *
     * @var bool
     */
    protected $sandbox;

    /**
     * Invoice ID.
     *
     * @var int
     */
    protected $invoiceId;

    /**
     * Client details.
     *
     * @var array
     */
    protected $clientDetails;

    /**
     * Action constructor.
     *
     * @param array $params action parameters
     */
    protected function __construct(array $params)
    {
        $whmcs = \DI::make('app');

        // Parameters.
        $this->actionParams = $params;
        $this->gatewayParams = \getGatewayVariables('mollierecurring');
        $this->invoiceId = $params['invoiceid'] ?? null;
        $this->clientDetails = $params['clientdetails'] ?? null;

        // WHMCS version.
        $this->whmcsVersion = $whmcs->get_config('Version');

        // Sandbox mode.
        $this->sandbox = strtolower($this->gatewayParams['sandbox']) === 'on';
    }

    /**
     * Get current request.
     *
     * @return Request
     */
    protected function getRequest()
    {
        return Request::createFromGlobals();
    }

    /**
     * Get Mollie customer ID for WHMCS client.
     *
     * @param int $clientId WHMCS client ID
     *
     * @return string|null Mollie customer ID or null if none defined
     */
    protected function getCustomerId($clientId)
    {
        $customerId = Capsule::table('mod_mollie_customers')
            ->where('clientid', $clientId)
            ->pluck('customerid')
        ;

        try {
            $customerId = \decrypt($customerId);

            if (empty($customerId)) {
                return null;
            }

            return $customerId;
        } catch (\Exception $ex) {
            return null;
        }
    }

    /**
     * Set Mollie customer ID for WHMCS client.
     *
     * @param int    $clientId   WHMCS client ID
     * @param string $customerId mollie customer ID
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
     * Get full URL to callback for use by the Mollie webhookUrl parameter.
     *
     * @return string|null
     */
    protected function getWebhookUrl()
    {
        $whmcs = \DI::make('app');

        // Get WHMCS URL.
        $whmcsUrl = $whmcs->isSSLAvailable() ? $whmcs->getSystemSSLURL() : $whmcs->getSystemURL();

        // Don't set callback when developing.
        if (array_key_exists('develop', $this->gatewayParams) && $this->gatewayParams['develop'] == 'on') {
            return null;
        }

        // Build URL.
        return "{$whmcsUrl}/modules/gateways/callback/mollie.php";
    }

    /**
     * Get Mollie API key.
     *
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
     * Check for pending transactions.
     *
     * @param int $invoiceId invoice ID
     *
     * @return bool
     */
    protected function hasPendingTransactions($invoiceId)
    {
        return Capsule::table('mod_mollie_transactions')
                        ->where('invoiceid', $invoiceId)
                        ->where('status', 'pending')
                        ->count() > 0;
    }

    /**
     * Check for failed transactions.
     *
     * @param int $invoiceId invoice ID
     *
     * @return bool
     */
    protected function hasFailedTransactions($invoiceId)
    {
        return Capsule::table('mod_mollie_transactions')
                        ->where('invoiceid', $invoiceId)
                        ->where('status', 'failed')
                        ->count() > 0;
    }

    /**
     * Set transaction status.
     *
     * @param int    $invoiceId     invoice ID
     * @param string $status        status of transaction, failed or pending
     * @param string $transactionId transaction ID when pending
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
                    'transid' => $transactionId,
                    'status' => $status
                ));

            return;
        }

        Capsule::table('mod_mollie_transactions')
            ->insert(array(
                'invoiceid' => $invoiceId,
                'transid' => $transactionId,
                'status' => $status
            ));
    }

    /**
     * Log transaction.
     *
     * @param string $description transaction description
     * @param string $status      transaction status
     */
    protected function logTransaction($description, $status = 'Success')
    {
        if ($this->sandbox) {
            $description = '[SANDBOX] '.$description;
        }

        logTransaction($this->gatewayParams['name'], $description, ucfirst($status));
    }

    /**
     * Initialization.
     *
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
     * Run action.
     */
    abstract public function run();
}
