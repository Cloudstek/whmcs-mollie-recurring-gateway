<?php

declare(strict_types=1);

namespace Cloudstek\WHMCS\MollieRecurring;

require_once __DIR__.'/../../../init.php';
require_once __DIR__.'/../mollierecurring/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Mollie\API\Model\Payment;
use Mollie\API\Mollie;

/**
 * Webhook callback class.
 */
class Callback
{
    /**
     * Gateway parameters
     *
     * @var array
     */
    private $params;

    /**
     * Sandbox mode
     *
     * @var bool
     */
    private $sandbox;

    /**
     * WHMCS version
     *
     * @var string
     */
    private $whmcsVersion;

    /**
     * Callback constructor.
     */
    public function __construct()
    {
        $whmcs = \DI::make('app');

        // Load WHMCS functions.
        $whmcs->load_function('gateway');
        $whmcs->load_function('invoice');

        // Store WHMCS version.
        $this->whmcsVersion = $whmcs->get_config('Version');

        // Gateway parameters.
        $this->params = \getGatewayVariables('mollierecurring');

        // Sandbox.
        $this->sandbox = $this->params['sandbox'] == 'on';
    }

    /**
     * Get single value from database.
     *
     * @param QueryBuilder $query  query to execute
     * @param string       $column table column to take values from
     *
     * @return mixed
     */
    private function pluck(QueryBuilder $query, $column)
    {
        if (version_compare($this->whmcsVersion, '7.0.0', '<')) {
            return $query->pluck($column);
        }

        return $query->value($column);
    }

    /**
     * Log transaction.
     *
     * @param string $description transaction description
     * @param string $status      transaction status
     */
    private function logTransaction($description, $status = 'Success')
    {
        if ($this->sandbox) {
            $description = '[SANDBOX] '.$description;
        }

        \logTransaction($this->params['name'], $description, ucfirst($status));
    }

    /**
     * Convert amount (in euros) to invoice currency.
     *
     * @param int   $invoiceId invoice ID
     * @param float $amount    transaction amount
     *
     * @return float
     */
    private function convertCurrency($invoiceId, $amount)
    {
        // Get invoice currency.
        $invoiceCurrencyId = $this->pluck(
            Capsule::table('tblinvoices as i')
                ->join('tblclients as u', 'u.id', '=', 'i.userid')
                ->join('tblcurrencies as c', 'c.id', '=', 'u.currency')
                ->where('i.id', $invoiceId),
            'c.id'
        );

        // Get euro currency.
        $euroCurrencyId = $this->pluck(
            Capsule::table('tblcurrencies')->where('code', 'EUR'),
            'id'
        );

        // Return our amount converted to invoice currency.
        return \convertCurrency($amount, $euroCurrencyId, $invoiceCurrencyId);
    }

    /**
     * Get Mollie API key.
     *
     * @return string|null
     */
    private function getApiKey()
    {
        if (empty($this->params)) {
            return null;
        }

        return $this->sandbox ? $this->params['test_api_key'] : $this->params['live_api_key'];
    }

    /**
     * Handle paid transaction.
     *
     * @param int     $invoiceId   invoice ID
     * @param Payment $transaction transaction
     */
    private function handlePaid($invoiceId, Payment $transaction)
    {
        // Quit if transaction exists.
        \checkCbTransID($transaction->id);

        // Convert paid amount in euros to invoice currency.
        $amount = $this->convertCurrency($invoiceId, $transaction->amount);

        // Log transaction.
        $this->logTransaction(
            "Payment {$transaction->id} completed successfully - invoice {$invoiceId}.",
            'Success'
        );

        // Add payment.
        \addInvoicePayment(
            $invoiceId,
            $transaction->id,
            $amount,
            0.00,
            $this->params['paymentmethod'],
            true
        );

        // Check if custom email template has been defined.
        $customMessage = Capsule::table('tblemailtemplates')
                            ->where('name', 'Mollie Recurring Payment Confirmation')
                            ->where('type', 'invoice')
                            ->count();

        // Send message.
        \sendMessage($customMessage ? 'Mollie Recurring Payment Confirmation' : 'Credit Card Payment Confirmation', $invoiceId);
    }

