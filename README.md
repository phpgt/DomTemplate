# Bind dynamic data to reusable HTML components.

Built on top of [PHP.Gt/Dom][dom], this project provides dynamic data binding to DOM Documents, document templating and reusable HTML components.

Directly manipulating the DOM in your code can lead to tightly coupling the logic and view. Binding data using custom elements and data attributes leads to highly readable, maintainable view files that are loosely coupled to the application logic.  

***

<a href="https://github.com/PhpGt/DomTemplate/actions" target="_blank">
	<img src="https://badge.status.php.gt/domtemplate-build.svg" alt="Build status" />
</a>
<a href="https://app.codacy.com/gh/PhpGt/DomTemplate" target="_blank">
	<img src="https://badge.status.php.gt/domtemplate-quality.svg" alt="Code quality" />
</a>
<a href="https://scrutinizer-ci.com/g/PhpGt/DomTemplate" target="_blank">
	<img src="https://badge.status.php.gt/domtemplate-coverage.svg" alt="Code coverage" />
</a>
<a href="https://packagist.org/packages/PhpGt/DomTemplate" target="_blank">
	<img src="https://badge.status.php.gt/domtemplate-version.svg" alt="Current version" />
</a>
<a href="http://www.php.gt/domtemplate" target="_blank">
	<img src="https://badge.status.php.gt/domtemplate-docs.svg" alt="PHP.G/DomTemplate documentation" />
</a>

## Example usage: Hello, you!

Consider a page with a form, with an input element to enter your name. When the form is submitted, the page should greet you by your name.

Without submitting the form, the HTML will be rendered untouched, showing the default message "Hello, you!".

### Source HTML (`name.html`)

The HTML below shows a basic "Hello, you" message, but due to the `data-bind:text` attribute, when the form is submitted, the PHP code binds the submitted name to the DOM at the correct place, greeting the user.

`greeter.html`:

```html
<!doctype html>
<h1>
	Hello, <span data-bind:text="name-output">you</span>!
</h1>

<form>
	<input name="name" placeholder="Your name, please" required />
	<button>Submit</button>
</form>
```

### PHP used to inject your name

```php
<?php
$html = file_get_contents("greeter.html");
$document = new \Gt\Dom\HTMLDocument($html);

if($name = $_GET["name"]) {
	$binder = new \Gt\DomTemplate\DocumentBinder($document);
	$binder->bindKeyValue("name-output", $name);
}

echo $document;
```

## Example usage: Shopping list

In the following example, the template of the HTML is defined, with a `data-template` attribute to indicate that the LI should be repeated for every item in the data, and a `data-bind:text` attribute to set the textContent of the LI to the value of each item in the data.

Note: when binding an array of strings (or `Stringable` objects), there is no need to specify a bind key for the `data-bind:text` attribute, however in the next example you will see how more complex data structures can be used to bind data at different locations within the template element.

`shopping-list.html`:

```html
<!doctype html>
<h1>Shopping list</h1>
<ul>
	<li data-template data-bind:text>Item</li>
</ul>
```

### PHP used to inject a shopping list

```php
$html = file_get_contents("shopping-list.html");
$document = new \Gt\Dom\HTMLDocument($html);

$shoppingList = [
	"Pasta",
	"Rice",
	"Butter",
	"Eggs",
	"Vegetables",
];

$binder = new \Gt\DomTemplate\DocumentBinder($document);
$binder->bindList($shoppingList);
// this removes the data-bind and data-template attributes:
$binder->cleanupDocument(); 
echo $document;
```

### Output

```html
<!doctype html>
<h1>Shopping list</h1>
<ul>
	<li>Pasta</li>
	<li>Rice</li>
	<li>Butter</li>
	<li>Eggs</li>
	<li>Vegetables</li>
</ul>
```

## Advanced usage: bind database results directly to the page

In the following example, the `data-template` attribute is used to repeat the LI for every result in the dataset. This dataset represents data that would typically come from a database query or API, but for this example we're simply hard-coding the data.

Within the LI, you can see where the various fields will be bound:

+ The surrounding anchor element has its href attribute injected with the user's ID.
+ The IMG src and alt attributes have their values injected with the username.
+ The IMG alt attribute has the username injected.
+ The H2 and H3 elements have their textContent set to the username and type field.

Note that the actual structure of the HTML can be changed at any time, and it's just the `data-bind` and use of curly braces that define where the data is bound to.

`user-list.html`:
```html
<!doctype html>
<h1>Users</h1>
<ul>
	<li data-template>
		<a href="/user/settings/{{id}}">
			<img src="/img/user/{{username}}.jpg" 
				alt="Profile image for {{username}}" />
			<h2 data-bind:text="username">username</h2>
			<h3 data-bind:text="type">user type</h3>
		</a>
	</li>
</ul>
```

### PHP used to inject the list

```php
// The $data could be from a database, or any other source.
// For now, we're just hard-coding the data, so we can see its structure.
$data = [
	[
		"id" => 123,
		"username" => "codyboy",
		"type" => "user",
	],
	[
		"id" => 456,
		"username" => "scarlett",
		"type" => "user",
	],
	[
		"id" => 789,
		"username" => "greg",
		"type" => "owner",
	],
];

$html = file_get_contents("user-list.html");
$document = new \Gt\Dom\HTMLDocument($html);
$binder = new \Gt\DomTemplate\DocumentBinder($document);

$binder->bindList($data);
$binder->cleanupDocument();
echo $document;
```

### Output

```html
<!doctype html>
<h1>Users</h1>
<ul>
	<li>
		<a href="/user/settings/123">
			<img src="/img/user/codyboy.jpg" 
				alt="Profile image for codyboy" />
			<h2>codyboy</h2>
			<h3>user</h3>
		</a>
	</li>
	<li>
		<a href="/user/settings/456">
			<img src="/img/user/scarlett.jpg"
				alt="Profile image for scarlett" />
			<h2>scarlett</h2>
			<h3>user</h3>
		</a>
	</li>
	<li>
		<a href="/user/settings/789">
			<img src="/img/user/greg.jpg"
				alt="Profile image for greg" />
			<h2>greg</h2>
			<h3>owner</h3>
		</a>
	</li>
</ul>
```

Features at a glance
--------------------

+ Binding of dynamic data - bind key value pairs, associative arrays, lists of associative arrays and even nested lists.
+ HTML components - organise and reuse DOM trees by storing separate components in their own HTML files, and including them using custom HTML tags.
+ Page templates - create partial HTML documents that "extend" others.
+ Easily modularise CSS by selecting their custom tag names.

[dom]: https://www.php.gt/dom

# Proudly sponsored by

[JetBrains Open Source sponsorship program](https://www.jetbrains.com/community/opensource/)

[![JetBrains logo.](https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.svg)](https://www.jetbrains.com/community/opensource/)
