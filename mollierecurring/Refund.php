<?php
/**
 * Mollie Recurring Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\MollieRecurring;

use Mollie\API\Mollie;

/**
 * Refund action
 */
class Refund extends ActionBase
{
    /** @var string $transactionId Transaction ID */
    private $transactionId;

    /** @var double $refundAmount */
    private $refundAmount;

    /** @var string $refundCurrency Currency sign */
    private $refundCurrency;

    /**
     * Refund action constructor
     * @param array $params Refund action parameters.
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Transaction ID.
        $this->transactionId = $params['transid'];

        // Refund amount and currency.
        $this->refundAmount = $params['amount'];
        $this->refundCurrency = $params['currency'];
    }

    /**
     * Generate status message
     *
     * @param string     $status  Status message type.
     * @param string     $message Status message content.
     * @param array|null $data    Additional data to include.
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
     * Run refund action
     * @return array
     */
    public function run()
    {
        $lang = \DI::make('lang');

        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage('error', $lang->trans('mollierecurring.refund.missingapikey', array(
                '%transid%' => $this->transactionId
            )));
        }

        try {
            // Mollie API key.
            $apiKey = $this->getApiKey();

            // Mollie API.
            $mollie = new Mollie($apiKey);

            // Create refund.
            $refund = $mollie->payment($this->transactionId)->refund()->create($this->refundAmount);

            // Return status message.
            return $this->statusMessage('success', $lang->trans('mollierecurring.refund.success', array(
                '%currency%' => $this->refundCurrency,
                '%amount%' => $this->refundAmount,
                '%transid%' => $this->transactionId
            )));
        } catch (\Exception $ex) {
            return $this->statusMessage('error', $lang->trans('mollierecurring.refund.error', array(
                '%transid%' => $this->transactionId,
                '%exception%' => $ex->getMessage()
            )));
        }
    }
}
