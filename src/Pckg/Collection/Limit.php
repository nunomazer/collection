<?php

namespace Pckg\Collection;

use LimitIterator;
use Pckg\Collection;

/**
 * Class Limit
 * @package Pckg\Collection
 */
class Limit extends Collection
{

    /**
     * @param     $limitCount
     * @param int $limitOffset
     * @return array
     */
    public function getLimited($limitCount, $limitOffset = 0)
    {
        $arrLimited = [];

        foreach (new LimitIterator($this, $limitOffset, $limitCount) AS $row) {
            $arrLimited[] = $row;
        }

        return $arrLimited;
    }

    /**
     * @return null
     */
    public function getFirst()
    {
        return isset($this->collection[0]) ? $this->collection[0] : null;
    }
}