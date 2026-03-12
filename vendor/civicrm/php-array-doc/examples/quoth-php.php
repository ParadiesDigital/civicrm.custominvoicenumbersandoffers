<?php

/**
 * Many ways to say the same thing.
 */
return [
  'quotes' => [
    'single-double' => '"',
    'single-slash-double' => '\"',
    'single-slash-single' => '\'',
    'single-tick' => '`',
    'double-single' => "'",
    'double-slash-double' => "\"",
    'double-slash-single' => "\'",
    'double-tick' => "`",
  ],
  'slashes' => [
    'single-back-back' => '\\',
    'double-back-back' => "\\",
  ],
  'lines' => [
    'single-real-line' => '
',
    'double-real-line' => "
",
    'single-escape-line-fake' => '\n',
    'double-escape-line' => "\n",
  ],
  'hex' => [
    'single-48-fake' => '\x48',
    'double-48' => "\x48",
  ],
  'expressions' => [
    'Ab\Cd\Ef',
    "Ab\Cd\Ef",
    'Ab\\Cd\\Ef',
    "Ab\\Cd\\Ef",
    'Ab\\\Cd\\\Ef',
    "Ab\\\Cd\\\Ef",
  ],
];
