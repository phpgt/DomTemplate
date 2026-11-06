<?php
namespace GT\DomTemplate;

use Closure;
use ReflectionMethod;

abstract class BindFilter {
	/** @var array<class-string, object> */
	private array $dependencies = [];

	public function setDependency(object $dependency):void {
		unset($this->dependencies[$dependency::class]);
		$this->dependencies[$dependency::class] = $dependency;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T|null
	 */
	public function getDependency(string $class):?object {
		foreach(array_reverse($this->dependencies) as $dependency) {
			if($dependency instanceof $class) {
				return $dependency;
			}
		}

		return null;
	}

	public function getFilter(string $name):?Closure {
		$methodName = "filter" . ucfirst($name);
		if(!method_exists($this, $methodName)) {
			return null;
		}

		$method = new ReflectionMethod($this, $methodName);
		if(!$method->isPublic() || $method->isStatic()) {
			return null;
		}

		return $method->getClosure($this);
	}
}
