<?php
namespace Gt\DomTemplate\Test;

use ArrayIterator;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\BindableCache;
use Gt\DomTemplate\ElementBinder;
use Gt\DomTemplate\HTMLAttributeBinder;
use Gt\DomTemplate\HTMLAttributeCollection;
use Gt\DomTemplate\IncorrectTableDataFormat;
use Gt\DomTemplate\ListBinder;
use Gt\DomTemplate\ListElementCollection;
use Gt\DomTemplate\PlaceholderBinder;
use Gt\DomTemplate\TableBinder;
use Gt\DomTemplate\TableDataStructureType;
use Gt\DomTemplate\TableElementNotFoundInContextException;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;
use PHPUnit\Framework\TestCase;

class TableBinderTest extends TestCase {
	/**
	 * Binding table data into an empty table will create all the
	 * appropriate <thead>, <tbody>, <tr>, <th>, and <td> elements.
	 */
	public function testBindTable_emptyTable():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$table = $document->getElementById("tbl1");

		self::assertEmpty($table->innerHTML);
		$sut->bindTableData([
			["Column 1", "Column 2", "Column 3"],
			["c1 val1", "c2 val1", "c3 val1"],
			["c1 val2", "c2 val2", "c3 val2"],
			["c1 val3", "c2 val3", "c3 val3"],
		], $table, "tableData");

		self::assertSame("Column 1", $table->tHead->rows[0]->children[0]->textContent);
		self::assertSame("Column 2", $table->tHead->rows[0]->children[1]->textContent);
		self::assertSame("Column 3", $table->tHead->rows[0]->children[2]->textContent);

		$tBody = $table->tBodies[0];
		self::assertCount(3, $tBody->children);

