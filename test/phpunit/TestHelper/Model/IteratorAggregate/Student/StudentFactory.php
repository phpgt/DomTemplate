<?php

namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student;

class StudentFactory {
	/**
	 * @param array<int, array<string, string|array<string>>> $input
	 * @return array<Student>
	 */
	public function buildStudentArray(array $input):array {
		$studentArray = [];

		foreach($input as $inputStudent) {
			$moduleList = [];
			foreach($inputStudent["modules"] as $moduleName) {
				array_push(
					$moduleList,
					new Module($moduleName),
				);
			}

			$name = new Name($inputStudent["firstName"], $inputStudent["lastName"]);

			array_push(
				$studentArray,
				new Student(
					$name,
					$moduleList,
				),
			);
		}

		return $studentArray;
	}
}
