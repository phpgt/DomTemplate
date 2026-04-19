<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\Bind;
use GT\DomTemplate\BindableCache;
use GT\DomTemplate\Binder;
use GT\DomTemplate\BindGetter;
use GT\DomTemplate\DocumentBinder;
use GT\DomTemplate\ElementBinder;
use GT\DomTemplate\HTMLAttributeBinder;
use GT\DomTemplate\HTMLAttributeCollection;
use GT\DomTemplate\ListBinder;
use GT\DomTemplate\ListElementCollection;
use GT\DomTemplate\PlaceholderBinder;
use GT\DomTemplate\TableBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding-objects#iterator-and-iteratoraggregate-objects

$html = <<<HTML
<!DOCTYPE html>
<h1>Events for <span data-bind:text="dateString">1st Jan 2000</span>:</h1>

<ol>
	<li data-list>
		<time data-bind:text="eventStartTimeText">00:00</time>
		<p data-bind:text="title">Title of event</p>
	</li>
</ol>
HTML;

function example(Binder $binder, CalendarDay $calendarDay):void {
	$binder->bindData($calendarDay);
}

class CalendarDay implements IteratorAggregate {
	private DateTime $dateTime;

	public function __construct(
		public int $year,
		public int $month,
		public int $day,
		private EventRepository $eventRepository,
	) {
		$this->dateTime = new DateTime("$year-$month-$day");
	}

	#[BindGetter]
	public function getDateString():string {
		return $this->dateTime->format("jS M Y");
	}

	/** @return Traversable<CalendarEvent> */
	public function getIterator():Traversable {
		yield from $this->eventRepository->getEventsForDate(
        		$this->year,
        		$this->month,
        		$this->day,
        	);
	}
}

class CalendarEvent {
	public function __construct(
		public string $id,
		public DateTime $eventStart,
		public string $title,
	) {}

	#[BindGetter]
	public function getEventStartTimeText():string {
		return $this->eventStart->format("H:i");
	}
}

// END OF EXAMPLE CODE

class EventRepository {
	public function getEventsForDate(int $y, int $m, int $d):array {
		return [
			new CalendarEvent(uniqid(), new DateTime(), "First event"),
			new CalendarEvent(uniqid(), new DateTime("+15 mins"), "Second event"),
			new CalendarEvent(uniqid(), new DateTime("+2 hours"), "Third event"),
		];
	}
}

$calendar = new CalendarDay(2024, 02, 14, new EventRepository());
$document = new HTMLDocument($html);
$binder = new DocumentBinder($document);
$htmlAttributeBinder = new HTMLAttributeBinder();
$tableBinder = new TableBinder();
$listBinder = new ListBinder();
$placeholderBinder = new PlaceholderBinder();
$elementBinder = new ElementBinder();
$htmlAttributeCollection = new HTMLAttributeCollection();
$elementBinder->setDependencies($htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);
$listElementCollection = new ListElementCollection($document);
$bindableCache = new BindableCache();
$listBinder->setDependencies($elementBinder, $listElementCollection, $bindableCache, $tableBinder);
$htmlAttributeBinder->setDependencies($listBinder, $tableBinder);
$binder->setDependencies(
	$elementBinder,
	$placeholderBinder,
	$tableBinder,
	$listBinder,
	$listElementCollection,
	$bindableCache,
);

example($binder, $calendar);
$binder->cleanupDocument();
echo $document;
