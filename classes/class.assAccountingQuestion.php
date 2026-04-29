<?php

/**
 * Copyright (c) 2013 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv2, see LICENSE
 */

use ILIAS\TestQuestionPool\Questions\QuestionAutosaveable;
use ILIAS\Test\Logging\AdditionalInformationGenerator;

/**
 * Class for accounting questions
 *
 * @author    Fred Neumann <fred.neumann@fim.uni-erlangen.de>
 * @version    $Id:  $
 * @ingroup ModulesTestQuestionPool
 */
class assAccountingQuestion extends assQuestion implements QuestionAutosaveable
{
    public const SUB_NUMERIC = 'numeric';  // Substitute a variable with float value as numeric string for further calculations (use . for decimals)
    public const SUB_DISPLAY = 'display';  // Substitute a variable with float value rounded with given precision for display
    public const SUB_DEFAULT = 'default';  // Substitute a variable with float value as string (use , for decimals)

    /**
     * Reference of the plugin object
     * @var ilassAccountingQuestionPlugin
     */
    private $plugin;

    /**
     * List of part objects
     * @var assAccountingQuestionPart[]
     */
    private $parts = array();

    /**
     * XML representation of accounts definitions
     * (stored in the DB)
     * @var string
     */
    private $accounts_xml = '';

    /**
     * Array representation of accounts definitions
     * Is set by setAccountsXML()
     * @var array
     */
    private $accounts_data = [];


    /**
     * Search for title in the dropdowns
     * Is set by setAccountsXML()
     * @var bool
     */
    private $accounts_search_title = false;


    /**
     * XML representation of variables definitions
     * (stored in the DB)
     * @var string
     */
    private $variables_xml = '';

    /**
     * Random variables of the question
     * Is set implictly by setVariablesXML()
     * @var ilAccqstVariable[]
     */
    private $variables = array();

    /**
     * Error from analyze functions
     * @var string
     */
    private $analyze_error = '';

    /**
     * Precision for comparing floating point values
     * @var int
     */
    private $precision = 2;

    /**
     * Thousands delimiter set by the question
     * @var null
     */
    private $thousands_delim_type = '';

    /**
     * ilAccountingQuestion constructor
     *
     * The constructor takes possible arguments an creates an instance of the ilAccountingQuestion object.
     *
     * @param string $title A title string to describe the question
     * @param string $comment A comment string to describe the question
     * @param string $author A string containing the name of the questions author
     * @param integer $owner A numerical ID to identify the owner/creator
     * @param string $question The question string of the single choice question
     * @access public
     * @see assQuestion:assQuestion()
     */
    public function __construct(
        $title = "",
        $comment = "",
        $author = "",
        $owner = -1,
        $question = ""
    ) {
        parent::__construct($title, $comment, $author, $owner, $question);

        // init the plugin object
        $this->getPlugin();
    }

    /**
     * @return ilassAccountingQuestionPlugin The plugin object
     */
    public function getPlugin()
    {
        global $DIC;

        if ($this->plugin == null) {
            /** @var ilComponentFactory $component_factory */
            $component_factory = $DIC["component.factory"];
            $this->plugin = $component_factory->getPlugin('accqst');
        }
        return $this->plugin;
    }


