<?php
/*
 * EasyGCO Payments Gateway for Real Easy Store
 *
 * @copyright Copyright (c) EasyGCO.com
 * 
 * @author   EasyGCO ( easygco.com )
 * @version  1.0.0
 */
require_once('../../load.php');
require_once("$__DIR/language/language.php");

if(empty($_REQUEST) || empty($_REQUEST['ps_response_data']) || !is_array($_REQUEST['ps_response_data'])) {
    http_response_code(403);
    exit('Error: Access is denied');
}

$apiResponseData = $_REQUEST['ps_response_data'];

if(!isset($apiResponseData['payment_uid'])) {
    http_response_code(403);
    exit('Error: Invalid PS-Data, no payment UID identified, Access is denied');
}

if(empty($apiResponseData['externalid'])) {
    http_response_code(403);
    exit('Success: IPN Received, payment transaction reference empty or not provided');
}

$paymentUID = $apiResponseData['payment_uid'];
$invoiceUID = intval($apiResponseData['externalid']);


$invoiceData = $db->where('invoice_uid',$invoiceUID)->getOne('invoices');
if(empty($invoiceData)) {
    http_response_code(200);
    exit('Success: IPN Received, No action taken, invoice is not exist');
}

if(!empty($invoiceData['is_active'])) {
    http_response_code(200);
    exit('Success: IPN Received, No action taken, invoice already paid');
}

$gatewayData = $db->where('is_active',1)->where('basename','easygco_payments')->getOne('payment_gateways');
$paymentGateway = sys_check_payment_gateway('easygco_payments');
if(empty($gatewayData) || empty($paymentGateway)) {
    http_response_code(200);
    exit('Failed: IPN Received, No action taken, gateway module is inactive or not installed');
}

$gatewayData['options'] = !empty($gatewayData['options'])? json_decode($gatewayData['options'],true) : [];
$paymentGateway['gateway_id'] = intval($gatewayData['gateway_id']);
$paymentGateway['options'] = array_merge($paymentGateway['options'],$gatewayData['options']);

$amountDecimals = intval($paymentGateway['options']['currency_decimals']);

$convertRate = !empty($paymentGateway['options']['currency_rate'])?
                number_format($paymentGateway['options']['currency_rate'], $amountDecimals, '.', '') : 1;
if(!is_numeric($convertRate) || $convertRate === 0) $convertRate = 1;
$amountDue = number_format(number_format($invoiceData['due'], $amountDecimals, '.', '') * $convertRate, $amountDecimals, '.', '');

$apiKey = $paymentGateway['options']['api_key'];
$apiSecret = $paymentGateway['options']['api_secret'];

require_once(__DIR__ . '/EasyGCO-Payments/vendor/autoload.php');

$ePaymentsClient = new EasyGCO\EasyGCOPayments\API($apiKey,$apiSecret);

$apiPath = 'payment/get';

$inputData = [
    'uid' => trim(urldecode($paymentUID)),
];

$apiResponse = $ePaymentsClient->doRequest($apiPath, $inputData);

if(!$ePaymentsClient->isSuccess($apiResponse)) {
    http_response_code(200);
    exit('Failed: IPN Received, No action taken, cannot verify payment UID');
}

$responseData = $ePaymentsClient->getData($apiResponse);

if(!isset($responseData['success']) || intval($responseData['success']) !== 1) {
    http_response_code(200);
    exit('Failed: IPN Received, No action taken, payment is unsuccessful');
}

$paidAmount = number_format($responseData['input_amounts']['paid'], $amountDecimals, '.', '');
$paidAmount = number_format($paidAmount / $convertRate, $amountDecimals, '.', '');
$transactionID = $paymentUID;

sys_create_transaction($invoiceData['user_id'], $invoiceData['invoice_id'], $paymentGateway['basename'], $transactionID, $paidAmount, true, $responseData);

http_response_code(200);
exit('SUCCESS: IPN Received, payment has been processed');

