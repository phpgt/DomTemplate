<?php
namespace GT\DomTemplate\Test\TestHelper\Model\ArrayIterator\Product;

use ArrayIterator;

class ProductList extends ArrayIterator {
	/**
	 * @param array<string> $categoryNameList
	 * @param array<array<string>> $productNameList
	 */
	public function __construct(array $categoryNameList, array $productNameList) {
		/** @var array<string, array<Product>> $categorisedProducts */
		$categorisedProducts = [];

		foreach($categoryNameList as $i => $categoryName) {
			$categorisedProducts[$categoryName] = [];

			foreach($productNameList[$i] as $productName) {
				array_push($categorisedProducts[$categoryName], new Product($productName));
			}
		}

		parent::__construct($categorisedProducts);
	}
}