    /**
     * Returns true, if the question is complete for use
     *
     * @return boolean True, if the single choice question is complete for use, otherwise false
     */
    public function isComplete(): bool
    {
        if (($this->title) and ($this->author) and ($this->question) and ($this->points > 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the analyzing error message
     */
    public function getAnalyzeError()
    {
        return $this->analyze_error;
    }

    /**
     * Saves an assAccountingQuestion object to a database
     *
     * @param    string $original_id        original id
     * @param    boolean $a_save_parts       save all parts, too
     * @access    public
     */
    public function saveToDb($original_id = null, $a_save_parts = true): void
    {
        global $DIC;

        $ilDB = $DIC->database();

        // collect the maximum points of all parts
        // must be done before basic data is saved
        $this->calculateMaximumPoints();

        // save the basic data (implemented in parent)
        $this->saveQuestionDataToDb($original_id);

        // save the account definition to a separate hash table
        $hash = hash("md5", $this->getAccountsXML());
        $ilDB->replace(
            'il_qpl_qst_accqst_hash',
            array(
                'hash' => array('text', $hash)
            ),
            array(
                'data' => array('clob', $this->getAccountsXML())
            )
        );

        // save data to DB
        $ilDB->replace(
            'il_qpl_qst_accqst_data',
            array(
                'question_fi' => array('integer', $ilDB->quote($this->getId(), 'integer'))
            ),
            array(
                'question_fi' => array('integer', $ilDB->quote($this->getId(), 'integer')),
                'account_hash' => array('text', $hash),
                'variables_def' => array('clob', $this->getVariablesXML()),
                'prec' => array('integer', $this->getPrecision()),
                'thousands_delim_type' => array('text', $this->getThousandsDelimType())
            )
        );

        // save all parts (also a new one)
        if ($a_save_parts) {
            foreach ($this->parts as $part_obj) {
                $part_obj->write();
            }
        }
        // save stuff like suggested solutions
        // update the question time stamp and completion status
        parent::saveToDb($original_id);
    }

    /**
     * Loads an assAccountingQuestion object from a database
     *
     * @param integer $question_id A unique key which defines the question in the database
     */
    public function loadFromDb($question_id): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        // load the basic question data
        $result = $ilDB->query("SELECT qpl_questions.* FROM qpl_questions WHERE question_id = "
            . $ilDB->quote($question_id, 'integer'));

        $data = $ilDB->fetchAssoc($result);
        $this->setId($question_id);
        $this->setTitle($data["title"] ?? '');
        $this->setComment($data["description"] ?? '');
        $this->setOriginalId($data["original_id"]);
        $this->setObjId($data["obj_fi"] ?? 0);
        $this->setAuthor($data["author"] ?? '');
        $this->setOwner($data["owner"] ?? -1);
        $this->setPoints($data["points"] ?? 0);

        $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"] ?? '', 1));

        try {
            $this->setLifecycle(ilAssQuestionLifecycle::getInstance($data['lifecycle']));
        } catch (ilTestQuestionPoolInvalidArgumentException $e) {
            $this->setLifecycle(ilAssQuestionLifecycle::getDraftInstance());
        }

        try {
            $this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
        } catch (ilTestQuestionPoolException $e) {
        }

        // get the question data
        $result = $ilDB->query(
            "SELECT account_hash, variables_def, prec, thousands_delim_type FROM il_qpl_qst_accqst_data "
            . " WHERE question_fi =" . $ilDB->quote($question_id, 'integer')
        );
        $data = $ilDB->fetchAssoc($result);

        $hash = $data['account_hash'] ?? '';
        $this->setVariablesXML($data['variables_def'] ?? '');
        $this->setPrecision($data['prec'] ?? 0);
        $this->setThousandsDelimType($data['thousands_delim_type'] ?? '');

        // get the hash value for accounts definition
        $result = $ilDB->query(
            "SELECT data FROM il_qpl_qst_accqst_hash "
            . " WHERE hash =" . $ilDB->quote($hash, 'text')
        );

        $data = $ilDB->fetchAssoc($result);
        $this->setAccountsXML($data["data"] ?? '');

        // load the question parts
        $this->loadParts();

        // loads additional stuff like suggested solutions
        parent::loadFromDb($question_id);
    }


    /**
     * Load the question parts
     */
    public function loadParts()
    {
        $this->parts = assAccountingQuestionPart::_getOrderedParts($this);
    }

    protected function onDuplicate(
        int $original_parent_id,
        int $original_question_id,
        int $duplicate_parent_id,
        int $duplicate_question_id
    ): void {
        parent::onDuplicate($original_parent_id, $original_question_id, $duplicate_parent_id, $duplicate_question_id);
        // clone all parts, $this is already the cloned question
        $this->cloneParts($this);
    }

    protected function onCopy(int $sourceParentId, int $sourceQuestionId, int $targetParentId, int $targetQuestionId): void
    {
        parent::onCopy($sourceParentId, $sourceQuestionId, $targetParentId, $targetQuestionId);

        // clone all parts, $this is already the cloned question
        $this->cloneParts($this);
    }

    /**
     * Synchronize a question with its original
     *
     * @access public
     */
    public function syncWithOriginal(): void
    {
        parent::syncWithOriginal();

        // delete all original parts and set clones of own parts
        // first load parts because they still point to the own parts

        $orig = clone $this;
        $orig->setId($this->getOriginalId());
        $orig->loadParts();
        $orig->deleteParts();
        $orig->cloneParts($this);
    }

    /**
     * Clone the parts of another question
     *
     * @param    assAccountingQuestion    $a_source_obj
     * @access    public
     */
    private function cloneParts($a_source_obj)
    {
        $cloned_parts = array();

        foreach ($a_source_obj->getParts() as $part_obj) {
            // cloning is handled in the part object
            // at this time the parent points to the original question
            $part_clone = clone $part_obj;

            // reset the part_id so that a new part is written to the database
            $part_clone->setPartId(0);

            // now set the new parent
            // which also sets the question id
            $part_clone->setParent($this);

            // write the new part object to db
            $part_clone->write();

            $cloned_parts[] = $part_clone;
        }

        $this->parts = $cloned_parts;
    }


    /**
     * Delete all parts of a question
     */
    public function deleteParts()
    {
        foreach ($this->parts as $part_obj) {
            $part_obj->delete();
        }
        $this->parts = array();
    }

    /**
     * get the parts of the question
     * @return assAccountingQuestionPart[]
     */
    public function getParts()
    {
        return $this->parts;
    }


    /*
     * get a part by its id
     *
     * if part is not found, an new part will be delivered
     */
    public function getPart($a_part_id = 0)
    {
        foreach ($this->parts as $part_obj) {
            if ($part_obj->getPartId() == $a_part_id) {
                return $part_obj;
            }
        }

        // add and return a new part object
        $part_obj = new assAccountingQuestionPart($this);
        $this->parts[] = $part_obj;
        return $part_obj;
    }

    /**
     * remove a part from the list of parts
     * @param int $a_part_id
     * @return bool
     */
    public function deletePart($a_part_id)
    {
        foreach ($this->parts as $part_obj) {
            if ($part_obj->getPartId() == $a_part_id) {
                // delete the found part
                if ($part_obj->delete()) {
                    unset($this->parts[$a_part_id]);
                    $this->calculateMaximumPoints();
                    $this->saveToDB(null, false);
                    return true;
                }
            }
        }

        // part not found
        return false;
    }


    /**
     * Analyze the XML accounts definition
     *
     * Data is set in class variable 'accounts_data' (not stored in db)
     *
     * @param    string $a_accounts_xml       xml definition of the accounts
     * @return    boolean        definition is ok (true/false)
     */
    public function setAccountsXML($a_accounts_xml)
    {
        // default values
        $this->accounts_data = array();

        $xml = null;
        try {
            $xml = simplexml_load_string($a_accounts_xml);
        } catch (Exception $e) {
        }


        if (!is_object($xml)) {
            return false;
        }

        $type = $xml->getName();
        if ($type != 'konten') {
            return false;
        }

        $display = (string) ($xml['anzeige'] ?? '');
        $search = (string) ($xml['suche'] ?? '');

        // init accounts data (not yed saved in db)
        $data[] = array();

        foreach ($xml->children() as $child) {
            // each account is an array of properties
            $account = array();

            $account['title'] = (string) ($child['titel'] ?? '');
            $account['number'] = (string) ($child['nummer'] ?? '');

            switch (strtolower($display)) {
                case 'nummer':
                    $account['text'] = ($account['number'] ?? '');
                    break;

                case 'titel':
                    $account['text'] = ($account['title'] ?? '');
                    break;

                default:
                    $account['text'] = ($account['number'] ?? '') . ': ' . ($account['title'] ?? '');
                    break;
            }

            // add the account to the data
            $data[] = $account;
        }

        // set data if ok
        $this->accounts_xml = $a_accounts_xml;
        $this->accounts_data = $data;
        $this->accounts_search_title = ($search == 'beide' || $search == 'titel');

        return true;
    }


    /**
     * get the accounts data
     *
     * @return    array    accounts data
     */
    public function getAccountsData()
    {
        return $this->accounts_data;
    }


    /**
     * get the account according to an input text
     *
     * @param    string $a_text   input text
     * @return    array    account data ('number', 'title', 'text')
     */
    public function getAccount($a_text)
    {
        foreach ($this->getAccountsData() as $account) {
            if ((int) ($account['number'] ?? 0) == (int) $a_text
                or strtolower($account['title'] ?? '') == strtolower($a_text)
                or strtolower($account['text'] ?? '') == strtolower($a_text)
            ) {
                return $account;
            }
        }
        return array();
    }

    /**
     * get the account text from an account number
     * @param string $number	Account number
     * @return string	Account text
     */
    public function getAccountText($number)
    {
        foreach ($this->getAccountsData() as $account) {
            if ((int) ($account['number'] ?? 0) == (int) $number) {
                return $account['text'] ?? '';
            }
        }
        return "";
    }


    /**
     * get the accounts definition as XML
     *
     * @return    string    xml definition of the accounts
     */
    public function getAccountsXML()
    {
        return $this->accounts_xml;
    }


    /**
     * get if search for account titles is allowed
     * @return bool
     */
    public function getAccountsSearchTitle()
    {
        return $this->accounts_search_title;
    }

    /**
     * set the variables definitions from XML
     * @param string $a_variables_xml	code
     * @return bool					definition is ok
     */
    public function setVariablesXML($a_variables_xml)
    {
        try {
            if (trim($a_variables_xml) != '') {
                $variables = ilAccqstVariable::getVariablesFromXmlCode($a_variables_xml, $this);
            } else {
                $variables = [];
            }

        } catch (Exception $e) {
            $this->analyze_error = $e->getMessage();
            return false;
        }


        $this->variables_xml = $a_variables_xml;
        $this->variables = $variables;
        return true;
    }


    /**
     * get the variables definition as XML
     *
     * @return    string    xml definition of the variables
     */
    public function getVariablesXML()
    {
        return $this->variables_xml;
    }

    /**
     * Get the list of variables
     * @return ilAccqstVariable[]
     */
    public function getVariables()
    {
        return $this->variables;
    }


    /**
     * Calculate the values of all variables
     * A calculation error mesage is provided with getAnalyzeError()
     * @return bool all variables are calculated
     */
    public function calculateVariables()
    {
        try {
            foreach ($this->variables as $name => $var) {
                if (!$var->calculateValue()) {
                    $this->analyze_error = sprintf($this->plugin->txt('var_not_calculated'), $var->name);
                    return false;
                }
            }
        } catch (Exception $e) {
            $this->analyze_error = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Set the values of the variables from a user solution
     * Otherwise calculate them
     * @param array $userSolution value1 => value2
     * @return bool the variables were complete in the user solution
     */
    public function initVariablesFromUserSolution($userSolution = [])
    {
        $complete = false;
        foreach ($userSolution as $value1 => $value2) {
            if ($value1 == 'accqst_vars') {
                $values = unserialize($value2);

                $complete = true;
                foreach ($this->variables as $name => $var) {
                    if (isset($values[$name])) {
                        $var->value = $values[$name];
                    } else {
                        $complete = false;
                    }
                }
            }
        }

        // be sure that variables have values
        if (!$complete) {
            $this->calculateVariables();
        }

        // apply the variables to the question and its parts
        $this->setQuestion($this->substituteVariables($this->getQuestion(), self::SUB_DISPLAY));
        foreach ($this->getParts() as $partObj) {
            $partObj->setText($this->substituteVariables($partObj->getText(), assAccountingQuestion::SUB_DISPLAY));
            $partObj->setBookingXML($partObj->getBookingXML(), true);
        }

        return $complete;
    }

    /**
     * Add variables to a user solution
     * @param array $userSolution
     * @return array value1 => value2
     */
    public function addVariablesToUserSolution($userSolution = [])
    {
        $values = [];
        foreach ($this->variables as $name => $var) {
            $values[$name] = $var->value;
        }
        $userSolution['accqst_vars'] = serialize($values);

        return $userSolution;
    }


    /**
     * Substitute the referenced variables in a string
     * @param string $string
     * @param string $mode
     * @return $string
     */
    public function substituteVariables($string, $mode = self::SUB_DEFAULT)
    {
        foreach ($this->getVariables() as $name => $var) {
            $pattern = '{' . $name . '}';
            if (strpos($string, $pattern) !== false) {
                switch ($mode) {
                    case self::SUB_NUMERIC:
                        $value = $var->getNumeric();
                        break;
                    case self::SUB_DISPLAY:
                        $value = $var->getDisplay();
                        break;
                    case self::SUB_DEFAULT:
                    default:
                        $value = $var->getString();
                }

                $string = str_replace($pattern, $value, $string);
            }
        }
        return $string;
    }

    /**
     * Get the calculation precision
     * @return int
     */
    public function getPrecision()
    {
        return $this->precision;
    }


    /**
     * Set the calculation precision
     * @param int $precision
     */
    public function setPrecision($precision)
    {
        $this->precision = (int) $precision;
    }

    /**
     * Get the thosands delimiter type set by this question
     */
    public function getThousandsDelimType()
    {
        return $this->thousands_delim_type;
    }

    /**
     * Set the thousands delimiter type set by this question
     * @var string  $delim
     */
    public function setThousandsDelimType($delim_type = '')
    {
        $this->thousands_delim_type = $delim_type;
    }

    /**
     * Get the effective thousands delimiter
     * The global configured delimiter will be used if the type is empty or a setting by question is not allowed
     */
    public function getThousandsDelim()
    {
        $config = $this->plugin->getConfig();

        if ($config->thousands_delim_per_question) {
            return $config->getThousandsDelim($this->thousands_delim_type);
        } else {
            return $config->getThousandsDelim();
        }
    }


    /**
     * Check if two values are equal
     * @param float $val1;
     * @param float $val2;
     * @return bool;
     */
    public function equals($val1, $val2)
    {
        return (abs($val1 - $val2) < (0.1 ** $this->getPrecision()));
    }


    /**
     * Calculate the maximum points
     *
     * This should be done whenever a part or booking file is changed
     */
    public function calculateMaximumPoints()
    {
        $points = 0;
        foreach ($this->parts as $part_obj) {
            $points += $part_obj->getMaxPoints();
        }

        $this->setPoints($points);
    }


    /**
     * Get a submitted solution array from $_POST
     *
     * The return value is used by:
     *        savePreviewData()
     *        saveWorkingData()
     *        calculateReachedPointsForSolution()
     *
     * @return    array    value1 => value2
     */
    protected function getSolutionSubmit()
    {
        $inputs = [];

        foreach ($this->getParts() as $part_obj) {
            $part_id = $part_obj->getPartId();

            // part_id is needed, because inputs are concatenated for storage
            // @see self::getSolutionStored()
            $xml = '<input part_id="' . $part_id . '">';
            for ($row = 0; $row < $part_obj->getMaxLines(); $row++) {
                $prefix = 'q_' . $this->getId() . '_part_' . $part_id . '_row_' . $row . '_';

                $xml .= '<row ';
                $xml .= 'rightValueMoney="' . $this->plugin->request()->getString($prefix . 'amount_right') . '" ';
                $xml .= 'leftValueMoney="' . $this->plugin->request()->getString($prefix . 'amount_left') . '" ';
                $xml .= 'rightValueRaw="' . $this->plugin->request()->getString($prefix . 'amount_right') . '" ';
                $xml .= 'leftValueRaw="' . $this->plugin->request()->getString($prefix . 'amount_left') . '" ';
                $xml .= 'rightAccountNum="' . $this->plugin->request()->getString($prefix . 'account_right') . '" ';
                $xml .= 'leftAccountNum="' . $this->plugin->request()->getString($prefix . 'account_left') . '" ';
                $xml .= 'rightAccountRaw="' . $this->getAccountText($this->plugin->request()->getString($prefix . 'account_right')) . '" ';
                $xml .= 'leftAccountRaw="' . $this->getAccountText($this->plugin->request()->getString($prefix . 'account_left')) . '"/> ';
            }
            $xml .= '</input>';

            $inputs[] = $xml;
        }

        $value1 = 'accqst_input';						    // key to idenify the storage format
        $value2 = implode('<partBreak />', $inputs);	// concatenated xml inputs for all parts

        return [$value1 => $value2];
    }


    /**
     * Get a solution array from the database
     *
     * The return value is used by:
     *        savePreviewData()
     *        saveWorkingData()
     *        calculateReachedPointsForSolution()
     *
     * @param	integer	$active_id	active id of the user
     * @param	integer	$pass	test pass
     * @param	mixed $authorized		true: get authorized solution, false: get intermediate solution, null: prefer intermediate
     * @return  array    	value1 => value2
     */
    public function getSolutionStored($active_id, $pass, $authorized = null)
    {
        if (is_null($authorized)) {
            // assAccountingQuestionGUI::getTestOutput() takes the latest storage
            $rows = $this->getUserSolutionPreferingIntermediate($active_id, $pass);
        } else {
            // other calls should explictly indicate whether to use the authorized or intermediate solutions
            $rows = $this->getSolutionValues($active_id, $pass, $authorized);
        }

        return $this->convertStoredSolutionValues($rows);
    }

    /**
     * Convert the stored solution values to a key-value array
     */
    public function convertStoredSolutionValues(array $values): array
    {
        $userSolution = array();
        foreach ($values as $row) {
            if (isset($row['value1'])) {
                $userSolution[$row['value1']] = $row['value2'] ?? '';
            }
        }
        return $userSolution;
    }

    /**
     * Get the XML parts of a user solution
     * @param array $userSolution	value1 => value2
     * @return array part_id =>  xml string
     */
    public function getSolutionParts($userSolution)
    {
        $parts = array();

        foreach ($userSolution as $value1 => $value2) {
            if ($value1 == 'accqst_input') {
                // new format since 1.3.1
                // all inputs are in one row, concatenated by '<partBreak />'
                // @see self::saveWorkingData()
                $inputs = explode('<partBreak />', $value2);
                foreach ($inputs as $input) {
                    $matches = array();
                    if (preg_match('/part_id="([0-9]+)"/', $input, $matches)) {
                        $part_id = $matches[1];
                        $parts[$part_id] = $input;
                    }
                }
            } else {
                // former format before 1.3.1, stored from the flash input
                // results are stored as key/value pairs
                // format of value1 is 'accqst_key_123' with 123 being the part_id
                // key 'input' is the user input
                // keys 'student' and 'correct' are textual analyses, 'result' are the given points (not longer used)
                $split = explode('_', $value1);
                $key = $split[1] ?? null;
                $part_id = $split[2] ?? 0;

                if ($key == 'input') {
                    $parts[$part_id] = $value2;
                }
            }
        }

        return $parts;
    }


    /**
     * Calculate the points a learner has reached answering the question in a test
     * The points are calculated from the given answers
     *
     * @param integer $active_id The Id of the active learner
     * @param integer $pass The Id of the test pass
     * @param boolean $authorizedSolution (deprecated !!)
     * @param boolean $returndetails (deprecated !!)
     * @return integer/array $points/$details (array $details is deprecated !!)
     * @throws ilTestException
     */
    public function calculateReachedPoints($active_id, $pass = null, $authorized_solution = true, $returndetails = false): float
    {
        if ($returndetails) {
            throw new ilTestException('return details not implemented for ' . __METHOD__);
        }

        if (is_null($pass)) {
            $pass = $this->getSolutionMaxPass($active_id);
        }

        // variables are always authorized
        $varsolution = $this->getSolutionStored($active_id, $pass, true);
        $this->initVariablesFromUserSolution($varsolution);

        $solution = $this->getSolutionStored($active_id, $pass, $authorized_solution);
        return $this->calculateReachedPointsForSolution($solution);
    }

    /**
     * Calculate the points a user has reached in a preview session
     * @param ilAssQuestionPreviewSession $preview_session
     * @return float
     */
    public function calculateReachedPointsFromPreviewSession(ilAssQuestionPreviewSession $preview_session)
    {
        $solution = (array) $preview_session->getParticipantsSolution();
        $this->initVariablesFromUserSolution($solution);
        return $this->calculateReachedPointsForSolution($solution);
    }


    /**
     * Calculate the reached points from a solution array
     *
     * @param   array $solution   value1 => value2
     * @return  float    reached points
     */
    protected function calculateReachedPointsForSolution($solution): float
    {
        $solutionParts = $this->getSolutionParts($solution);
        $points = 0;
        foreach ($this->getParts() as $part_obj) {
            $part_id = $part_obj->getPartId();
            $part_obj->setWorkingXML($solutionParts[$part_id] ?? '');
            $points += $part_obj->calculateReachedPoints();
        }

        // return the raw points given to the answer
        // these points will afterwards be adjusted by the scoring options of a test
        return $points;
    }

    /**
     * Save the submitted input in a preview session
     * @param ilAssQuestionPreviewSession $preview_session
     */
    protected function savePreviewData(ilAssQuestionPreviewSession $preview_session): void
    {
        $this->initVariablesFromUserSolution($preview_session->getParticipantsSolution());
        $userSolution = $this->addVariablesToUserSolution($this->getSolutionSubmit());

        $preview_session->setParticipantsSolution($userSolution);
    }


    /**
     * Saves the learners input of the question to the database
     *
     * @param    integer	$active_id
     * @param	 integer	$pass
     * * @param	 boolean	$authorized
     * @return   boolean 	successful saving
     *
     * @see    self::getSolutionStored()
     */
    public function saveWorkingData($active_id, $pass = null, $authorized = true): bool
    {
        if (is_null($pass)) {
            $pass = ilObjTest::_getPass($active_id);
        }

        // get the values to be stored
        // this does not include the variables which have been saved before in assAccountingQuestionGUI::getTestOutput()
        $userSolution = $this->getSolutionSubmit();

        // update the solution with process lock
        $this->getProcessLocker()->executeUserSolutionUpdateLockOperation(function () use ($active_id, $pass, $authorized, $userSolution) {
            // variables are kept
            $this->removeCurrentSolution($active_id, $pass, $authorized);
            foreach ($userSolution as $value1 => $value2) {
                $this->saveCurrentSolution($active_id, $pass, $value1, $value2, $authorized);
            }
        });

        return true;
    }



    /**
     * Reworks the already saved working data if neccessary
     *
     * @abstract
     * @access protected
     * @param integer $active_id
     * @param integer $pass
     * @param boolean $obligationsAnswered
     * * @param boolean $authorized
     */
    protected function reworkWorkingData($active_id, $pass, $obligationsAnswered, $authorized)
    {
        // nothing to rework!
    }

    /**
     * Remove the current user solution
     * Overwritten to keep the stored variables
     *
     * @inheritdoc
     */
    public function removeCurrentSolution($active_id, $pass, $authorized = true): int
    {
        global $ilDB;

        if ($this->getStep() !== null) {
            $query = "
				DELETE FROM tst_solutions
				WHERE active_fi = %s
				AND question_fi = %s
				AND pass = %s
				AND step = %s
				AND authorized = %s
				AND value1 <> 'accqst_vars'
			";

            return $ilDB->manipulateF(
                $query,
                array('integer', 'integer', 'integer', 'integer', 'integer'),
                array($active_id, $this->getId(), $pass, $this->getStep(), (int) $authorized)
            );
        } else {
            $query = "
				DELETE FROM tst_solutions
				WHERE active_fi = %s
				AND question_fi = %s
				AND pass = %s
				AND authorized = %s
				AND value1 <> 'accqst_vars'
			";

            return $ilDB->manipulateF(
                $query,
                array('integer', 'integer', 'integer', 'integer'),
                array($active_id, $this->getId(), $pass, (int) $authorized)
            );
        }
    }

    /**
     * Remove authorized and intermediate solution for a user in the test pass
     * Overwritten to keep the stored variables
     *
     * @inheritdoc
     */
    public function removeExistingSolutions($activeId, $pass): int
    {
        global $ilDB;

        $query = "
			DELETE FROM tst_solutions
			WHERE active_fi = %s
			AND question_fi = %s
			AND pass = %s
			AND value1 <> 'accqst_vars'
		";

        if ($this->getStep() !== null) {
            $query .= " AND step = " . $ilDB->quote((int) $this->getStep(), 'integer') . " ";
        }

        return $ilDB->manipulateF(
            $query,
            array('integer', 'integer', 'integer'),
            array($activeId, $this->getId(), $pass)
        );
    }


    /**
     * Lookup if an authorized or intermediate solution exists
     * Overwritten to keep the stored variables
     *
     * @inheritdoc
     */
    public function lookupForExistingSolutions($activeId, $pass): array
    {
        /** @var $ilDB \ilDBInterface  */
        global $ilDB;

        $return = array(
            'authorized' => false,
            'intermediate' => false
        );

        $query = "
			SELECT authorized, COUNT(*) cnt
			FROM tst_solutions
			WHERE active_fi = %s
			AND question_fi = %s
			AND pass = %s
			AND value1 <> 'accqst_vars'
		";

        if ($this->getStep() !== null) {
            $query .= " AND step = " . $ilDB->quote((int) $this->getStep(), 'integer') . " ";
        }

        $query .= "
			GROUP BY authorized
		";

        $result = $ilDB->queryF($query, array('integer', 'integer', 'integer'), array($activeId, $this->getId(), $pass));

        while ($row = $ilDB->fetchAssoc($result)) {
            if ($row['authorized']) {
                $return['authorized'] = $row['cnt'] > 0;
            } else {
                $return['intermediate'] = $row['cnt'] > 0;
            }
        }
        return $return;
    }

    /**
     * Returns the question type of the question
     *
     * @return string The question type of the question
     */
    public function getQuestionType(): string
    {
        return "assAccountingQuestion";
    }

    public function getAdditionalTableName(): string
    {
        return 'il_qpl_qst_accqst_data';
    }

    public function deleteAdditionalTableData(int $question_id): void
    {
        // todo: cleanup account data
        // - get the account hash from the question (il_qpl_qst_accqst_data)
        // - check if hash is used by other question  (needs index)
        // - delete hash from il_qpl_qst_accqst_hash if it is not used

        parent::deleteAdditionalTableData($question_id);

        $this->db->manipulateF(
            "DELETE FROM il_qpl_qst_accqst_part WHERE question_fi = %s",
            ['integer'],
            [$question_id]
        );
    }

    /**
     * Returns the name of the answer table in the database
     * @return string The answer table name
     */
    public function getAnswerTableName(): string
    {
        return "";
    }

    /**
     * Collects all text in the question which could contain media objects
     * which were created with the Rich Text Editor
     */
    protected function getRTETextWithMediaObjects(): string
    {
        $text = parent::getRTETextWithMediaObjects();
        foreach ($this->getParts() as $part_obj) {
            $text .= $part_obj->getText();
        }
        return $text;
    }

    public function toLog(AdditionalInformationGenerator $additional_info): array
    {
        // TODO: Implement toLog() method.
        return [];
    }

    protected function solutionValuesToLog(
        AdditionalInformationGenerator $additional_info,
        array $solution_values
    ): array|string {
        // TODO: Implement solutionValuesToLog() method.
        return [];
    }

    protected function solutionValuesToText(array $solution_values): array|string
    {
        // TODO: Implement solutionValuesToText() method.
        return [];
    }
}
