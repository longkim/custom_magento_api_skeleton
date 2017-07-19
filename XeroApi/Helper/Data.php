<?php
require_once(Mage::getBaseDir('lib') . '/Xero/lib/OAuthSimple.php');
require_once(Mage::getBaseDir('lib') . '/Xero/lib/XeroOAuth.php');
require_once(Mage::getBaseDir('lib') . '/Xero/tests/testRunner.php');
/**
 * Class Towersystems_Retailer_Helper_Data
 */
class Towersystems_XeroApi_Helper_Data extends Mage_Core_Helper_Abstract {

	
	public function configData(){
		// echo "test";exit();
		
		$useragent = "Tower Systems";

		$signatures = array (
				'consumer_key' =>  Mage::getStoreConfig('xeroapi/xero/cons_key'),
				'shared_secret' => Mage::getStoreConfig('xeroapi/xero/cons_sec'),
				// API versions
				'core_version' => '2.0',
				'payroll_version' => '1.0',
				'file_version' => '1.0' 
		);
		// echo BASE_PATH;

		$signatures ['rsa_private_key'] = Mage::getBaseDir('lib').'/Xero' . '/certs/privatekey.pem';
		$signatures ['rsa_public_key'] = Mage::getBaseDir('lib').'/Xero' . '/certs/publickey.cer';
		
		$XeroOAuth = new XeroOAuth ( array_merge ( array (
				'application_type' => 'Private',
				'oauth_callback' => 'oob',
				'user_agent' => $useragent 
		), $signatures ) );

		$initialCheck = $XeroOAuth->diagnostics ();

		$checkErrors = count ( $initialCheck );
		if ($checkErrors > 0) {
			// you could handle any config errors here, or keep on truckin if you like to live dangerously
			foreach ( $initialCheck as $check ) {
				Mage::log($check, null, 'debug.txt');
			}
		}else {

			$session = persistSession ( array (
					'oauth_token' => $XeroOAuth->config ['consumer_key'],
					'oauth_token_secret' => $XeroOAuth->config ['shared_secret'],
					'oauth_session_handle' => '' 
			) );
			$oauthSession = retrieveSession ();
			
			if (isset ( $oauthSession ['oauth_token'] )) {
				$XeroOAuth->config ['access_token'] = $oauthSession ['oauth_token'];
				$XeroOAuth->config ['access_token_secret'] = $oauthSession ['oauth_token_secret'];
				
			}
			return $XeroOAuth;
		}	
	}

