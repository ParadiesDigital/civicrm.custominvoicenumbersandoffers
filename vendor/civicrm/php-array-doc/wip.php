<?php
namespace PhpArrayDocument;

require_once 'vendor/autoload.php';

// Example usage:
$code = <<<'CODE'
<?php
use Civi\Core\SettingsDefinition;
use CRM_Mosaico_ExtensionUtil as E;

// About this doc
return SettingsDefinition::create([
  /* The name is important */
  'name' => 'hello',
  // The label is shown to somebody
  'label' => E::ts('Hello World!'),
  'active' => TRUE,
  'html' => [
    // To boldly go
    'bold' => TRUE,
  ],
  'details' => fn() => [
    'alskjdf asdf' => 123,
  ]
]);
CODE;

// $code = <<<'CODE'
// <?php
// use CRM_Mosaico_ExtensionUtil as E;
//
// // About this doc
// return MyStuff::go([
//   'details' => fn() => [
//     'alskjdf asdf' => XX::yy(123),
//   ]
//   'older' => function () { return [
//     'alskjdf asdf' => 123,
//   ]; }
// ]);
// CODE;

$parser = new Parser();
$document = $parser->parse($code);
// $document->dereferenceClassAliases();
// $document->root['active'] = new ScalarNode('yabba');
// $document->root->getItem('active')->key = 'enabled';

print_r($document);
print_r([
  'value of label is' => $document->root['label'],
  'value of html.bold is' => $document->root['html']['bold'],
]);

echo "\n";
echo (new Printer())->print($document);
