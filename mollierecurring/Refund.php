<?php

declare(strict_types=1);

namespace Cloudstek\WHMCS\MollieRecurring;

use Mollie\API\Mollie;

/**
 * Refund action.
 */
class Refund extends ActionBase
{
    /**
     * Generate status message.
     *
     * @param string     $status  status message type
     * @param string     $message status message content
     * @param array|null $data    additional data to include
     *
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
     * Run refund action.
     *
     * @return array
     */
    public function run()
    {
        $lang = \DI::make('lang');

        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage('error', $lang->trans('mollierecurring.refund.missingapikey', array(
                '%transid%' => $this->actionParams['transid']
            )));
        }

        try {
            // Mollie API key.
            $apiKey = $this->getApiKey();

            // Mollie API.
            $mollie = new Mollie($apiKey);

            // Create refund.
            $refund = $mollie->payment($this->actionParams['transid'])->refund()->create($this->actionParams['amount']);

            // Return status message.
            return $this->statusMessage('success', $lang->trans('mollierecurring.refund.success', array(
                '%currency%' => $this->actionParams['currency'],
                '%amount%' => $this->actionParams['amount'],
                '%transid%' => $this->actionParams['transid']
            )));
        } catch (\Exception $ex) {
            return $this->statusMessage('error', $lang->trans('mollierecurring.refund.error', array(
                '%transid%' => $this->actionParams['transid'],
                '%exception%' => $ex->getMessage()
            )));
        }
    }
}
