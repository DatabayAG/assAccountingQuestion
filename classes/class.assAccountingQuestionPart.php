<?php

/**
 * Copyright (c) 2013 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv2, see LICENSE
 */

/**
 * Class for part of an accounting questions
 *
 * @author    Fred Neumann <fred.neumann@fim.uni-erlangen.de>
 * @ingroup ModulesTestQuestionPool
 */
class assAccountingQuestionPart
{
    /**
     * @var object	Reference of the plugin object
     */
    private $plugin = null;

    /**
     * @var object	Reference of the parent assAccountingQuestion object
     */
    private $parent = null;

    /**
     * @var integer		Id of the parent question
     */
    private $question_id = 0;

    /**
     * @var integer		Unique id of this question part
     */
    private $part_id = 0;

    /**
     *
     * @var integer		Order position of this part in the accounting question
     */
    private $position = 0;

    /**
     * @var string		Textual question given for this part
     */
    private $text = '';

    /**
     * @var string		XML representation of the booking definitions (stored in the DB)
     * 					Is set by setBookingXml();
     */
    private $booking_xml = '';

    /**
     * @var array 		Array representation of the booking definitions (not stored)
     * 					Is set by setBookingXML()
     */
    private $booking_data = [];

    /**
     * @var integer		Maximum number of lines to be shown to the user (stored in the DB)
     * 					Is set by setBookingXML()
     */
    private $max_lines = 0;

    /**
     * @var integer		Maximum number of points a user can reach (stored in the DB)
     * 					Is set by setBookingXML()
     */
    private $max_points = 0;


    /**
     * @var array 		Array representation of the student input
     * 					Is set by setWorkingXML()
     */
    private $working_data = null;


    /**
     * constructor
     * @param assAccountingQuestion $a_parent_obj
     * @param integer $a_part_id
     */
    public function __construct($a_parent_obj, $a_part_id = null)
    {
        $this->parent = $a_parent_obj;
        $this->plugin = $a_parent_obj->getPlugin();

        if (isset($a_part_id)) {
            // part id given, read the object
            $this->setPartId($a_part_id);
            $this->read();
        } else {
            // init a new part for the question
            $this->setQuestionId($a_parent_obj->getId());
        }
    }


    /**
     * get ordered array with all parts of a question
     *
     * @param assAccountingQuestion $a_question_obj
     * @return assAccountingQuestionPart[] list of parts, ordered by their position
     */
    public static function _getOrderedParts($a_question_obj)
    {
        global $DIC;
        $ilDB = $DIC->database();

        $parts = array();

        $query = "SELECT * FROM il_qpl_qst_accqst_part "
            . " WHERE question_fi = " . $ilDB->quote($a_question_obj->getId(), 'integer')
            . " ORDER BY position ASC";

        $result = $ilDB->query($query);
        while ($row = $ilDB->fetchAssoc($result)) {
            $part = new assAccountingQuestionPart($a_question_obj);
            $part->setData($row);
            $parts[] = $part;
        }
        return $parts;
    }


    /**
     * reads the part data from a database
     *
     * @param    integer  $a_part_id  part_id of the question part
     */
    public function read($a_part_id = null)
    {
        global $DIC;
        $ilDB = $DIC->database();

        if (!isset($a_part_id)) {
            $a_part_id = $this->getPartId();
        }

        $query = "SELECT * FROM il_qpl_qst_accqst_part WHERE part_id = "
            . $ilDB->quote($a_part_id, 'integer');

        $result = $ilDB->query($query);
        $this->setData($ilDB->fetchAssoc($result));
    }


    /**
     * Set the object data
     *
     * @param array  $a_data  data row from db
     */
    public function setData($a_data)
    {
        if (is_array($a_data)) {
            $this->setPartId($a_data['part_id']);
            $this->setQuestionId($a_data['question_fi']);
            $this->setPosition($a_data['position']);
            $this->setBookingXML($a_data['booking_def']);
            $this->setMaxPoints($a_data['max_points']);
            $this->setMaxLines($a_data['max_lines']);

            $this->setText(ilRTE::_replaceMediaObjectImageSrc($a_data["text"], 1));
        }
    }

