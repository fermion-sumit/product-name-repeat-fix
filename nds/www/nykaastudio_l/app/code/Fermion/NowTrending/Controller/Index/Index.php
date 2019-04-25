<?php

namespace Fermion\NowTrending\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Fermion\NowTrending\Model\ProductsFactory;

class Index extends Action {

  protected $_modelProductsFactory;

  public function __construct(
    Context $context, ProductsFactory $modelProductsFactory
  ) {
    parent::__construct($context);
    $this->_modelProductsFactory = $modelProductsFactory;
  }

  public function execute() {

    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $store = $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore();
    $imageHelper = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Catalog\Helper\Image::class);

    $return = array();
    $now1 = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('now_trending1Home/general/nowtrending_ids');

    $now2 = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('now_trending2Home/general/nowtrending_ids1');

    $placeholderImageUrl = $imageHelper->getDefaultPlaceholderUrl('image');
    $currencysymbol = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
    $currency = $currencysymbol->getStore()->getCurrentCurrencyCode();
    $currency = $objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load($currency);
    $currencySymbol = $currency->getCurrencySymbol();


    if ($now1) {
      $productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
      $collection = $productCollection->create()
      ->addAttributeToSelect('*')
      ->addAttributeToFilter('entity_id', array('in' => $now1))
      ->load();
      $html='';
      foreach ($collection as $key => $collections) {

       $optionText ='';
       $attr = $collections->getResource()->getAttribute('brand');
       if ($attr->usesSource()) {
         $optionText = $attr->getSource()->getOptionText($collections->getData('brand'));
       }

       //$pname = str_ireplace($optionText,'',$collections->getName());
       $pname=preg_replace('~'.$optionText.'~i','',$collections->getName());

       $imageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $collections->getImage();

       $html .= '<div>'.'<div class="nyProductBlock">'
       . '<div class="imageSecton">'
       . '<a target="_blank" href="' . $collections->getProductUrl() . '">';

       if ($collections->getImage() !='no_selection') {
        $html .= '<img src="'.$imageUrl.'"/>';
      }else{

       $html .= '<img src="'.$placeholderImageUrl.'"/>';
     }
     $html .= '</a>';

     if (!$collections->isSalable()) {
       $html .='<div class="outOfStock">Sold Out</div>';
     }
     $html .= '</div>'
     . '<div class="content">'
     . '<a href="'.$collections->getProductUrl().'" class="productName">'
     . strtoupper( $optionText).'</a>'
     . '<a href="'.$collections->getProductUrl().'" class="productDescription opText">'
     . ucwords($pname)
     . '</a>'
     . '<div class="productPrice">'
     . $currencySymbol . number_format($this->convertPrice($collections->getFinalPrice()))
     . '</div>'
     . '</div>'
     . '</div>'
     . '</div>';
     $return['html'] = $html;

   }

 }

 if ($now2) {
  $productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
  $collection = $productCollection->create()
  ->addAttributeToSelect('*')
  ->addAttributeToFilter('entity_id', array('in' => $now2))
  ->load();
  $html2='';
  foreach ($collection as $key => $collections) {

   $optionText ='';
   $attr = $collections->getResource()->getAttribute('brand');
   if ($attr->usesSource()) {
     $optionText = $attr->getSource()->getOptionText($collections->getData('brand'));
   }

   //$pname = str_replace($optionText,'',$collections->getName());
   $pname=preg_replace('~'.$optionText.'~i','',$collections->getName());

   $imageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $collections->getImage();

   $html2 .= '<div>'.'<div class="nyProductBlock">'
   . '<div class="imageSecton">'
   . '<a target="_blank" href="' . $collections->getProductUrl() . '">';

   if ($collections->getImage() !='no_selection') {
    $html2 .= '<img src="'.$imageUrl.'"/>';
  }else{

   $html2 .= '<img src="'.$placeholderImageUrl.'"/>';
 }

 $html2 .=
 '</a>';

 if (!$collections->isSalable()) {
   $html .='<div class="outOfStock">Sold Out</div>';
 }

 $html2 .= '</div>'
 . '<div class="content">'
 . '<a href="'.$collections->getProductUrl().'" class="productName">'
 . strtoupper( $optionText).'</a>'
 . '<a href="'.$collections->getProductUrl().'" class="productDescription opText">'
 . ucwords($pname)
 . '</a>'
 . '<div class="productPrice">'
 . $currencySymbol . number_format($this->convertPrice($collections->getFinalPrice()))
 . '</div>'
 . '</div>'
 . '</div>'
 . '</div>';
 $return['html2'] = $html2;

}
}
echo json_encode($return);exit();
}

public function convertPrice($amount = 0, $store = null, $currency = null) {
  $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
  $priceCurrencyObject = $objectManager->get('Magento\Framework\Pricing\PriceCurrencyInterface');
  $storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');

  $currency = $storeManager->getStore()->getCurrentCurrencyCode();

  if ($store == null) {
    $store = $storeManager->getStore()->getStoreId();
  }
  $rate = $storeManager->getStore()->getBaseCurrency()->getRate($currency);
  $amount = $amount * $rate;
  return ceil($amount);
}

}