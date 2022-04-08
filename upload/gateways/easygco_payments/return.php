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

$redirectURL = do_format_url('account','invoices');

if(empty($_GET['invid']) || !is_numeric($_GET['invid']) || intval($_GET['invid']) <= 0 || empty($_GET['ePaymentUid'])
|| empty($_GET['invid_signature']) || empty($_GET['ePaymentUid']) 
|| !is_string($_GET['invid_signature']) || strlen($_GET['invid_signature']) !== 32
|| $_GET['invid_signature'] !== md5('easygco_payments'.$_OPTIONS['system']['security_hash'].$_GET['invid']))
  exit(header('Location: ' . $redirectURL));

$paymentUID = trim(urldecode($_GET['ePaymentUid']));

$invoiceData = $db->where('invoice_id',$_GET['invid'])->getOne('invoices');
if(empty($invoiceData)) exit(header('Location: ' . $redirectURL));

$redirectURL =  do_format_url('account','invoices','invoice',['uid' => $invoiceData['invoice_uid']]);
if(!empty($invoiceData['is_active'])) exit(header('Location: ' . $redirectURL));

$gatewayData = $db->where('is_active',1)->where('basename','easygco_payments')->getOne('payment_gateways');
$paymentGateway = sys_check_payment_gateway('easygco_payments');
if(empty($gatewayData) || empty($paymentGateway)) exit(header('Location: ' . $redirectURL));

$gatewayData['options'] = !empty($gatewayData['options'])? json_decode($gatewayData['options'],true) : [];
$paymentGateway['gateway_id'] = round($gatewayData['gateway_id']);
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
    'uid' => $paymentUID,
];

$apiResponse = $ePaymentsClient->doRequest($apiPath, $inputData);

if(!$ePaymentsClient->isSuccess($apiResponse)) 
    exit(header('Location: ' . $redirectURL));

$responseData = $ePaymentsClient->getData($apiResponse);

if(!isset($responseData['success']) || intval($responseData['success']) !== 1)
    exit(header("Location: " . $redirectURL));

$paidAmount = number_format($responseData['input_amounts']['paid'], $amountDecimals, '.', '');
$paidAmount = number_format($paidAmount / $convertRate, $amountDecimals, '.', '');
$transactionID = $paymentUID;

sys_create_transaction($invoiceData['user_id'],$invoiceData['invoice_id'],$paymentGateway['basename'],$transactionID,$paidAmount,true,$responseData);

exit(header('Location: ' . $redirectURL));
