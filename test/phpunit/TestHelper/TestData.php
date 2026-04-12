<?php
namespace GT\DomTemplate\Test\TestHelper;

use GT\DomTemplate\Test\TestHelper\Model\Address;
use GT\DomTemplate\Test\TestHelper\Model\Country;
use GT\DomTemplate\Test\TestHelper\Model\Currency;
use GT\DomTemplate\Test\TestHelper\Model\Customer;
use GT\DomTemplate\Test\TestHelper\Model\Money;
use GT\DomTemplate\Test\TestHelper\Model\Order;
use GT\DomTemplate\Test\TestHelper\Model\ShopItem;

class TestData {
	const MUSIC = [
		"A Band From Your Childhood" => [
			"This Album is Good" => [
				"The Best Song You‘ve Ever Heard",
				"Another Cracking Tune",
				"Top Notch Music Here",
				"The Best Is Left ‘Til Last",
			],
			"Adequate Collection" => [
				"Meh",
				"‘sok",
				"Sounds Like Every Other Song",
			],
		],
		"Bongo and The Bronks" => [
			"Salad" => [
				"Tomatoes",
				"Song About Cucumber",
				"Onions Make Me Cry (but I love them)",
			],
			"Meat" => [
				"Steak",
				"Is Chicken Really a Meat?",
				"Don‘t Look in the Sausage Factory",
				"Stop Horsing Around",
			],
			"SnaxX" => [
				"Crispy Potatoes With Salt",
				"Pretzel Song",
				"Pork Scratchings Are Skin",
				"The Peanut Is Not Actually A Nut",
			],
		],
		"Crayons" => [
			"Pastel Colours" => [
				"Egg Shell",
				"Cotton",
				"Frost",
				"Periwinkle",
			],
			"Different Shades of Blue" => [
				"Cobalt",
				"Slate",
				"Indigo",
				"Teal",
			],
		]
	];

	const STUDENTS = [
		[
			"firstName" => "Freddie",
			"lastName" => "Williams",
			"modules" => [
				"Programming 1", "Networking Fundamentals", "Computational Logic"
			]
		],
		[
			"firstName" => "Melissa",
			"lastName" => "Adams",
			"modules" => [
				"Computational Mathematics", "Networks and Security", "Graphics I"
			]
		],
		[
			"firstName" => "Sofia",
			"lastName" => "Reid",
			"modules" => [
				"Databases", "Programming 2", "Networking Fundamentals"
			]
		],
	];

	const TODO_DATA = [
		[
			"id" => 100,
			"title" => "Create DOM facade",
			"completedAt" => "2021-05-18 10:03:57",
		], [
			"id" => 101,
			"title" => "Bind data to the DOM",
			"completedAt" => "2021-05-18 16:32:20",
		], [
			"id" => 102,
			"title" => "Bundle into WebEngine",
			"completedAt" => null,
		], [
			"id" => 103,
			"title" => "Release WebEngine v4",
			"completedAt" => null,
		]
	];

	/** @return array<Customer> */
	public static function getCustomerOrderOverview1():array {
		$customer1 = new Customer(
			1001,
			"James Hendler",
			new Address(
				"23 Concord Dr",
				"Middletown",
				"Rhode Island",
				"02842",
				new Country("US"),
			),
		);
		$customer2 = new Customer(
			2002,
			"Annie Easley",
			new Address(
				"63rd Hwy",
				"Calera",
				"Alabama",
				"35040",
				new Country("US")
			),
		);

		$customer1->addOrder(new Order(
			500_001_001,
			new Money(55.50, Currency::USD),
			$customer1->address,
			[
				new ShopItem(239, "Maryland Flag", "Vexillologist's nightmare", new Money(79.99, Currency::USD)),
				new ShopItem(814, "NeXTcube", "High-end workstation computer", new Money(7_995.00, Currency::USD)),
			]
		));
		$customer1->addOrder(new Order(
			500_001_002,
			new Money(7.50, Currency::USD),
			$customer1->address,
			[
				new ShopItem(330, "Getting started with DAML", "Everything you need to know about DARPA's Agent Markup Language", new Money(20.00, Currency::USD)),
			]
		));

		$customer2->addOrder(new Order(
			500_002_001,
			new Money(8.00, Currency::USD),
			$customer2->address,
			[
				new ShopItem(241, "New Orleans Flag", "A simple and traditional gem", new Money(79.99, Currency::USD)),
			]
		));
		$customer2->addOrder(new Order(
			500_002_002,
			new Money(5.00, Currency::USD),
			$customer2->address,
			[
				new ShopItem(190, "NASA t-shirt, ladies M", "Original design used by engineers in the 1960s", new Money(25.00, Currency::USD)),
				new ShopItem(921, "Bottle of fresh O-Zone", "Taken from your local stratosphere", new Money(12.50, Currency::USD)),
				new ShopItem(800, "Science and Engineering Newsletter", "Backprint of issue 48", new Money(15.00, Currency::USD)),
			]
		));

		return [
			$customer1,
			$customer2,
		];
	}
}