    /**
     * write the part to the database
     */
    public function write()
    {
        global $DIC;
        $ilDB = $DIC->database();

        if (!$this->getPartId()) {
            $this->setPartId($ilDB->nextId('il_qpl_qst_accqst_part'));
        }

        // save data to DB
        $ilDB->replace(
            'il_qpl_qst_accqst_part',
            array(
                'part_id' => array('integer', $this->getPartId())
            ),
            array(
                'question_fi' => array('integer', $this->getQuestionId()),
                'position' => array('integer', $this->getPosition()),
                'text' => array('text', ilRTE::_replaceMediaObjectImageSrc($this->getText(), 0)),
                'booking_def' => array('text', $this->getBookingXML()),
                'max_points' => array('float', $this->getMaxPoints()),
                'max_lines' => array('integer', $this->getMaxLines())
            )
        );
    }

    /**
     * Delete the part
     */
    public function delete()
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = 'DELETE FROM il_qpl_qst_accqst_part WHERE part_id=' . $ilDB->quote($this->getPartId(), 'integer');

        if ($rows = $ilDB->manipulate($query)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * set a parent object
     *
     * this will also set the question id of the new parent
     *
     * @param assAccountingQuestion    $a_parent_obj
     */
    public function setParent($a_parent_obj)
    {
        $this->parent = $a_parent_obj;
        $this->question_id = $a_parent_obj->getId();
    }


    /**
     * get the question id
     */
    public function getQuestionId()
    {
        return $this->question_id;
    }

    /**
     * set the question id
     * @param integer $a_question_id
     */
    public function setQuestionId($a_question_id)
    {
        $this->question_id = $a_question_id;
    }


    /**
     * get the part id
     */
    public function getPartId()
    {
        return $this->part_id;
    }

    /**
     * set the part id
     * @param integer $a_part_id
     */
    public function setPartId($a_part_id)
    {
        $this->part_id = $a_part_id;
    }


    /**
     * get the position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * set the position
     * @param integer $a_position
     */
    public function setPosition($a_position)
    {
        $this->position = $a_position;
    }


    /**
     * get the question text
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * set the question text
     * @param integer $a_text
     */
    public function setText($a_text)
    {
        $this->text = $a_text;
    }


    /**
     * get the maximum number of lines in the booking table
     *
     * @return    integer    lines
     */
    public function getMaxLines()
    {
        return $this->max_lines;
    }

    /**
     * set the maximum number of lines in the booking table
     *
     * @param integer $a_lines
     */
    public function setMaxLines($a_lines)
    {
        $this->max_lines = $a_lines;
    }

    /**
     * Returns the maximum points, a learner can reach answering the question
     *
     * @see $points
     */
    public function getMaxPoints()
    {
        return $this->max_points;
    }

    /**
     * set the maximum points, a learner can reach answering the question
     * @param integer $a_points
     */
    public function setMaxPoints($a_points)
    {
        $this->max_points = $a_points;
    }

    /**
     * get the booking definition as XML
     */
    public function getBookingXML()
    {
        return $this->booking_xml;
    }

    /**
     * get the correct booking data
     *
     * @param string|false $arg
     * @return    array    booking data array
     */
    public function getBookingData($arg = false)
    {
        if (is_string($arg)) {
            return $this->booking_data[$arg] ?? null;
        } else {
            return $this->booking_data;
        }
    }


    /**
     * Set a booking definition
     *
     * This should be done whenever the booking is changed
     * Maximum points and display lines may be set for storing in db
     * Other data is set in class variable 'booking_data'
     *
     * @param    string          $a_booking_xml
     * @param    boolean         $a_substitute_variables
     * @return    boolean        booking definition is ok (true/false)
     */
    public function setBookingXML($a_booking_xml, $a_substitute_variables = false)
    {
        // load the xml object
        $xml = null;
        try {
            $xml = simplexml_load_string($a_booking_xml);
        } catch (Exception $e) {
        }

        if (!is_object($xml)) {
            return false;
        }
        $type = $xml->getName();

        // init booking data (not yet saved in db)
        $data = array();

        // init specific criteria (saved in db)
        $max_lines = (int) $this->plugin->toFloat($xml['zeilen']);
        $max_points = 0;

        switch ($type) {
            case 'konto':

                $data['type'] = 't-account';
                $data['showLines'] = (int) ($xml['zeilen'] ?? 0);
                $data['headerLeft'] = (string) ($xml['links'] ?? $this->plugin->txt('t_account_left'));
                $data['headerCenter'] = (string) ($xml['mitte'] ?? $this->plugin->txt('t_account_right'));
                $data['headerRight'] = (string) ($xml['rechts'] ?? $this->plugin->txt('t_account_center'));

                // t-accounts have all bookings in one record
                $record = array();
                $record['bonusOrderLeft'] = $this->plugin->toFloat($xml['bonus_reihe_links'] ?? 0);
                $record['bonusOrderRight'] = $this->plugin->toFloat($xml['bonus_reihe_rechts'] ?? 0);
                $record['malusCountLeft'] = -$this->plugin->toFloat($xml['malus_anzahl_links'] ?? 0);
                $record['malusCountRight'] = -$this->plugin->toFloat($xml['malus_anzahl_rechts'] ?? 0);
                $record['malusSumsDiffer'] = -$this->plugin->toFloat($xml['malus_summen'] ?? 0);
                $record['sumValuesLeft'] = 0;
                $record['sumValuesRight'] = 0;
                $record['sumPointsLeft'] = 0;
                $record['sumPointsRight'] = 0;

                $max_points += $record['bonusOrderLeft'];
                $max_points += $record['bonusOrderRight'];

                // a record may have different row count on left and right side
                $rows = array();
                $leftrow = 0;
                $rightrow = 0;
                foreach ($xml->children() as $booking) {

                    $konto = (string) ($booking['konto'] ?? '');
                    $betrag = (string) ($booking['betrag'] ?? '');
                    if ($a_substitute_variables) {
                        $konto = $this->parent->substituteVariables($konto);
                        $betrag = $this->parent->substituteVariables($betrag);
                    }
                    $account = $this->parent->getAccount($konto);

                    switch ($booking->getName()) {
                        case 'links':
                            $rows[$leftrow]['leftAccountRaw'] = $konto;
                            $rows[$leftrow]['leftAccountNum'] = $account['number'] ?? '';
                            $rows[$leftrow]['leftAccountText'] = $account['text'] ?? '';
                            $rows[$leftrow]['leftValueRaw'] = $betrag;
                            $rows[$leftrow]['leftValueMoney'] = $this->plugin->toFloat($betrag);
                            $rows[$leftrow]['leftPoints'] = $this->plugin->toFloat($booking['punkte'] ?? 0);
                            $record['sumValuesLeft'] += $rows[$leftrow]['leftValueMoney'];
                            $record['sumPointsLeft'] += $rows[$leftrow]['leftPoints'];
                            $leftrow++;
                            break;

                        case 'rechts':
                            $rows[$rightrow]['rightAccountRaw'] = $konto;
                            $rows[$rightrow]['rightAccountNum'] = $account['number'] ?? '';
                            $rows[$rightrow]['rightAccountText'] = $account['text'] ?? '';
                            $rows[$rightrow]['rightValueRaw'] = $betrag;
                            $rows[$rightrow]['rightValueMoney'] = $this->plugin->toFloat($betrag);
                            $rows[$rightrow]['rightPoints'] = $this->plugin->toFloat($booking['punkte'] ?? 0);
                            $record['sumValuesRight'] += $rows[$rightrow]['rightValueMoney'];
                            $record['sumPointsRight'] += $rows[$rightrow]['rightPoints'];
                            $rightrow++;
                            break;
                    }

                    $max_points += $this->plugin->toFloat($booking['punkte']);
                }
                $record['rows'] = $rows;
                $record['countLeft'] = $leftrow;
                $record['countRight'] = $rightrow;

                $data['record'] = $record;
                $data['sumPoints'] = $max_points;
                break;

            case 'buchungssaetze':

                $data['type'] = 'records';
                $data['showLines'] = (int) ($xml['zeilen'] ?? 0);
                $data['headerLeft'] = (string) ($xml['links'] ?? '');
                $data['headerCenter'] = (string) ($xml['mitte'] ?? '');
                $data['headerRight'] = (string) ($xml['rechts'] ?? '');

                $data['headerLeft'] = $data['headerLeft'] ?? $this->plugin->txt('records_left');
                $data['headerRight'] = $data['headerRight'] ?? $this->plugin->txt('records_right');
                $data['headerCenter'] = $data['headerCenter'] ?? $this->plugin->txt('records_center');

                foreach ($xml->children() as $child) {
                    // each child is one record
                    $record = array();
                    $record['malusCountLeft'] = -$this->plugin->toFloat($child['malus_anzahl_von'] ?? 0);
                    $record['malusCountRight'] = -$this->plugin->toFloat($child['malus_anzahl_an'] ?? 0);
                    $record['malusSumsDiffer'] = -$this->plugin->toFloat($child['malus_summen'] ?? 0);
                    $record['sumValuesLeft'] = 0;
                    $record['sumValuesRight'] = 0;
                    $record['sumPointsLeft'] = 0;
                    $record['sumPointsRight'] = 0;

                    // a record may have different row count on left and right side
                    $rows = array();
                    $leftrow = 0;
                    $rightrow = 0;

                    foreach ($child->children() as $booking) {

                        $konto = (string) ($booking['konto'] ?? '');
                        $betrag = (string) ($booking['betrag'] ?? '');
                        if ($a_substitute_variables) {
                            $konto = $this->parent->substituteVariables($konto);
                            $betrag = $this->parent->substituteVariables($betrag);
                        }
                        $account = $this->parent->getAccount($konto);

                        switch ($booking->getName()) {
                            case 'von':
                                $rows[$leftrow]['leftAccountRaw'] = $konto;
                                $rows[$leftrow]['leftAccountNum'] = $account['number'] ?? '';
                                $rows[$leftrow]['leftAccountText'] = $account['text'] ?? '';
                                $rows[$leftrow]['leftValueRaw'] = $betrag;
                                $rows[$leftrow]['leftValueMoney'] = $this->plugin->toFloat($betrag);
                                $rows[$leftrow]['leftPoints'] = $this->plugin->toFloat($booking['punkte'] ?? 0);
                                $record['sumValuesLeft'] += $rows[$leftrow]['leftValueMoney'];
                                $record['sumPointsLeft'] += $rows[$leftrow]['leftPoints'];
                                $leftrow++;
                                break;

                            case 'an':
                                $rows[$rightrow]['rightAccountRaw'] = $konto;
                                $rows[$rightrow]['rightAccountNum'] = $account['number'] ?? '';
                                $rows[$rightrow]['rightAccountText'] = $account['text'] ?? '';
                                $rows[$rightrow]['rightValueRaw'] = $betrag;
                                $rows[$rightrow]['rightValueMoney'] = $this->plugin->toFloat($betrag);
                                $rows[$rightrow]['rightPoints'] = $this->plugin->toFloat($booking['punkte'] ?? 0);
                                $record['sumValuesRight'] += $rows[$rightrow]['rightValueMoney'];
                                $record['sumPointsRight'] += $rows[$rightrow]['rightPoints'];
                                $rightrow++;
                                break;
                        }
                        $max_points += $this->plugin->toFloat($booking['punkte']);
                    }
                    $record['rows'] = $rows;
                    $record['countLeft'] = $leftrow;
                    $record['countRight'] = $rightrow;
                    $data['record'] = $record;

                    // we take only the first record
                    // multiple records in one table are not longer supported  since 1.3.1
                    break;
                }
                $data['sumPoints'] = $max_points;
                break;

            default:
                return false;
        }

        $this->booking_xml = $a_booking_xml;
        $this->booking_data = $data;
        $this->setMaxLines($max_lines);
        $this->setMaxPoints($max_points);

        return true;
    }


    /**
     * Set the working data from the xml input of a runing test
     *
     * @param    string $a_working_xml       xml input
     */
    public function setWorkingXML($a_working_xml)
    {
        // get the correct solution
        $correct = $this->getBookingData();

        // load the xml object
        try {
            $xml = simplexml_load_string($a_working_xml);
        } catch (Exception $e) {
        }
        if (!is_object($xml)) {
            return;
        }

        // prepare the return data
        $data = array();
        $data['type'] = $correct['type'] ?? '';
        $data['showLines'] = $correct['showLines'] ?? 0;
        $data['headerLeft'] = $correct['headerLeft'] ?? '';
        $data['headerCenter'] = $correct['headerCenter'] ?? '';
        $data['headerRight'] = $correct['headerRight'] ?? '';
        $data['sumPoints'] = 0;

        // create a new records
        $record = array();
        $record['countLeft'] = 0;
        $record['countRight'] = 0;
        $record['bonusOrderLeft'] = 0;
        $record['bonusOrderRight'] = 0;
        $record['malusCountLeft'] = 0;
        $record['malusCountRight'] = 0;
        $record['malusSumsDiffer'] = 0;
        $record['sumValuesLeft'] = 0;
        $record['sumValuesRight'] = 0;
        $record['sumPointsLeft'] = 0;
        $record['sumPointsRight'] = 0;
        $record['rows'] = array();

        foreach ($xml->children() as $child) {
            $row = array();
            $row['leftAccountRaw'] = (string) ($child['leftAccountRaw'] ?? '');
            $row['leftAccountNum'] = (string) ($child['leftAccountNum'] ?? '');
            $row['leftAccountText'] = (string) ($child['leftAccountRaw'] ?? ''); // take the raw input as text
            $row['leftValueRaw'] = (string) ($child['leftValueRaw'] ?? '');
            $row['leftValueMoney'] = $this->plugin->toFloat($child['leftValueMoney'] ?? 0);
            $row['leftPoints'] = 0;

            $row['rightAccountRaw'] = (string) ($child['rightAccountRaw'] ?? '');
            $row['rightAccountNum'] = (string) ($child['rightAccountNum'] ?? '');
            $row['rightAccountText'] = (string) ($child['rightAccountRaw'] ?? ''); // take the raw input as text
            $row['rightValueRaw'] = (string) ($child['rightValueRaw'] ?? '');
            $row['rightValueMoney'] = $this->plugin->toFloat($child['rightValueMoney'] ?? 0);
            $row['rightPoints'] = 0;

            // completely empty lines are omitted
            if ($row['leftAccountRaw'] != ''
                or $row['rightAccountRaw'] != ''
                or $row['leftValueMoney'] != 0
                or $row['rightValueMoney'] != 0
            ) {
                $record['rows'][] = $row;
                $record['countLeft'] += ($row['leftAccountRaw'] != '' ? 1 : 0);
                $record['countRight'] += ($row['rightAccountRaw'] != '' ? 1 : 0);
                $record['sumValuesLeft'] += $row['leftValueMoney'];
                $record['sumValuesRight'] += $row['rightValueMoney'];
            }
        }

        $data['record'] = $record;
        $this->working_data = $data;
    }


    /**
     * Get the working data in the same format as the booking data
     */
    public function getWorkingData()
    {
        return $this->working_data;
    }

    public function getTableHeader(): string
    {
        switch ($this->booking_data['type'] ?? null) {
            case 't-account':
                $header = $this->plugin->txt('t_account_center');
                break;
            case 'records':
            default:
                $header = $this->plugin->txt('records_center');
                break;
        }

        return $this->booking_data['headerCenter'] ?? $header;
    }

    /**
     * Get the booking or working data as text output
     */
    public function getDataAsText(array $data): string
    {
        switch ($this->booking_data['type'] ?? null) {
            case 't-account':
                $header_left = $this->plugin->txt('t_account_left');
                $header_right = $this->plugin->txt('t_accounts_right');
                break;
            case 'records':
            default:
                $header_left = $this->plugin->txt('records_left');
                $header_right = $this->plugin->txt('records_right');
                break;
        }

        $left = [];
        $right = [];
        $record = $data['record'] ?? [];
        $rows = $record['rows'] ?? [];

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $left_account = $row['leftAccountText'] ?? '';
                $right_account = $row['rightAccountText'] ?? '';

                $left_value = empty($row['leftValueMoney']) ? '' : $this->plugin->toString($row['leftValueMoney'], $this->parent->getPrecision(), $this->parent->getThousandsDelim());
                $right_value = empty($row['rightValueMoney']) ? '' : $this->plugin->toString($row['rightValueMoney'], $this->parent->getPrecision(), $this->parent->getThousandsDelim());

                if (!empty($left_account) || !empty($left_value)) {
                    $left[] = $left_account . ' ' . $left_value;
                }
                if (!empty($right_account) || !empty($right_value)) {
                    $right[] = $right_account . ' ' . $right_value;
                }
            }
        }

