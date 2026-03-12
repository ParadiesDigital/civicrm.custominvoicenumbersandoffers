<?php

namespace PhpArrayDocument;

trait CommentableTrait {

  /**
   * @var string[]
   *   Ex: ["// First line\n", "// Second line\n"]
   */
  private $comments = NULL;

  /**
   * Get the comments, including any comment-markers.
   *
   * @return array|null
   *   Ex: ["// First line\n", "// Second line\n"]
   */
  public function getOuterComments(): ?array {
    return $this->comments;
  }

  /**
   * Set the comments, including aany comment markers.
   *
   * @param array|null $comments
   *   Ex: ["// First line\n", "// Second line\n"]
   * @return $this
   */
  public function setOuterComments(?array $comments) {
    $this->comments = $comments;
    return $this;
  }

  /**
   * Get the inner content of the comment, without any comment-markers.
   *
   * @return string|null
   *   Ex: "First line\nSecond line"
   */
  public function getInnerComments(): ?string {
    return CommentUtil::toCleanComments($this->comments);
  }

  /**
   * Set the inner content of the comment. (Comment markers will be added automatically.)
   *
   * @param string|null $comment
   *   Ex: "First line\nSecond line"
   * @return $this
   */
  public function setInnerComments(?string $comment) {
    $this->comments = CommentUtil::toRawComments($comment);
    return $this;
  }

  /**
   * Generate a printable version of the comment.
   *
   * @param string $prefix
   * @return string|null
   */
  public function renderComments(string $prefix = ''): ?string {
    return CommentUtil::toIndentedComments($prefix, $this->comments);
  }

}
