<?php
namespace Gt\DomTemplate;

use Gt\Dom\Document;
use Gt\Dom\Element;
use Gt\Dom\ElementType;
use Traversable;

/**
 * @phpstan-type TableRow array<int, string>
 * @phpstan-type DoubleHeaderRow array<string, array<int, string>>
 * @phpstan-type NormalisedTableData array<int, TableRow|DoubleHeaderRow>
 * @phpstan-type BindTableDataInput array<int, TableRow|array<int|string, string|array<int, mixed>>>
 * @phpstan-type IndexedTableIterable iterable<int, iterable<int, string>|iterable<string, string>>
 * @phpstan-type HeaderValueIterable iterable<string, iterable<int, string>>
 * @phpstan-type BindTableIterable IndexedTableIterable|HeaderValueIterable
 */
class TableBinder {
	private ListBinder $listBinder;
	private ListElementCollection $templateCollection;
	private ElementBinder $elementBinder;
	private HTMLAttributeBinder $htmlAttributeBinder;
	private HTMLAttributeCollection $htmlAttributeCollection;
	private PlaceholderBinder $placeholderBinder;

	public function setDependencies(
		ListBinder $listBinder,
		ListElementCollection $listElementCollection,
		ElementBinder $elementBinder,
		HTMLAttributeBinder $htmlAttributeBinder,
		HTMLAttributeCollection $htmlAttributeCollection,
		PlaceholderBinder $placeholderBinder,
	):void {
		$this->listBinder = $listBinder;
		$this->templateCollection = $listElementCollection;
		$this->elementBinder = $elementBinder;
		$this->htmlAttributeBinder = $htmlAttributeBinder;
		$this->htmlAttributeCollection = $htmlAttributeCollection;
		$this->placeholderBinder = $placeholderBinder;
	}

	/**
	 * @param BindTableDataInput $tableData
	 * @param Element $context
	 */
	public function bindTableData(
		array $tableData,
		Document|Element $context,
		?string $bindKey = null
	):void {
		$tableData = $this->normaliseTableData($tableData);
		$this->initBinders($context);

		if($context instanceof Document) {
			$context = $context->documentElement;
		}

		$tableArray = $this->findMatchingTables($context, $bindKey);
		if(empty($tableArray)) {
			throw new TableElementNotFoundInContextException();
		}

		$headerRow = array_shift($tableData);
		foreach($tableArray as $table) {
			foreach($tableData as $rowData) {
				$this->bindRowData($table, $context, $headerRow, $rowData, $tableData);
			}
		}
	}

	/** @return array<int, Element> */
	private function findMatchingTables(
		Element $context,
		?string $bindKey,
	):array {
		$tableArray = $this->collectTables($context);
		return array_values(
			array_filter(
				$tableArray,
				fn(Element $table):bool => $this->tableMatchesBindKey($table, $bindKey),
			)
		);
	}

	/** @return array<int, Element> */
	private function collectTables(Element $context):array {
		if($context->elementType === ElementType::HTMLTableElement) {
			return [$context];
		}

		$tableArray = [];
		foreach($context->querySelectorAll("table") as $table) {
			$tableArray[] = $table;
		}

		return $tableArray;
	}

	private function tableMatchesBindKey(Element $table, ?string $bindKey):bool {
		$dataBindTableElement = $table;
		if(!$dataBindTableElement->hasAttribute("data-bind:table")) {
			$dataBindTableElement = $this->findTableBindingAncestor($table) ?? $table;
		}

		return $dataBindTableElement->getAttribute("data-bind:table") == $bindKey;
	}

	private function findTableBindingAncestor(Element $element):?Element {
		$parent = $element->parentElement;
		while($parent) {
			if($parent->hasAttribute("data-bind:table")) {
				return $parent;
			}

			$parent = $parent->parentElement;
		}

		return null;
	}

	/**
	 * @param array<int, string> $headerRow
	 * @param NormalisedTableData $tableData
	 * @param TableRow|DoubleHeaderRow $rowData
	 */
	private function bindRowData(
		Element $table,
		Element $context,
		array $headerRow,
		array $rowData,
		array $tableData,
	):void {
		$allowedHeaders = $this->resolveAllowedHeaders($table, $headerRow);
		$tableBody = $table->tBodies[0] ?? $table->createTBody();
		$tableRow = $this->createTableRow($context, $tableBody);
		$this->populateTableCells($tableRow, $headerRow, $allowedHeaders, $rowData);
		$this->bindRowValues($tableRow, $headerRow, $rowData, $tableData);
	}