        $text = '';
        if (!empty($left) || !empty($right)) {
            $text = ($data['headerLeft'] ?? $header_left) . ' (' . implode('; ', $left)
                . ') '
                . ($data['headerRight'] ?? $header_right) . ' (' . implode('; ', $right)
                . ') ';
        }

        return $text;
    }

    /**
     * Calculate the reached points for the working data
     * The reached points are directly merged in the working_data array
     */
    public function calculateReachedPoints()
    {
        if (!isset($this->booking_data) or !isset($this->working_data)) {
            return false;
        }

        // use copy of booking record to forget calculation flags at the end
        $correct = $this->booking_data['record'] ?? [];

        // use reference of working record to add calculated results
        $student = $this->working_data['record'] ?? [];

        // left and right side can be evaluated equally
        foreach (array('left', 'right') as $side) {
            $uside = ucfirst($side);	// side as suffix

            $sumPoints = 0;				// sum of points
            $sumMatches = 0;			// sum of matching rows
            $matched = [];              // notion of matched correct bookings
            $matchOrder = true;			// assume a correct matching order, set to false on break
            $lastMatch = -1;			// last matching correct row, start with -1 (none)

            // scan the student rows of this side
            for ($s = 0; $s < count($student['rows'] ?? []); $s++) {
                $srow = $student['rows'][$s];	// allow manipulation

                // find matching entry in correct rows of this side
                for ($c = 0; $c < count($correct['rows'] ?? []); $c++) {
                    $crow = $correct['rows'][$c];	// allow manipulation

                    if (($srow[$side . 'AccountNum'] ?? '') == ($crow[$side . 'AccountNum'] ?? '')
                        && $this->parent->equals($srow[$side . 'ValueMoney'] ?? 0, $crow[$side . 'ValueMoney'] ?? 0)
                        && empty($matched[$c][$side])) {
                        $student['rows'][$s][$side . 'Points'] = ($crow[$side . 'Points'] ?? 0);
                        $matched[$c][$side] = true;

                        $sumPoints += ($crow[$side . 'Points'] ?? 0);
                        $sumMatches++;

                        // order is broken when current matching row is before last matching row
                        if ($c <= $lastMatch) {
                            $matchOrder = false;
                        }
                        $lastMatch = $c;
                    }
                }
            }

            // store the pure sum of points for this side (without bonus)
            $student['sumPoints' . $uside] = $sumPoints;

            // give bonus for correct order if at least two correct bookings exist
            if ($sumMatches > 1 && $matchOrder) {
                $student['bonusOrder' . $uside] = $correct['bonusOrder' . $uside] ?? 0;
            }
        }

        // total sum of points reached so far
        $totalPoints =
            ($student['sumPointsLeft'] ?? 0) + ($student['bonusOrderLeft'] ?? 0)
            + ($student['sumPointsRight'] ?? 0) + ($student['bonusOrderRight'] ?? 0);

        // malus for exceeding number of records on a side
        foreach (array('Left', 'Right') as $uside) {
            if (($student['count' . $uside] ?? 0) > ($correct['count' . $uside] ?? 0)) {
                switch ($this->booking_data['type']) {
                    case 't-account':
                        // limit the malus to the points reached on this side including bonus
                        $limit = -(($student['sumPoints' . $uside] ?? 0) + ($student['bonusOrder' . $uside] ?? 0));
                        break;
                    case 'records':
                        // limit the malus to the points reached so far
                        // in case of right side, the left side malus is already applied
                        // not nice, but the flash version calculated this way
                        $limit = -$totalPoints;
                        break;
                    default:
                        $limit = 0;
                }
                $student['malusCount' . $uside] = max($correct['malusCount' . $uside] ?? 0, $limit);
                $totalPoints += ($student['malusCount' . $uside] ?? 0);
            }
        }

        // give malus for different sum of values on both sides
        if (!$this->parent->equals($student['sumValuesLeft'] ?? 0, $student['sumValuesRight'] ?? 0)) {
            // limit the malus to the points reached so far
            $student['malusSumsDiffer'] = max($correct['malusSumsDiffer'] ?? 0, -$totalPoints);
            $totalPoints += $student['malusSumsDiffer'] ?? 0;
        }

        // the total points for this part will not be negative
        $this->working_data['record'] = $student;
        $this->working_data['sumPoints'] = $totalPoints;
        return $totalPoints;
    }
}
