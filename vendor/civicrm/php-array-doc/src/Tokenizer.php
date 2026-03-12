<?php

namespace PhpArrayDocument;

if (!defined('T_FN')) {
  define('T_FN', '_polyfill_T_FN');
}

class Tokenizer {

  public static function getTokens(string $content): iterable {
    yield from [];
    foreach (token_get_all($content) as $token) {
      // PHP 7.3 doesn't have T_FN. But we can pretend it does.
      if (is_array($token) && $token[0] === T_STRING && $token[1] === 'fn') {
        $token[0] = T_FN;
        yield $token;
      }
      // PHP 7.x puts newlines into T_COMMENT, but PHP 8.x puts them in separate T_WHITESPACE. Act like PHP 8..x.
      elseif (is_array($token) && $token[0] === T_COMMENT && substr($token[1], -1) === "\n") {
        $token[1] = substr($token[1], 0, -1);
        yield $token;
        yield [T_WHITESPACE, "\n", $token[2]];
      }
      else {
        yield $token;
      }
    }
  }

  /**
   * @param int|string|array $token
   * @return string
   */
  public static function getName($token): ?string {
    $id = is_array($token) ? $token[0] : $token;
    if (is_string($id) && strlen($id) === 1) {
      return $id;
    }
    if (is_string($id) && strpos($id, '_polyfill_') === 0) {
      return substr($id, strlen('_polyfill_'));
    }
    if ($id === NULL) {
      return NULL;
    }
    return token_name($id);
  }

}
