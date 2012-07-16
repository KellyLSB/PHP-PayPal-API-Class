<?php
/**
 * PayPal Class
 *
 * Class for handling Express Checkout and Direct Payment
 * Using PayPal's NVP API
 *
 * @author		Kelly Lauren Summer Becker
 * @link		http://gaiasenigma.com
 * @license		MIT Copyright (c) 2011 Kelly Lauren Summer Becker
 * @version		1.0
 *
 * Country and State Codes are listing in arrays at the bottom of the Class these are in the proper format for PayPal already.
 *
 * === Express Checkout USAGE ===
 *
 * Call PayPal and goto their site for checkout.
 *
 * $paypal->express_checkout(
 *    array(
 *       'RETURN' => 'http://my.return/handler.php',
 *       'CANCEL' => 'http://my.cancel/handler.php',
 *       'INVNUM' => 'Invoice Number',
 *       'ITEMS' => array(
 *                     '1' => array(
 *                               'NAME' => 'Item Name',
 *                               'QTY' => 'Item Quantity',
 *                               'AMT' => 'Item Price',
 *                            )
 *                  )
 *    ), 'checkout'
 * );
 *
 * Return to our site and process/finalize the order
 *
 * $paypal->express_checkout(
 *    array(
 *       'IPN_URL' => 'http://my.ipn/handler.php'
 *    ), 'process'
 * );
 *
 * === Direct Payments Usage (untested due to paypal not having website payments pro test site easily available) ===
 *
 * $paypal->direct_payment(
 *    array(
 *       'IPN_URL' => 'http://my.ipn/handler.php',
 *       'INVNUM' => 'Invoice Number',
 *       'CREDITCARD_TYPE' => 'Master, Visa, Discover, Amex',
 *       'CREDITCARD_NUM' => 'Credit Card Number',
 *       'CVV' => 'Credit Card CVV Code (3-4 digits)',
 *       'EXPIRY' => 'Credit Card Expiry Date MMYYYY',
 *       'FNAME' => 'First Name',
 *       'LNAME' => 'Last Name',
 *       'STREET' => 'Street Address',
 *       'CITY' => 'City',
 *       'STATE' => 'State Code',
 *       'COUNTRY' => 'Country'
 *       'ITEMS' => array(
 *                     '1' => array(
 *                               'NAME' => 'Item Name',
 *                               'QTY' => 'Item Quantity',
 *                               'AMT' => 'Item Price',
 *                            )
 *                  )
 *    )
 * )
 *
 * === IPN Processor Usage ===
 *
 * $this->process_ipn();
 *
 */
class Paypal {
	
	var $user;
	var $pass;
	var $sig;
	var $brandname;
	var $currency;
	
	// Holder for transactions
	var $nvps 			= array();
	
	// API Urls
	var $nvp			= 'https://api-3t.paypal.com/nvp';
	var $webscr			= 'https://www.paypal.com/cgi-bin/webscr';
	
	// IPN Log file
	var $ipn_log;
		
	// Cookie Domain - REQUIRED FOR EXPRESS CHECKOUT!
	var $cookie_domain 	= 'yenn3.gaiasenigma.com';
	
	public function __construct(){
		
		$this->user 		= 'My API Username';
		$this->pass			= 'My API Password';
		$this->sig 			= 'My API Signature';
		$this->brandname 	= 'My Store';
		$sandbox 			= FALSE;
		$this->currency		= 'USD';
		$this->ipn_log 		= './ipn.log';
				
		if($sandbox) {
			$this->nvp	 	= 'https://api-3t.sandbox.paypal.com/nvp';
			$this->webscr	= 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}
		
		$this->add_nvp('USER', $this->user);
		$this->add_nvp('PWD', $this->pass);
		$this->add_nvp('SIGNATURE', $this->sig);
		$this->add_nvp('BRANDNAME', $this->brandname);
		$this->add_nvp('VERSION', '63.0');
		$this->add_nvp('CURRENCYCODE', $this->currency);
		
	} // End __construct()
	