	/**
	 * @param array<int, string> $headerRow
	 * @return array<int, string>
	 */
	private function resolveAllowedHeaders(Element $table, array $headerRow):array {
		$tableHead = $table->tHead;
		if($tableHead) {
			return $this->readExistingHeaders($tableHead);
		}

		$tableHead = $table->createTHead();
		$tableHeadRow = $tableHead->insertRow();
		foreach($headerRow as $headerValue) {
			$headerCell = $tableHeadRow->insertCell();
			$headerCell->textContent = $headerValue;
		}

		return $headerRow;
	}

	/** @return array<int, string> */
	private function readExistingHeaders(Element $tableHead):array {
		$allowedHeaders = [];
		$tableHeadRow = $tableHead->rows[0];
		foreach($tableHeadRow->cells as $cell) {
			$allowedHeaders[] = $cell->hasAttribute("data-table-key")
				? $cell->getAttribute("data-table-key")
				: trim($cell->textContent);
		}

		return $allowedHeaders;
	}

	private function createTableRow(Element $context, Element $tableBody):Element {
		$templateCollection = $this->templateCollection
			?? new ListElementCollection($context->ownerDocument);

		try {
			$tableRowTemplate = $templateCollection->get($tableBody);
			return $tableRowTemplate->insertListItem();
		}
		catch(ListElementNotFoundInContextException) {
			return $tableBody->insertRow();
		}
	}

	/**
	 * @param array<int, string> $headerRow
	 * @param array<int, string> $allowedHeaders
	 * @param TableRow|DoubleHeaderRow $rowData
	 */
	private function populateTableCells(
		Element $tableRow,
		array $headerRow,
		array $allowedHeaders,
		array $rowData,
	):void {
		foreach($allowedHeaders as $headerIndex => $allowedHeader) {
			$rowIndex = array_search($allowedHeader, $headerRow);
			[$columnValue, $cellType] = $this->resolveColumnValue($rowData, $rowIndex);
			if($rowIndex === false && $columnValue === null) {
				continue;
			}

			$cellElement = $this->resolveCellElement(
				$tableRow,
				$headerIndex,
				$cellType,
			);
			$cellElement->textContent = $columnValue ?? "";
			if(!$cellElement->parentElement) {
				$tableRow->appendChild($cellElement);
			}
		}
	}

	/**
	 * @param TableRow|DoubleHeaderRow $rowData
	 * @return array{0:?string,1:string}
	 */
	private function resolveColumnValue(array $rowData, int|false $rowIndex):array {
		$firstKey = key($rowData);
		if(is_string($firstKey)) {
			if($rowIndex === 0) {
				return [$firstKey, "th"];
			}

			return [$rowData[$firstKey][$rowIndex - 1] ?? "", "td"];
		}

		if($rowIndex === false) {
			return [null, "td"];
		}

		return [$rowData[$rowIndex] ?? "", "td"];
	}

	private function resolveCellElement(
		Element $tableRow,
		int $headerIndex,
		string $cellType,
	):Element {
		if($headerIndex < $tableRow->cells->length - 1) {
			return $tableRow->cells[$headerIndex];
		}

		return $tableRow->ownerDocument->createElement($cellType);
	}

	/**
	 * @param array<int, string> $headerRow
	 * @param array<int, string>|array<string, array<int, string>> $rowData
	 * @param array<int, array<int, string>>|array<int, array<string,array<int, string>>> $tableData
	 */
	private function bindRowValues(
		Element $tableRow,
		array $headerRow,
		array $rowData,
		array $tableData,
	):void {
		foreach($rowData as $index => $value) {
			$headerRowIndex = $this->resolveHeaderRowIndex($index, $tableData);
			$key = $headerRow[$headerRowIndex];
			$this->elementBinder->bind($key, $value, $tableRow);
		}
	}

