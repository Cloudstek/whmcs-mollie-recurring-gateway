<?php
/**
 * Mollie Recurring Payment Gateway
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/mollierecurring/vendor/autoload.php';

use Cloudstek\WHMCS\MollieRecurring\AdminStatus as MollieRecurringAdminStatus;
use Cloudstek\WHMCS\MollieRecurring\Capture as MollieRecurringCapture;
use Cloudstek\WHMCS\MollieRecurring\Link as MollieRecurringLink;
use Cloudstek\WHMCS\MollieRecurring\Refund as MollieRecurringRefund;

use Symfony\Component\Translation\Loader\PhpFileLoader;

/**
 * Payment gateway metadata
 * @return array
 */
function mollierecurring_MetaData()
{
    return array(
        'DisplayName'                   => 'Mollie Recurring Payments',
        'APIVersion'                    => '1.1',
        'DisableLocalCredtCardInput'    => true
    );
}

/**
 * Payment gateway language
 * @return void
 */
function mollierecurring_lang()
{
    global $_LANG;

    // Language.
    $lang = \DI::make('lang');

    // Initialize translation loader.
    $lang->addLoader('mollierecurring', new PhpFileLoader());

    // Load messages.
    foreach (glob(__DIR__. '/lang/*.php') as $langFile) {
        echo("<pre>");
        var_dump($langFile);
        echo("</pre>");
        die();
        $lang->addResource('mollierecurring', $langFile, substr(basename($langFile), 0, -4));
    }

    // Update $_LANG global with new messages.
    $_LANG = $lang->toArray();
    
    echo("<pre>");
    print_r($lang);
    echo("</pre>");
    die();
}

/**
 * Payment gateway configuration
 * @return array
 */
function mollierecurring_config()
{
    // Initialize addon language.
    mollierecurring_lang();

    // Language.
    $lang = \DI::make('lang');

    // Visible options.
    return array(
        'FriendlyName'  => array(
            'Type'  => 'System',
            'Value' => 'Mollie Recurring Payments'
        ),
        'live_api_key'  => array(
            'FriendlyName' => $lang->trans('mollierecurring.config.liveapikey.name'),
            'Type' => 'text',
            'Size' => '25',
            'Description' => $lang->trans('mollierecurring.config.liveapikey.description')
        ),
        'test_api_key'  => array(
            'FriendlyName' => $lang->trans('mollierecurring.config.testapikey.name'),
            'Type' => 'text',
            'Size' => '25',
            'Description' => $lang->trans('mollierecurring.config.testapikey.description')
        ),
        'sandbox'       => array(
            'FriendlyName' => $lang->trans('mollierecurring.config.sandbox.name'),
            'Type' => 'yesno',
            'Size' => '25',
            'Description' => $lang->trans('mollierecurring.config.sandbox.description')
        )
    );
}

/**
 * Capture transaction
 *
 * @param array $params Payment Gateway Module Parameters.
 * @return array Transaction response status
 */
function mollierecurring_capture(array $params)
{
    return (new MollieRecurringCapture($params))->run();
}

/**
 * Refund transaction
 *
 * @param  array $params Payment Gateway Module Parameters.
 * @return array Transaction response status
 */
function mollierecurring_refund(array $params)
{
    return (new MollieRecurringRefund($params))->run();
}

/**
 * Invoice page payment form output
 *
 * @param array $params Client area payment form output.
 * @return string Payment form HTML
 */
function mollierecurring_link(array $params)
{
    return (new MollieRecurringLink($params))->run();
}

/**
 * Display message when invoice payment is pending
 *
 * @param array $params Admin status message parameters.
 * @return array
 */
function mollierecurring_adminstatusmsg(array $params)
{
    return (new MollieRecurringAdminStatus($params))->run();
}