	public function add_nvp($field = false, $value = false) {
		
		if(!$field||!$value) return false;
		
		$this->nvps[$field] = $value;
		
	} // End add_nvp()
	
	public function direct_payment($array = False) {
		
		if(!is_array($array)) return false;

		// Paypal Method
		$this->add_nvp('METHOD', 'DoDirectPayment');
		// Make a sale
		$this->add_nvp('PAYMENTACTION', 'Sale');
		// Customers IP Address
		$this->add_nvp('IPADDRESS', $_SERVER['REMOTE_ADDR']);
		// Mastercard, Visa, Discover, Amex
		$this->add_nvp('CREDITCARDTYPE', $array['CREDITCARD_TYPE']);
		// Credit Card Number
		$this->add_nvp('ACCT', $array['CREDITCARD_NUM']);
		// Credit Card CVV2 Number - Visa, Mastercard, Discover = 3, Amex = 4
		$this->add_nvp('CVV2', $array['CVV']);
		// Card Expiry - MMYYYY
		$this->add_nvp('EXPDATE', $array['EXPIRY']);
		// Name on Card
		$this->add_nvp('FIRSTNAME', $array['FNAME']);
		$this->add_nvp('LASTNAME', $array['LNAME']);
		// IPN URL
		$this->add_nvp('NOTIFYURL', $array['IPN_URL']);
		// Invoice Number
		$this->add_nvp('INVNUM', $array['INVNUM']);
		// Address
		$this->add_nvp('STREET', $array['STREET']);
		$this->add_nvp('CITY', $array['CITY']);
		$this->add_nvp('STATE', $array['STATE']);
		$this->add_nvp('ZIP', $array['ZIP']);
		$this->add_nvp('COUNTRYCODE', $array['COUNTRY']);
		
		// --- ITEMS ------- //

		$total_amt = 0;
		foreach($array['ITEMS'] as $key=>$item){
			$this->add_nvp('L_NAME'.$key, $item['NAME']);
			$this->add_nvp('L_QTY'.$key, $item['QTY']);
			$this->add_nvp('L_AMT'.$key, number_format($item['AMT'],'2'));
			$total_amt += ($item['AMT']*$item['QTY']);
		}

		// --- END ITEMS --- //

		// Sum amount of all items
		$this->add_nvp('ITEMAMT', $total_amt);
		// Amount customer is to be charged
		$this->add_nvp('AMT', $total_amt);
		
		// All looks good lets Call PayPal
		return $this->send_api_call();
			
	} // End direct_payment()
	
