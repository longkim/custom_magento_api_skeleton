<?php 
/**
* @author Vinh Kim
* @version 1.0
* Capture the authorised request token from xero
* + Get the access token base on the authorised request token
* + Prepare data for 2 different request : Item and Invoice
* + Push the item detail (sku or any unique code is minumum parameter) to Xero Inventory in order to display item code in Xer Invoice
* + Push the Order detail to Xero
*/
class Towersystems_XeroApi_XeroController  extends Mage_Core_Controller_Front_Action {


	public function indexAction(){

		  try{	
		  		$api_url =  Mage::getStoreConfig('xeroapi/xero/api_url');
				$req_url =  Mage::getStoreConfig('xeroapi/xero/req_url');
				$acc_url =  Mage::getStoreConfig('xeroapi/xero/acc_url');
				$auth_url = Mage::getStoreConfig('xeroapi/xero/auth_url');
				$cons_key = Mage::getStoreConfig('xeroapi/xero/cons_key');
				$cons_sec = Mage::getStoreConfig('xeroapi/xero/cons_sec');

				//assign config data
				$config = array(
				    'callbackUrl' => Mage::getUrl('ts/xero'),
				    'siteUrl' => $api_url,
				    'requestTokenUrl'=>$req_url,
				    'accessTokenUrl'=>$acc_url,
				    'authorizeUrl'=>$auth_url,
				    'consumerKey' => $cons_key,
				    'consumerSecret' => $cons_sec
				);
				//new oauth object
				$consumer = new Zend_Oauth_Consumer ($config);
				//if the url has the authorised token from xero
		    	if (!empty($_GET) && isset($_SESSION['XERO_REQUEST_TOKEN']) && !isset($_SESSION['XERO_ACCESS_TOKEN'])) {
				    $token = $consumer->getAccessToken($_GET,unserialize($_SESSION['XERO_REQUEST_TOKEN']));
				  	header('Content-Type: text/javascript; charset=utf-8');
					
				    $_SESSION['XERO_ACCESS_TOKEN'] = serialize($token);
				    // Now that we have an Access Token, we can discard the Request Token
				   
				 } 

				    
				 //prevent user to request the access token again
				 //if this session exist, access token has been granted from xero
				if(isset($_SESSION['XERO_ACCESS_TOKEN'])){
					//convert from string to object
				 	$token = unserialize($_SESSION['XERO_ACCESS_TOKEN']);
				   	
				   	//create a client with the pre-defined configuration 
				 	$client = $token->getHttpClient($config);

				 	//populate this variable with the data from the last order
				    $data= $_SESSION['BEANIEBOOS_ORDER_DETAILS'];
				 	// if the tier shipping module exist, it means this site used split order.
				 	if (Mage::helper('core')->isModuleEnabled('Towersystems_TieredShipping')){
					 	//send sales invoice
					    Mage::helper('xeroapi/data')->sendSalesInvoiceWithSplitOrderToXero($data,$client,$api_url);

					    //send bill with split order to Xero
					    Mage::helper('xeroapi/data')->sendBillWithSplitOrderToXero($data,$client,$api_url);
						

					//if tier shipping module not exist, regular magento site 
					}else{
						Mage::helper('xeroapi/data')->sendBillWithNoSplitOrderToXero($data,$client,$api_url);

						//Mage::helper('xeroapi/data')->sendSalesInvoiceWithNoSplitOrderToXero($data,$client,$api_url);		
					}	
				}

		 	} catch (Zend_Oauth_Exception $e){
		 		print_r($e->getMessage());
		 	}
		   
		
	}

	

	/*
	 * Accept the split order id to get the xero response 
	 *
	*/

	public function viewResponseAction(){
		
		$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('split_order_id',$_GET['id']);
		$filename = $_GET['id']."_Bill_Error_Response.txt";
		foreach($splitCollection as $order) { 
			header ("Content-Type: application/octet-stream");
			header ("Content-disposition: attachment; filename=".$filename);
			echo ($order->getXeroResponse());
		}
	}

	public function viewInvoiceResponseAction(){

		$filename = $_GET['id']."_Invoice_Error_Response.txt";
		$order = Mage::getModel("sales/order")->load($_GET['id'],'increment_id');
		header ("Content-Type: application/octet-stream");
		header ("Content-disposition: attachment; filename=".$filename);
		echo ($order->getXeroResponse());

		
	}		

	/**
	 * Re-send the sales data to xero after  
	**/
	public function resendSalesDataFromAdminAction(){
		Mage::getModel("towersystems_xeroapi/xeroapi")->recallApi($_GET['id']);
	}

	/**
	 * Re-send the invoice data to xero after  
	**/
	public function resendInvoiceDataFromAdminAction(){
		Mage::getModel("towersystems_xeroapi/xeroapi")->recallInvoiceApi($_GET['id']);
	}


	public function crobJobAction(){
		Mage::getModel("towersystems_xeroapi/xeroapi")->cronJobXeroBillApiSender();
	}

}
?>