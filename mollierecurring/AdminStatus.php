<?php

declare(strict_types=1);

namespace Cloudstek\WHMCS\MollieRecurring;

use Mollie\API\Mollie;

/**
 * Admin status message action.
 */
class AdminStatus extends ActionBase
{
    /**
     * Generate status message.
     *
     * @param string      $status  status message type
     * @param string      $message status message content
     * @param string|null $title   status message title
     *
     * @return array
     */
    private function statusMessage($status, $message, $title = null)
    {
        return array(
            'type' => $status,
            'msg' => $message,
            'title' => empty($title) ? $this->gatewayParams['name'] : $title
        );
    }

    /**
     * Run admin status message action.
     *
     * @return array|null
     */
    public function run()
    {
        $lang = \DI::make('lang');

        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage('error', $lang->trans('mollierecurring.admin.missingapikey'));
        }

        // Check for pending transaction.
        if ($this->invoiceStatus == 'Unpaid') {
            // Get customer ID.
            $customerId = $this->getCustomerId($this->actionParams['userid']);

            // Check for customer ID.
            if (!$customerId) {
                return $this->statusMessage('error', $lang->trans('mollierecurring.admin.notsetup'));
            }

            // Check for pending transactions.
            if ($this->hasPendingTransactions($this->invoiceId)) {
                return $this->statusMessage('info', $lang->trans('mollierecurring.admin.paymentpending'));
            }

            // Check for failed transactions.
            if ($this->hasFailedTransactions($this->invoiceId)) {
                return $this->statusMessage('error', $lang->trans('mollierecurring.admin.paymentfailed'));
            }

            // Check for valid mandates.
            try {
                // Get API key.
                $apiKey = $this->getApiKey();

                // Mollie API instance.
                $mollie = new Mollie($apiKey);

                // Get customer.
                $customer = $mollie->customer($customerId)->get();

                // Check mandates.
                if (!$customer->mandate()->hasValid()) {
                    return $this->statusMessage('error', $lang->trans('mollierecurring.admin.novalidmandate'));
                }
            } catch (\Exception $ex) {
                return $this->statusMessage('error', $lang->trans('mollierecurring.admin.notsetup'));
            }
        }
    }
}