	public function express_checkout($array = False, $method = False){
		
		if(!is_array($array)||!$method) return false;
		
		if($method == 'checkout'){
			
			// PayPal Method
			$this->add_nvp('METHOD', 'SetExpressCheckout');
			// Return URL
			$this->add_nvp('RETURNURL', $array['RETURN']);
			// Cancel URL
			$this->add_nvp('CANCELURL', $array['CANCEL']);
			// Require PayPal user to have valid address
			$this->add_nvp('REQCONFIRMSHIPPING', FALSE);
			// Display shipping address fields
			$this->add_nvp('NOSHIPPING', TRUE);
			// Invoice Number
			$this->add_nvp('PAYMENTREQUEST_0_INVNUM', $array['INVNUM']);
			// Payment Description
			$this->add_nvp('PAYMENTREQUEST_0_DESC', $this->brandname);

			// --- ITEMS ------- //

			$total_amt = 0;
			foreach($array['ITEMS'] as $key=>$item){
				$this->add_nvp('L_PAYMENTREQUEST_0_NAME'.$key, $item['NAME']);
				$this->add_nvp('L_PAYMENTREQUEST_0_QTY'.$key, $item['QTY']);
				$this->add_nvp('L_PAYMENTREQUEST_0_AMT'.$key, number_format($item['AMT'],'2'));
				$total_amt += ($item['AMT']*$item['QTY']);
			}

			// --- END ITEMS --- //

			// Sum amount of all items
			$this->add_nvp('PAYMENTREQUEST_0_ITEMAMT', number_format($total_amt,'2'));
			// Amount customer is to be charged
			$this->add_nvp('PAYMENTREQUEST_0_AMT', number_format($total_amt,'2'));
						
			// All looks good lets Call PayPal
			$response = $this->send_api_call();
			
			// Make sure it went through
			if(!$response) return 'Unexpected error communicating with PayPal.';
			
			// Save the PayPal token to a cookie
			setcookie('PP_TOKEN', $response['TOKEN'], time() + 7200, '/', $this->cookie_domain);

			// Send user to PayPal for checkout process
			header("Location: ".$this->webscr."?cmd=_express-checkout&token=".$response['TOKEN']);
			
		} // End $method == checkout
		
		if($method == 'process'){
			
			if(empty($_COOKIE['PP_TOKEN'])) return 'Session invalid or expired';
			
			// Set Token To A Variable
			$PP_TOKEN = $_COOKIE['PP_TOKEN'];
			
			// Remove the Cookie
			setcookie ('PP_TOKEN', '', time() - 3600);
			
			// --- Get Details --- //
			
			// PayPal Method
			$this->add_nvp('METHOD', 'GetExpressCheckoutDetails');
			// TOKEN from before
			$this->add_nvp('TOKEN', $PP_TOKEN);
			
			// All looks good lets call PayPal
			$response = $this->send_api_call();
			
			// Make sure it went through
			if(!$response) return 'Unexpected error communicating with PayPal.';
			
			//Set Tmp Variable
			$invnum = $response['INVNUM'];
			
			// --- Do Payment --- //
			
			// PayPal Method
			$this->add_nvp('METHOD', 'DoExpressCheckoutPayment');
			// TOKEN from before
			$this->add_nvp('TOKEN', $response['TOKEN']);
			// PAYERID
			$this->add_nvp('PAYERID', $response['PAYERID']);
			// AMT
			$this->add_nvp('AMT', $response['AMT']);
			// Make a sale
			$this->add_nvp('PAYMENTACTION', 'Sale');
			// IPN Notify URL
			$this->add_nvp('NOTIFYURL', $array['IPN_URL']);
			
			// All looks good lets call PayPal
			$response = $this->send_api_call();
			
			// Make sure it went through
			if(!$response) return 'Unexpected error communicating with PayPal.';
			
			$response['INVNUM'] = $invnum;
			
			return $response;			
			
		} // End $method == process
		
	} // End express_checkout()
	
	public function send_api_call() {
						
		// Build Query String
		$qstring = '?'.http_build_query($this->nvps);
		
		// Call PayPal with string
		$result = file_get_contents($this->nvp.$qstring);
		
		// Decode URL
		$result = urldecode($result);
		
		// Parse Response Into Readable Array
		parse_str($result, $response);
				
		//If error return false
		if($response['ACK'] != 'Success') return $response['L_SHORTMESSAGE0'].': '.$response['L_LONGMESSAGE0'];
		
		// Return the response
		return $response;
		
	} // End send_api_call()
	
	public function process_ipn(){
				
		// Make sure there is no session
		define('NO_SESSION', 1);

		// Grab post data that paypal sent
		$post = file_get_contents('php://input');

		// Initiate curl session
		$c = curl_init($this->webscr);

		// Set curl session options
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $post . '&cmd=_notify-validate');

		// Call paypal to confirm IPN
		$result = curl_exec($c);

		// Open IPN Log / Write IPN request to log
		$f = fopen($this->ipn_log, 'a');
		flock($f, LOCK_EX);
		fwrite($f, time() . "\n$post\n$result\n\n");
		fclose($f);

		//If the result is not verified then return false
		if ($result != 'VERIFIED') return false;

		//Turn previous Paypal request into a readable array
		parse_str($post, $result);

		//If the result completes
		if($result['payment_status'] !== 'Completed') return false;
		
