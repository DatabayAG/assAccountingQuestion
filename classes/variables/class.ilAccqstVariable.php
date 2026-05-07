<?php

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Base class for variable definition
 */
abstract class ilAccqstVariable
{
    public const MAX_DEPTH = 100;

    public const TYPE_RANGE = 'range';
    public const TYPE_SELECT = 'select';
    public const TYPE_SWITCH = 'switch';
    public const TYPE_EVAL = 'eval';

    /** @var string name of the variable */
    public $name;

    /** @var mixed the currently selected or calculated value */
    public $value = null;

    /** @var assAccountingQuestion */
    protected $question;

    /** @var ilAssAccountingQuestionPlugin */
    protected $plugin;

    /**
     * Get variables from an XML definition
     * @param   string $xml
     * @param   assAccountingQuestion $question
     * @return self[]  (indexed by name)
     * @throws ilException
     */
    public static function getVariablesFromXmlCode($xml, $question)
    {
        $xml = trim($xml);
        if (empty($xml)) {
            return [];
        }

        $variables = [];
        $plugin = $question->getPlugin();

        $root = null;
        try {
            $root = simplexml_load_string($xml);
        } catch (Exception $e) {
        }

        if (!($root instanceof SimpleXMLElement && $root->getName() == 'variables')) {
            throw new ilException($plugin->txt('missing_element_variables'));
        }

        foreach ($root->children() as $element) {
            if ($element->getName() != 'var' || empty($element['name'])) {
                throw new ilException($plugin->txt('missing_var_or_name'));
            }
            $name = (string) $element['name'] ?? '';
            $type = (string) $element['type'] ?? '';

            if (isset($variables[$name])) {
                // variable is already defined
                throw new ilException(sprintf($plugin->txt('double_variable_definition'), $name));
            }

            switch ($type) {
                case self::TYPE_RANGE:
                    $variable = new ilAccqstRangeVar($name, $question);
                    break;
                case self::TYPE_SELECT:
                    $variable = new ilAccqstSelectVar($name, $question);
                    break;
                case self::TYPE_SWITCH:
                    $variable = new ilAccqstSwitchVar($name, $question);
                    break;
                case self::TYPE_EVAL:
                    $variable = new ilAccqstEvalVar($name, $question);
                    break;

                default:
                    // unknown type
                    throw new ilException(sprintf($plugin->txt('unknown_variable_type'), $name));
            }

            $variable->initFromXmlElement($element);
            $variables[$name] = $variable;
        }

        return $variables;
    }


    /**
     * ilAccqstVariable constructor.
     * @param string $name
     * @param assAccountingQuestion $question
     */
    public function __construct($name, $question)
    {
        $this->name = $name;
        $this->question = $question;
        $this->plugin = $question->getPlugin();
    }

    /**
     * Init a variable definition from an XML element
     * (null in case of parse error)
     * @param SimpleXMLElement $element
     * @throws ilException
     */
    abstract public function initFromXmlElement(SimpleXMLElement $element);


    /**
     * Get the names of all variables that are directly used by this variable
     * @return string[]
     */
    abstract public function getUsedNames();

    /**
     * Calculate the value of the variable
     * Child classes should override this and call the parent at the beginning
     *
     * @param  integer  $depth calculation depth
     * @return bool     value is calculated
     */
    public function calculateValue($depth = 0)
    {
        $variables = $this->question->getVariables();

        // probably a circular reference
        if ($depth > self::MAX_DEPTH) {
            throw new ilException(sprintf($this->plugin->txt('exceeded_calculation_depth'), $this->name));
        }

        // value is already calculated (e.g. by recursion from another variable)
        if (isset($this->value)) {
            return true;
        }

        // calculate all dependencies
        foreach ($this->getUsedNames() as $name) {
            if ($name == $this->name) {
                throw new ilException(sprintf($this->plugin->txt('forbidden_self_reference'), $this->name));
            }
            if (!isset($variables[$name])) {
                throw new ilException(sprintf($this->plugin->txt('unknown_variable_reference'), $this->name, $name));
            }
            $variables[$name]->calculateValue($depth + 1);
        }

        // child class must calculate
        return false;
    }


    /**
     * Get the floating point value of the variable
     * @return float|null
     */
    public function getFloat()
    {
        return $this->plugin->toFloat($this->value);
    }


    /**
     * Get a numeric string value for calculations
     * The value is rounded by the given precision
     * @return string
     */
    public function getNumeric()
    {
        return number_format($this->plugin->toFloat($this->value), $this->question->getPrecision(), '.', '');
    }

    /**
     * Get the string value of the variable
     * @return string|null
     */
    public function getString()
    {
        return $this->plugin->toString($this->value);
    }

    /**
     * Get the value to be displayed
     * Floats are converted to strings with the precision defined by the question
     */
    public function getDisplay()
    {
        return $this->plugin->toString($this->value, $this->question->getPrecision(), $this->question->getThousandsDelim());
    }

    public function getName(): string
    {
        return $this->name;
    }
}
