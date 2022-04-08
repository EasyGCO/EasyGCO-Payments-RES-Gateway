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

$gateway = array(
	'basename' => 'easygco_payments',
	'title' => 'EasyGCO Payments',
	'fields' => array(
		'friendly_name' => array('title' => 'Friendly Name','type' => 'text','required' => 1),
		'api_key' => array('title' => 'API Key','type' => 'text','required' => 1),
		'api_secret' => array('title' => 'API Secret','type' => 'text','required' => 1),
		'currency_type' => array('title' => 'Currency Type','type' => 'select', 'values' => ['Fiat','Crypto']),
		'currency_code' => array('title' => 'Currency Code','type' => 'text', 'pattern' => '[a-zA-Z]{3}','required' => 1),
		'currency_decimals' => array('title' => 'Currency Decimals','type' => 'number', 'min' => 0, 'max' => 18,'required' => 1),
		'currency_rate' => array('title' => 'Currency Rate','type' => 'number','step' => 'any','min' => '0','required' => 1),
	),
	'options' => array(
		'friendly_name' => 'EasyGCO Payments',
		'api_key' => '',
		'api_secret' => '',
		'currency_type' => 'Fiat',
		'currency_code' => 'USD',
		'currency_decimals' => 4,
		'currency_rate' => 1,
	),
	'icon' => 'easygco.png',
	'payment_template' => 'template.html',
);
