<?php

class Towersystems_XeroApi_Model_Xeroapi {
	

	/**
	 * @author Vinh Kim
	 * @version 1.0
	 * Only Prepare and send Sales Data to Talink Webservice
	 * when the user is not a retaler or admin
	 * The user will be direct to xero page for authorizsation
	 * @param $observer
	 *
	 */

	public function sendSalesDataToTalink($observer){
		// if the tier shipping module exist, it means this site used split order.
		if (Mage::helper('core')->isModuleEnabled('Towersystems_TieredShipping')){

			$tierShippingInstance =  Mage::getModel('tieredshipping/carrier_tieredshipping');
			//if the logged in user is retailer group , do not send the sales data to xero
			if(!$tierShippingInstance->isCustomerRetailer()){

				//get the order id which is generated from the last order
				$lastOrderId = $observer->getEvent()->getOrderIds();

				if (is_array($lastOrderId)) {
					if (!count($lastOrderId) === 1) {
						Mage::log('last order id not singular in observer sendStoresOrderConfirmation', null, 'debug.txt');
						Mage::log($lastOrderId, null, 'debug.txt');
					}
					$lastOrderId = array_shift($lastOrderId);
				} elseif (!is_integer($lastOrderId)) {
					Mage::log('last order id not an integer in observer sendStoresOrderConfirmation', null, 'debug.txt');
					Mage::log($lastOrderId, null, 'debug.txt');
				}

				$data = Mage::helper('xeroapi/data')->prepareOrderData($lastOrderId);
				Mage::helper('xeroapi/data')->sendSalesInvoiceWithSplitOrderToXero($data);
				Mage::helper('xeroapi/data')->sendBillWithSplitOrderToXero($data);
				
			}

		//if tier shipping module not exist, regular magento site 
		}else{
			//get the order id which is generated from the last order
			$lastOrderId = $observer->getEvent()->getOrderIds();
			$data = Mage::helper('xeroapi/data')->prepareOrderDataWithNoSplitOrder($lastOrderId);

			if(Mage::getStoreConfig('xeroapi/xero/is_talink') == 1){
		   		foreach ($data['item'] as $detail){
					try{
						$talinkUrl = Mage::getStoreConfig('xeroapi/xero/talink_url');
						ini_set("soap.wsdl_cache_enabled", 0);
						$client = new SoapClient($talinkUrl);
						$result = $client->rcti(
							"colinhms",
							"Beanie Boos Australia",
							$orderNumber,
							"Beanie Boos", 
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
								"UnitAmount" => $detail['price_inc_tax']	
								));
					}catch (SoapFault $e) { 
   						 echo $e->faultcode; 
					}
				}
				exit();	
			}

			Mage::helper('xeroapi/data')->sendSalesInvoiceWithSplitOrderToXero($data);
			Mage::helper('xeroapi/data')->sendBillWithNoSplitOrderToXero($data);
		}	
	}

	public function recallApi($splitOrderID){
		$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('split_order_id',$splitOrderID);

		foreach($splitCollection as $order) { 
				$orderId = $order->getParentOrderId();
				$data = Mage::helper('xeroapi/data')->prepareOrderData($orderId);
				$data['admin'] = 1;
				$data['redirect'] = 1;
				Mage::helper('xeroapi/data')->sendBillWithSplitOrderToXero($data);
			}

	}

	public function recallBillApiWithNoSplitOrder($orderId){
		$order = Mage::getModel("sales/order")->load($orderId,'increment_id');
		$orderId = $order->getEntityId();
		$data = Mage::helper('xeroapi/data')->prepareOrderDataWithNoSplitOrder($orderId);
		$data['admin'] = 1;
		$data['redirect'] = 1;
		Mage::helper('xeroapi/data')->sendSalesInvoiceWithNoSplitOrderToXero($data);
	}

	public function recallInvoiceApi($orderId){
		$order = Mage::getModel("sales/order")->load($orderId,'increment_id');
		$orderId = $order->getEntityId();
		$data = Mage::helper('xeroapi/data')->prepareOrderData($orderId);
		$data['admin'] = 1;
		$data['redirect'] = 1;
		Mage::helper('xeroapi/data')->sendSalesInvoiceWithSplitOrderToXero($data);
		
	}


	public function cronJobXeroInvoiceApiSender(){
		// get all the order with xero_status = 0
		$orders = Mage::getModel("sales/order")->getCollection()->addFieldToFilter('xero_status','0');
		if(count($orders) > 0 ){
			foreach ($orders as $order){
				$data = json_decode($order->getXeroData(), True);
				$data['admin'] = 1 ;
				$data['redirect'] = 0;
				// print_r($data);exit();
				Mage::helper('xeroapi/data')->sendSalesInvoiceWithSplitOrderToXero($data);			
			}
		
		}
		

		
	}


	public function cronJobXeroBillApiSender(){
		if (Mage::helper('core')->isModuleEnabled('Towersystems_TieredShipping')){
			// get all the split order (bill) with xero_status =0
			$splitCollection = Mage::getModel("retailer/split_order")->getCollection()->addFieldToFilter('xero_status','0');
			if (count($splitCollection) > 0 ){
				$i = 0;
				foreach($splitCollection as $order) { 
					//put together all the xero data
					$splitData[$i] = json_decode($order->getXeroData(), True);
					$splitData[$i]['admin'] = 1;
					$splitData[$i]['redirect'] = 0;
				 $i++;
				}
				
				//remove duplicate value in xero data, data with the same order is usually the same,
				//remove the dulicate will reduce the number of times needed to call xero api (1000 calls/day)
				$splitData = array_map("unserialize", array_unique(array_map("serialize", $splitData)));

				foreach ($splitData as $s){
					// print_r($s);
					Mage::helper('xeroapi/data')->sendBillWithSplitOrderToXero($s);
				}

			}
		}else{
			$orders = Mage::getModel("sales/order")->getCollection()->addFieldToFilter('xero_bill_status','0');
			if(count($orders) > 0 ){
				foreach ($orders as $order){
					$data = json_decode($order->getXeroBillData(), True);
					$data['admin'] = 1 ;
					$data['redirect'] = 0;
					// print_r($data);exit();
					Mage::helper('xeroapi/data')->sendBillWithNoSplitOrderToXero($data);			
				}
			}
		}
	}

}

?>