		return $result;
		
	} // End process_ipn()
	
	public function state_codes(){
		return array( 
			"AB" => "Alberta",
			"BC" => "British Columbia",
 			"MB" => "Manitoba",
			"NB" => "New Burnswick",
            		"NL" => "Newfoundland and Labrador",
            		"NS" => "Nova Scotia",
            		"NT" => "Northwest Territories",
			"NU" => "Nunavut",
			"ON" => "Ontario",
			"PE" => "Prince Edward Island",
			"QC" => "Quebec",
			"SK" => "Saskatchewan",
			"YT" => "Yukon",
			"AL" => "Alabama",
			"AK" => "Alaska",
			"AZ" => "Arizona",
			"AR" => "Arkansas",
			"CA" => "California",
			"CO" => "Colorado",
			"CT" => "Connecticut",
			"DE" => "Delaware",
			"DC" => "District of Columbia",
			"FL" => "Florida",
			"GA" => "Georgia",
			"HI" => "Hawaii",
			"ID" => "Idaho",
			"IL" => "Illinois",
			"IN" => "Indiana",
			"IA" => "Iowa",
			"KS" => "Kansas",
			"KY" => "Kentucky",
			"LA" => "Louisiana",
			"ME" => "Maine",
			"MD" => "Maryland",
			"MA" => "Massachusetts",
			"MI" => "Michigan",
			"MN" => "Minnesota",
			"MS" => "Mississippi",
			"MO" => "Missouri",
			"MT" => "Montana",
			"NE" => "Nebraska",
			"NV" => "Nevada",
			"NH" => "New Hampshire",
			"NJ" => "New Jersey",
			"NM" => "New Mexico",
			"NY" => "New York",
			"NC" => "North Carolina",
			"ND" => "North Dakota",
			"OH" => "Ohio",
			"OK" => "Oklahoma",
			"OR" => "Oregon",
			"PA" => "Pennsylvania",
			"RI" => "Rhode Island",
			"SC" => "South Carolina",
			"SD" => "South Dakota",
			"TN" => "Tennessee",
			"TX" => "Texas",
			"UT" => "Utah",
			"VT" => "Vermont",
			"VA" => "Virginia",
			"WA" => "Washington",
			"WV" => "West Virginia",
			"WI" => "Wisconsin",
			"WY" => "Wyoming"
		);
	} // End state_codes()
	
	public function country_codes(){
		return array_flip(array( 
			"Anguilla" => "AI", 
			"Argentina" => "AR", 
			"Australia" => "AU", 
			"Austria" => "AT", 
			"Belgium" => "BE", 
			"Brazil" => "BR", 
			"Canada" => "CA", 
			"Chile" => "CL", 
			"China" => "CN", 
			"Costa Rica" => "CR", 
			"Denmark" => "DK", 
			"Dominican Republic" => "DO", 
			"Ecuador" => "EC", 
			"Finland" => "FI", 
			"France" => "FR", 
			"Germany" => "DE", 
			"Greece" => "GR", 
			"Hong Kong" => "HK", 
			"Iceland" => "IS", 
			"India" => "IN", 
			"Ireland" => "IE", 
			"Israel" => "IL", 
			"Italy" => "IT", 
			"Jamaica" => "JM", 
			"Japan" => "JP", 
			"Luxembourg" => "LU", 
			"Malaysia" => "MY", 
			"Mexico" => "MX", 
			"Monaco" => "MC", 
			"Netherlands" => "NL", 
			"New Zealand" => "NZ", 
			"Norway" => "NO", 
			"Portugal" => "PT", 
			"Singapore" => "SG", 
			"South Korea" => "KR", 
			"Spain" => "ES", 
			"Sweden" => "SE", 
			"Switzerland" => "CH", 
			"Taiwan" => "TW", 
			"Thailand" => "TH", 
			"Turkey" => "TR", 
			"United Kingdom" => "GB", 
			"United States" => "US", 
			"Uruguay" => "UY"
		));
	} // End country_codes()
	
} // End class paypal