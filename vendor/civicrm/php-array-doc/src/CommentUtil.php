<?php
namespace PhpArrayDocument;

class CommentUtil {

  /**
   * @param array|null $rawComments
   *   Ex: ["// First line\n", "// Second line\n"]
   * @return string|null
   *   Ex: "First line\nSecond line\n";
   */
  public static function toCleanComments(?array $rawComments): ?string {
    if ($rawComments === NULL || $rawComments === []) {
      return NULL;
    }

    $buf = '';
    foreach ($rawComments as $comment) {
      $comment = trim($comment);
      if (substr($comment, 0, 2) === '//') {
        $buf .= trim(substr($comment, 2)) . "\n";
      }
      elseif (substr($comment, 0, 3) === '/**') {
        $lines = explode("\n", trim(substr($comment, 3, -2)));
        $lines = preg_replace('/^\s*\* ?/', '', $lines);
        $buf .= implode("\n", $lines) . "\n";
      }
      elseif (substr($comment, 0, 2) === '/*') {
        $buf .= trim(substr($comment, 2, -2)) . "\n";
      }
      elseif ($comment === '') {
        // ignore
      }
      else {
        throw new \LogicException("Malformed comment");
      }
    }

    return $buf;
  }

  /**
   * @param string|null $cleanComments
   *   Ex: "First line\nSecond line\n";
   * @return string[]|null
   *   Ex: ["/**\n * First line\n * Second line\n *"]
   */
  public static function toRawComments(?string $cleanComments): ?array {
    if ($cleanComments === NULL) {
      return [];
    }

    $buf = "/**\n";
    $lines = explode("\n", rtrim($cleanComments, "\n"));
    foreach ($lines as $line) {
      $buf .= ' * ' . $line . "\n";
    }
    $buf .= " */\n";
    return [$buf];
  }

  /**
   * @param string $prefix
   * @param array|null $comments
   * @return string|null
   */
  public static function toIndentedComments(string $prefix, ?array $comments): ?string {
    if ($comments === NULL || $comments === []) {
      return NULL;
    }

    $buf = '';
    foreach ($comments as $comment) {
      if (substr($comment, 0, 2) === '//') {
        $buf .= $prefix . $comment;
      }
      elseif (substr($comment, 0, 3) === '/**') {
        $buf .= $prefix . rtrim(str_replace("\n", "\n{$prefix}", $comment), " ");
      }
      elseif (substr($comment, 0, 2) === '/*') {
        $buf .= $prefix . $comment;
      }
      elseif ($comment === '') {
        // ignore
      }
      else {
        throw new \LogicException("Malformed comment");
      }
    }

    return $buf;
  }

}
