<?php
namespace GT\DomTemplate;

use Closure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty as RefProperty;
use ReflectionProperty;
use stdClass;
use Stringable;

class BindableCache {
	/**
	 * @var array<string, array<string, callable>> Outer array key is the
	 * fully-qualified class name, inner array key is the bind key, callable
	 * is the method that returns the bind value.
	 */
	private array $bindableClassMap;
	/**
	 * @var array<string, bool> A cache of class names that are known to
	 * NOT be bindable (to avoid having to check with reflection each time).
	 */
	private array $nonBindableClassMap;

	public function __construct() {
		$this->bindableClassMap = [];
		$this->nonBindableClassMap = [];
	}

	public function isBindable(object $object):bool {
		$className = $this->getClassName($object);
		if(isset($this->bindableClassMap[$className])) {
			return true;
		}

		if(isset($this->nonBindableClassMap[$className])) {
			return false;
		}

		$reflectionClass = $this->getReflectionClass($object);
		[$attributeCache, $objectKeys] = $this->buildClassMap($reflectionClass);
		if(empty($attributeCache)) {
			$this->nonBindableClassMap[$className] = true;
			return false;
		}

		$this->bindableClassMap[$className] = $this->expandObjects(
			$attributeCache,
			$objectKeys,
			$className,
		);
		return true;
	}

	private function getClassName(object $object):string {
		if($object instanceof ReflectionClass) {
			return $object->getName();
		}

		return $object::class;
	}

	/** @return ReflectionClass<object> */
	private function getReflectionClass(object $object):ReflectionClass {
		if($object instanceof ReflectionClass) {
			return $object;
		}

		return new ReflectionObject($object);
	}

	/**
	 * @param ReflectionClass<object> $reflectionClass
	 * @return array{
	 *  0: array<string, Closure>,
	 *  1: array<string, class-string>
	 * }
	 */
	private function buildClassMap(ReflectionClass $reflectionClass):array {
		$attributeCache = [];
		$objectKeys = [];

		foreach($reflectionClass->getMethods() as $reflectionMethod) {
			$this->addMethodBindings(
				$attributeCache,
				$objectKeys,
				$reflectionMethod,
			);
		}

		foreach($reflectionClass->getProperties() as $reflectionProperty) {
			$this->addPropertyBindings(
				$attributeCache,
				$objectKeys,
				$reflectionProperty,
			);
		}

		return [$attributeCache, $objectKeys];
	}

	/**
	 * @param array<string, Closure> $attributeCache
	 * @param array<string, class-string> $objectKeys
	 */
	private function addMethodBindings(
		array &$attributeCache,
		array &$objectKeys,
		ReflectionMethod $reflectionMethod,
	):void {
		$returnType = $reflectionMethod->getReturnType();
		if(!$returnType instanceof ReflectionNamedType) {
			return;
		}

		$methodName = $reflectionMethod->getName();
		foreach($this->getBindAttributes($reflectionMethod) as $reflectionAttribute) {
			$bindKey = $this->getBindKey($reflectionAttribute, $reflectionMethod);
			$attributeCache[$bindKey] = fn(object $object):null|iterable|string
				=> $this->nullableStringOrIterable($object, $methodName);
			$this->cacheNestedClassName(
				$objectKeys,
				$bindKey,
				$returnType->getName(),
			);
		}
	}

	/**
	 * @param array<string, Closure> $attributeCache
	 * @param array<string, class-string> $objectKeys
	 */
	private function addPropertyBindings(
		array &$attributeCache,
		array &$objectKeys,
		ReflectionProperty $reflectionProperty,
	):void {
		$propertyName = $reflectionProperty->getName();
		$attributeList = $this->getBindAttributes($reflectionProperty);

		if(!empty($attributeList)) {
			foreach($attributeList as $reflectionAttribute) {
				$bindKey = $this->getBindKey($reflectionAttribute);
				$attributeCache[$bindKey] = fn(object $object):null|iterable|string
					=> $this->nullableStringOrIterable($object, $propertyName);
				$this->cacheNestedClassName(
					$objectKeys,
					$bindKey,
					$this->getNamedTypeName($reflectionProperty),
				);
			}

			return;
		}

		if(!$reflectionProperty->isPublic()) {
			return;
		}

		$attributeCache[$propertyName] = fn(object $object):null|iterable|string
			=> isset($object->$propertyName)
				? $this->nullableStringOrIterable($object, $propertyName)
				: null;
		$this->cacheNestedClassName(
			$objectKeys,
			$propertyName,
			$this->getNamedTypeName($reflectionProperty),
		);
	}

	/**
	 * @param array<string, class-string> $objectKeys
	 */
	private function cacheNestedClassName(
		array &$objectKeys,
		string $bindKey,
		?string $typeName,
	):void {
		if($typeName && class_exists($typeName)) {
			$objectKeys[$bindKey] = $typeName;
		}
	}

	private function getNamedTypeName(
		ReflectionMethod|ReflectionProperty $reflectionTarget,
	):?string {
		$type = $reflectionTarget->getType();
		if(!$type instanceof ReflectionNamedType) {
			return null;
		}

		return $type->getName();
	}

