<?php
/*
 * EasyGCO Payments Gateway for Real Easy Store
 *
 * @copyright Copyright (c) EasyGCO.com
 * 
 * @author   EasyGCO ( easygco.com )
 * @version  1.0.0
 */
if(! defined('DB_HOST')) exit();

function gateway_easygco_payments_invoice_precheck($invoice,$user) {
	if(!is_numeric($invoice['due'])) return false;
	if(number_format($invoice['due'], 18, '.', '') <= 0) return false;
	return true;
}

function gateway_easygco_payments_payment_hook($payment,$user) {
	global $__DIR, $_OPTIONS;
	
	$paymentGateway = (!empty($payment['gateway']) && is_array($payment['gateway']))? $payment['gateway'] : null;
	$invoiceData = (!empty($payment['invoice']) && is_array($payment['invoice']))? $payment['invoice'] : null;

	if(!$paymentGateway || !$invoiceData) return false;

	$amountDecimals = intval($paymentGateway['options']['currency_decimals']);

	$convertRate = !empty($paymentGateway['options']['currency_rate'])?
	number_format($paymentGateway['options']['currency_rate'], $amountDecimals, '.', '') : 1;
	if(!is_numeric($convertRate) || $convertRate === 0) $convertRate = 1;

	$amountDue = number_format(number_format($invoiceData['due'], $amountDecimals, '.', '') * $convertRate, $amountDecimals, '.', '');

	$returnURL = $paymentGateway['path_url'].'/return.php?invid='.$invoiceData['invoice_id'];
	$returnURL .= "&invid_signature=".md5($paymentGateway['basename'].$_OPTIONS['system']['security_hash'].$invoiceData['invoice_id']);

	$ipnURL = $paymentGateway['path_url'].'/ipn.php?invid='.$invoiceData['invoice_id'];
	$ipnURL .= "&invid_signature=".md5($paymentGateway['basename'].$_OPTIONS['system']['security_hash'].$invoiceData['invoice_id']);

	$apiKey = $paymentGateway['options']['api_key'];
	$apiSecret = $paymentGateway['options']['api_secret'];

	require_once(__DIR__ . '/EasyGCO-Payments/vendor/autoload.php');

	$ePaymentsClient = new EasyGCO\EasyGCOPayments\API($apiKey,$apiSecret);

	$apiPath = 'token/generate';

	$inputData = [
		'transaction_id' 	=> $invoiceData['invoice_id'],
		'description' 		=> 'Payment for invoice #' . $invoiceData['invoice_id'],
		'code' 				=> $paymentGateway['options']['currency_code'],
		'type' 				=> strtolower($paymentGateway['options']['currency_type']),
		'amount' 			=> 	number_format($amountDue, $amountDecimals, '.', ''),
		"return_url"		=>	$returnURL,
		"success_url"		=>	$returnURL,
		"cancel_url"		=>	$invoiceData['cancel_url'],
		"notify_url"		=>	$ipnURL,
	];

	$apiResponse = $ePaymentsClient->doRequest($apiPath, $inputData);

	if(!$ePaymentsClient->isSuccess($apiResponse)) {
		do_add_notice($ePaymentsClient->getMessage($apiResponse),"danger");
		return false;
	}

	$responseData = $ePaymentsClient->getData($apiResponse);

	$ePaymentsClient->doRedirect($responseData['url']);
	
	return true;
}

function gateway_easygco_payments_payment_request($payment,$user,$postData) {
	return gateway_easygco_payments_payment_hook($payment,$user);
}
