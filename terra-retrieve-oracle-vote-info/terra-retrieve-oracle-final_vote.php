<?php

/** CONFIGURE - START **/
	
	//RPC Documentation (Endpoints):  https://docs.tendermint.com/v0.34/rpc/#/
	$rpc_base_url = 'https://terra-classic-rpc.publicnode.com/';
	$lcd_param_base_url = 'https://lcd.terra.dev';
	
	// To use TLS, download the CA certificate from https://curl.se/docs/caextract.html and put it in the same directory as this php file
	//		Note:  Current version direct download link is at:  https://curl.se/ca/cacert.pem  
	$use_tls = false;
	
/** CONFIGURE - END **/



$blockheight = get(PHP_EOL . PHP_EOL . "  Enter Blockheight To Get Oracle Pricing From (ie. 9716094)(Press Enter When Done): ", 30);

$use_tls = is_bool($use_tls) ? $use_tls : false;
$blockheight = intval($blockheight) > 0 ? intval($blockheight) : null;

$response = json_decode(https_request_curl(rtrim($rpc_base_url, '\\\/'), '/block', $use_tls), true);
$result_height = isset($response['result']['block']['header']['height']) ? $response['result']['block']['header']['height'] : null;


if (!is_int($blockheight) || !isset($result_height) || (isset($result_height) && intval($result_height) < intval($blockheight))) {
	exit("Blockheight Must Be Less Than Or Equal To " . $result_height . PHP_EOL . PHP_EOL);
}
else {
	$oracle_response = json_decode(https_request_curl(rtrim($rpc_base_url, '\\\/'), '/block_results?height=' . $blockheight, $use_tls), true);
	$arr_decode = '';
	
	if (isset($oracle_response['result']['end_block_events'])) {
		foreach ($oracle_response['result']['end_block_events'] as $k => $v){
			$type = isset($v['type']) ? $v['type'] : null;
			$attrs = isset($v['attributes']) && is_array($v['attributes']) ? $v['attributes'] : null;
			
			if (strtolower($type) == 'exchange_rate_update' && is_array($attrs)){
				unset($tmp_arr_decode);
				
				foreach ($attrs as $k2 => $v2) {
					foreach ($v2 as $k3 => $v3) {
						if (strtolower($k3) == 'key' || strtolower($k3) == 'value') {
							$tmp_arr_decode[] = base64_decode($v3);
							//print_r($tmp_arr_decode);
						}
					}
				}
				
				if (count($tmp_arr_decode) == 4) {
				
					$arr_decode .= '1 LUNA v1 = ' . $tmp_arr_decode['0'] . ': ' . $tmp_arr_decode['1'] . ' - ' . $tmp_arr_decode['2'] . ': ' . $tmp_arr_decode['3'] . PHP_EOL;
				}
			}
		}
		
		echo PHP_EOL . PHP_EOL . $arr_decode . PHP_EOL;
	}
	else {
		$param_response = json_decode(https_request_curl(rtrim($lcd_param_base_url, '\\\/'), '/terra/oracle/v1beta1/params', $use_tls), true);
		
		$param = isset($param_response['params']['vote_period']) ? $param_response['params']['vote_period'] : null;
		
		if (intval($param) > 0) {
			echo PHP_EOL . PHP_EOL . 'This Block Does Not Have Finalized Oracle Voting Results, Voting Takes Place Over ' . $param . ' Blocks' . PHP_EOL;
		}
		else {
			echo PHP_EOL . PHP_EOL . 'This Block Does Not Have Finalized Oracle Voting Results, Voting Takes Place Over A Number Of Blocks' . PHP_EOL;
		}
	}
}


function prompt() {
	// See:  https://www.php.net/manual/en/ref.readline.php#38022
    if (substr(PHP_OS, 0, 3) == "WIN") {	
      $tty = fopen("\con", "rb");	  	  
    } else {
      if (!($tty = fopen("/dev/tty", "r"))) {
        $tty = fopen("php://stdin", "r");
      }
    }
	
	return $tty;
}


function get($string, $length = 1024) {	
    echo $string;
	$tty = prompt();
    $result = trim(fgets($tty, $length));
    
    return $result;
}


function https_request_curl($_url, $_request_path, $_tls = true, $_form_fields = array(), &$_status = null) {
	// Initialize session and set URL.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $_url . $_request_path);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:58.0) Gecko/20100101 Firefox/58.0');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

	// If Form Fields Set
	if (count($_form_fields) > 0) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_form_fields);
	}

	// Set so curl_exec returns the result instead of outputting it.
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	if ($_tls !== false) {
		// TLS Verification (2 = Check that the common name exists and that it matches the host name of the server)
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cacert.pem");
		curl_setopt($ch, CURLOPT_CERTINFO, true);
	}
	else {
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	}
	
	// Get the response and close the channel.
	$response = curl_exec($ch);

	$_status = curl_getinfo($ch);

	// For Testing        
	//print_r($_status);

	curl_close($ch);

	return $response;
}
