<?php

namespace Pckg\Collection;

use Pckg\Collection;

/**
 * Class Tree
 * @package Pckg\Collection
 */
class Tree extends Collection
{
    /**
     * @var
     */
    protected $foreign;

    /* sets callback to retreive relation/key */

    /**
     * @param $foreign
     * @return array
     */
    public function getHierarchy($foreign)
    {
        $this->setForeign($foreign);

        $parents = $this->getParents();

        foreach ($parents AS &$parent) {
            $parent = $this->buildParent($parent);
        }

        return $parents;
    }

    /* builds tree */

    /**
     * @param $foreign
     */
    public function setForeign($foreign)
    {
        $this->foreign = $foreign;
    }

    /* transforms parent into object/array and children */

    /**
     * @return array
     */
    public function getParents()
    {
        $arrParents = [];

        foreach ($this->collection AS $row) {
            if (!$row->{$this->foreign}()) { // has no set parrent
                $arrParents[] = $row;
            } else {
                $found = false;
                foreach ($this->collection AS $row2) { // if has no parent
                    if ($row->{$this->foreign}() == $row2->getId()) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $arrParents[] = $row;
                }
            }
        }

        return $arrParents;
    }

    /* recursively builds parents */

    /**
     * @param $parent
     * @return mixed
     */
    public function buildParent($parent)
    {
        $parent->getChildren = $this->buildChildren($parent);
        $parent->subcontents = $parent->getChildren;

        return $parent;
    }

    /* returns records with $this->foreign != true */

    /**
     * @param null $parent
     * @return array
     */
    public function buildChildren($parent = null)
    {
        $arrChildren = $this->getChildren($parent);

        foreach ($arrChildren AS &$child) {
            $child = $this->buildParent($child);
        }

        return $arrChildren;
    }

    /* returns records with $this->foreign != false */

    /**
     * @param null $parent
     * @return array
     */
    public function getChildren($parent = null)
    {
        $arrChildren = [];

        if ($parent) {
            foreach ($this->collection AS $one) {
                if ($parent->getId() == $one->{$this->foreign}()) {
                    $arrChildren[] = $one;
                }
            }
        }

        return $arrChildren;
    }
}