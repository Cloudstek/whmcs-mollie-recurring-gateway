<?php

$_ADDONLANG['mollierecurring'] = array(
    'config' => array(
        'liveapikey' => array(
            'name' => 'Mollie Live API Sleutel',
            'description' => 'Voer uw live API sleutel in.'
        ),
        'testapikey' => array(
            'name' => 'Mollie Test API Sleutel',
            'description' => 'Voer uw test API sleutel in.'
        ),
        'sandbox' => array(
            'name' => 'Sandbox Modus',
            'description' => 'Schakel sandbox modus in met test API sleutel. Er worden geen echte transacties gedaan.'
        )
    ),
    'link' => array(
        'paymentpending' => 'Uw betaling is momenteel in behandeling en zal automatisch worden verwerkt.',
        'error' => 'Er is een fout opgetreden, selecteer a.u.b. een andere betalingsgateway of probeer het later '
            . 'opnieuw.'
    ),
    'admin' => array(
        'missingapikey' => 'Vul a.u.b. uw API sleutel(s) in om deze betalingsgateway te gebruiken.',
        'notsetup' => 'Automatisch incasso is niet ingesteld door de klant.',
        'paymentpending' => 'Er is een betaling in behandeling voor deze factuur. Status zal automatisch '
            . 'bijgewerkt worden zodra een bevestiging is ontvangen van Mollie.',
        'paymentfailed' => 'Automatisch incasso voor deze factuur is niet gelukt. Bekijk het gateway logboek voor meer '
            . 'details.',
        'novalidmandate' => 'Automatisch incasso is niet ingesteld door de klant. Een geldig mandaat ontbreekt.',
    ),
    'capture' => array(
        'missingapikey' => 'Failed to create payment for invoice %invoice% - API sleutel ontbreekt!',
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
