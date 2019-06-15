<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


/**
 * Base range variable
 * Selects a value from a range of values
 */
class ilAccqstRangeVar extends ilAccqstVariable
{
    /** @var string minimum value */
    private $min;

    /** @var string maximum value */
    private $max;

    /** @var string step for creating values between min and max */
    private $step = 1;


    /**
     * Init the variable definition from an XML element
     * @param SimpleXMLElement $element
     * @throws ilException
     */
    public function initFromXmlElement(SimpleXMLElement $element)
    {
        if (empty($element['min']) || empty($element['max']))
        {
            throw new ilException(sprintf($this->plugin->txt('missing_min_max'), $this->name));
        }

        $this->min = (string) $element['min'];
        $this->max = (string) $element['max'];
        if (!empty($element['step']))
        {
            $this->step = (string) $element['step'];
        }
    }

    /**
     * Get the names of all variables that are directly used by this variable
     * @param string[] $names list of all available variable names
     * @return string[]
     */
    public function getUsedNames($names)
    {
        return [];
    }

}