		self::assertSame("c1 val2", $tBody->rows[1]->children[0]->textContent);
		self::assertSame("c2 val2", $tBody->rows[1]->children[1]->textContent);
		self::assertSame("c3 val2", $tBody->rows[1]->children[2]->textContent);
	}

	/**
	 * This test is almost identical to the one above. The difference is the
	 * use of the bind key, to identify which table to bind data to.
	 */
	public function testBindTable_emptyTableSpecifiedByName():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$table0 = $document->getElementById("tbl0");
		$table1 = $document->getElementById("tbl1");
		$table2 = $document->getElementById("tbl2");
		$table3 = $document->getElementById("tbl3");

		self::assertEmpty($table0->innerHTML);
		self::assertEmpty($table1->innerHTML);
		$sut->bindTableData([
			["Column 1", "Column 2", "Column 3"],
			["c1 val1", "c2 val1", "c3 val1"],
			["c1 val2", "c2 val2", "c3 val2"],
			["c1 val3", "c2 val3", "c3 val3"],
		], $document, "matchingTableBindKey");

		self::assertNotEmpty($table0->innerHTML);

		self::assertSame("Column 1", $table0->tHead->rows[0]->children[0]->textContent);
		self::assertSame("Column 2", $table0->tHead->rows[0]->children[1]->textContent);
		self::assertSame("Column 3", $table0->tHead->rows[0]->children[2]->textContent);

		$tBody = $table0->tBodies[0];
		self::assertCount(3, $tBody->children);
		self::assertEmpty($table1->tBodies);
		self::assertCount(1, $table2->tBodies[0]->rows);
		self::assertCount(1, $table3->tBodies[0]->rows);
	}

	public function testBindTable_matchesAncestorBindKey():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLE_ANCESTOR_BIND_KEY);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$sut->bindTableData([
			["Name", "Role"],
			["Cody", "Maintainer"],
		], $document, "tableData");

		$table = $document->getElementById("tbl");
		self::assertSame("Cody", $table->tBodies[0]->rows[0]->cells[0]->textContent);
		self::assertSame("Maintainer", $table->tBodies[0]->rows[0]->cells[1]->textContent);
	}

	/**
	 * Binding table data into a table that already has a <thead> element
	 * will use the existing <th> values to limit which columns are output.
	 */
	public function testBindTable_existingTHead():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$table = $document->getElementById("tbl2");

		$thead = $table->tHead;
		$originalTheadHTML = $thead->innerHTML;

		$tableData = [
// Notice that there are more columns here than in the actual HTML.
			["id", "firstName", "lastName", "username", "email"],
			[34, "Derek", "Rethans", "derek", "derek@php.net"],
			[35, "Christoph", "Becker", "cmbecker69", "cmbecker69@php.net"],
			[25, "Sara", "Golemon", "pollita", "pollita@php.net"],
		];
		$sut->bindTableData($tableData, $table);

		$tbody = $table->tBodies[0];

		self::assertSame($originalTheadHTML, $thead->innerHTML);
		self::assertCount(count($tableData), $tbody->rows);
		$row0 = $tbody->rows[0];
		$row1 = $tbody->rows[1];
		$row2 = $tbody->rows[2];
		$row3 = $tbody->rows[3];
		self::assertCount(3, $row0->cells);
		self::assertCount(3, $row1->cells);
		self::assertCount(3, $row2->cells);
		self::assertCount(3, $row3->cells);

		self::assertSame("Greg", $row0->cells[0]->textContent);
		self::assertSame("Bowler", $row0->cells[1]->textContent);
		self::assertSame("greg@php.gt", $row0->cells[2]->textContent);
		self::assertSame("Derek", $row1->cells[0]->textContent);
		self::assertSame("Rethans", $row1->cells[1]->textContent);
		self::assertSame("derek@php.net", $row1->cells[2]->textContent);
		self::assertSame("Christoph", $row2->cells[0]->textContent);
		self::assertSame("Becker", $row2->cells[1]->textContent);
		self::assertSame("cmbecker69@php.net", $row2->cells[2]->textContent);
		self::assertSame("Sara", $row3->cells[0]->textContent);
		self::assertSame("Golemon", $row3->cells[1]->textContent);
		self::assertSame("pollita@php.net", $row3->cells[2]->textContent);
	}

	public function testBindTable_dataNormalised():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$table = $document->getElementById("tbl2");

		$tableData = [
			"id" => [34, 35, 25],
			"firstName" => ["Derek", "Christoph", "Sara"],
			"lastName" => ["Rethans", "Becker", "Golemon"],
			"username" => ["derek", "cmbecker69", "pollita"],
			"email" => ["derek@php.net", "cmbecker69@php.net", "pollita@php.net"],
		];
		$sut->bindTableData(
			$tableData,
			$table
		);

		$tbody = $table->tBodies[0];
		$row0 = $tbody->rows[0];
		$row1 = $tbody->rows[1];
		$row2 = $tbody->rows[2];
		$row3 = $tbody->rows[3];

		self::assertSame("Greg", $row0->cells[0]->textContent);
		self::assertSame("Bowler", $row0->cells[1]->textContent);
		self::assertSame("greg@php.gt", $row0->cells[2]->textContent);
		self::assertSame("Derek", $row1->cells[0]->textContent);
		self::assertSame("Rethans", $row1->cells[1]->textContent);
		self::assertSame("derek@php.net", $row1->cells[2]->textContent);
		self::assertSame("Christoph", $row2->cells[0]->textContent);
		self::assertSame("Becker", $row2->cells[1]->textContent);
		self::assertSame("cmbecker69@php.net", $row2->cells[2]->textContent);
		self::assertSame("Sara", $row3->cells[0]->textContent);
		self::assertSame("Golemon", $row3->cells[1]->textContent);
		self::assertSame("pollita@php.net", $row3->cells[2]->textContent);
	}

	public function testBindTable_traversableData():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$tableData = new ArrayIterator([
			["Name", "Position"],
			["Alan Statham", "Head of Radiology"],
			["Sue White", "Staff Liason Officer"],
		]);
		$sut->bindTableData($tableData, $document->getElementById("tbl1"), "tableData");

		$table = $document->getElementById("tbl1");
		self::assertSame("Alan Statham", $table->tBodies[0]->rows[0]->cells[0]->textContent);
		self::assertSame("Staff Liason Officer", $table->tBodies[0]->rows[1]->cells[1]->textContent);
	}

	/**
	 * A "double header" is a term I use to describe tables that have
	 * header data in the first column going along the top, but also another
	 * header line as the first row. See the MDN example for why <th> might
	 * be present in the first column of a table:
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTML/Element/tbody
	 */
	public function testBindTable_doubleHeader_shouldEmitTHElementsInRows():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$table = $document->getElementById("tbl1");

		$tableData = [
			["Item", "Price", "Stock Level"],
			[
				"Washing machine" => [698_00, 24],
				"Television" => [998_00, 7],
				"Laptop" => [799_99, 60],
			]
		];
		$sut->bindTableData(
			$tableData,
			$table,
			"tableData"
		);

		$tbody = $table->tBodies[0];
		$row0 = $tbody->rows[0];
		$row1 = $tbody->rows[1];
		$row2 = $tbody->rows[2];

		foreach($row0->cells as $i => $cell) {
			if($i === 0) {
				self::assertSame("th", $cell->tagName);
				self::assertSame("Washing machine", $cell->textContent);
			}
			else {
				self::assertSame("td", $cell->tagName);
				self::assertEquals($tableData[1]["Washing machine"][$i - 1], $cell->textContent);
			}
		}

		foreach($row1->cells as $i => $cell) {
			if($i === 0) {
				self::assertSame("th", $cell->tagName);
				self::assertSame("Television", $cell->textContent);
			}
			else {
				self::assertSame("td", $cell->tagName);
				self::assertEquals($tableData[1]["Television"][$i - 1], $cell->textContent);
			}
		}

		foreach($row2->cells as $i => $cell) {
			if($i === 0) {
				self::assertSame("th", $cell->tagName);
				self::assertSame("Laptop", $cell->textContent);
			}
			else {
				self::assertSame("td", $cell->tagName);
				self::assertEquals($tableData[1]["Laptop"][$i - 1], $cell->textContent);
			}
		}
	}

	public function testBindTable_nonIterableValue():void {
		$sut = new TableBinder();
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);

		$table = $document->getElementById("tbl1");

		$tableData = [
			["Item", "Price", "Stock Level"],
			"incorrect",
			"table",
			"format",
		];
		self::expectException(IncorrectTableDataFormat::class);
		self::expectExceptionMessage("Row 1 data is not iterable");
		$sut->bindTableData(
			$tableData,
			$table
		);
	}

	public function testBindTable_doubleHeaderNonIterableValue():void {
		$sut = new TableBinder();
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);

		$table = $document->getElementById("tbl1");

		$tableData = [
			["Item", "Price", "Stock Level"],
			[
// Note that the inner array does not represent its data as an array.
				"Washing machine" => 698_00,
				"Television" => 998_00,
				"Laptop" => 799_99,
			]
		];
		self::expectException(IncorrectTableDataFormat::class);
		self::expectExceptionMessage("Row 1 has a string key (Washing machine) but the value is not iterable.");
		$sut->bindTableData($tableData, $table);
	}

	public function testBindTable_assocArrayWithoutIterableColumns():void {
		$sut = new TableBinder();
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);

		$table = $document->getElementById("tbl1");
		$tableData = [
// This is emulating a common syntax mistake - the columns are not within an
// array, but from a glance the shape looks OK.
			"Item" => ["Washing machine", "Television", "Laptop"],
			"Price" => 698_00, 998_00, 799_99,
			"Stock Level", [24, 7, 60],
		];
		self::expectException(IncorrectTableDataFormat::class);
		self::expectExceptionMessage("Column data \"Price\" is not iterable.");
		$sut->bindTableData($tableData, $table);
	}

	public function testBindTable_multipleTables():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));
		$tableData = [
			"id" => [34, 35, 25],
			"firstName" => ["Derek", "Christoph", "Sara"],
			"lastName" => ["Rethans", "Becker", "Golemon"],
			"username" => ["derek", "cmbecker69", "pollita"],
			"email" => ["derek@php.net", "cmbecker69@php.net", "pollita@php.net"],
		];

		$sut->bindTableData(
			$tableData,
			$document->getElementById("multi-table-container"),
			"tableData"
		);

		$tableList = $document->querySelectorAll("#multi-table-container table");
		self::assertCount(3, $tableList);

		foreach($tableList as $table) {
			if($table->parentElement->id === "s2") {
				continue;
			}

			$tbody = $table->tBodies[0];
			$tableDataKeys = array_keys($tableData);
			foreach($tbody->rows as $rowIndex => $row) {
				foreach($row->cells as $cellIndex => $cell) {
					$key = $tableDataKeys[$cellIndex];
					self::assertEquals(
						$tableData[$key][$rowIndex],
						$cell->textContent
					);
				}
			}
		}
	}

	public function testBindTable_keyNamesInTHead():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));
		$tableData = [
			"id" => [34, 35, 25],
			"firstName" => ["Derek", "Christoph", "Sara"],
			"lastName" => ["Rethans", "Becker", "Golemon"],
			"username" => ["derek", "cmbecker69", "pollita"],
			"email" => ["derek@php.net", "cmbecker69@php.net", "pollita@php.net"],
		];

		$table = $document->getElementById("tbl3");
		$sut->bindTableData(
			$tableData,
			$table
		);

		$tableDataKeys = [];
		foreach($table->rows as $rowIndex => $row) {
			if($rowIndex === 1) {
				self::assertEquals("Greg", $row->cells[0]->textContent);
				continue;
			}

			foreach($row->cells as $cellIndex => $cell) {
				if($rowIndex === 0) {
					array_push($tableDataKeys, $cell->textContent);
					continue;
				}

				$key = $tableDataKeys[$cellIndex];
				self::assertEquals(
// `$rowIndex - 2` because we're counting the header, and the pre-existing row.
					$tableData[$key][$rowIndex - 2],
					$cell->textContent
				);
			}
		}
	}

	public function testBindTableData_documentContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLE_NO_BIND_KEY);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));
		$tableData = [
			"id" => [34, 35, 25],
			"firstName" => ["Derek", "Christoph", "Sara"],
			"lastName" => ["Rethans", "Becker", "Golemon"],
			"username" => ["derek", "cmbecker69", "pollita"],
			"email" => ["derek@php.net", "cmbecker69@php.net", "pollita@php.net"],
		];

		$sut->bindTableData($tableData, $document);

		self::assertCount(4, $document->querySelectorAll("table tr"));
	}

	public function testBindTableData_emptyHeader():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLE_ID_NAME_CODE);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));
		$tableData = [
			["ID", "Name", "Code"],
		];
		for($i = 1; $i <= 10; $i++) {
			$name = "Thing $i";
			array_push($tableData, [$i, $name, md5($name)]);
		}

		$sut->bindTableData($tableData, $document);

		$table = $document->querySelector("table");
		$theadRow = $table->tHead->rows[0];
		self::assertCount(4, $theadRow->cells);
		self::assertSame("Delete", $theadRow->cells[3]->textContent);

		$tbody = $table->tBodies[0];
		foreach($tbody->rows as $rowIndex => $row) {
			foreach($row->cells as $cellIndex => $cell) {
				$expected = $tableData[$rowIndex + 1][$cellIndex] ?? "";
				self::assertSame((string)$expected, $cell->textContent);
			}
		}
	}

	public function testBindTableData_existingBodyRow():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLE_EXISTING_CELLS);
		$tableData = [
			["id", "code", "name", "deleted"],
		];
