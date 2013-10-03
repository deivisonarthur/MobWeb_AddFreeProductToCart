<?php

class MobWeb_AddFreeProductToCart_Model_Observer
{
	/*
	 *
	 * This function is called everytime before the cart is saved
	 *
	 */
	public function salesQuoteSaveBefore( $observer )
	{
		// Get a reference to the cart
		$quote = $observer->getEvent()->getQuote();

		// Loop through the cart items to get the count of cart
		// items *after* saving (hence the isDeleted() check)
		$count = 0;
		foreach( $quote->getAllItems() AS $item ) {
			if( $item->isDeleted() ) {
				continue;
			}

			// Count only the products that are *not* the free product
			$free_product_sku = Mage::getStoreConfig( 'addfreeproducttocart/general/product_sku' );
			if( $item->getSku() !== $free_product_sku ) {
				$count += $item->getQty();
			}
		}

		// Compare the number of cart items with the min_qty defined
		// by the admin
		$addFreeProduct = ( $count >= Mage::getStoreConfig( 'addfreeproducttocart/general/min_qty' ) );

		// Update the free product in the cart. Either add or remove it
		$this->_updateFreeProductOnQuote( $quote, $addFreeProduct );
	}

	/*
	 *
	 * This function updates the cart by either removing or adding the
	 * free product
	 *
	 */
	protected function _updateFreeProductOnQuote( Mage_Sales_Model_Quote $quote, $addFreeProduct = false )
	{
		// Get the SKU of the designated free product
		$sku = Mage::getStoreConfig( 'addfreeproducttocart/general/product_sku' );

		try {
			// Loop through all the quote items, and check if the designated
			// free product is already in the cart
			foreach( $quote->getAllItems() AS $item ) {
				// If there is already a free item in the quote
				if( $this->_isFreeProduct( $item ) ) {
					// Check if it has to be removed
					if( !$addFreeProduct ) {
						// Remove it, because the quote doesn't qualify for
						// a free item anymore
						$quote->removeItem( $item->getId() );
					}

					// Return and don't add another free item
					return;
				}
			}

			// If the free product has to be added, do it
			if( $addFreeProduct ) {
				// Load the product object by the SKU
				if( $product = Mage::getModel( 'catalog/product' )->loadByAttribute( 'sku', $sku ) ) {
					if( $product->getId() ) {
						// Load the inventory data of the free product
						Mage::getModel( 'cataloginventory/stock_item' )->assignProduct( $product );

						// Add the free product to the cart
						$quote->addProduct( $product );

						// Get a reference to the quote/cart item (and not
						// the product itself)
						$quoteItem = $quote->getItemByProduct( $product );

						Util::kdie( $quoteItem );

						// Set the free item's price to 0
						//TODO: Doesn't work...
						$quoteItem->setPriceInclTax( 0 );

						// Enable free shipping for the free item
						$quoteItem->setFreeShipping( 1 );

						// Dispatch an event and let other observers know that
						// an item has been added to the cart
						Mage::dispatchEvent( 'checkout_cart_product_add_after', array( 'quote_item' => $quoteItem, 'product' => $product ) );
					}
				}
			}
		} catch( Mage_Core_Exception $e ) {
			// Display an error
			Mage::getSingleton( 'core/session' )->addError( $e->getMessage() );
		}
	}

	// This function checks if the $item is the free item that has been added
	// to the cart
	protected function _isFreeProduct( Mage_Sales_Model_Quote_Item $item )
	{
		$sku = Mage::getStoreConfig( 'addfreeproducttocart/general/product_sku' );

		// Compare the designated free item's SKU with the current $item's SKU
		// and also check if the price is set to 0
		return ( $item->getSku() === $sku );
	}
}