    /**
     * Handle charged back transaction.
     *
     * @param int     $invoiceId   invoice ID
     * @param Payment $transaction transaction
     */
    private function handleChargedBack($invoiceId, Payment $transaction)
    {
        // Get invoice user ID.
        $userId = $this->pluck(
            Capsule::table('tblinvoices')
                ->where('id', $invoiceId),
            'userid'
        );

        // Set invoice unpaid.
        Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->update(array(
                'status' => 'Unpaid'
            ));

        // Convert refunded amount in euros to invoice currency.
        $amount = $this->convertCurrency($invoiceId, $transaction->amount);

        // Transaction description.
        $transDescription = "Payment {$transaction->id} charged back by customer - invoice {$invoiceId}.";

        // Log transaction.
        $this->logTransaction($transDescription, 'Charged Back');

        // Add transaction.
        \addTransaction(
            $userId,
            0,
            $transDescription,
            0,
            0,
            $amount,
            $this->params['paymentmethod'],
            $transaction->id,
            $invoiceId
        );

        // Check if custom email template has been defined.
        $customMessage = Capsule::table('tblemailtemplates')->where('name', 'Mollie Recurring Payment Failed')->count();

        // Send message.
        \sendMessage($customMessage ? 'Mollie Recurring Payment Failed' : 'Credit Card Payment Failed', $invoiceId);
    }

    /**
     * Check if gateway module is activated and API keys are configured.
     *
     * If no API keys have been entered, we cannot handle any payments and we can skip initialisation steps.
     *
     * @return bool
     */
    public function isActive()
    {
        $apiKey = $this->getApiKey();

        return !empty($apiKey);
    }

    /**
     * Process transaction.
     *
     * Main entry point of the callback. Mollie calls our callback with a POST request containing the id of our
     * transaction that has changed status. It's up to us to get the transaction by the id provided and handle it
     * according to its new status.
     *
     * @param int|null $transId mollie transaction ID to process
     *
     * @throws \Exception invoice ID is missing from transaction metadata
     */
    public function process($transId)
    {
        // Don't do anything if we're not active.
        if (!$this->isActive()) {
            return;
        }

        // API key.
        $apiKey = $this->getApiKey();

        // Mollie API instance.
        $mollie = new Mollie($apiKey);

        // Request.
        $request = Request::createFromGlobals();

        try {
            // Get transaction.
            $transaction = $mollie->payment($transId)->get();

            // Find invoice ID by transaction ID.
            $invoiceId = $transaction->metadata->whmcs_invoice;

            if (empty($invoiceId)) {
                throw new \Exception('Invoice ID is missing from transaction metadata');
            }

            // Validate invoice ID.
            \checkCbInvoiceID($invoiceId, $this->params['name']);

            // Allow manually calling callback to set payment status with test mode payments.
            if ($this->sandbox && $transaction->mode == 'test') {
                $status = $request->query->get('status');

                if (!empty($status)) {
                    $transaction->status = $status;
                }
            }

            // Handle transaction status.
            switch ($transaction->status) {
                case 'paid':
                    $this->handlePaid($invoiceId, $transaction);
                    break;
                case 'charged_back':
                    $this->handleChargedBack($invoiceId, $transaction);
                    break;
            }

            // Remove pending payment.
            Capsule::table('mod_mollie_transactions')
                ->where('invoiceid', $invoiceId)
                ->delete();
        } catch (\Exception $ex) {
            $exMessage = $ex->getMessage();

            // Log error.
            $this->logTransaction(
                "Payment {$transId} failed with an error - {$exMessage}.",
                'Error'
            );

            // Find invoice ID by transaction ID.
            $invoiceId = $this->pluck(
                Capsule::table('tblaccounts')
                    ->where('transid', $transId),
                'invoiceid'
            );

            // Validate invoice ID or exit when it doesn't exist.
            \checkCbInvoiceID($invoiceId, $this->params['name']);

            if (!empty($invoiceId)) {
                // Check if custom email template has been defined.
                $customMessage = Capsule::table('tblemailtemplates')->where('name', 'Mollie Recurring Payment Failed')->count();

                // Send message.
                \sendMessage($customMessage ? 'Mollie Recurring Payment Failed' : 'Credit Card Payment Failed', $invoiceId);
            }
        }
    }
}

// Request.
$request = Request::createFromGlobals();

// Transaction ID.
$id = $request->request->get('id');

// Check our transaction ID.
if (empty($id)) {
    die();
}

// Initialize callback.
$cb = new Callback();

// Check if payment gateway active.
if (!$cb->isActive()) {
    $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
    header("{$protocol} 503 Service Unavailable");
    die('Gateway not activated.');
}

// Process transaction.
$cb->process($request->request->get('id'));

// Make sure we send no output.
exit;