	public function sendSalesInvoiceWithSplitOrderToXero($data){
		// print_r($data);
		if($data['admin'] == 1){
			$XeroOAuth = $this->configData();
			
			$itemXML =  "<Items>";
			$xml =  "<Invoices>";
		    $xml .= "<Invoice>";
	        $xml .= "<Type>ACCREC</Type>";
	        $xml .= "<Contact>";
	        $xml .= "<Name>Beanie Boos Australia</Name>";
	        $xml .= "<Addresses><Address><AddressType>Street</AddressType><AddressLine1>Shop 25 Brandon park Shopping Centre</AddressLine1>
	            <AddressLine2>Wheelers Hill, VIC</AddressLine2>
	            <City>Wellington</City><PostalCode>3150</PostalCode></Address></Addresses>";
	        $xml .= "</Contact>";
	        $xml .= "<InvoiceNumber>".$data['order_number']."</InvoiceNumber>";
	        $xml .= "<Reference >".$data['order_number']."</Reference >";
	        $xml .= "<Status>AUTHORISED</Status>";
	        $xml .= "<Date>".$data['order_date']."</Date>";
	        $xml .= "<DueDate>".$data['order_date']."</DueDate>";

	        $xml .= "<LineAmountTypes>Inclusive</LineAmountTypes>";
	        $xml .= "<LineItems>";
	        foreach ($data['item'] as $storeName => $store ){
	        	foreach ($store as $details => $detail){
	        		if($details !== "shipping" && $details !== "sub_total"&& $details !== "discount_amount" && $details !== "processing_fee"){
		        		$itemXML .= "<Item>";
						$itemXML .= "<Code>".$detail["barcode"]."</Code>";
						$itemXML .= "</Item>";

						$xml .= "<LineItem>";
						//remove special character from product name, something likse & can make api stop working.
		            	$xml .= "<Description>".preg_replace('/[^A-Z a-z0-9()-]/', '',str_replace("&", "-", $detail["name"])).' via '.$storeName."</Description>";
		            	$xml .= "<Quantity>".$detail["qty"]."</Quantity>";
		            	$xml .= "<UnitAmount>".$detail["price_inc_tax"]."</UnitAmount>";
		            	$xml .= "<AccountCode>200</AccountCode>";
		            	$xml .= "<ItemCode>".$detail["barcode"]."</ItemCode>";
		            	$xml .= "</LineItem>";
	        		}
	        	}
	        }
	        $xml .= "<LineItem>";
	        $xml .= "<Description>Shipping</Description>";
	        $xml .= "<UnitAmount>".$data["shipping"]."</UnitAmount>";
	        $xml .= "<AccountCode>200</AccountCode>";
	        $xml .= "</LineItem>";
	         $xml .= "<LineItem>";
	        $xml .= "<Description>Discount</Description>";
	        $xml .= "<UnitAmount>".$data["discount_amount"]."</UnitAmount>";
	        $xml .= "<AccountCode>200</AccountCode>";
	        $xml .= "</LineItem>";
	        $xml .= "</LineItems>";
	        $xml .= "</Invoice>";
	        $xml .= "</Invoices>";
			$itemXML .= "</Items>";

		
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Items', 'core'), array(), $itemXML);
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
			// print_r($data);exit();
			//If the request is not sucessful
			if($XeroOAuth->response['code'] != 200){
				$errorCode = $XeroOAuth->response['code'];
				$responseXML = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				// print_r($responseXML);

				//if the access token expire
				if($XeroOAuth->response['code'] == 401){
					$errorMessage = "Unauthorized";
				}else{
					//for other type of response code, capture the error message
					$errorMessage = (String) $responseXML->Message;
				}

				//Send email when fail
				//=============================================
				$templateId = Mage::getStoreConfig('xeroapi/xero/invoice_template_id');// Enter you new template ID
				$senderName = Mage::getStoreConfig('trans_email/ident_support/name');  //Get Sender Name from Store Email Addresses
				$senderEmail = Mage::getStoreConfig('trans_email/ident_support/email');  //Get Sender Email Id from Store Email Addresses
				$sender = array('name' => $senderName,
				            'email' => $senderEmail);

				// Set recepient information
				$recepientEmail = "vinh@towersystems.com.au";
				$recepientName = "accounts";      

				// Get Store ID     
				$store = Mage::app()->getStore()->getId();

				// Set variables that can be used in email template
				$vars = array('errors' => $errorMessage, 
							  'orderId' => $data['order_number'],
							  'date' => Mage::getModel('core/date')->date('d/m/Y H:ia'));  


				$transactionalEmail = Mage::getModel('core/email_template');
				$transactionalEmail->getMail()->createAttachment($XeroOAuth->response['response'],
				"application/actet-stream", // Default
				Zend_Mime::DISPOSITION_ATTACHMENT,  // Default
				Zend_Mime::ENCODING_BASE64, // Default
				"error.txt");
				// Send Transactional Email
				$transactionalEmail->sendTransactional($templateId, $sender, $recepientEmail, $recepientName, $vars, $store);

				//============================================================



				//store the response message into database
				$order = Mage::getModel("sales/order")->load($data['order_number'],'increment_id');
				// echo $errorMessage;
				try {
					
					$order->addData(array('xero_message' => "Error Code: ".$errorCode." - ".$errorMessage,
					  					  'xero_response' => $XeroOAuth->response['response'],
					  					  'xero_data' => json_encode($data),
					  					  'xero_status' => 1
					  						));
					  
					$order->save();
					echo "Data Update successful";
					
				} catch (Exception $e){
				    echo $e->getMessage(); 
				    Mage::log($e->getMessage()); 
				}

			//if the request is successful
			} else {
				//get the body
				$responseXML = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

				$order = Mage::getModel("sales/order")->load($data['order_number'],'increment_id');
				$invoiceID = (String)$responseXML->Invoices->Invoice->InvoiceID;
				$amount = (String)$responseXML->Invoices->Invoice->AmountDue;
			
				$pxml = "<Payments>";
		        $pxml .= "<Payment>";
		        $pxml .= "<Invoice>";
		        $pxml .= "<InvoiceID>".$invoiceID."</InvoiceID>";
		        $pxml .= "</Invoice>";
		        $pxml .= "<Date>".$data['order_date']."</Date>";
		        $pxml .= "<Account>";
		        $pxml .= "<Code>".Mage::getStoreConfig('xeroapi/xero/account_code')."</Code>";
		        $pxml .= "</Account>";
		        $pxml .= "<Amount>".$amount."</Amount>";
		        $pxml .= "<Reference>Tower Systems</Reference>";
		        $pxml .= "</Payment>";
		        $pxml .= "</Payments>";
				 // print_r($splitCollection);
				$response = $XeroOAuth->request('POST', $XeroOAuth->url('Payments', 'core'), array(), $pxml);
				// outputError($XeroOAuth);
				try {			
				  	$order->addData(array('xero_id' => $invoiceID,
					  					  'xero_message' => "",
					  					  'xero_response' =>"",
					  					  'xero_data' => json_encode($data),
					  					  'xero_status' => 1
					  						));
					$order->save();
					//echo "Data updated successfully.";
					
				} catch (Exception $e){
				    echo $e->getMessage(); 
				    Mage::log($e->getMessage()); 
				}
				if($data['redirect'] == 1){
					//if request come from backend, redirect back to the order page
					Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=> $data['order_id'])));
				}
			}
			//if the request is not from admin, only store the data and xml into db	
			}else{
				$order = Mage::getModel("sales/order")->load($data['order_number'],'increment_id');
				try {			
				  	$order->addData(array('xero_data' => json_encode($data),
				  						  'xero_status' => 0
				  						));
					$order->save();
					//echo "Data updated successfully.";
					
				} catch (Exception $e){
				    echo $e->getMessage(); 
				    Mage::log($e->getMessage()); 
				}

			}
	}


	public function sendBillWithSplitOrderToXero($data){
		// print_r($data);exit();
		if($data['admin'] == 1){
			
			$XeroOAuth = $this->configData();
			$xml =  "<Invoices>";
		    //init the item data, item needs to be pushed to xero inventory 
		    //before we can use the item code which is the sku of the product
		    $itemXML =  "<Items>";
		    $i = 0;
		    //loop through the item to get the detail of each item belong to each store
			foreach ($data['item'] as $storeName => $store ){
				
	            $xml .= "<Invoice>";
	            $xml .= "<Type>ACCPAY</Type>";
	            $xml .= "<Contact>";
	            $xml .= "<Name>".$storeName."</Name>";
	            $xml .= "<Addresses><Address><AddressType>Street</AddressType><AddressLine1>Shop 25 Brandon park Shopping Centre</AddressLine1>
	            <AddressLine2>Wheelers Hill, VIC</AddressLine2>
	            <City>Wellington</City><PostalCode>3150</PostalCode></Address></Addresses>";
	            $xml .= "</Contact>";
	            if($i == 0){
	            	$xml .= "<InvoiceNumber>".$data['order_number']."</InvoiceNumber>";
					}else{
						$a = $i+1;
						$xml .= "<InvoiceNumber>".$data['order_number']."-".$a."</InvoiceNumber>";
					}
	            $xml .= "<Date>".$data['order_date']."</Date>";
	            // $xml .= "<Status>AUTHORISED</Status>";
	            $xml .= "<DueDate>".$data['order_date']."</DueDate>";
	            $xml .= "<LineAmountTypes>Inclusive</LineAmountTypes>";
	            $xml .= "<LineItems>";
	            // print_r("ASAS:".$i);
	            $i++;

	            //loop through each store to get the data
				foreach ($store as $details => $detail){
					if($details !== "shipping" && $details !== "sub_total" && $details !== "processing_fee" && $details !== "discount_amount"){
					  	$itemXML .= "<Item>";
						$itemXML .= "<Code>".$detail["barcode"]."</Code>";
						$itemXML .= "</Item>";

						$xml .= "<LineItem>";
						//remove special character from product name, something likse & can make api stop working.
	                	$xml .= "<Description>".preg_replace('/[^A-Z a-z0-9()-]/', '',str_replace("&", "-", $detail["name"]))."</Description>";
	                	$xml .= "<Quantity>".$detail["qty"]."</Quantity>";
	                	$xml .= "<UnitAmount>".$detail["price_inc_tax"]."</UnitAmount>";
	                	$xml .= "<AccountCode>200</AccountCode>";
	                	$xml .= "<ItemCode>".$detail["barcode"]."</ItemCode>";
	                	$xml .= "</LineItem>";

					}
				}
				
			 	$xml .= "<LineItem>";
	            $xml .= "<Description>Shipping</Description>";
	            $xml .= "<UnitAmount>".$store["shipping"]."</UnitAmount>";
	            $xml .= "<AccountCode>200</AccountCode>";
	            $xml .= "</LineItem>";
	            $xml .= "<LineItem>";
	            $xml .= "<Description>3% Processing Fee</Description>";
	            $xml .= "<UnitAmount>".-$store["processing_fee"]."</UnitAmount>";
	            $xml .= "<AccountCode>200</AccountCode>";
	            $xml .= "</LineItem>";
	            $xml .= "<LineItem>";
	            $xml .= "<Description>Discount</Description>";
	            $xml .= "<UnitAmount>".-$store["discount_amount"]."</UnitAmount>";
	            $xml .= "<AccountCode>200</AccountCode>";
	            $xml .= "</LineItem>";
	            $xml .= "</LineItems>";
	            $xml .= "</Invoice>";
			}

			$xml .= "</Invoices>";
			$itemXML .= "</Items>";
		
			//create item in Xero Inventory first to get the Item Code for the next request
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Items', 'core'), array(), $itemXML);
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
			

			// outputError($XeroOAuth);
			// print_r($response->getBody());
			//If the request is not sucessful
			if($XeroOAuth->response['code'] != 200){
				$errorCode = $XeroOAuth->response['code'];
				$responseXML = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				// print_r($XeroOAuth->response['response']);exit();

				//if the access token expire
				if($XeroOAuth->response['code'] == 401){
					$errorMessage = "Unauthorized";
				}else{
					//for other type of response code, capture the error message
					$errorMessage = (String) $responseXML->Message;
				}

				//store the response message into database
				$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('parent_increment_id', $data['order_number']);
				// echo $errorMessage;


				//Send email when fail
				//=============================================
				$templateId = Mage::getStoreConfig('xeroapi/xero/bill_template_id'); // Enter you new template ID
				$senderName = Mage::getStoreConfig('trans_email/ident_support/name');  //Get Sender Name from Store Email Addresses
				$senderEmail = Mage::getStoreConfig('trans_email/ident_support/email');  //Get Sender Email Id from Store Email Addresses
				$sender = array('name' => $senderName,
				            'email' => $senderEmail);

				// Set recepient information
				$recepientEmail = "vinh@towersystems.com.au";
				$recepientName = "accounts";      

				// Get Store ID     
				$store = Mage::app()->getStore()->getId();

				// Set variables that can be used in email template
				$vars = array('errors' => $errorMessage, 
							  'orderId' => $data['order_number'],
							  'date' => Mage::getModel('core/date')->date('d/m/Y H:ia'));  


				$transactionalEmail = Mage::getModel('core/email_template');
				$transactionalEmail->getMail()->createAttachment($XeroOAuth->response['response'],
				"application/actet-stream", // Default
				Zend_Mime::DISPOSITION_ATTACHMENT,  // Default
				Zend_Mime::ENCODING_BASE64, // Default
				"error.txt");
				// Send Transactional Email
				$transactionalEmail->sendTransactional($templateId, $sender, $recepientEmail, $recepientName, $vars, $store);

				//========================================================



				try {
					foreach($splitCollection as $order) { 
					  
					  $order->addData(array('xero_message' => "Error Code: ".$errorCode." - ".$errorMessage,
					  						'xero_response' => $XeroOAuth->response['response'],
					  						'xero_data' => json_encode($data),
					  						'xero_status' => 1
					  						));
					  
					  $order->save();
					 
					}
				} catch (Exception $e){
				    echo $e->getMessage(); 
				    Mage::log($e->getMessage()); 
				}
				if($data['redirect'] == 1){
				//if request come from backend, redirect back to the order page
					Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=> $data['order_id'])));
				}
				
			//if the request is successful
			} else {
				
				//get the body
				$responseXML = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

				foreach ($responseXML->Invoices->Invoice as $invoice){
					//store incoive id into an array with the store name eg: invoiceID[newXpress Southland] = 123456
					$invoiceID[(String)$invoice->Contact->Name] = (String)$invoice->InvoiceID;
					// $pxml = "<Payments>";
			  //       $pxml .= "<Payment>";
			  //       $pxml .= "<Invoice>";
			  //       $pxml .= "<InvoiceID>".(String)$invoice->InvoiceID."</InvoiceID>";
			  //       $pxml .= "</Invoice>";
			  //       $pxml .= "<Date>".$data['order_date']."</Date>";
			  //       $pxml .= "<Account>";
			  //       $pxml .= "<Code>".Mage::getStoreConfig('xeroapi/xero/account_code')."</Code>";
			  //       $pxml .= "</Account>";
			  //       $pxml .= "<Amount>".(String)$invoice->AmountDue."</Amount>";
			  //       $pxml .= "<Reference>Tower Systems</Reference>";
			  //       $pxml .= "</Payment>";
			  //       $pxml .= "</Payments>";
			  //        // print_r($splitCollection);
					// $response = $XeroOAuth->request('POST', $XeroOAuth->url('Payments', 'core'), array(), $pxml);
				}
				// print_r($data1);

				$xeroInvoiceID = (String) $responseXML->Invoices->Invoice->InvoiceID[0];
				// echo "<pre>";
				// print_r($xmlObject);
				// echo "</pre>";
				// exit();
				$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('parent_increment_id', $data['order_number']);
 				

 			// 	$storeIds = Mage::getResourceModel('demac_multilocationinventory/location')->lookupStoreIds('2'); 
				// $stores = array_diff($storeIds, array(Mage_Core_Model_App::ADMIN_STORE_ID, Mage::helper('retailer')->getAllStoresView()));					
				// $storeId = array_shift($stores);

				// // $storeid = Mage::helper("retailer/location")->getStoreIdByLocation(2);
				//  // print_r($splitCollection);
				// Mage::log("StoreID :".print_r($storeIds),null,'cron.log');
				try {
					foreach($splitCollection as $order) { 
					 
					  //$storeid = Mage::helper("retailer/location")->getStoreIdByLocation($order->getLocationId());
					  $storeIds = Mage::getResourceModel('demac_multilocationinventory/location')->lookupStoreIds($order->getLocationId()); 
					  $stores = array_diff($storeIds, array(Mage_Core_Model_App::ADMIN_STORE_ID, Mage::helper('retailer')->getAllStoresView()));					
					  $storeId = array_shift($stores);
					 
					  $store = Mage::getModel('core/store')->load($storeId);
		
					  $name = $store->getName();
			
					  $order->addData(array('xero_id' => $invoiceID[$name],
					  						'xero_message' => "",
					  						'xero_response' =>"",
					  						'xero_data' => json_encode($data),
					  						'xero_status' => 1
					  						));
					  
					  $order->save();
					 
					  //echo "Data updated successfully.";
					}
				} catch (Exception $e){
				    echo $e->getMessage();
				    Mage::log($e->getMessage()); 
				}
				if($data['redirect'] == 1){
				//if request come from backend, redirect back to the order page
					Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=> $data['order_id'])));
				}
				
			}
		//if the request doesn't come from admin, save it to db only
		} else{
			$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('parent_increment_id', $data['order_number']);
				 // print_r($splitCollection);
			
			try {
				foreach($splitCollection as $order) { 
				  $order->addData(array('xero_data' => json_encode($data),
				  						'xero_status' => 0
				  						));
				  $order->save();
				  //echo "Data updated successfully.";
				}
			} catch (Exception $e){
			    echo $e->getMessage(); 
			    Mage::log($e->getMessage()); 
			}
			
		}
	}

	public function sendBillWithNoSplitOrderToXero($data){
		if($data['admin'] == 1){
			$XeroOAuth = $this->configData();
			//init the main invoice data
		    $xml =  "<Invoices>";
		    $xml .= "<Invoice>";
	        $xml .= "<Type>ACCPAY</Type>";
	        $xml .= "<Contact>";
	        $xml .= "<Name>Beanieboos Australia</Name>";
	        $xml .= "<Addresses><Address><AddressType>Street</AddressType><AddressLine1>Shop 25 Brandon park Shopping Centre</AddressLine1>
	            <AddressLine2>Wheelers Hill, VIC</AddressLine2>
	            <City>Wellington</City><PostalCode>3150</PostalCode></Address></Addresses>";
	        $xml .= "</Contact>";
	        $xml .= "<InvoiceNumber>".$data['order_number']."_BeanieBoo</InvoiceNumber>";
	        $xml .= "<Date>".$data['order_date']."</Date>";
	        $xml .= "<DueDate>".$data['order_date']."</DueDate>";
	        $xml .= "<LineAmountTypes>Inclusive</LineAmountTypes>";
	        $xml .= "<LineItems>";
	        $itemXML =  "<Items>";
	        foreach ($data['item'] as $item){
	        	$itemXML .= "<Item>";
				$itemXML .= "<Code>".$item["barcode"]."</Code>";
				$itemXML .= "<name>".preg_replace('/[^A-Z a-z0-9()-]/', '',str_replace("&", "-", $item["name"]))."</name>";
				$itemXML .= "</Item>";

	    		$xml .= "<LineItem>";
				//remove special character from product name, something likse & can make api stop working.
	        	$xml .= "<Description>".preg_replace('/[^A-Z a-z0-9()-]/', '',str_replace("&", "-", $item["name"]))."</Description>";
	        	$xml .= "<Quantity>".$item["qty"]."</Quantity>";
	        	$xml .= "<UnitAmount>".$item["price_inc_tax"]."</UnitAmount>";
	        	$xml .= "<AccountCode>200</AccountCode>";
	        	$xml .= "<ItemCode>".$item["barcode"]."</ItemCode>";
	        	$xml .= "</LineItem>";
	        	$xml .= "<LineItem>";
	        }
	        $xml .= "<Description>Shipping</Description>";
	        $xml .= "<UnitAmount>".$data["shipping"]."</UnitAmount>";
	        $xml .= "<AccountCode>200</AccountCode>";
	        $xml .= "</LineItem>";
	        $xml .= "<LineItem>";
	        $xml .= "<Description>Discount</Description>";
	        $xml .= "<UnitAmount>".$data["discount_amount"]."</UnitAmount>";
	        $xml .= "<AccountCode>200</AccountCode>";
	        $xml .= "</LineItem>";
	        $xml .= "<LineItem>";
	        $xml .= "<Description>3% Processing Fee</Description>";
	        $xml .= "<UnitAmount>".-$data["processing_fee"]."</UnitAmount>";
	        $xml .= "<AccountCode>200</AccountCode>";
	        $xml .= "</LineItem>";
	        $xml .= "</LineItems>";
	        $xml .= "</Invoice>";
	        $xml .= "</Invoices>";

	        $itemXML .= "</Items>";
		   	//create item in Xero Inventory first to get the Item Code for the next request
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Items', 'core'), array(), $itemXML);
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);


			//If the request is not sucessful
			if($XeroOAuth->response['code'] != 200){
				$errorCode = $XeroOAuth->response['code'];
				$responseXML = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				// print_r($XeroOAuth->response['response']);exit();

				//if the access token expire
				if($XeroOAuth->response['code'] == 401){
					$errorMessage = "Unauthorized";
				}else{
					//for other type of response code, capture the error message
					$errorMessage = (String) $responseXML->Message;
				}


					//Send email when fail
				//=============================================
				$templateId = Mage::getStoreConfig('xeroapi/xero/bill_template_id'); // Enter you new template ID
				$senderName = Mage::getStoreConfig('trans_email/ident_support/name');  //Get Sender Name from Store Email Addresses
				$senderEmail = Mage::getStoreConfig('trans_email/ident_support/email');  //Get Sender Email Id from Store Email Addresses
				$sender = array('name' => $senderName,
				            'email' => $senderEmail);

				// Set recepient information
				$recepientEmail = "accounts@beanieboosaustralia.com";
				$recepientName = "accounts";      

				// Get Store ID     
				$store = Mage::app()->getStore()->getId();

				// Set variables that can be used in email template
				$vars = array('errors' => $errorMessage, 
							  'orderId' => $data['order_number'],
							  'date' => Mage::getModel('core/date')->date('d/m/Y H:ia'));  


				// Send Transactional Email
				Mage::getModel('core/email_template')
				    ->sendTransactional($templateId, $sender, $recepientEmail, $recepientName, $vars, $store);

				//========================================================


				//store the response message into database
				$order = Mage::getModel("sales/order")->load($data['order_number'],'increment_id');
				try {
					  $order->addData(array('xero_bill_message' => "Error Code: ".$errorCode." - ".$errorMessage,
					  						'xero_bill_response' => $XeroOAuth->response['response'],
					  						'xero_bill_data' => json_encode($data),
					  						'xero_bill_status'=>1
					  						));
					  $order->save();
					 
					
				} catch (Exception $e){
				    echo $e->getMessage(); 
				    Mage::log($e->getMessage()); 
				}

			//if the request is successful
			} else {
				//get the body
				$responseXML = $response->getBody();
				$xmlObject = simplexml_load_string($responseXML);
				
				$invoiceID = (String)$xmlObject->Invoices->Invoice->InvoiceID;
				
				$order = Mage::getModel("sales/order")->load($data['order_number'],'increment_id');
				
				try {
					  $order->addData(array('xero_bill_id' => $invoiceID,
					  						'xero_bill_message' => "",
					  						'xero_bill_response' =>"",
					  						'xero_bill_data' => json_encode($data),
					  						'xero_bill_status' => 1
					  						));
					  $order->save();
					  //echo "Data updated successfully.";
					
				} catch (Exception $e){
				    echo $e->getMessage(); 
				    Mage::log($e->getMessage()); 
				}
				if($data['redirect'] == 1){
				//if request come from backend, redirect back to the order page
					Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=> $data['order_id'])));
				}
			}

		}else{
			$order = Mage::getModel("sales/order")->load($data['order_number'],'increment_id');
			
			try {
				  $order->addData(array('xero_bill_data' => json_encode($data),
				  						'xero_bill_status' => 0
				  						));
				  $order->save();
				  //echo "Data updated successfully.";
				
			} catch (Exception $e){
			    echo $e->getMessage(); 
			    Mage::log($e->getMessage()); 
			}

		}	
		
	}

	public function prepareOrderData($lastOrderId){
		$order = Mage::getModel('sales/order')->load($lastOrderId);

		//fetch the split order detail in towersystems_retailer_split_order table by using the order id
		$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('parent_order_id', $lastOrderId);

		//get the location instance
		$location = Mage::helper("retailer/location");

		//init 
		$shippInclTax = array();

		//loop through the result return by the above query, put the shipping cost in an assosiate array with the following format
		//shippInclTax["7"] => 9.0 , 7 is the store id and 9.0 is the shipping inclu tax for that store in the last order
		foreach($splitCollection as $locationOrder) { 
			// print_r($locationOrder);
			//get the store id by using the location id 
			$storeid = $location->getStoreIdByLocation($locationOrder->getLocationId());

			//store the shipping cost assosiate with its store
			$shippInclTax[$storeid] = $locationOrder->getShippingInclTax();


		}
		// print_r($shippInclTax);
		//fetch all the item from the last order	
		$items = $order->getAllItems();
		
		//get orderNumber here, since order->getIncrementID returns an Object, we can't assign it directly into an array.
		$orderNumber = $order->getIncrementId();
		$billingAddress = $order->getBillingAddress();
		// print_r($billingAddress->getData());exit();
		$addressLine1 = $billingAddress->getStreet();
		// print_r($addressLine1);exit();
		$addressLine2 = $billingAddress->getRegion();
		$postCode = $billingAddress->getPostcode();
		$city = $billingAddress->getCity();
		$orderDate = Mage::app()->getLocale()->date(strtotime($order->getCreatedAtStoreDate()), null, null, false)->toString('YYYY-MM-dd');

		//Prepare the array for api call.
		$data = array();

		//we don't need to include subtotal and grandtotal here since Xero will not accept that parameter and calculate base on the price
		//and quantity of each item instead
		$data['order_number'] = $orderNumber;
		$data['store_name'] = $order->getStore()->getName();
		$data['order_date'] = Mage::app()->getLocale()->date(strtotime($order->getCreatedAtStoreDate()), null, null, false)->toString('YYYY-MM-dd');
		$data['order_id'] = $lastOrderId;
		$data['discount_amount'] = $order->getDiscountAmount();
		$data['admin'] = 0;
		//total shipping for the whole order
		$data['shipping'] =  $order->getShippingInclTax();
		$subtotal  = $order->getGrandTotal() - $order->getShippingInclTax();
		//default percentage is 3%
		$processing_fee_percentage = Mage::getStoreConfig('xeroapi/xero/processing_fee_percentage');
		//total processing fee not devide by store
		$data['processing_fee'] = round( ( $subtotal + $data['shipping'] ) * $processing_fee_percentage,2);
		//init
		$i = 0;
		//get each item detail

		foreach ($items as $itemId => $item)
		{	
			//get the store name using the store id
			$store = Mage::getModel('core/store')->load($item->getStoreId());
			$name = $store->getName();
			
			//bind the appropriate data
    		$data['item'][$name][$i]['name'] = $item->getName();
    		$data['item'][$name][$i]['price'] = $item->getPrice();
    		$data['item'][$name][$i]['price_inc_tax'] = $item->getPriceInclTax();
    		$data['item'][$name][$i]['barcode'] = $item->getSku();
    		$data['item'][$name][$i]['qty'] = $item->getQtyToInvoice();
    		$data['item'][$name][$i]['store_id'] = $item->getStoreId();
    		$data['item'][$name][$i]['discount_amount'] = $item->getDiscountAmount();
    		// $data['item'][$name]['shipping'] = $shippInclTax[$item->getStoreId()];
    		$i++;

		}

		$subtotal= 0;
		if(Mage::getStoreConfig('xeroapi/xero/is_talink') == 0){
			//calculate processing fee since it maybe different in each store
			//processing fee is 3% of subtotal
			foreach ($data['item'] as $storeName => $storeDetail){
				$sub_total = 0;
				$shipping =0 ;
				$discount = 0;
				foreach ($storeDetail as $detail){
					$sub_total += $detail['price_inc_tax']* $detail['qty'];
					$discount += $detail['discount_amount'];
					if($shippInclTax[$detail['store_id']] !=""){
						//individual store shopping fee
						$data['item'][$storeName]['shipping'] = $shippInclTax[$detail['store_id']];
					}
					$data['item'][$storeName]['discount_amount'] = $discount;
					// print_r( $detail['qty']);exit();
				}
				// print_r($shippInclTax[$detail['store_id']]);exit();
				if($shippInclTax[$detail['store_id']] =="" || $shippInclTax[$detail['store_id']] == "0"){
					$data['item'][$storeName]['shipping'] =  Mage::helper("tieredshipping/data")->getShippingTier($sub_total);
				}
				//individual store processing fee
				$data['item'][$storeName]['processing_fee'] = round( ( $sub_total + $data['item'][$storeName]['shipping'] ) * $processing_fee_percentage,2);

			}
		}else{
			foreach ($data['item'] as $storeName => $storeDetail){
				foreach ($storeDetail as $detail){
						try{
							$talinkUrl = Mage::getStoreConfig('xeroapi/xero/talink_url');
							ini_set("soap.wsdl_cache_enabled", 0);
							$client = new SoapClient($talinkUrl);
							$result = $client->rcti(
								"colinhms",
								"Beanie Boos Australia",
								$orderNumber,
								$storeName, 
								$addressLine1[0],
								$addressLine2,
								$city,
								$addressLine2,
								$postCode,
								$orderDate,
								$orderDate,
								array(
									"ItemCode" => $detail['barcode'],
									"ItemDescription" => $detail['name'],
									"Quantity" => $detail['qty'],
									"UnitAmount" => $detail['price']	
									));
							exit();
						}catch (SoapFault $e) { 
	   						 echo $e->faultcode; 
						}
					}
				}	
			}
		return $data;
	}


	public function prepareOrderDataWithNoSplitOrder($lastOrderId){
		//get the order detail
		$order = Mage::getModel('sales/order')->load($lastOrderId);

		//fetch all the item from the last order	
		$items = $order->getAllItems();
		
		//get orderNumber here, since order->getIncrementID returns an Object, we can't assign it directly into an array.
		$orderNumber = $order->getIncrementId();

		$billingAddress = $order->getBillingAddress();
		// print_r($billingAddress->getData());exit();
		$addressLine1 = $billingAddress->getStreet();
		// print_r($addressLine1);exit();
		$addressLine2 = $billingAddress->getRegion();
		$postCode = $billingAddress->getPostcode();
		$city = $billingAddress->getCity();
		$orderDate = Mage::app()->getLocale()->date(strtotime($order->getCreatedAtStoreDate()), null, null, false)->toString('YYYY-MM-dd');

		//Prepare the array for api call.
		$data = array();
		$processing_fee_percentage = Mage::getStoreConfig('xeroapi/xero/processing_fee_percentage');
		//we don't need to include subtotal and grandtotal here since Xero will not accept that parameter and calculate base on the price
		//and quantity of each item instead
		//increment id
		$data['order_number'] = $orderNumber;
		//380
		$data['order_id'] = $lastOrderId;
		$data['store_name'] = $order->getStore()->getName();
		$data['order_date'] = Mage::app()->getLocale()->date(strtotime($order->getCreatedAtStoreDate()), null, null, false)->toString('YYYY-MM-dd');
		$data['shipping'] =  $order->getShippingInclTax();
		$data['discount_amount'] = $order->getDiscountAmount();
		$subtotal  = $order->getGrandTotal() - $order->getShippingInclTax();
		$data['processing_fee'] = round( ( $subtotal + $data['shipping'] ) * $processing_fee_percentage,2);
		//default percentage is 3%
		$i = 0;
		//get each item detail
		foreach ($items as $item)
		{	
			
			//bind the appropriate data
    		$data['item'][$i]['name'] = $item->getName();
    		$data['item'][$i]['price'] = $item->getPrice();
    		$data['item'][$i]['price_inc_tax'] = $item->getPriceInclTax();
    		$data['item'][$i]['barcode'] = $item->getSku();
    		$data['item'][$i]['qty'] = $item->getQtyToInvoice();
    		$data['item'][$i]['discount_amount'] = $item->getDiscountAmount();
    		// $data['item'][$i]['store_id'] = $item->getStoreId();
    		$i++;

		}

		return $data;
	}

}
?>