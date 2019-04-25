<?php

namespace Fermion\Order\Observer;

/**
 * Description of OrderComplete
 *
 * @author admin
 */
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class PlaceOrder implements ObserverInterface {
	
	protected $_logger;


	public function __construct(
		\Psr\Log\LoggerInterface $logger
	) {
		$this->_logger = $logger;
		
	}

	/**
	 * @param Observer $observer
	 * @return void
	 * event sales_order_place_before
	 */

	public function execute(\Magento\Framework\Event\Observer $observer) {
		$order = $observer->getOrder();
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        #####  // added by shubhanshu for getting payment method #######
           $payment = $order->getPayment();
   		   $methodTitle = $payment->getMethod();
    		if($methodTitle == 'cashondelivery'){
    	    $methodTitle = 'COD';
   			 }else{
    	    $methodTitle = 'Online';
  			  }
        ###################################################
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
		$currencyCode = $storeManager->getStore()->getCurrentCurrencyCode();
		$currencyModel = $objectManager->create('Magento\Directory\Model\Currency');
		$currencySymbol = $currencyModel->load($currencyCode)->getCurrencySymbol();
		$precision = 2;
		//$customerName = $order->getCustomerName();
		$shippingAddress = $order->getShippingAddress()->getData();
		$countryCode=$shippingAddress['country_id'];
		$countryFactory= $objectManager->create('Magento\Directory\Model\CountryFactory');
		$country = $countryFactory->create()->loadByCode($countryCode);
		$countryName =  $country->getName();
		$customerSession = $objectManager->get('Magento\Customer\Model\Session');
		  if($customerSession->isLoggedIn()) {
				$customerName = $order->getCustomerFirstname();
		  }else{
				$customerName = $shippingAddress['firstname']; 
		  }
		$grandTotal = round($order->getGrandTotal(),2);
		$orderId = $order->getIncrementId();
		// $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderId);
		$itemCount = $order->getData('total_qty_ordered');
		$mobileNumber = $order->getShippingAddress()->getTelephone();
		$customer_email = $order->getCustomerEmail();
		$orderCurrencyCode = $order->getOrderCurrencyCode();
		//$content = "Confirmed: We have received your Nykaa Design Studio order #$orderId for INR $grandTotal with $itemCount item(s). We will try our best to ship the order as soon as possible. Please refer to your order confirmation email for the expected delivery dates.";
		// $content = "Confirmed: We have received your Nykaa Design Studio order #$orderId for INR $grandTotal with $itemCount item(s). Please refer to your order confirmation email for the expected delivery dates.";
		$content = "We have received your Nykaa Fashion order #$orderId for $orderCurrencyCode $grandTotal with $itemCount item(s) . Please refer to your order confirmation email for the expected delivery dates. Love, Team NykaaFashion";
		$objectManager->create('Fermion\Order\Helper\Data')->smsCredentials($content,$mobileNumber);
		$catalogHelper = $objectManager->get('Fermion\Catalog\Helper\Data');

		$email_obj = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');

		
		$IconfigPath = 'carriers/flatrate/international_shipping_days';
		$international_shipping_days =  $email_obj->getValue($IconfigPath,\Magento\Store\Model\ScopeInterface::SCOPE_STORE);


		$offerPath = 'general/nykaa_offer/nykaa_offer_active';
		$nykaaOffer =  $email_obj->getValue(
       					$offerPath,
       					\Magento\Store\Model\ScopeInterface::SCOPE_STORE
   						);

		$orderSubtotal = $order->getSubtotal();
		$orderDiscount = $order->getDiscountAmount();
		$orderShipping = $order->getShippingAmount();
		// $offerLowerLimit = $catalogHelper->CurrencyCloneCeil(2500);
		$offerUpperLimit = $catalogHelper->CurrencyCloneCeil(3500);
		$offerText = '';

		$mobAppHelper = $objectManager->get('Fermion\Mobapp\Helper\Data');
		$isAppData = $mobAppHelper->manageVersionResponse();
		if(isset($isAppData['platform']) && $isAppData['platform'] == ''){
			$isApp = 0;
		}else{
			$isApp = 1;
		}

		$offerCondition = $orderSubtotal + $orderDiscount - $orderShipping;
		$detectedCountry = isset($_COOKIE['countryName']) ? $_COOKIE['countryName'] : 'IN';

		if($nykaaOffer){
			if($offerCondition >= $offerUpperLimit && $detectedCountry == 'IN' && $isApp == 0){
				$offerText = '<b>OFFER</b> : You will receive <b>free 2 Nykaa Mirror Chrome Nail Lacquer</b> with this order.';
			}
			/*else if($offerCondition >= $offerLowerLimit){
				$offerText = '<b>OFFER</b> : You will receive a <b>free Nykaa Pout Perfect lip and cheek crayon</b> worth Rs. 499 with this order.';
			}*/
		}


		$store_name = $email_obj->getValue('trans_email/ident_sales/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$store_email = $email_obj->getValue('trans_email/ident_sales/email',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$templateOptions = array('area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeManager->getStore()->getId());
		$bccEmails = $email_obj->getValue('sales_email/order/copy_to');
		$bccEmail = explode(',', $bccEmails);
		$sender = array('name' => $store_name, 'email' => 'noreply@nykaafashion.com');
		$totalItems = $order->getTotalQtyOrdered();
		$baseUrl = $storeManager->getStore()->getBaseUrl();
		$html = '';
		$html='<tr>
					<td class="msgNote">
						<h2 class="title" style="text-align:center;font-size: 1.750em;
				margin: 50px 0px 30px;font-weight: 500;	color:#f38379;text-transform: uppercase;line-height: 40px;">
							THANK YOU FOR <br/>SHOPPING WITH US
						</h2>
						<p class="note" style="font-size: 1.125em; margin: 0px;margin-bottom: 30px;color:#000;font-weight: 400; font-family: Helvetica,serif;line-height: 30px;"><strong style="font-weight: 900;">Hi '.$customerName.',</strong><br/>
							Your order is confirmed. We hope you had a good time shopping with us. Once it\'s been shipped, we will send you a link to help you track your order.  
						</p>
						<h2 class="title" style="text-align:center;font-size: 1.750em;
				margin: 50px 0px 30px;font-weight: 500;color:#f38379;text-transform: uppercase;line-height: 40px;">Here\'s what you ordered:</h2>
						<p class="bgborder" style="font-size: 1.125em;margin: 0px;
				margin-bottom: 30px;color:#000;font-weight: 400;font-family: Helvetica,serif;line-height: 30px;background-image: url('.$baseUrl.'pub/static/frontend/Nykaa/theme001/en_US/images/border.png);background-repeat: repeat-x;background-position: 0 68% !important;"><span style="background: #fff;padding: 0px 5px;">ORDER# : '.$orderId.'</span><span style="float: right;background: #fff;padding: 0px 5px;">'.round($totalItems).' ITEMS </span></p>
					</td>
				</tr> 
				<tr>
					<td>
						<table class="productlist" width="100%" border="0">';
		$allItems = $order->getAllVisibleItems();
		$imageHelper  = $objectManager->get('\Magento\Catalog\Helper\Image');
		
		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');

		$producttobe_removed_array = array();
		$connection = $resource->getConnection();

	   $totalMrp = 0;
       $bagDiscount = 0;
		foreach($allItems as $item) {
			$itemId = $item->getId();

			  $itemData = $item->getData();
            $originalPrice = isset($itemData['original_price']) ? $itemData['original_price'] : 0;
              $originalPrice  = $originalPrice * $itemData['qty_ordered'];
            $totalMrp += $originalPrice;
            $itemPrice = isset($itemData['price']) ? $itemData['price'] : 0;
            $itemPrice  = $itemPrice * $itemData['qty_ordered'];
            
                    if($originalPrice == $itemPrice){
                        $isSpecialPrice = 0;
                    }else{
                        $isSpecialPrice = 1;
                    }
                    $difference = $originalPrice - $itemPrice;
                    $bagDiscount += $difference;
			
			$productID = $item->getProductId();
			
			$qty = round($item->getQtyOrdered());
			$_product = $objectManager->create('Magento\Catalog\Model\Product')->load($productID); 

			$postcode = $order->getShippingAddress()->getPostCode();
            $ships_in = $_product->getData('ships_in');
        	
            
	    	if($ships_in <= 10){
                $ships_in = 5;
            }elseif($ships_in <= 15){
                $ships_in = 7;
            }else{
                $ships_in = round($ships_in / 2);
            }
            
        	if(isset($shippingAddress['country_id']) && $shippingAddress['country_id'] != 'IN'){
                $total_delivery_days = $ships_in + $international_shipping_days;
        	}else{
                $total_delivery_days =  $ships_in;   
        	}
            $delivery_date = $catalogHelper->getNextNBusinessDays($total_delivery_days);
            $est_delivery_date = date('jS F Y', $delivery_date);
            	
				
				
				$currentStore = $storeManager->getStore();
				$mediaUrl = $currentStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
				if ($_product->getImage()) {
				    $image_url = $mediaUrl . 'catalog/product' . $_product->getImage();
				} else {
				    $image_url  = $storeManager->getStore()->getBaseUrl() . 'pub/static/frontend/Nykaa/theme001/en_US/Magento_Catalog/images/product/placeholder/image.jpg';
				}
				//$image_url = 'http://preprod.nykaafashion.com/pub/media/catalog/product/w/a/warp_and_weft_srch12561_1_.jpg';
				$brand = $_product->getResource()->getAttribute('brand')->getFrontend()->getValue($_product);
				$color = $_product->getResource()->getAttribute('primary_color')->getFrontend()->getValue($_product);
				$color = isset($color) ? $color : 'NA';
				$name = $_product->getName();
				$options = $item->getData('product_options');
				$attributes = isset($options['attributes_info']) ? $options['attributes_info'] : array();
				$attr = array();
				foreach($attributes as $attribute) {
					$attr[] = $attribute['value'];
				}
				if(empty($attr)) {
					$attr = 'NA';
				} else {
					$attr = implode(' | ', $attr);	
				}
				$formattedPrice = $currencyModel->format($item->getRowTotal(), ['symbol' => $currencySymbol, 'precision'=> $precision], false, false);
				

				

				$html .='<tr style="border-bottom: 1px solid #000;display: inline-block;width: 100%;padding:20px 0px;">
							<td class="prod" style="vertical-align: top;
					padding-top: 10%; width:170px; padding: 0px;"> <img src="'.$image_url.'" style="width: 100%"></td>
							<td style="padding: 0px 5px;
					vertical-align: top;
					padding-top: 10%;">
								<p style="font-size: 1em;margin:0px;margin-bottom:5px;font-weight: 900;">'.$name.'</p>
								<p style="font-size: 1em;margin:0px;margin-bottom:5px">'.$brand.'</p>
								<p style="margin-top:20px font-size: 1em;margin:0px;margin-bottom:5px">QTY:'.$qty.'</p>
								<p style="margin-top:20px font-size: 1em;margin:0px;margin-bottom:5px">Estimated Delivery Date: <span>'.$est_delivery_date.'</span></p>
							</td>
							<td align="right" style="padding: 0px 5px;
					vertical-align: top;
					padding-top: 10%;">
								<p style="margin: 0px 0px 7px;font-size: 1em;margin:0px;margin-bottom:5px">'.$attr.'|'.$color.'</p><br/><br/>
								<p style="font-size: 1em;margin:0px;margin-bottom:5px"> <span>'.$formattedPrice.'</span></p>
							</td>
						</tr>';
			$objectManager->get('Magento\Catalog\Model\Product')->reset();
		}
		 $totalMrp = number_format($totalMrp,2);
         $bagDiscount = number_format($bagDiscount,2);
		
		$total = $currencyModel->format($order->getSubTotal(), ['symbol' => $currencySymbol, 'precision'=> $precision], false, false);
		$grandTotal = $currencyModel->format($order->getGrandTotal(), ['symbol' => $currencySymbol, 'precision'=> $precision], false, false);
		$discount = $currencyModel->format($order->getDiscountAmount(), ['symbol' => $currencySymbol, 'precision'=> $precision], false, false);
		$shipping = $currencyModel->format($order->getShippingAmount(), ['symbol' => $currencySymbol, 'precision'=> $precision], false, false);

		$discountAmt = $currencyModel->format($order->getDiscountAmount(), ['symbol' => $currencySymbol, 'precision'=> $precision], false, false);
		$discountLine ='';
$discountprice ='';
		                         if($order->getDiscountAmount() != 0){
									$discountLine .='<p>Discount<span style="float:right">:</span></p>';	
									$discountprice .='<p style="color:#50e3c2">'.$discountAmt.'</p>';
									}
		//$shippingAddress = $order->getShippingAddress()->getData();
		$html .= '<tr style="border: none;display: inline-block;
    width: 100%; color:#f38379; padding:20px 0px;"><td>'.$offerText.'</td></tr></table><table width="100%" style="border-top:1px solid #FFF;border-bottom: 1px solid #000;"> 
							  <tr>
        <td></td>
        <td colspan="2" align="left" width="55%">
        <p>Total MRP<span style="float:right">:</span></p>';

        if((int)$bagDiscount > 0){
         $html .= '<p>Bag Discounts<span style="float:right">:</span></p>';     
        }
                
        $html .= '<p>Subtotal<span style="float:right">:</span></p>';
      
          if($order->getDiscountAmount() != 0){
            $html .= '<p>Coupon Discount<span style="float:right">:</span></p>';
          }

          $html .= '<p>Delivery Charges<span style="float:right">:</span></p>
        <p>Grand Total<span style="float:right">:</span></p>
        <p>Mode of Payment<span style="float:right">:</span></p>

        </td>
        <td align="right" width="30%">
        <p>'.$currencySymbol.$totalMrp.'</p>';
        if((int)$bagDiscount > 0){
           $html .=  '<p  style="color:#50e3c2">'.'-'.$currencySymbol.$bagDiscount.'</p>';
        }
        $html .= '<p>'.$total.'</p>';

        if($order->getDiscountAmount() != 0){
        $html .=   '<p>'.$discountprice.'</p>';
       }
         $html .= '<p>'.$shipping.'</p>
        <p>'.$grandTotal.'</p>
        <p>'.$methodTitle.'</p>
        </td>
        </tr>
						</table>
						<table width="100%" class="address" style="font-size: 0.875em;
								margin-top:10px;">
							<tr style="border-bottom: 1px solid #FFF;display: inline-block;margin-bottom: 30px;padding-bottom: 20px;">
								<td width="50%">
									<p class="title" style="font-weight: 900;
								font-size: 1.125em">SHIPPING DETAILS</p>
									<p>'.$shippingAddress['firstname'].' '.$shippingAddress['lastname'].'</p>
									<p>'.$shippingAddress['street'].', '.$shippingAddress['city'].', '.$shippingAddress['region'].', '.$countryName.'-'.$shippingAddress['postcode'].'</p>
								</td>
								<td>
									<p style="margin-top: 34px;"></p>
									<p style="opacity:0;visibility: hidden">Your Order will be delivered 
										on or before 24th March 2018
									</p>
									<p>You will be notified about your order on '.$mobileNumber.' & '.$customer_email.'</p>
								</td>
							</tr>';
		$template_params = array(
			'order'=> $order,
			'html' => $html
		);
		//error_log($html);
		$transport_mail_to_admin =  $objectManager->get('Magento\Framework\Mail\Template\TransportBuilder')->setTemplateIdentifier(4)
		->setTemplateOptions($templateOptions)
		->setTemplateVars($template_params)
		->setFrom($sender)
		->addTo($customer_email)
		->addBcc($bccEmail)
		->getTransport();
		try{
		$res = $transport_mail_to_admin->sendMessage();
		}catch(\Exception $e){
			error_log('ERROR    '.$e->getMessage());
		}


		$items = $order->getItemsCollection();
		foreach ($items as $item) {
		    $productid=$item->getProductId();
		    $_product = $objectManager->get('Magento\Catalog\Model\Product')->load($productid);
		 	$attribute_code="vendor"; 
		    $description_attribute = $_product->getResource()->getAttribute($attribute_code);
		    $vendorCode = $description_attribute->getFrontend()->getValue($_product);
		    $itemId = $item->getItemId();
			$sql1 = "update `sales_order_item` set vendor_code = '".$vendorCode."' where item_id = '".$itemId."'"; 
		    $connection->query($sql1);  
		}



	}

}
