<?php

use Magento\Eav\Setup\EavSetup;

require __DIR__ . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$CacheInterface = $objectManager->create('\Magento\Framework\App\CacheInterface');
$productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');

$productObj = $objectManager->create('Magento\Catalog\Model\Product');
$helperImport   = $objectManager->get('\Magento\Catalog\Helper\Image');
$productCollectionFactory = $objectManager->create('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
$collection = $productCollectionFactory->create();
$catalogHelper = $objectManager->create('\Fermion\Catalog\Helper\Data');
$StoreManagerInterface = $objectManager->create('\Magento\Store\Model\StoreManagerInterface');
$storeId = $StoreManagerInterface->getStore()->getId();



$categoryFactory = $objectManager->create('Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');
$categories = $categoryFactory->create()                              
    ->addAttributeToSelect('*')
    ->setStore($StoreManagerInterface->getStore());

$level3_categories = array();
$designerCategory = array();
foreach ($categories as $category){
	if($category->getLevel() == 3)
	{
		// $parent_id=$category->getParentCategory()->getId();
		if($category->getParentCategory()->getId() == 102){
			$designerCategory[$category->getId()] = $category->getUrl();
			$designerCategory['name'.$category->getId()] = $category->getName();
			continue;
		}
		$level3_categories[$category->getId()] = $category->getLevel();

	}
}   
echo '<pre>';
print_r($level3_categories); die;
$image = 'category_page_grid';
$productCollection
    ->addAttributeToSelect('sku,brand')
    ->addAttributeToFilter('visibility', ['in'=> array(2,4)])
    ->addAttributeToFilter(
            'status', array('eq' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
        )
    ->load();

$masterProductCollection = $productCollection;
$productMasterArr = array();
$currentStore = $StoreManagerInterface->getStore();
$mediaUrl = $currentStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
$ListProduct = $objectManager->create('\Magento\Catalog\Block\Product\ListProduct');
$productRepository = $objectManager->get('Magento\Catalog\Api\ProductRepositoryInterface');

$k = 0;
foreach ($masterProductCollection as $key => $value) {
	$productSku = $value->getData('sku');
	   $productId = $value->getId();
	   echo $k."----".$productId."\n";
	$brandWidgetKey = 'brand_widget_'.$productSku;
	$categoryWidgetKey = 'category_widget_'.$productSku;

	$mainProduct = $productRepository->getById($productId);
	$brandId = $mainProduct->getData('brand');

	$categoryIds = $mainProduct->getCategoryIds();
	
	$categoryCollectionArr = array();
	$brandRedirect = '';
	$brandName='';
	foreach ($categoryIds as $row) 
	{
		if(isset($level3_categories[$row]))
		{
			$categoryCollectionArr[] = $row;
		}
		if(isset($designerCategory[$row]))
		{
			 $brandRedirect = $designerCategory[$row];
		     $brandName = $designerCategory['name'.$row];	


		}
	}
	$productCollection->clear()->getSelect()->reset(Zend_Db_Select::WHERE);
	$productCollection->getSelect()->reset(Zend_Db_Select::ORDER);
	
	$productCollection
    ->addAttributeToSelect('*')
    ->addAttributeToFilter('brand', $brandId)
    ->addAttributeToFilter('visibility', ['in'=> array(2,4)])
    ->addAttributeToFilter('price', array("gt" => 0))
    ->addAttributeToFilter(
            'status', array('eq' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
        )	
    ->setPageSize(15)
    ->load();
	$productCollection->getSelect()->orderRand();

	$productArr = array();

	$i = 0;
	foreach ($productCollection as $product) {

		 $product_Id = $product->getId();
		 // echo $product_Id."\n";
		$product_Sku = $product->getData('sku'); 
		$brand = '';
        $brandattr = $product->getResource()->getAttribute('brand')->setStoreId($storeId)->getFrontend()->getValue($product);
		$price = $product->getData('price');
		$discountedPrice = $product->getData('special_price');
		$productUrl = $product->getProductUrl();
		$priceNds = $product->getData('price_value_nds');
		 
			$productImageUrl='';
			try {
					 $productImageUrl = $helperImport->init($product, 'product_page_image_large')
					    ->setImageFile($product->getthumbnail())
					    ->getUrl();
				
			} catch (Exception $e) {
			     $productImageUrl = $mediaUrl.'catalog/product'.$product->getData('image');
			}
	

		$productArr[$i]['productId'] = $product_Id;
		$productArr[$i]['productSku'] = $product_Sku;
		//$productArr[$i]['productName'] = ltrim(str_ireplace($brandattr,'',$product->getData('name')));
		$productArr[$i]['productName'] = ltrim(preg_replace('~'.$brandattr.'~i','',$product->getData('name'),1))
		$productArr[$i]['price'] = $price;
		$productArr[$i]['discountedPrice'] = $discountedPrice;
		if($discountedPrice > 0 && $price > 0)
        {
          $discount = (($price - $discountedPrice)*100) /$price ;
          $productArr[$i]['discount'] = (int)$discount;
        }
        $productArr[$i]['imageUrl'] = $productImageUrl;
        $productArr[$i]['ProductUrl'] = $productUrl;
        $productArr[$i]['PriceValueNds'] = $priceNds;
		$attr = $product->getResource()->getAttribute('brand');
		$productArr[$i]['brand']= $attr->getSource()->getOptionText($product->getData('brand'));
        if(!isset($productMasterArr[$product_Id]))
        	{
				$productMasterArr[$product_Id] = $productArr;
			}
			// echo "<pre>";print_r($productMasterArr[$product_Id]); echo "</pre>";
			// echo "<pre>";print_r($product); echo "</pre>";
			// echo $product_Id.' done'."\n";
		$i++;
	}
	/* save brand cache */
	$returnArr = array('brandWidget'=>$productArr,'designer'=>$brandName,'designerUrl'=>$brandRedirect);
	$CacheInterface->save(json_encode($returnArr),$brandWidgetKey,array(),889000);
	
	$data = $CacheInterface->load($brandWidgetKey);
	/* ---- end ---*/

	

	// print_r($categoryCollectionArr);
	if(!empty($categoryCollectionArr))
	{

			$categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');// Instance of Category Model
			$categoryId = isset($categoryCollectionArr[0])?$categoryCollectionArr[0]:''; // YOUR CATEGORY ID
			$category = $categoryFactory->create()->load($categoryCollectionArr[0]);
			$more_from_category=$category->getName();
			$more_from_category_url=$category->getUrl();
		$productArray = array();
			$collection->getSelect()->reset(Zend_Db_Select::ORDER);
			$collection->clear()->getSelect()->reset(Zend_Db_Select::WHERE);

        $collection->addAttributeToSelect('*')
        ->addAttributeToFilter('visibility', ['in'=> array(2,4)])
        ->addAttributeToFilter('price', array("gt" => 0))
	    ->addAttributeToFilter(
	            'status', array('eq' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
	        );
        $collection->addCategoriesFilter(['in' => $categoryCollectionArr]);
        $collection->getSelect()->limit(15);
        $collection->getSelect()->orderRand();
		
		foreach ($collection as $product) {
			$product_Id = $product->getId();
			$product_Sku = $product->getData('sku'); 
			$brand = '';
	        $brandattr = $product->getResource()->getAttribute('brand')->setStoreId($storeId)->getFrontend()->getValue($product);
			$price = $product->getData('price');
			$discountedPrice = $product->getData('special_price');
			$productUrl = $product->getProductUrl();
			$priceNds = $product->getData('price_value_nds');
			 
			$productImageUrl='';
			try {

				 $productImageUrl = $helperImport->init($product, 'product_page_image_large')
				    ->setImageFile($product->getthumbnail())
				    ->getUrl();
				
			} catch (Exception $e) {
				  $productImageUrl = $mediaUrl.'catalog/product'.$product->getData('image');
			}

		 
			$productArray[$i]['productId'] = $product_Id;
			$productArray[$i]['productSku'] = $product_Sku;
			$productArray[$i]['productName'] = ltrim(str_ireplace($brandattr,'',$product->getData('name')));
			$productArray[$i]['price'] = $price;
			$productArray[$i]['discountedPrice'] = $discountedPrice;
			if($discountedPrice > 0 && $price > 0)
	        {
	          $discount = (($price - $discountedPrice)*100) /$price ;
	          $productArray[$i]['discount'] = (int)$discount;
	        }
	        $productArray[$i]['imageUrl'] = $productImageUrl;
	        $productArray[$i]['ProductUrl'] = $productUrl;
	        $productArray[$i]['PriceValueNds'] = $priceNds;
      


	        // echo "_______".$product_Id."---".round($price)."\n";
	        $attr = $product->getResource()->getAttribute('brand');
			$productArray[$i]['brand']= $attr->getSource()->getOptionText($product->getData('brand'));
	        if(!isset($productMasterArr[$product_Id]))
	        	{
					$productMasterArr[$product_Id] = $productArray;
				}
			$i++;
		}

		/* save category cache */
		$returnArr = array('categoryWidget'=>$productArray,'more_from_category'=>$more_from_category,'more_from_category_url'=>$more_from_category_url);
		// echo "<pre>";print_r($returnArr);
		// print_r($returnArr);
		$CacheInterface->save(json_encode($returnArr),$categoryWidgetKey,array(),889000);
		/* ---- end ---*/
		$data = $CacheInterface->load($categoryWidgetKey);

	}

	$k++;
// die;
}

?>