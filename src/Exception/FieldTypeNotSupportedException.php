<?php

namespace Drupal\testsite_builder\Exception;

use Throwable;

/**
 * Class FieldTypeNotSupportedException.
 */
class FieldTypeNotSupportedException extends \Exception {

  /**
   * The unsupported field type.
   *
   * @var string
   */
  protected $fieldType;

  /**
   * FieldTypeNotSupportedException constructor.
   *
   * @param string $field_type
   *   The unsupported field type.
   * @param int $code
   *   The Exception code.
   * @param \Throwable $previous
   *   The previous throwable used for the exception chaining.
   */
  public function __construct(string $field_type, int $code = 0, Throwable $previous = NULL) {
    parent::__construct(sprintf('The field type %s is currently not supported.', $field_type), $code, $previous);
    $this->fieldType = $field_type;
  }

  /**
   * Returns the field type.
   *
   * @return string
   *   The unsupported field type.
   */
  public function getFieldType() : string {
    return $this->fieldType;
  }

}