	/**
	 * @param array<string, callable> $cache
	 * @param array<string, class-string> $objectKeys
	 * @return array<string, callable>
	 */
	private function expandObjects(
		array $cache,
		array $objectKeys,
		string $className,
	):array {
		if(empty($objectKeys)) {
			return $cache;
		}

		foreach(array_keys($cache) as $key) {
			$objectType = $objectKeys[$key] ?? null;
			if(!$objectType || !$this->shouldExpandObjectType($objectType, $className)) {
				continue;
			}

			$this->appendNestedBindableKeys($cache, $key, $objectType);
		}

		return $cache;
	}

	private function shouldExpandObjectType(
		string $objectType,
		string $className,
	):bool {
		if($objectType === $className) {
			return false;
		}

		$reflectionClass = new ReflectionClass($objectType);
		$reflectionClassName = $reflectionClass->getName();
		return isset($this->bindableClassMap[$reflectionClassName])
			|| $this->isBindable($reflectionClass);
	}

	/**
	 * @param array<string, callable> $cache
	 */
	private function appendNestedBindableKeys(
		array &$cache,
		string $key,
		string $objectType,
	):void {
		foreach($this->bindableClassMap[$objectType] as $bindableKey => $bindableClosure) {
			$cache["$key.$bindableKey"] = $bindableClosure;
		}
	}

	/**
	 * @param object|array<string, string> $object
	 * @return array<string, string>
	 */
	public function convertToKvp(object|array $object):array {
		if(is_array($object)) {
			return $object;
		}

		if($object instanceof stdClass) {
			return $this->stringifyMap(get_object_vars($object));
		}

		if(!$this->isBindable($object)) {
			return [];
		}

		return $this->convertBindableObjectToKvp($object);
	}

	/** @return array<string, null|string|array<int|string, mixed>> */
	private function convertBindableObjectToKvp(object $object):array {
		$kvp = [];
		foreach($this->bindableClassMap[$object::class] as $key => $valueGetter) {
			$targetObject = $this->resolveTargetObject($object, $key);
			$value = $targetObject
				? $valueGetter($targetObject)
				: null;
			$kvp[$key] = $this->normalizeValue($value);
		}

		return $kvp;
	}

	private function resolveTargetObject(object $object, string $key):?object {
		$targetObject = $object;
		$segments = explode(".", $key);
		$segmentCount = count($segments);

		while($segmentCount > 1 && $targetObject) {
			$propertyName = array_shift($segments);
			$targetObject = $this->readNestedObjectValue(
				$targetObject,
				$propertyName,
			);
			$segmentCount--;
		}

		return $targetObject;
	}

	private function readNestedObjectValue(
		object $object,
		string $propertyName,
	):mixed {
		if(property_exists($object, $propertyName)) {
			$reflectionProperty = new RefProperty($object, $propertyName);
			if(!$reflectionProperty->isPublic()) {
				return null;
			}

			if(!$reflectionProperty->isInitialized($object)) {
				return null;
			}

			return $object->$propertyName;
		}

		$getterName = "get" . ucfirst($propertyName);
		if(method_exists($object, $getterName)) {
			return $object->$getterName();
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $valueMap
	 * @return array<string, null|string|array<int|string, mixed>>
	 */
	private function stringifyMap(array $valueMap):array {
		$kvp = [];
		foreach($valueMap as $key => $value) {
			$kvp[$key] = $this->normalizeValue($value);
		}

		return $kvp;
	}

	/** @return null|string|array<int|string, mixed> */
	private function normalizeValue(mixed $value):null|array|string {
		if(is_null($value)) {
			return null;
		}

		if(is_iterable($value)) {
			return $value;
		}

		return (string)$value;
	}

	/** @return array<ReflectionAttribute<Bind|BindGetter>> */
	private function getBindAttributes(ReflectionMethod|ReflectionProperty $ref):array {
		return array_filter(
			$ref->getAttributes(),
			fn(ReflectionAttribute $refAttr) => $refAttr->getName() === Bind::class
				|| $refAttr->getName() === BindGetter::class
		);
	}

	/** @param ReflectionAttribute<Bind|BindGetter> $refAttr */
	private function getBindKey(
		ReflectionAttribute $refAttr,
		?ReflectionMethod $refMethod = null,
	):string {
		if($refAttr->getName() === BindGetter::class && $refMethod) {
			$methodName = $refMethod->getName();
			if(!str_starts_with($methodName, "get")) {
				throw new BindGetterMethodDoesNotStartWithGetException(
					"Method $methodName has the BindGetter Attribute, "
					. "but its name doesn't start with \"get\". "
					. "For help, see https://www.php.gt/domtemplate/bindgetter"
				);
			}
			return lcfirst(
				substr($methodName, 3)
			);
		}

		return $refAttr->getArguments()[0];
	}

	/** @return null|string|array<int|string, mixed> */
	private function nullableStringOrIterable(
		object $object,
		string $keyOrMethod,
	):null|iterable|string {
		if(method_exists($object, $keyOrMethod)) {
			$value = $object->$keyOrMethod();
		}
		elseif(property_exists($object, $keyOrMethod)) {
			$value = $object->$keyOrMethod;
		}
		else {
			return null;
		}

		if(is_scalar($value)) {
			return $value;
		}
		elseif(is_iterable($value)) {
			return $value;
		}
		elseif(is_object($value)) {
			if($value instanceof Stringable || method_exists($value, "__toString")) {
				return (string)$value;
			}
		}

		return null;
	}
}
