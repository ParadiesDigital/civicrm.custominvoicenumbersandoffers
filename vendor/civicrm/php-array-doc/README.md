# PhpArrayDocument

This is a parser/printer for a subset of PHP focused on data. A document
looks like:

```php
<?php
return [
  'key_1' => 'value_1',
  'key_2' => [2.1, 2.2, 2.3],
  'key_3' => [
    'part_a' => 'Apple',
    'part_b' => 'Banana',
  ]
];
```

Additionally, you may declare tagged/factory values:

```php
<?php
use MyHelper as H;
return [
  'name' => 'greeter',
  'label' => H::translate('Hello World'),
];
```

Or even:

```php
use MyHelper as H;
return H::record([
  'name' => 'greeter',
  'label' => H::translate('Hello World'),
]);
```

`PhpArrayDocument` is a compatible subset of PHP, with these considerations:

* Allows `array` values and `scalar` values (`string`, `int`, `bool`, etc).
* Allows *tagged-values* or *factory-functions* which look like *global or static method-calls*.
* Allows deferred construction (`fn() => [...data...]`)
* Prohibits objects, loops, math, concatenation, includes, custom functions, etc.

## Model

* Basic Concepts
    * __Substance__: Each "_PHP array document_" contains a tree of `array`s and `scalar`s.
    * __Metadata__: Individual values may be annotated with comments and/or factory-functions (such as `ts(...)`).
    * __Evaluation__: If you `include` or `require` the PHP document directly, you will literally get `array`s and `scalar`s.
    * __Read/Write__: If you need to programmatically inspect or update the content, then the `PhpArrayDocument` aims to help.
* Verbs
    * __Parse__: Read the PHP document as an instance of `PhpArrayDocument`
    * __Print__: Render `PhpArrayDocument` as a string (`<?php return [...]`)
    * __Import Data__: Add basic PHP array data to a `PhpArrayDocument` (*without any metadata/comments/factory-functions*)
    * __Export Data__: Grab the PHP (*discarding any metadata/comments/factory-functions*)
    * __Walk Nodes__: Visit all the nodes in the tree. Useful for general filtering/searching/replacing.
* Classes
    * Data-Focused Classes
        * `PhpArrayDocument`: This captures the overall `*.php` file, including any top-level elements (`use` or docblocks) and the root `array`.
        * `ArrayNode` (extends `BaseNode`): An element in the tree that corresponds to `array()`
        * `ArrayItemNode` (extends `BaseNode`): A key-value pair that exists within an `array()`
        * `ScalarNode` (extends `BaseNode`): An atomic value that lives inside an array.
    * Functionality-Focused Classes
        * `Parser`: Take a raw `string`. Generate a `PhpArrayDocument`.
        * `Printer`: Take a `PhpArrayDocument`. Generate a string

## Examples

```php
# Generate a file from basic array data
use PhpArrayDocument\PhpArrayDocument;
$doc = PhpArrayDocument::create();
$doc->getRoot()->importData([
  'foo...',
  'bar...',
]);
file_put_contents($file, (new Printer())->print($doc));
```

```php
# Update a file
use PhpArrayDocument\Parser;
$file = 'my-example.data.php';
$doc = (new Parser())->parse(file_get_contents($file));

$doc->root['label'] = ScalarNode::create('Hello World')
	->setFactory('E::ts');

file_put_contents($file, (new Printer())->print($doc));
```

```php
# Rename a factory method
$doc = (new Parser())->parse(file_get_contents($file));
foreach ($doc->root->walkNodes() as $node) {
  if ($node->getFactory() === 'OldHelper::method') {
    $node->setFactory('NewHelper::method');
  }
} 
```

## Cheatsheet

Some commands to help with debugging:

```bash
## Parse a PHP file
cat examples/simple-array.php | ./scripts/parse.php | less

## Parse an improvised PHP snippet
echo '<?php return [1,2,3];' | ./scripts/parse.php

## Tokenize a PHP file
cat examples/simple-array.php | ./scripts/tokenize.php | less

## Tokenize an improvised PHP snippet
echo '<?php return [1,2,3];' | ./scripts/tokenize.php
```