// 3, 6 and 9 will be marked as "Deleted".
		for($i = 1; $i <= 10; $i++) {
			$name = "Thing $i";
			array_push($tableData, [$i, md5($name), $name, $i % 3 === 0]);
		}

		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$sut->bindTableData($tableData, $document);

		$tbody = $document->querySelector("table tbody");

		$headers = array_shift($tableData);

		self::assertCount(count($tableData), $tbody->rows);

		foreach($tbody->rows as $rowIndex => $tr) {
			$rowData = array_combine($headers, $tableData[$rowIndex]);

			self::assertSame((string)$rowData["id"], $tr->cells[1]->textContent);
			self::assertSame((string)$rowData["name"], $tr->cells[2]->textContent);
			self::assertSame((string)$rowData["code"], $tr->cells[3]->textContent);

			$input = $tr->cells[0]->querySelector("input");
			self::assertSame((string)$rowData["id"], $input->value);

			$input = $tr->cells[4]->querySelector("input");
			self::assertSame((string)$rowData["id"], $input->value);

			if(($rowIndex + 1) % 3 === 0) {
				self::assertTrue($tr->cells[0]->classList->contains("deleted"));
			}
			else {
				self::assertFalse($tr->cells[0]->classList->contains("deleted"));
			}
		}
	}

	public function testBindTableData_existingBodyRow_differentDataShape():void {
		$tableData = [
			"id" => [],
			"code" => [],
			"name" => [],
			"deleted" => [],
		];

// 3, 6 and 9 will be marked as "Deleted".
		for($i = 1; $i <= 10; $i++) {
			$name = "Thing $i";
			array_push($tableData["id"], $i);
			array_push($tableData["code"], md5($name));
			array_push($tableData["name"], $name);
			array_push($tableData["deleted"], $i % 3 === 0);
		}

		$document = new HTMLDocument(HTMLPageContent::HTML_TABLE_EXISTING_CELLS);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));

		$sut->bindTableData($tableData, $document);

		$tbody = $document->querySelector("table tbody");

		self::assertCount(count($tableData["id"]), $tbody->rows);

		foreach($tbody->rows as $rowIndex => $tr) {
			$rowData = [];
			foreach(array_keys($tableData) as $key) {
				$rowData[$key] = $tableData[$key][$rowIndex];
			}

			self::assertSame((string)$rowData["id"], $tr->cells[1]->textContent);
			self::assertSame((string)$rowData["name"], $tr->cells[2]->textContent);
			self::assertSame((string)$rowData["code"], $tr->cells[3]->textContent);

			$input = $tr->cells[0]->querySelector("input");
			self::assertSame((string)$rowData["id"], $input->value);

			$input = $tr->cells[4]->querySelector("input");
			self::assertSame((string)$rowData["id"], $input->value);

			if(($rowIndex + 1) % 3 === 0) {
				self::assertTrue($tr->cells[0]->classList->contains("deleted"));
			}
			else {
				self::assertFalse($tr->cells[0]->classList->contains("deleted"));
			}
		}
	}

	/** This test is introduced for https://github.com/PhpGt/DomTemplate/issues/247 */
	public function testBindTableData_datumPerRow():void {
		$tableData = [
			[
				"ID" => 55,
				"Forename" => "Carlos",
				"Surname" => "Sainz",
				"Country" => "Spain",
			],
			[
				"ID" => 5,
				"Forename" => "Sebastian",
				"Surname" => "Vettel",
				"Country" => "Germany",
			],
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLE_CRUD);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));
		$sut->bindTableData($tableData, $document);
		$tBody = $document->querySelector("table tbody");
		self::assertCount(count($tableData), $tBody->rows);

		foreach($tableData as $i => $rowData) {
			$rowEl = $tBody->rows[$i];
			self::assertEquals($rowData["ID"], $rowEl->cells[0]->textContent);
			self::assertEquals($rowData["Forename"], $rowEl->cells[1]->textContent);
			self::assertEquals($rowData["Surname"], $rowEl->cells[2]->textContent);
			self::assertEquals($rowData["Country"], $rowEl->cells[3]->textContent);
			self::assertCount(6, $rowEl->cells);
		}
	}

	public function testDetectTableStructureType_invalid():void {
		$sut = new TableBinder();
		self::expectException(IncorrectTableDataFormat::class);
		$sut->detectTableDataStructureType(
			[
				123 => "test",
				[
					"this" => "is incorrect"
				]
			]
		);
	}

	public function testDetectTableStructureType_normalised():void {
		$data = [
			["name", "species"],
			["Greg", "Human"],
			["Sarah", "Human"],
			["Cody", "Feline"],
		];

		$sut = new TableBinder();
		self::assertSame(
			TableDataStructureType::NORMALISED,
			$sut->detectTableDataStructureType($data),
		);
	}

	public function testDetectTableStructureType_doubleHeader():void {
		$data = [
			["Item", "Price", "Stock Level"],
			[
				"Washing machine" => [698_00, 24],
				"Television" => [998_00, 7],
				"Laptop" => [799_99, 60],
			]
		];
		$sut = new TableBinder();
		self::assertSame(
			TableDataStructureType::DOUBLE_HEADER,
			$sut->detectTableDataStructureType($data),
		);
	}

	public function testDetectTableStructureType_assocRow():void {
		$data = [
			[
				"name" => "Greg",
				"species" => "Human",
			],
			[
				"name" => "Sarah",
				"species" => "Human",
			],
			[
				"name" => "Cody",
				"species" => "Feline",
			],
		];
		$sut = new TableBinder();
		self::assertSame(
			TableDataStructureType::ASSOC_ROW,
			$sut->detectTableDataStructureType($data),
		);
	}

	public function testDetectTableStructureType_headerValueList():void {
		$data = [
			"name" => ["Greg", "Sarah", "Cody"],
			"species" => ["Human", "Human", "Feline"],
		];
		$sut = new TableBinder();
		self::assertSame(
			TableDataStructureType::HEADER_VALUE_LIST,
			$sut->detectTableDataStructureType($data),
		);
	}

	public function testDetectTableStructureType_emptyIsNormalised():void {
		$sut = new TableBinder();
		self::assertSame(
			TableDataStructureType::NORMALISED,
			$sut->detectTableDataStructureType([]),
		);
	}

	public function testDetectTableStructureType_headerValueListRejectsAssocColumns():void {
		$data = [
			"name" => [
				"first" => "Greg",
				"second" => "Sarah",
			],
			"species" => [
				"first" => "Human",
				"second" => "Human",
			],
		];
		$sut = new TableBinder();

		self::expectException(IncorrectTableDataFormat::class);
		$sut->detectTableDataStructureType($data);
	}

	public function testDetectTableStructureType_listRejectsMixedRowShapes():void {
		$data = [
			["Item", "Price", "Stock Level"],
			["Washing machine", 698_00, 24],
			[
				"Television" => "99800",
				"Stock Level" => "7",
			],
		];
		$sut = new TableBinder();

		self::expectException(IncorrectTableDataFormat::class);
		$sut->detectTableDataStructureType($data);
	}

	public function testDetectTableStructureType_listRejectsMixedIterableAndScalarShapes():void {
		$data = [
			["Item", "Price"],
			["Washing machine", "69800"],
			[
				"Television" => ["99800"],
				"Stock Level" => ["7"],
			],
		];
		$sut = new TableBinder();

		self::expectException(IncorrectTableDataFormat::class);
		$sut->detectTableDataStructureType($data);
	}

	public function testBindTableData_noTableInitializesFallbackDependencies():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EMPTY);
		$sut = new TableBinder();

		self::expectException(TableElementNotFoundInContextException::class);
		$sut->bindTableData([
			["Column"],
			["Value"],
		], $document);
	}

	public function testBindTableData_initializesFallbackDependenciesWhenTableExists():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new TableBinder();
		$tableData = [
			["Name", "Position"],
			["Alan Statham", "Head of Radiology"],
			["Sue White", "Staff Liason Officer"],
		];

		$sut->bindTableData($tableData, $document, "tableData");

		$table = $document->getElementById("tbl1");
		self::assertSame("Alan Statham", $table->tBodies[0]->rows[0]->cells[0]->textContent);
		self::assertSame("Staff Liason Officer", $table->tBodies[0]->rows[1]->cells[1]->textContent);
	}

	public function testBindTableData_debugAddsMetadataToGeneratedCells():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_BIND_DEBUG_GENERATED_TABLE);
		$sut = new TableBinder();
		$sut->setDependencies(...$this->tablebinderDependencies($document));
		$sut->setDebugSource("app/Controller.php:10");

		$sut->bindTableData([
			["Name", "Role"],
			["Cody", "Maintainer"],
		], $document, "tableData");

		$headerCell = $document->querySelector("thead td");
		$bodyCell = $document->querySelector("tbody td");
		self::assertSame("text=app/Controller.php:10", $headerCell->getAttribute("data-bind-debug"));
		self::assertSame("text=app/Controller.php:10", $bodyCell->getAttribute("data-bind-debug"));
	}

	private function tablebinderDependencies(HTMLDocument $document):array {
		$htmlAttributeBinder = new HTMLAttributeBinder();
		$htmlAttributeCollection = new HTMLAttributeCollection();
		$placeholderBinder = new PlaceholderBinder();
		$elementBinder = new ElementBinder();
		$listElementCollection = new ListElementCollection($document);
		$bindableCache = new BindableCache();
		$listBinder = new ListBinder();
		$tableBinder = new TableBinder();

		$htmlAttributeBinder->setDependencies($listBinder, $tableBinder);
		$elementBinder->setDependencies($htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);
		$listBinder->setDependencies($elementBinder, $listElementCollection, $bindableCache, $tableBinder);
		$tableBinder->setDependencies($listBinder, $listElementCollection, $elementBinder, $htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);

		return [
			$listBinder,
			$listElementCollection,
			$elementBinder,
			$htmlAttributeBinder,
			$htmlAttributeCollection,
			$placeholderBinder,
		];
	}

}
