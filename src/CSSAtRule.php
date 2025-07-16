<?php
namespace Nocto\Tools\CSSFlattener;

class CSSAtRule {
    public bool $isBlock = true;
    public string $name;
    public string $params;
    public array $children = [];

    public function __construct(string $name, string $params) {
        $this->name = $name;
        $this->params = $params;
    }
}
