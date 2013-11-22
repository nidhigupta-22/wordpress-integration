<?php

/*
 * Kayako Main API Class
 * 
 */
/**
 * Description of KayakoApi
 * It handles all the work like creating ticket, authenticating the staff/admin users,
 * Generating curl requests, Use of XML library etc.
 * 
 * @author Saloni Dhall <saloni.dhall@kayako.com>
 */

if (!class_exists('KayakoApi')):

    class KayakoApi {

	private $baseUrl;
	private $username;
	private $password;
	private $apiKey;
	private $secretKey;
	private $signature;
	private $encodedSignature;
	private $salt;



	/*
	 * Define Constructor
	 * BaseURL, ApiKey, SecretKey
	 */
	public function __construct($baseUrl = '', $apiKey = '', $secretKey = '') {

	    $this->baseUrl = $baseUrl;
	    $this->apiKey = $apiKey;
	    $this->secretKey = $secretKey;

	    // Generates a random string of ten digits
	    $this->salt = mt_rand();

	    // Computes the signature by hashing the salt with the secret key as the key
	    $this->signature = hash_hmac('sha256', $this->salt, $this->secretKey, true);

	    // base64 encode...
	    $this->encodedSignature = base64_encode($this->signature);

	    // urlencode...
	    $encodedSignature = urlencode($encodedSignature);
	}

	
	/*
	 * Converts XML to array 
	 * $xml variable takes the XML and converts it to an array
	 * 
	 */

	public function _xml_to_array($xml) {
	    $iter = 0;
	    $arr = array();
	    if (is_string($xml))
		$xml = new SimpleXMLElement($xml);

	    if (!($xml instanceof SimpleXMLElement))
		return $arr;

	    $has_children = false;

	    foreach ($xml->children() as $element) {

		$has_children = true;

		$elementName = $element->getName();

		if ($element->children()) {
		    $arr[$elementName][] = $this->_xml_to_array($element, $namespaces);
		} else {
		    $shouldCreateArray = array_key_exists($elementName, $arr) && !is_array($arr[$elementName]);

		    if ($shouldCreateArray) {
			$arr[$elementName] = array($arr[$elementName]);
		    }

		    $shouldAddValueToArray = array_key_exists($elementName, $arr) && is_array($arr[$elementName]);

		    if ($shouldAddValueToArray) {
			$arr[$elementName][] = trim($element[0]);
		    } else {
			$arr[$elementName] = trim($element[0]);
		    }
		}

		$iter++;
	    }

	    if (!$has_children) {
		$arr['_contents'] = trim($xml[0]);
	    }

	    return $arr;
	}

	/*
	 * This function gets the URL and processes the curl request on it.
	 * POST, GET Request are being processed
	 */

	protected function _processRequest($url, $method, $parameters = array(), $data = array(), $files = array()) {
	    $headers = array();
	    $result = array();
	    
	    $request_body = http_build_query($data, '', '&');
	    $curl_options = array(
		CURLOPT_HEADER => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_CONNECTTIMEOUT => 2,
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_FRESH_CONNECT => true,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_URL => $url,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
		CURLOPT_ENCODING => 'gzip'
	    );

	    switch ($method) {
		case 'GET':
		    break;
		case 'POST':
		    $curl_options[CURLOPT_POSTFIELDS] = $request_body;
		    $curl_options[CURLOPT_POST] = true;
		    break;
		case 'PUT':
		    $curl_options[CURLOPT_CUSTOMREQUEST] = 'PUT';
		    $curl_options[CURLOPT_POSTFIELDS] = $request_body;
		    break;
	    }

	    $curl_options[CURLOPT_HTTPHEADER] = $headers;
	    $curl_handle = curl_init();

	    curl_setopt_array($curl_handle, $curl_options);

	    $response = curl_exec($curl_handle);

	    $http_status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	    
	    
	    if ($http_status != 200) {
		   $result['errorMessage'] = $http_status;
		   return $result;
	    }
	    
	    if (!stristr($response, "<?xml"))
	    {
		 $result['errorReceived'] = $response;
		 return $result;
	    }
	    
	    curl_close($curl_handle);
	    
	    //removing any output prior to proper XML response (ex. Kayako notices)
	    $xml_start_pos = stripos($response, "<?xml");
	    if ($xml_start_pos > 0) {
		$response = substr($response, $xml_start_pos);
	    }
	    $result['result'] = $this->_xml_to_array($response);
	  
	    return $result;
	}

	/* Sets the Base URL
	 * 
	 * 
	 */

	public function setBaseApiURL($_queryPost) {
	    $url = $this->baseUrl . $_queryPost;
	    return $url;
	}

	/* Ticket Information
	 * 
	 */

	public function getTicketInfo($_ticketID) {
	    $_baseUrl = 'Tickets/TicketSearch/';

	    $_getURL = $this->setBaseApiURL($_baseUrl);

	    if ($_ticketID) {

		$_data = array("ticketid" => 1, "query" => $_ticketID, "signature" => $this->encodedSignature, "apikey" => $this->apiKey, "salt" => $this->salt);
	    }
	    //Generates a curl request..............

	    $result = $this->_processRequest($_getURL, 'POST', '', $_data);

	    return $result;
	}

	/*
	 * Helper: Kayako Ticket URL
	 * 
	 * Returns the URL to the Kayako ticket given the ticket MaskID.
	 * 
	 */

	public function _ticket_url($ticket_id) {
	     if (stripos($this->baseUrl, 'index.php'))
	    {
		$_to_remove_pos = stripos($this->baseUrl, 'index.php');
		$_baseUrl = substr($this->baseUrl, 0, $_to_remove_pos-4);
		$_ticketSupportCenterURL = $_baseUrl. 'index.php?';
	    } else
	    {
		$_ticketSupportCenterURL = substr($this->baseUrl, 0, (strpos($this->baseUrl, 'api')-1));
	    }
	    //$_ticketSupportCenterURL = substr($this->baseUrl, 0, (strpos($this->baseUrl, 'api')-1));
	    
	    return $_ticketSupportCenterURL . '/Tickets/Ticket/View/' . $ticket_id;
	}
       
	/* Get view ticketproperties
	 * 
	 * 
	 */

	public function getTicketProperties($_searchQueryLink) {
	    
	    $_retreiveURL = $this->setBaseApiURL($_searchQueryLink);

	    $_retreiveURL .= sprintf("&apikey=%s&salt=%s&signature=%s", $this->apiKey, $this->salt, $this->encodedSignature);

	    $result = $this->_processRequest($_retreiveURL, 'GET');

	    return $result;
	}
	
	/*Ticket Creation 
	 * 
	 * 
	 */
	 public function CreateTicketRESTAPI($_fullName, $_email, $_contents, $_subject, $_departmentID, $_ticketStatusID, $_ticketPriority, $_ticketTypeID)
	 {
	     
	     if ( !empty($_fullName) && !empty($_email) && !empty($_contents) && !empty($_subject) && !empty($_departmentID) && !empty($_ticketStatusID) && !empty($_ticketPriority) && !empty($_ticketTypeID) )
	     {
		    $url = $this->baseUrl.'/Tickets/Ticket/';
	     
		    $_data = array("subject" => $_subject,"fullname" =>$_fullName, "email" =>$_email , "contents" => $_contents, "departmentid" =>$_departmentID, "ticketstatusid"=>$_ticketStatusID , "ticketpriorityid" => $_ticketPriority, "tickettypeid" =>$_ticketTypeID, "autouserid" => '1', "salt" =>$this->salt, "signature" => $this->encodedSignature, "apikey" =>$this->apiKey);
	     
		    $result = $this->_processRequest($url, 'POST', '', $_data);
		    return $result;
		 
	     }
	   
	  }
	  
	  
	  /*
	   * Update the ticket propoerties
	   * 
	   */
	  public function UpdateTicket($_getValues)
	  {
	    if ( is_array($_getValues))
	     {
		    $url = $this->baseUrl.'Tickets/Ticket/'.$_getValues['ticketid'];
	     
		    $updateTicketdetails = array("ticketstatusid" => $_getValues['statusid'],"ticketpriorityid" =>$_getValues['priorityid'], "salt" =>$this->salt, "signature" => $this->encodedSignature, "apikey" =>$this->apiKey);
	     
		    $result = $this->_processRequest($url, 'PUT', '', $updateTicketdetails);
		    return $result;
	    }
	      
	  }
	  
	  /*
	   * Create a Ticket Post 
	   * 
	   */
	  public function CreateTicketPost($ticketid, $contents, $userid)
	  {
	       
	     if ( !empty($ticketid) && !empty($contents) && !empty($userid))
	     {
		    $url = $this->baseUrl.'Tickets/TicketPost/';
	     
		    $_dataDetails = array("ticketid" => $ticketid,"contents" =>$contents, "userid" =>$userid,"salt" =>$this->salt, "signature" => $this->encodedSignature, "apikey" =>$this->apiKey);
	     
		    $result = $this->_processRequest($url, 'POST', '', $_dataDetails);
		    return $result;
		 
	     }
	      
	  }
	  
          
          /*Searches for given email address
           * 
           * 
           */
	  public function _getUserSearchEmailAddress($_emailAddress)
          {   
            $_baseUrl = 'Base/UserSearch/';

	    $_getURL = $this->setBaseApiURL($_baseUrl);

	    if ($_emailAddress) {
                
                $_data = array("email" => 1, "query" => $_emailAddress, "signature" => $this->encodedSignature, "apikey" => $this->apiKey, "salt" => $this->salt);
	    
             }
	    //Generates a curl request..............

	    $result = $this->_processRequest($_getURL, 'POST', '', $_data);

	    return $result;
           }
          
          
          /*Get List of all tickets 
           * 
           * 
           */
          public function _getTicketList($_emailAddress)
          {
              
            $_baseUrl = 'Tickets/TicketSearch/';

	    $_getURL = $this->setBaseApiURL($_baseUrl);

	    if ($_emailAddress) {
                
                $_data = array("user" => 1, "query" => $_emailAddress, "signature" => $this->encodedSignature, "apikey" => $this->apiKey, "salt" => $this->salt);
	    
             }
	    //Generates a curl request..............

	    $result = $this->_processRequest($_getURL, 'POST', '', $_data);

	    return $result;
              
              
          }
      

    }
 
endif;