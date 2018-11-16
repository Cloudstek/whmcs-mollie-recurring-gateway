<?php

/**
 * Mollie Recurring Payment Gateway
 *
 * @author Cloudstek <info@cloudstek.nl>
 * @copyright 2016 Cloudstek
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\MollieRecurring;

use Mollie\API\Mollie;
use Mollie\API\Exception\RequestException;

/**
 * Capture action
 */
class Capture extends ActionBase
{
    /** @var int $invoiceId Invoice ID */
    private $invoiceId;

    /** @var array $clientDetails Client details */
    private $clientDetails;

    /**
     * Capture action constructor
     * @param array $params Capture action parameters.
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Store invoice ID, you'll need it.
        $this->invoiceId = $params['invoiceid'];

        // Client details.
        $this->clientDetails = $params['clientdetails'];
    }

    /**
     * Generate status message
     * @param string $status  Status.
     * @param string $message Status message.
     * @param array  $data    Raw data to append to message.
     * @return array
     */
    private function statusMessage($status, $message, array $data = null)
    {
        // Build message.
        $msg = array(
            'status' => $status,
            'rawdata' => array(
                'message' => $message
            )
        );

        // Merge with additional data.
        if (!empty($data)) {
            $msg['rawdata'] = array_merge($msg['rawdata'], $data);
        }

        return $msg;
    }

    /**
     * Run capture action
     * @return array|string
     */
    public function run()
    {
        $lang = \DI::make('lang');

        // Initialize.
        if (!$this->initialize()) {
            // Mark transaction failed.
            $this->updateTransactionStatus($this->invoiceId, 'failed');

            // Return error message.
            return $this->statusMessage('error', $lang->trans('mollierecurring.capture.missingapikey', array(
                '%invoice%', $this->invoiceId
            )));
        }

        // Check for pending transactions.
        if ($this->hasPendingTransactions($this->invoiceId)) {
            return $this->statusMessage('pending', $lang->trans('mollierecurring.capture.paymentpending', array(
                '%invoice%' => $this->invoiceId
            )));
        }

        // Get customer ID.
        if (!$customerId = $this->getCustomerId($this->clientDetails['userid'])) {
            // Mark transaction failed.
            $this->updateTransactionStatus($this->invoiceId, 'failed');

            // Return error message.
            return $this->statusMessage('error', $lang->trans('mollierecurring.capture.missingcustomerid', array(
                '%invoice%' => $this->invoiceId
            )));
        }

        try {
            // Mollie API key.
            $apiKey = $this->getApiKey();

            // Mollie API instance.
            $mollie = new Mollie($apiKey);

            // Get Mollie customer.
            $customer = $mollie->customer($customerId)->get();

            // Check for valid mandates.
            if (!$customer->mandate()->hasValid()) {
                // Mark transaction failed.
                $this->updateTransactionStatus($this->invoiceId, 'failed');

                // Return error message.
                return $this->statusMessage('error', $lang->trans('mollierecurring.capture.novalidmandate', array(
                    '%invoice%' => $this->invoiceId
                )));
            }

            // Create transaction.
            $transaction = $customer->payment()->createRecurring(
                $this->actionParams['amount'],
                $this->actionParams['description'],
                array(
                    'whmcs_invoice' => $this->invoiceId
                ),
                array(
                    'webhookUrl' => $this->getWebhookUrl()
                )
            );

            // Store pending transaction.
            $this->updateTransactionStatus($this->invoiceId, 'pending', $transaction->id);

            // Log transaction.
            $this->logTransaction($lang->trans('mollierecurring.capture.paymentattempted', array(
                '%invoice%' => $this->invoiceId,
                '%transaction%' => $transaction->id
            )), 'Success');

            // Return "success" as string to avoid WHMCS marking invoice as paid and executing hooks.
            return "success";
        } catch (RequestException $ex) {
            // Mark transaction failed.
            $this->updateTransactionStatus($this->invoiceId, 'failed');

            // Get response.
            $resp = $ex->getResponse();

            // Handle customer not found error.
            if ($resp->code == 404) {
                // Delete customer ID from database.
                $this->setCustomerId($this->clientDetails['userid'], '');

                // Return error message.
                return $this->statusMessage('error', $lang->trans('mollierecurring.capture.customernotfound', array(
                    '%invoice%' => $this->invoiceId,
                    '%customer%' => $customerId
                )), array(
                    'exception' => $ex->getMessage()
                ));
            }

            // Return error message.
            return $this->statusMessage('error', $lang->trans('mollierecurring.capture.paymentfailed', array(
                '%invoice%' => $this->invoiceId
            )), array(
                'exception' => $ex->getMessage()
            ));
        } catch (\Exception $ex) {
            // Mark transaction failed.
            $this->updateTransactionStatus($this->invoiceId, 'failed');

            // Return error message.
            return $this->statusMessage('error', $lang->trans('mollierecurring.capture.paymentfailed', array(
                '%invoice%' => $this->invoiceId
            )), array(
                'exception' => $ex->getMessage()
            ));
        }
    }
}