	/**
	 * @param array<int, array<int, string>>|array<int, array<string,array<int, string>>> $tableData
	 */
	private function resolveHeaderRowIndex(
		int|string $index,
		array $tableData,
	):int {
		if(is_int($index)) {
			return $index;
		}

		foreach($tableData as $tableDataIndex => $tableDatum) {
			if($index === key($tableDatum)) {
				return $tableDataIndex;
			}
		}

		return 0;
	}

	/** @param array<int, array<int,string>> | array<int, array<string, string>> | array<string, array<int, string>> | array<int, array<int, string>> | array<string, string> $array */
	public function detectTableDataStructureType(array $array):TableDataStructureType {
		if(empty($array)) {
			return TableDataStructureType::NORMALISED;
		}

		if(array_is_list($array)) {
			return $this->detectListTableStructureType($array);
		}

		if($this->allNamedRowsContainLists($array)) {
			return TableDataStructureType::HEADER_VALUE_LIST;
		}

		throw new IncorrectTableDataFormat();
	}

	/** @param array<int, array<int,string>|array<string, string>> $array */
	private function detectListTableStructureType(array $array):TableDataStructureType {
		$allRowsAreLists = true;
		$allRowDataAreLists = true;
		$allRowDataAreAssoc = true;

		foreach($array as $rowIndex => $rowData) {
			$this->assertRowIsArray($rowIndex, $rowData);
			if(array_is_list($rowData)) {
				$allRowDataAreAssoc = false;
			}
			else {
				$allRowsAreLists = false;
			}

			if(!$this->rowContainsOnlyListData($array, $rowIndex, $rowData)) {
				$allRowDataAreLists = false;
			}
		}

		if($allRowsAreLists) {
			return TableDataStructureType::NORMALISED;
		}

		if($allRowDataAreLists) {
			return TableDataStructureType::DOUBLE_HEADER;
		}

		if($allRowDataAreAssoc) {
			return TableDataStructureType::ASSOC_ROW;
		}

		throw new IncorrectTableDataFormat();
	}

	private function assertRowIsArray(int|string $rowIndex, mixed $rowData):void {
		if(is_array($rowData)) {
			return;
		}

		throw new IncorrectTableDataFormat("Row $rowIndex data is not iterable");
	}

