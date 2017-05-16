<?php
/**
 *
 * Please refer to the documentation @ http://docs.whmcs.com/Hooks for more information
 *
 * @package    WHMCS Add Invoice Activity to LeadDyno
 * @author     Eric Baker <eric@ericbaker.me>
 * @copyright  GPLv2 (or later)
 * @license    http://www.fsf.org/
 * @version    0.1.0
 * @link       http://www.gowp.com/
 */

if (!defined("WHMCS"))
    die("This file cannot be accessed directly");

// Let's set this here since everyone will want access to it.
$leaddyno_key = '';

add_hook('InvoicePaid', 1, function($vars) {
	// $vars['invoiceid'] is the only var passed into this hook.
	
	// 1. Get the invoice information from WHMCS
	function get_invoice_info($invoice_id) {
		//Define the paramaters
		$command = 'GetInvoice';
		$values = array(
			'invoiceid' => $invoice_id
		);
		$adminUsername = 'admin';

		// Call the Local API
		$invoice_info = localAPI($command, $postData, $adminUsername);

		// Make it an object
		return json_decode($invoice_info);
	}
	
	// 2. Get the Client email from WHMCS
	function get_client_email($client_id) {
		//Define the paramaters
		$command = 'GetClientDetails';
		$values = array(
			'clientid' => $client_id,
			'stats' => false,
		);
		$adminUsername = 'admin';

		// Call the local API
		$client_info = localAPI($command, $postData, $adminUsername);

		//Make it pretty
		$client_array = json_decode($client_info, true);
		return $client_array['client[email]'];
	}
	
    // 3. Create LeadDyno Purchase
	function create_leaddyno_purchase($vars){
	    $invoiceinfo = get_invoice_info($vars['invoiceid']);
	    $clientemail = get_client_email($invoiceinfo->userid);
	
		$url = 'https://api.leaddyno.com/v1/purchases';
		$req = array(
				'email' => $clientemail,
		        'purchase_amount' => $invoiceinfo->total
		    );
		
		make_curl_request( $url, $req );
	}

	// 4. Get the Client info from WHMCS
	function get_client_info($client_id) {
		//Define the paramaters
		$command = 'GetClientDetails';
		$values = array(
			'clientid' => $client_id,
			'stats' => false,
		);
		$adminUsername = 'admin';

		// Call the local API
		$client_info = localAPI($command, $postData, $adminUsername);

		//Make it pretty
		$client_array = json_decode($client_info, true);
		return $client_array;
	}

	// 5. Create a LeadDyno affiliate. If they already exists, it ignores our request
	function create_a_leaddyno_affiliate($vars){
		$clientinfo = get_client_info(get_invoice_info($vars['invoiceid'])->userid);

		$url = 'https://api.leaddyno.com/v1/affiliates';
		$req = array(
				'email' => $clientinfo['client[email]'],
		        'first_name' => $clientinfo['client[firstname]'],
		        'last_name' => $clientinfo['client[lastname]']
		    );
		make_curl_request($url, $req);
	}

	$create_purchase = create_leaddyno_purchase($vars);
	if ($create_purchase) { create_a_leaddyno_affiliate($vars); }

});

add_hook('InvoiceRefunded', 1, function($vars) {
    // Perform hook code here...
});

// This is the function that does the stuffs for everybody
function make_curl_request( $url, array $args, $leaddyno_key ) {
	$req = array_merge( array( 'key' => $leaddyno_key ), $args );
	$fields_string =http_build_query($req);
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_POST,1);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	$afResult = curl_exec($ch);
	curl_close($ch);
	$afJson = json_decode($afResult);
}