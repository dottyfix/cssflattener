<?php
namespace Dottyfix\CSSFlattener;

class CSSDeclaration extends CSSNode {
    public $property;
    public $value;

    public function __construct($property, $value) {
        $this->property = trim($property);
        $this->value = trim($value);
    }
}
