<?php

$_ADDONLANG['mollierecurring'] = array(
    'config' => array(
        'liveapikey' => array(
            'name' => 'Mollie Live API Key',
            'description' => 'Please enter your live API key.'
        ),
        'testapikey' => array(
            'name' => 'Mollie Test API Key',
            'description' => 'Please enter your test API key.'
        ),
        'sandbox' => array(
            'name' => 'Sandbox Mode',
            'description' => 'Enable sandbox mode with test API key. No real transactions will be made.'
        )
    ),
    'link' => array(
        'paymentpending' => 'Your payment is currently pending and will be processed automatically.',
        'error' => 'Error occurred, please select a different payment method or try again later.'
    ),
    'admin' => array(
        'missingapikey' => 'Please enter your API key(s) to use this payment gateway.',
        'notsetup' => 'Automatic payments have not been set up by the client.',
        'paymentpending' => 'There is a payment pending for this invoice. Status will be automatically updated once a'
            . ' confirmation is received from Mollie.',
        'paymentfailed' => 'Automatic payment for this invoice has failed. Please check the gateway logs for details.',
        'novalidmandate' => 'Automatic payments have not been set up by the client. A valid mandate is missing.',
    ),
    'capture' => array(
        'missingapikey' => 'Failed to create payment for invoice %invoice% - API key is missing!',
        'paymentpending' => 'Payment is already pending for invoice %invoice%',
        'paymentattempted' => 'Recurring payment capture attempted for invoice %invoice%. Awaiting payment confirmation'
            .' from callback for transaction %transaction%.',
        'paymentfailed' => 'Failed to create payment for invoice %invoice%.',
        'missingcustomerid' => 'Failed to create payment for invoice %invoice%. Customer ID is missing - customer'
            . ' should set up recurring payments again.',
        'novalidmandate' => 'Failed to create payment for invoice %invoice%. No valid mandates found - customer should'
            . ' set up recurring payments again.',
        'customernotfound' => 'Failed to create payment for invoice %invoice%. Customer %customer% could not be found'
            . ' - customer should set up recurring payments again.'
    ),
    'refund' => array(
        'missingapikey' => 'Failed to create refund for transaction %transid% - API key is missing!',
        'error' => 'Failed to create refund for transaction %transid% - %exception%',
        'success' => 'Successfully refunded %currency% %amount% of transaction %transid%.'
    )
);

return $_ADDONLANG;