	/**
	 * @param array<int, array<int|string, mixed>> $tableData
	 * @param array<int|string, mixed> $rowData
	 */
	private function rowContainsOnlyListData(
		array $tableData,
		int $rowIndex,
		array $rowData,
	):bool {
		if($rowIndex === 0) {
			return true;
		}

		foreach($rowData as $cellIndex => $cellData) {
			if($this->requiresIterableCellData($tableData, $rowData)
			&& !is_iterable($cellData)) {
				throw new IncorrectTableDataFormat(
					"Row $rowIndex has a string key ($cellIndex) but the "
					. "value is not iterable."
				);
			}

			if(!is_array($cellData) || !array_is_list($cellData)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<int, array<int|string, mixed>> $tableData
	 * @param array<int|string, mixed> $rowData
	 */
	private function requiresIterableCellData(array $tableData, array $rowData):bool {
		return !empty($tableData[0])
			&& array_is_list($tableData[0])
			&& !array_is_list($rowData);
	}

	/** @param array<string, mixed> $array */
	private function allNamedRowsContainLists(array $array):bool {
		foreach($array as $rowIndex => $rowData) {
			if(!is_array($rowData)) {
				throw new IncorrectTableDataFormat(
					"Column data \"$rowIndex\" is not iterable."
				);
			}

			if(!array_is_list($rowData)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param BindTableIterable $bindValue
		 * The structures allowed by this method are:
		 *
		 * 1) iterable<int, iterable<int,string>> If $bindValue has keys of type
	 * int, and the value of index 0 is an iterable of strings, then the
	 * value of index 0 must represent the columnHeaders; subsequent values
	 * must represent the columnValues.
	 * 2) iterable<int, iterable<int,string>|iterable<string,string>>
	 * Similar to structure 1, but with a key difference. If the value of
	 * index 0 is an iterable of strings, BUT the next value is an iterable
	 * with keys of type string, this represents "double header" data - the
	 * returned normalised value retains this double header data so the
	 * binder can insert <th> elements in the <tbody>.
	 * 3) iterable<int, iterable<string,string>> If $bindValue has keys of
	 * type int, and the value of index 0 is associative, then the value of
	 * each index must represent the individual rows, where the
	 * columnHeaders are the string key of the inner iterable, and the
	 * columnValues are the string value of the inner iterable.
	 * 4) iterable<string,iterable<int,string>> If $bindValue has keys of
	 * type string, the keys must represent the columnHeaders and the values
	 * must represent the columnValues.
	 *
	 * @return NormalisedTableData A two-dimensional array where the outer
	 * array represents the rows, the inner array represents the columns.
	 * The first index's value is always the columnHeaders. The other index's
	 * values are always the columnValues. Typically, columnValues will be
	 * `array<int,string>` apart from when the data represents double-header
	 * tables, in which case the columnValues will be within
	 * `array<string,array<int,string>>`.
	 */
	private function normaliseTableData(iterable $bindValue):array {
		if($bindValue instanceof Traversable) {
			$bindValue = iterator_to_array($bindValue);
		}

		$structureType = $this->detectTableDataStructureType($bindValue);
		return match($structureType) {
			TableDataStructureType::NORMALISED => $bindValue,
			TableDataStructureType::ASSOC_ROW => $this->normalizeAssocRows($bindValue),
			TableDataStructureType::HEADER_VALUE_LIST => $this->normalizeHeaderValueList($bindValue),
			TableDataStructureType::DOUBLE_HEADER => $this->normalizeDoubleHeader($bindValue),
		};
	}

	/**
	 * @param array<int, array<string, string>> $bindValue
	 * @return array<int, array<int, string>>
	 */
	private function normalizeAssocRows(array $bindValue):array {
		$normalised = [];
		$headers = [];
		foreach($bindValue as $row) {
			if(empty($headers)) {
				$headers = array_keys($row);
				$normalised[] = $headers;
			}

			$normalisedRow = [];
			foreach($headers as $header) {
				$normalisedRow[] = $row[$header];
			}
			$normalised[] = $normalisedRow;
		}

		return $normalised;
	}

	/**
	 * @param array<string, array<int, string>> $bindValue
	 * @return array<int, array<int, string>>
	 */
	private function normalizeHeaderValueList(array $bindValue):array {
		$normalised = [];
		$headers = array_keys($bindValue);
		$normalised[] = $headers;
		$firstHeader = $headers[0];

		foreach(array_keys($bindValue[$firstHeader]) as $rowIndex) {
			$row = [];
			foreach($headers as $header) {
				$row[] = $bindValue[$header][$rowIndex];
			}
			$normalised[] = $row;
		}

		return $normalised;
	}

	/**
	 * @param array<int, array<int, string>|array<string, array<int, string>>> $bindValue
	 * @return array<int, array<int, string>|array<string, array<int, string>>>
	 */
	private function normalizeDoubleHeader(array $bindValue):array {
		$headers = $bindValue[0];
		$rows = [];
		foreach($bindValue[1] ?? [] as $headerValue => $bindValueRow) {
			$rows[] = [$headerValue => $bindValueRow];
		}

		return [
			$headers,
			...$rows,
		];
	}

	private function initBinders(Document|Element $context):void {
		$document = $context instanceof Document
			? $context
			: $context->ownerDocument;

		if(!isset($this->htmlAttributeBinder)) {
			$this->htmlAttributeBinder = new HTMLAttributeBinder();
		}
		if(!isset($this->htmlAttributeCollection)) {
			$this->htmlAttributeCollection = new HTMLAttributeCollection();
		}
		if(!isset($this->placeholderBinder)) {
			$this->placeholderBinder = new PlaceholderBinder();
		}
		if(!isset($this->elementBinder)) {
			$this->elementBinder = new ElementBinder();
		}
		if(!isset($this->templateCollection)) {
			$this->templateCollection = new ListElementCollection($document);
		}
		if(!isset($this->listBinder)) {
			$this->listBinder = new ListBinder();
		}

		$this->htmlAttributeBinder->setDependencies($this->listBinder, $this);
		$this->elementBinder->setDependencies(
			$this->htmlAttributeBinder,
			$this->htmlAttributeCollection,
			$this->placeholderBinder,
		);
		$this->listBinder->setDependencies(
			$this->elementBinder,
			$this->templateCollection,
			new BindableCache(),
			$this,
		);
	}
}
