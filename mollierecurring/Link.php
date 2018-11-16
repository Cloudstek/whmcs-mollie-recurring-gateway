<?php
/**
 * Mollie Recurring Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\MollieRecurring;

use Mollie\API\Mollie;
use Mollie\API\Exception\RequestException;

/**
 * Link action
 */
class Link extends ActionBase
{
    /** @var int $invoiceId Invoice ID */
    private $invoiceId;

    /** @var array $clientDetails */
    private $clientDetails;

    /** @var Nonce $nonce */
    private $nonce;

    /** @var string $nonceToken Nonce token */
    private $nonceToken;

    /**
     * Link action constructor
     * @param array $params Link action parameters.
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Store invoice ID, you'll need it.
        $this->invoiceId = $params['invoiceid'];

        // Client details.
        $this->clientDetails = $params['clientdetails'];

        // Nonce generator.
        $this->nonce = new Nonce();

        // Nonce token.
        $this->nonceToken = $this->clientDetails['userid'] . session_id();
    }

    /**
     * Return an HTML formatted and translated message
     *
     * @param string $message Untranslated message.
     * @return string HTML formatted and translated message
     */
    private function statusMessage($message)
    {
        return
            ($this->sandbox ? '<strong style="color: red;">SANDBOX MODE</strong><br />' : null)
            . '<p>'.$message.'</p>';
    }

    /**
     * Pay now button form
     * @return string HTML form
     */
    private function payNowForm()
    {
        // Create nonce.
        $nonce = $this->nonce->create($this->nonceToken);

        // Session.
        $session = $this->getSession();

        // Store nonce.
        $session->set('paynow_nonce', $nonce);

        // Form.
        $form = <<<FORM
            <form action="" method="POST">
                <input type="hidden" name="action" value="paynow" />
                <input type="hidden" name="nonce" value="{$nonce}" />
                <input type="submit" value="{$this->actionParams['langpaynow']}" />
            </form>
FORM;

        // Add sandbox message.
        if ($this->sandbox) {
            $form = '<strong style="color: red;">SANDBOX MODE</strong><br />' . $form;
        }

        return $form;
    }

    /**
     * Get or create Mollie customer
     *
     * @param Mollie $mollie Mollie API instance.
     * @return Mollie\API\Model\Customer
     */
    private function getOrCreateCustomer(Mollie $mollie)
    {
        // Get customer ID or create customer.
        if (!$customerId = $this->getCustomerId($this->clientDetails['userid'])) {
            // Create customer ID.
            $customer = $mollie->customer()->create(
                $this->clientDetails['fullname'],
                $this->clientDetails['email'],
                array(
                    'whmcs_id' => $this->clientDetails['userid']
                )
            );

            // Store customer ID.
            $this->setCustomerId($this->clientDetails['userid'], $customer->id);

            return $customer;
        }

        // Get customer.
        return $mollie->customer($customerId)->get();
    }

    /**
     * Create first payment and redirect to payment page
     *
     * @param Mollie\API\Model\Customer $customer Mollie customer.
     * @return void
     */
    private function createFirstPayment(Mollie\API\Model\Customer $customer)
    {
        // Language.
        $lang = \DI::make('lang');

        // Create first payment.
        $transaction = $customer->payment()->createFirstRecurring(
            $this->actionParams['amount'],
            $this->actionParams['description'],
            $this->actionParams['returnurl'],
            array(
                'whmcs_invoice' => $this->invoiceId
            ),
            array(
                'webhookUrl' => $this->getWebhookUrl()
            )
        );

        // Store pending payment.
        $this->updateTransactionStatus($this->invoiceId, 'pending', $transaction->id);

        // Log transaction.
        $this->logTransaction(
            $lang->trans('mollierecurring.capture.paymentattempted', array(
                '%invoice%' => $this->invoiceId,
                '%transaction%' => $transaction->id
            )),
            'Success'
        );

        // Redirect to payment page.
        $transaction->gotoPaymentPage();
    }

    /**
     * Run link action
     * @return string
     */
    public function run()
    {
        // Language.
        $lang = \DI::make('lang');

        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage($lang->trans('mollierecurring.link.error'));
        }

        try {
            // Mollie API.
            $mollie = new Mollie($this->getApiKey());

            // Get customer.
            $customer = $this->getOrCreateCustomer($mollie);

            // Request.
            $request = $this->getRequest();

            // Current session.
            $session = $this->getSession();

            // Check for valid mandates or pending transactions.
            if ($customer->mandate()->hasValid() || $this->hasPendingTransactions($this->invoiceId)) {
                return $this->statusMessage($lang->trans('mollierecurring.link.paymentpending'));
            }

            // Handle form submission.
            if ($request->request->get('action') == "paynow") {
                // Get nonce and remove it from session.
                $nonce = $session->getAndDelete('paynow_nonce');

                // Check nonce.
                if (!empty($nonce) && $this->nonce->check($nonce, $this->nonceToken)) {
                    $this->createFirstPayment($customer);
                }
            }

            // Show payment form.
            return $this->payNowForm();
        } catch (\Exception $ex) {
            if ($ex instanceof RequestException && $ex->getResponse()->code == 404) {
                // Remove customer ID from database.
                $this->setCustomerId($this->clientDetails['userid'], '');

                // Refresh the page.
                header('Refresh: 0');
            }

            return $this->statusMessage($lang->trans('mollierecurring.link.error'));
        }
    }
}
