<?php
namespace Dottyfix\CSSFlattener;

class CSSRule extends CSSNode {
    public $selector;
    public $declarations = [];
    public $children = [];

    public function __construct($selector) {
        $this->selector = trim($selector);
    }
}
