<?php

/**
 * Copyright (c) 2013 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv2, see LICENSE
 */

use ILIAS\GlobalScreen\Scope\Layout\MetaContent\MetaContent;

/**
 * Accounting question GUI representation
 *
 * @author    Fred Neumann <fred.neumann@fim.uni-erlangen.de>
 * @version    $Id: $
 * @ingroup    ModulesTestQuestionPool
 * @ilCtrl_isCalledBy assAccountingQuestionGUI: ilObjQuestionPoolGUI, ilObjTestGUI
 * @ilCtrl_Calls assAccountingQuestionGUI: ilFormPropertyDispatchGUI
 * */
class assAccountingQuestionGUI extends assQuestionGUI
{
    /**
     * @const	string	URL base path for including special javascript and css files
     */
    public const URL_PATH = "./Customizing/global/plugins/Modules/TestQuestionPool/Questions/assAccountingQuestion";

    /** @var ilassAccountingQuestionPlugin */
    protected $plugin = null;


    /** @var ilPropertyFormGUI */
    protected $form;

    private MetaContent $meta;

    /**
     * assAccountingQuestionGUI constructor
     *
     * The constructor takes possible arguments and creates an instance of the assAccountingQuestionGUI object.
     *
     * @param integer $id The database id of a question object
     * @access public
     */
    public function __construct($id = -1)
    {
        global $DIC;
        parent::__construct();
        /** @var ilComponentFactory $component_factory */
        $component_factory = $DIC["component.factory"];
        $this->plugin = $component_factory->getPlugin('accqst');
        $this->object = new assAccountingQuestion();
        if ($id >= 0) {
            $this->object->loadFromDb($id);
        }

        $this->meta = $DIC->globalScreen()->layout()->meta();
        $this->meta->addCss(self::URL_PATH . '/templates/accqstStyles.css');
    }

    protected function getAdditionalEditQuestionCommands(): array
    {
        return ['saveAddBooking'];
    }

    public function isSaveCommand(): bool
    {
        return parent::isSaveCommand() || $this->ctrl->getCmd() == 'saveAddBooking';
    }

    /**
     * Command: edit the question
     * @see assNumericGUI::editQuestion()
     */
    public function editQuestion(
        bool $checkonly = false,
        ?bool $is_save_cmd = null
    ): bool {
        $save = $is_save_cmd ?? $this->isSaveCommand();

        $add_booking = $save && ($this->plugin->request()->getString('position_0') !== '');
        $this->initQuestionForm($add_booking);

        $errors = false;

        if ($save) {
            $this->form->setValuesByPost();
            $errors = !$this->form->checkInput();
            $this->form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes

            if ($errors) {
                $checkonly = false;
            }
        }

        if (!$checkonly) {
            $this->renderEditForm($this->form);
        }

        return $errors;
    }


    /**
     * Command: save and add a new booking part
     */
    public function saveAddBooking()
    {
        if (parent::saveQuestion()) {
            $form = $this->initQuestionForm(true);
            $this->renderEditForm($this->form);
        }
    }

    /**
     * Command: Delete a part of the question
     */
    public function deletePart()
    {
        if ($this->object->deletePart($this->plugin->request()->getInt('part_id'))) {
            $this->tpl->setOnScreenMessage('success', $this->plugin->txt('part_deleted'), true);
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt('part_not_deleted'), true);
        }

        $this->ctrl->redirect($this, 'editQuestion');
    }

    /**
     * Creates an output of the edit form for the question
     *
     * @param    boolean  $add_booking      add a new booking to the form
     */
    private function initQuestionForm($add_booking = false)
    {
        // use it without return value to just show the "question in use message"
        $this->outQuestionType();

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->getQuestionTypeTranslation());
        $form->setMultipart(false);
        $form->setTableWidth("100%");
        $form->setId("accqst");

        // title, author, description, question, working time (assessment mode)
        $this->addBasicQuestionFormProperties($form);

        // maximum points
        $item = new ilNonEditableValueGUI($this->plugin->txt('max_score'));
        $item->setValue($this->object->getMaximumPoints());
        $form->addItem($item);

        // accounts XML definition
        $item = new ilTextAreaInputGUI($this->plugin->txt('accounts_xml'), 'accounts_xml');
        $item->setInfo($this->plugin->txt('accounts_xml_info'));
        $item->setRows(10);
        $item->setValue($this->object->getAccountsXML());

        // upload accounts definition
        $subitem = new ilFileInputGUI($this->plugin->txt('accounts_file'), 'accounts_file');
        $subitem->setSuffixes(array('xml'));
        $item->addSubItem($subitem);

        // download accounts definition
        if (strlen(($this->object->getAccountsXML()))) {
            $this->ctrl->setParameter($this, 'xmltype', 'accounts');
            $tpl = $this->plugin->getTemplate('tpl.il_as_qpl_accqst_form_custom.html');
            $tpl->setCurrentBlock('button');
            $tpl->setVariable('BUTTON_HREF', $this->ctrl->getLinkTarget($this, 'downloadXml'));
            $tpl->setVariable('BUTTON_TEXT', $this->plugin->txt('download_accounts_xml'));
            $tpl->ParseCurrentBlock();

            $subitem = new ilCustomInputGUI('');
            $subitem->setHTML($tpl->get());
            $item->addSubItem($subitem);
        }
        $form->addItem($item);


        // variables XML definition
        $item = new ilTextAreaInputGUI($this->plugin->txt('variables_xml'), 'variables_xml');
        $item->setInfo($this->plugin->txt('variables_xml_info'));
        $item->setRows(10);
        $item->setValue($this->object->getVariablesXML());

        if ($this->plugin->isDebug()) {
            $error = '';
            if (!$this->object->calculateVariables()) {
                $error = $this->object->getAnalyzeError() . "\n";
            }
            $dump = [];
            foreach ($this->object->getVariables() as $name => $var) {
                $dump[$var->name] = get_object_vars($var);
            }
            $dump = print_r($dump, true);
            $dump = str_replace('{', '&#123;', $dump);
            $dump = str_replace('}', '&#125;', $dump);

            $subitem = new ilCustomInputGUI('DEBUG');
            $subitem->setHTML($error . $dump);
            $item->addSubItem($subitem);
        }

        // upload variables definition
        $subitem = new ilFileInputGUI($this->plugin->txt('variables_file'), 'variables_file');
        $subitem->setSuffixes(array('xml'));
        $item->addSubItem($subitem);

        // download variables definition
        if (strlen(($this->object->getVariablesXML()))) {
            $this->ctrl->setParameter($this, 'xmltype', 'variables');
            $tpl = $this->plugin->getTemplate('tpl.il_as_qpl_accqst_form_custom.html');
            $tpl->setCurrentBlock('button');
            $tpl->setVariable('BUTTON_HREF', $this->ctrl->getLinkTarget($this, 'downloadXml'));
            $tpl->setVariable('BUTTON_TEXT', $this->plugin->txt('download_variables_xml'));
            $tpl->ParseCurrentBlock();

            $subitem = new ilcustomInputGUI('');
            $subitem->setHTML($tpl->get());
            $item->addSubItem($subitem);
        }

        $form->addItem($item);


        // calculation tolerance
        $item = new ilNumberInputGUI($this->plugin->txt('precision'), 'precision');
        $item->setInfo($this->plugin->txt('precision_info'));
        $item->setSize(2);
        $item->allowDecimals(false);
        $item->setMinValue(0);
        $item->setMaxValue(10, true);
        $item->setValue($this->object->getPrecision());
        $form->addItem($item);

        // thousands delimiter type
        if ($this->plugin->getConfig()->thousands_delim_per_question) {
            $td = new ilSelectInputGUI($this->plugin->txt('thousands_delim_type'), 'thousands_delim_type');
            $td->setInfo($this->plugin->txt('thousands_delim_type_info'));
            $td->setOptions(array(
                '' => sprintf($this->plugin->txt('delim_default'), $this->plugin->getConfig()->getThousandsDelimText()),
                assAccountingQuestionConfig::DELIM_NONE => $this->plugin->txt('delim_none'),
                assAccountingQuestionConfig::DELIM_DOT => $this->plugin->txt('delim_dot'),
                assAccountingQuestionConfig::DELIM_SPACE => $this->plugin->txt('delim_space'),
            ));
            $td->setValue($this->object->getThousandsDelimType());
            $form->addItem($td);
        }

        // add the existing booking parts
        $parts = $this->object->getParts();
        $i = 1;
        foreach ($parts as $part_obj) {
            $this->initPartProperties($form, $part_obj, $i++);
            if ($part_obj->getPartId() == 0) {
                // new booking is already posted
                $add_booking = false;
            }
        }

        // add a new booking part
        if (count($parts) == 0 || $add_booking) {
            $this->initPartProperties($form, null, $i);
        } else {
            $form->addCommandButton('saveAddBooking', $this->plugin->txt('add_booking'));
        }

        $this->populateTaxonomyFormSection($form);
        $this->addQuestionFormCommandButtons($form);
        $this->form = $form;
    }


    /**
     * add the properties of a question part to the form
     *
     * @param ilPropertyFormGUI $form
     * @param assAccountingQuestionPart $part_obj
     * @param integer  $counter of the question part
     */
    private function initPartProperties($form, $part_obj = null, $counter = 1)
    {
        // Use a dummy part object for a new booking definition
        if (!isset($part_obj)) {
            $part_obj = new assAccountingQuestionPart($this->object);
            $part_obj->setPosition($counter);
        }

        // Title
        $item = new ilFormSectionHeaderGUI();
        $item->setTitle($this->plugin->txt('accounting_table') . ' ' . $counter);
        $form->addItem($item);

        // Position
        $item = new ilNumberInputGUI($this->plugin->txt('position'), 'position_' . $part_obj->getPartId());
        $item->setSize(2);
        $item->setDecimals(1);
        $item->SetInfo($this->plugin->txt('position_info'));
        $item->setValue(sprintf("%01.1f", $part_obj->getPosition()));
        $form->addItem($item);

        // Maximum Points
        $item = new ilNonEditableValueGUI($this->plugin->txt('max_score'));
        $item->setValue($part_obj->getMaxPoints());
        $form->addItem($item);


        // Text
        $item = new ilTextAreaInputGUI($this->plugin->txt("question_part"), 'text_' . $part_obj->getPartId());
        $item->setValue(self::prepareTextareaOutput($part_obj->getText()));
        $item->setRows(10);
        $item->setCols(80);
        if (!$this->object->getSelfAssessmentEditingMode()) {
            $item->setUseRte(true);
            $item->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
            $item->addPlugin("latex");
            $item->addButton("latex");
            $item->addButton("pastelatex");
            $item->setRTESupport($this->object->getId(), "qpl", "assessment");
        } else {
            $item->setRteTags(ilAssSelfAssessmentQuestionFormatter::getSelfAssessmentTags());
            $item->setUseTagsForRteOnly(false);
        }
        $form->addItem($item);

        // Booking XML definition
        $item = new ilTextAreaInputGUI($this->plugin->txt('booking_xml'), 'booking_xml_' . $part_obj->getPartId());
        $item->setInfo($this->plugin->txt('booking_xml_info'));
        $item->setRows(10);
        $item->setValue($part_obj->getBookingXML());

        // Booking file
        $subitem = new ilFileInputGUI($this->plugin->txt('booking_file'), "booking_file_" . $part_obj->getPartId());
        $subitem->setSuffixes(array('xml'));
        $item->addSubItem($subitem);

        // Download button
        if (strlen($part_obj->getBookingXML())) {
            $tpl = $this->plugin->getTemplate('tpl.il_as_qpl_accqst_form_custom.html');
            $this->ctrl->setParameter($this, 'xmltype', 'booking');
            $this->ctrl->setParameter($this, 'part_id', $part_obj->getPartId());
            $tpl->setCurrentBlock('button');
            $tpl->setVariable('BUTTON_HREF', $this->ctrl->getLinkTarget($this, 'downloadXml'));
            $tpl->setVariable('BUTTON_TEXT', $this->plugin->txt('download_booking_xml'));
            $tpl->ParseCurrentBlock();

            $subitem = new ilcustomInputGUI('');
            $subitem->setHTML($tpl->get());
            $item->addSubItem($subitem);
        }
        $form->addItem($item);


        // Delete Button
        if ($part_obj->getPartId()) {
            $tpl = $this->plugin->getTemplate('tpl.il_as_qpl_accqst_form_custom.html');
            $tpl->setCurrentBlock('button');
            $this->ctrl->setParameter($this, 'part_id', $part_obj->getPartId());
            $tpl->setVariable('BUTTON_HREF', $this->ctrl->getLinkTarget($this, 'deletePart'));
            $tpl->setVariable('BUTTON_TEXT', $this->plugin->txt('delete_accounting_table'));
            $tpl->ParseCurrentBlock();

            $item = new ilcustomInputGUI();
            $item->setHTML($tpl->get());
            $form->addItem($item);
        }
    }


    /**
     * Evaluates a posted edit form and writes the form data in the question object
     * (called frm generic commands in assQuestionGUI)
     *
     * @return integer    0: question can be saved / 1: form is not complete
     */
    protected function writePostData($always = false): int
    {
        $hasErrors = (!$always) ? $this->editQuestion(true) : false;

        if (!$hasErrors) {
            $error = false;

            // write the basic data
            $this->writeQuestionGenericPostData();

            // get the accounts definition either by file upload or post
            if ($this->plugin->request()->hasFile('accounts_file')) {
                $accounts_xml = $this->plugin->request()->getFileContent('accounts_file');
            } else {
                $accounts_xml = $this->plugin->request()->getXml('accounts_xml');
            }
            $this->form->getItemByPostVar('accounts_xml')->setValue($accounts_xml);

            // check the accounts definition but save it anyway
            if (!$this->object->setAccountsXML($accounts_xml)) {
                $e = $this->plugin->txt('xml_accounts_error');
                $this->form->getItemByPostVar('accounts_xml')->setAlert($e);
                $error = true;
            }

            // get the variables definition either by file upload or post
            if ($this->plugin->request()->hasFile('variables_file')) {
                $variables_xml = $this->plugin->request()->getFileContent('variables_file');
            } else {
                $variables_xml = $this->plugin->request()->getXml('variables_xml');
            }
            $this->form->getItemByPostVar('variables_xml')->setValue($variables_xml);

            // check the variables XML but save it anyway
            if (!$this->object->setVariablesXML($variables_xml)) {
                $e = $this->plugin->txt('xml_variables_error') . '<br />' . $this->object->getAnalyzeError();
                $this->form->getItemByPostVar('variables_xml')->setAlert($e);
                $error = true;
            } elseif (!$this->object->calculateVariables()) {
                $e = $this->plugin->txt('xml_variables_error') . '<br />' . $this->object->getAnalyzeError();
                $this->form->getItemByPostVar('variables_xml')->setAlert($e);
                $error = true;
            }

            // calculation tolerance
            $this->object->setPrecision($this->plugin->request()->getInt('precision'));
            $this->object->setLifecycle(ilAssQuestionLifecycle::getInstance($this->plugin->request()->getString('lifecycle')));

            // thousands delimiter type
            if ($this->plugin->getConfig()->thousands_delim_per_question) {
                $this->object->setThousandsDelimType($this->plugin->request()->getString('thousands_delim_type'));
            }

            // sort the part positions
            $positions = array();
            foreach ($this->plugin->request()->getIntArray('parts') as $part_id) {
                $positions[$part_id] = $this->plugin->request()->getString('position_' . $part_id);
            }

            $positions = [];
            foreach ($this->object->getParts() as $part) {
                $positions[$part->getPartId()] = $this->plugin->request()->getString('position_' . $part->getPartId());
            }
            // a new part is added
            if ($this->plugin->request()->getString('position_0') !== '') {
                $positions[0] = $this->plugin->request()->getString('position_0');
            }
            asort($positions, SORT_NUMERIC);

            // set the part data
            $i = 1;
            foreach ($positions as $part_id => $pos) {
                $pos = $i++;

                // save the question part
                // a new part object is created if part_id is 0
                $part_obj = $this->object->getPart($part_id);
                $part_obj->setText($this->form->getInput('text_' . $part_id));
                $part_obj->setPosition($pos);
                if ($this->plugin->request()->hasFile('booking_file_' . $part_id)) {
                    $booking_xml = $this->plugin->request()->getFileContent('booking_file_' . $part_id);
                } else {
                    $booking_xml = $this->plugin->request()->getXml('booking_xml_' . $part_id);
                }
                $this->form->getItemByPostVar('booking_xml_' . $part_id)->setValue($booking_xml);

                // check the booking definition but save it anyway
                if (!$part_obj->setBookingXML($booking_xml)) {
                    $e = sprintf($this->plugin->txt('xml_booking_error'), $pos);
                    $this->form->getItemByPostVar('booking_xml_' . $part_id)->setAlert($e);
                    $error = true;
                }
            }

            if ($error) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('form_input_not_valid'), true);
                $this->renderEditForm($this->form);
                return 1;
            }

            // save taxonomy assignment
            $this->saveTaxonomyAssignments();

            // indicator to save the question
            return 0;

        } else {
            // indicator to show the edit form with errors
            $this->renderEditForm($this->form);
            return 1;
        }
    }


    /**
     * Command: Download an xml file (accounts or booking)
     *
     * The file type is given in $_GET['xmltype']
     * The part ID is given in    $_GET['part_id']
     */
    public function downloadXml()
    {
        switch ($this->plugin->request()->getString('xmltype')) {
            case 'accounts':
                $file = $this->object->getAccountsXML();
                $filename = 'accounts' . $this->object->getId() . '.xml';
                break;

            case 'booking':
                $part_obj = $this->object->getPart($this->plugin->request()->getInt('part_id'));
                $file = $part_obj->getBookingXML();
                $filename = 'booking' . $part_obj->getPartId() . '.xml';
                break;

            case 'variables':
                $file = $this->object->getVariablesXML();
                $filename = 'variables' . $this->object->getId() . '.xml';
                break;


            default:
                $this->editQuestion();
                return;
        }

        ilUtil::deliverData($file, $filename, 'text/xml');
    }


    /**
     * Get the HTML output of the question for a test
     *
     * @param integer $active_id The active user id
     * @param integer $pass The test pass
     * @param boolean $is_question_postponed Question is postponed
     * @param boolean $user_post_solutions User post solutions
     * @param boolean $show_specific_inline_feedback Show a feedback
     * @return string
     */
    public function getTestOutput(
        int $active_id,
        int $pass,
        bool $is_question_postponed = false,
        array|bool $user_post_solutions = false,
        bool $show_specific_inline_feedback = false
    ): string {
        $solution = null;
        // get the solution of the user for the active pass or from the last pass if allowed
        if ($active_id) {
            // get the stored variables (always authorized)
            // init and store them if they were not yet initialized
            $varsolution = $this->object->getSolutionStored($active_id, $pass, true);
            if (!$this->object->initVariablesFromUserSolution($varsolution)) {
                foreach ($this->object->addVariablesToUserSolution() as $value1 => $value2) {
                    $this->object->saveCurrentSolution($active_id, $pass, $value1, $value2, true);
                }
            }

            // get preferrably the intermediate solution
            $solution = $this->object->getSolutionStored($active_id, $pass, null);
        }

        $questionoutput = $this->getQuestionOutput($solution);
        $pageoutput = $this->outQuestionPage("", $is_question_postponed, $active_id, $questionoutput);
        return $pageoutput;
    }


    /**
     * Get the html output of the question for different usages (preview, test)
     *
     * @param  array   $a_solution  value1 => value2
     * @return string
     * @see assAccountingQuestion::getSolutionSubmit()
     */
    private function getQuestionOutput($a_solution = array())
    {

        // init the javascript support for answer input
        // NOTE: the own URL suffix does not work with addJavascript
        global $DIC;
        $this->meta->addCss(self::URL_PATH . '/js/combobox/css/bootstrap-combobox.css');
        $this->meta->addJs(self::URL_PATH . '/js/combobox/js/bootstrap-combobox.js');
        $this->meta->addJs(self::URL_PATH . '/js/ilAccountingQuestion.js');

        if ($this->object->getAccountsSearchTitle()) {
            $this->meta->addOnLoadCode('il.AccountingQuestion.init({nameMatching:true});');
        } else {
            $this->meta->addOnLoadCode('il.AccountingQuestion.init({nameMatching:false});');
        }

        // get the question output template
        $tpl = $this->plugin->getTemplate("tpl.il_as_qpl_accqst_output.html");

        if ($this->plugin->isDebug()) {
            $debug = [];
            foreach ($this->object->getVariables() as $name => $var) {
                $debug[$name] = $var->value;
            }
            $tpl->setVariable('DEBUG', print_r($debug, true));
        }

        // general question text
        $questiontext = $this->object->getQuestion();

        $tpl->setVariable("QUESTION_TEXT", self::prepareTextareaOutput($questiontext, true));

        $parts = $this->object->getParts();
        $accounts = $this->object->getAccountsData();

        $tpl->setVariable("QUESTION_ID", $this->object->getId());

        $solutionParts = $this->object->getSolutionParts($a_solution);
        foreach ($parts as $part_obj) {
            $part_id = $part_obj->getPartId();
            $part_obj->setWorkingXML($solutionParts[$part_id] ?? '');
            $w_data = $part_obj->getWorkingData();

            $tpl->setCurrentBlock('question_part');
            $tpl->setVariable("PART_QUESTION_TEXT", $part_obj->getText());

            //Fill accounting table header
            $tpl->setVariable("FIRST_COLUMN_HEADER", $part_obj->getBookingData('headerLeft'));
            $tpl->setVariable("SECOND_COLUMN_HEADER", "");
            $tpl->setVariable("THIRD_COLUMN_HEADER", $part_obj->getBookingData('headerCenter'));
            $tpl->setVariable("FOURTH_COLUMN_HEADER", "");
            $tpl->setVariable("FIFTH_COLUMN_HEADER", $part_obj->getBookingData('headerRight'));

            $num_rows = $part_obj->getBookingData('showLines') ?? 0;

            //LEFT
            for ($i = 0; $i < $num_rows; $i++) {
                $tpl->setCurrentBlock('accounting_left');
                $tpl->setVariable("QUESTION_ID", $this->object->getId());
                $tpl->setVariable("QUESTION_PART", $part_id);
                $tpl->setVariable("ROW", $i);
                foreach ($accounts as $account) {
                    $tpl->setCurrentBlock('accounts_left');
                    $tpl->setVariable("ACCOUNT_VALUE", $account['number'] ?? '');
                    $tpl->setVariable("ACCOUNT_TEXT", $account['text'] ?? '');
                    $tpl->parseCurrentBlock();
                }
                $tpl->setCurrentBlock('accounting_left');
                $tpl->setVariable("SELECTED_ACCOUNT_LEFT_VALUE", ($w_data["record"]['rows'][$i]['leftAccountNum'] ?? ''));
                $tpl->setVariable("SELECTED_LEFT_ACCOUNT", ($w_data["record"]['rows'][$i]['leftAccountRaw'] ?? ''));
                $tpl->setVariable("DEBIT_AMOUNT", ($w_data["record"]['rows'][$i]['leftValueRaw'] ?? ''));
                $tpl->parseCurrentBlock();
            }

            for ($i = 0; $i < $num_rows; $i++) {
                $tpl->setCurrentBlock('accounting_right');
                $tpl->setVariable("QUESTION_ID", $this->object->getId());
                $tpl->setVariable("QUESTION_PART", $part_id);
                $tpl->setVariable("ROW", $i);
                foreach ($accounts as $account) {
                    $tpl->setCurrentBlock('accounts_right');
                    $tpl->setVariable("ACCOUNT_VALUE", ($account['number'] ?? ''));
                    $tpl->setVariable("ACCOUNT_TEXT", ($account['text'] ?? ''));
                    $tpl->parseCurrentBlock();
                }
                $tpl->setCurrentBlock('accounting_right');
                $tpl->setVariable("SELECTED_ACCOUNT_RIGHT_VALUE", ($w_data["record"]['rows'][$i]['rightAccountNum'] ?? ''));
                $tpl->setVariable("SELECTED_RIGHT_ACCOUNT", ($w_data["record"]['rows'][$i]['rightAccountRaw'] ?? ''));
                $tpl->setVariable("CREDIT_AMOUNT", ($w_data["record"]['rows'][$i]['rightValueRaw'] ?? ''));
                $tpl->parseCurrentBlock();
            }

            $tpl->setCurrentBlock('question_part');
            $tpl->parseCurrentBlock();

            if ($this->plugin->isDebug()) {
                $part_debug = print_r($part_obj->getBookingData(), true);
                $tpl->setVariable('PART_DEBUG', ilLegacyFormElementsUtil::prepareFormOutput($part_debug));
            }
        }

        return $tpl->get();
    }


    /**
     * Get the output for question preview
     * (called from ilObjQuestionPoolGUI)
     *
     * @param boolean $show_question_only   show only the question instead of embedding page (true/false)
     * @return string
     */
    public function getPreview(
        bool $show_question_only = false,
        bool $show_inline_feedback = false
    ): string {
        if (is_object($this->getPreviewSession())) {
            // get or create the variable values
            $solution = (array) $this->getPreviewSession()->getParticipantsSolution();

            if (!$this->object->initVariablesFromUserSolution($solution)) {
                $solution = $this->object->addVariablesToUserSolution($solution);
                $this->getPreviewSession()->setParticipantsSolution($solution);
            }
            // show interactive preview
            $questionoutput = $this->getQuestionOutput($solution);
        } else {
            // show empty tables for printing or editing
            $this->object->calculateVariables();
            $questionoutput = $this->getPaperOutput();
        }

        if (!$show_question_only) {
            // get page object output
            $questionoutput = $this->getILIASPage($questionoutput);
        }
        return $questionoutput;
    }


    /**
     * Get the output for printing the question on paper
     *
     * @return    string    html code of the paper version
     */
    private function getPaperOutput()
    {
        // get the question output template
        $tpl = $this->plugin->getTemplate("tpl.il_as_qpl_accqst_output_paper.html");

        // general question text
        $questiontext = $this->object->getQuestion();
        $tpl->setVariable("QUESTIONTEXT", self::prepareTextareaOutput($questiontext, true));

        // add all answering parts
        $parts = $this->object->getParts();
        foreach ($parts as $part_obj) {
            // intro text of part
            if ($text = $part_obj->getText()) {
                $tpl->setCurrentBlock('part_intro');
                $tpl->setVariable('TEXT', $text);
                $tpl->parseCurrentBlock();
            }

            $data = $part_obj->getBookingData();
            $tpl->setVariable('TABLE', $this->getPaperTable($data));

            $tpl->setCurrentBlock('part');
            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
    }

    /**
     * Get the HTML output of an empty part
     *
     * @param array  $data      part data
     * @return string
     */
    private function getPaperTable($data)
    {
        // get the table output template
        $tpl = $this->plugin->getTemplate("tpl.il_as_qpl_accqst_output_paper_table.html", true, true);

        // table title
        $tpl->setCurrentBlock('table_title');
        if ($data['type'] == 'records') {
            $tpl->setVariable('TXT_TITLE_LEFT', $data['headerLeft'] ?? $this->plugin->txt('records_left'));
            $tpl->setVariable('TXT_TITLE_RIGHT', $data['headerRight'] ?? $this->plugin->txt('records_right'));
            $tpl->setVariable('TXT_TITLE_CENTER', $data['headerCenter'] ?? $this->plugin->txt('records_center'));
        } else {
            $tpl->setVariable('TXT_TITLE_LEFT', $data['headerLeft'] ?? $this->plugin->txt('t_account_left'));
            $tpl->setVariable('TXT_TITLE_RIGHT', $data['headerRight'] ?? $this->plugin->txt('t_account_right'));
            $tpl->setVariable('TXT_TITLE_CENTER', $data['headerCenter'] ?? $this->plugin->txt('t_account_center'));
        }
        $tpl->parseCurrentBlock();


        // booking header
        $tpl->setCurrentBlock('head_row');
        $tpl->setVariable('TXT_ACCOUNT', $this->plugin->txt('booking_account'));
        $tpl->setVariable('TXT_VALUE', $this->plugin->txt('money_value'));
        $tpl->parseCurrentBlock();


        // rows
        for ($i = 0; $i < $data['showLines']; $i++) {
            $tpl->touchBlock('booking_row');
        }

        // sums
        $tpl->setCurrentBlock('sum_row');
        $tpl->setVariable('TXT_SUM', $this->plugin->txt('label_sum') . ': ');
        $tpl->parseCurrentBlock();

        return $tpl->get();
    }

    /**
     * Get the question solution output
     * (called from assQuestionGUI)
     *
     * An activated DEBUG in client.ini to show the boolean parameters
     *
     * @param integer $active_id 				The active user id
     * @param integer $pass 					The test pass
     * @param boolean $graphicalOutput 			Show visual feedback for right/wrong answers
     * @param boolean $result_output 			Show the reached points for parts of the question
     * @param boolean $show_question_only 		Show the question without the ILIAS content around
     * @param boolean $show_feedback 			Show the question feedback
     * @param boolean $show_correct_solution 	Show the correct solution instead of the user solution
     * @param boolean $show_manual_scoring 		Show specific information for the manual scoring output
     * @param boolean $show_question_text 		Show the question text
     * @return string                       	The solution output of the question as HTML code
     */
    public function getSolutionOutput(
        $active_id,
        $pass = null,
        $graphicalOutput = false,
        $result_output = false,
        $show_question_only = true,
        $show_feedback = false,
        $show_correct_solution = false,
        $show_manual_scoring = false,
        $show_question_text = true,
        $show_inline_feedback = true
    ): string {

        return $this->renderSolutionOutput(
            null,
            $active_id,
            $pass,
            $graphicalOutput,
            $result_output,
            $show_question_only,
            $show_feedback,
            $show_correct_solution,
            $show_manual_scoring,
            $show_question_text,
            false,
            $show_inline_feedback,
        );
    }


    public function renderSolutionOutput(
        mixed $user_solutions,
        int $active_id,
        int $pass = null,
        bool $graphical_output = false,
        bool $result_output = false,
        bool $show_question_only = true,
        bool $show_feedback = false,
        bool $show_correct_solution = false,
        bool $show_manual_scoring = false,
        bool $show_question_text = true,
        bool $show_autosave_title = false,
        bool $show_inline_feedback = false
    ): string {
        global $DIC;
        $ilCtrl = $DIC->ctrl();
        $ilAccess = $DIC->access();

        $show_grading_details = false;
        $grading_details_note = '';

        // adjust the parameters for special cases
        if ($ilCtrl->getCmd() == 'print'
            and ($ilCtrl->getCmdClass() == 'ilobjquestionpoolgui' or $ilCtrl->getCmdClass() == 'ilobjtestgui')) {
            switch ($this->plugin->request()->getString('output')) {
                case 'detailed':
                    $show_correct_solution = true;
                    $show_grading_details = false;
                    break;
                case 'detailed_scoring':
                default:
                    $show_correct_solution = true;
                    $show_grading_details = true;
                    break;
            }
        } elseif (is_object($this->getPreviewSession())) {
            $show_correct_solution = true; // needed!
            $show_grading_details = true;
        } elseif ($show_manual_scoring) {
            $show_grading_details = true;
        } elseif ($ilAccess->checkAccess('write', '', $this->plugin->request()->getInt('ref_id'))) {
            $show_grading_details = true;
            $grading_details_note = $this->plugin->txt('grading_details_note');
        }

        if (is_array($user_solutions)) {
            // given from raw solution values by external call (maybe intermediate solution)
            $user_solutions = $this->object->convertStoredSolutionValues($user_solutions);
        } elseif (is_object($this->getPreviewSession())) {
            // preview input
            $user_solutions = (array) $this->getPreviewSession()->getParticipantsSolution();
        } else {
            // standard call from getSolutionOutput() in test
            $user_solutions = $this->object->getSolutionStored($active_id, $pass, true);
        }

        $solutionParts = $this->object->getSolutionParts($user_solutions);
        $this->object->initVariablesFromUserSolution($user_solutions);

        // get the output template
        $template = $this->plugin->getTemplate("tpl.il_as_qpl_accqst_output_solution.html");

        foreach ($this->object->getParts() as $part_obj) {
            $part_id = $part_obj->getPartId();

            if ($show_correct_solution) {
                $table_data = $part_obj->getBookingData();
            } else {
                $part_obj->setWorkingXML($solutionParts[$part_id] ?? '');
                $part_obj->calculateReachedPoints();
                $table_data = $part_obj->getWorkingData();
            }

            // show the part table
            $template->setCurrentBlock('part');

            // show the part's intro text
            if ($part_obj->getText()) {
                $template->setVariable('TEXT', self::prepareTextareaOutput($part_obj->getText(), true));
            }

            // show the user solution or the correct solution
            // include the grading details only when they are freshly calculated
            // a stored solution from the flash-base version provides its own textual details
            $template->setVariable('SOLUTION', $this->getSolutionTable($table_data, $show_grading_details));

            if ($show_grading_details) {
                if ($show_correct_solution) {
                    // show the total points for the part
                    $template->setVariable('TXT_SCORE', $this->plugin->txt('max_score') . ': ');
                    $template->setVariable('SCORE', $part_obj->getMaxPoints());
                } else {
                    // use the reached points that are calculated from analyzing the working data
                    $template->setVariable('TXT_SCORE', $this->plugin->txt('reached_score') . ': ');
                    $template->setVariable('SCORE', $table_data["sumPoints"] ?? 0);
                }
            }

            $template->parseCurrentBlock();
        }


        // add note that grading details are only shown because user has write access
        if ((!empty($grading_details_note))) {
            $template->setVariable('GRADING_DETAILS_NOTE', $grading_details_note);
        }

        // add the question text
        $questiontext = $this->object->getQuestion();
        if ($show_question_text) {
            $template->setVariable("QUESTIONTEXT", self::prepareTextareaOutput($questiontext, true));
        }

        if ($this->plugin->isDebug()) {
            $template->setVariable("DEBUG_RESULT_OUTPUT", $result_output);
            $template->setVariable("DEBUG_SHOW_QUESTION_ONLY", $show_question_only);
            $template->setVariable("DEBUG_SHOW_FEEDBACK", $show_feedback);
            $template->setVariable("DEBUG_SHOW_CORRECT_SOLUTION", $show_correct_solution);
            $template->setVariable("DEBUG_SHOW_MANUAL_SCORING", $show_manual_scoring);
            $template->setVariable("DEBUG_SHOW_QUESTION_TEXT", $show_question_text);
            $template->setVariable("DEBUG_SHOW_GRADING_DETAILS", $show_grading_details);
        }

        $questionoutput = $template->get();

        // get the surrounding template
        $solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", true, true, "components/ILIAS/TestQuestionPool");
        $solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);

        // add generic feedback
        // this is used when the question is viewed by the teacher in the detailed results of a participant
        $feedback = ($show_feedback) ? $this->getGenericFeedbackOutput($active_id, $pass) : "";
        if (strlen($feedback)) {
            $solutiontemplate->setVariable("FEEDBACK", self::prepareTextareaOutput($feedback, true));
        }

        $solutionoutput = $solutiontemplate->get();
        if (!$show_question_only) {
            // get page object output
            $solutionoutput = $this->getILIASPage($solutionoutput);
        }
        return $solutionoutput;
    }


    /**
     * Get the HTML output of solution table
     *
     * @param array $data       part data (user input or correct solution)
     * @param boolean $a_show_points   show the points of the part data
     * @return string
     */
    private function getSolutionTable($data, $a_show_points = false)
    {
        // get the table output template
        $tpl = $this->plugin->getTemplate("tpl.il_as_qpl_accqst_output_solution_table.html", true, true);

        if ($a_show_points) {
            $tpl->touchBlock('with_points');
            $title_colspan = 7;
        } else {
            $tpl->touchBlock('without_points');
            $title_colspan = 5;

        }

        // table title
        $tpl->setCurrentBlock('table_title');
        $tpl->setVariable('TXT_TITLE_LEFT', (string) ($data['headerLeft'] ?? ''));
        $tpl->setVariable('TXT_TITLE_RIGHT', (string) ($data['headerRight'] ?? ''));
        $tpl->setVariable('TXT_TITLE_CENTER', (string) ($data['headerCenter'] ?? ''));
        $tpl->setVariable('TITLE_COLSPAN', $title_colspan);
        $tpl->parseCurrentBlock();

        // header row
        $tpl->setCurrentBlock('head_row');
        $tpl->setVariable('TXT_ACCOUNT', $this->plugin->txt('booking_account'));
        $tpl->setVariable('TXT_VALUE', $this->plugin->txt('money_value'));
        if ($a_show_points) {
            $tpl->setVariable('TXT_LEFT_POINTS', $this->plugin->txt('points'));
            $tpl->setVariable('TXT_RIGHT_POINTS', $this->plugin->txt('points'));
        }
        $tpl->parseCurrentBlock();

        // all rows of the record
        $record = $data['record'] ?? [];
        $rows = $record['rows'] ?? [];

        if (!empty($rows)) {
            foreach ($record['rows'] as $row) {
                $tpl->setCurrentBlock('booking_row');
                $tpl->setVariable('LEFT_ACCOUNT', (string) ($row['leftAccountText'] ?? ''));
                $tpl->setVariable('RIGHT_ACCOUNT', (string) ($row['rightAccountText'] ?? ''));
                $tpl->setVariable('LEFT_VALUE', empty($row['leftValueMoney']) ? '' : $this->plugin->toString($row['leftValueMoney'], $this->object->getPrecision(), $this->object->getThousandsDelim()));
                $tpl->setVariable('RIGHT_VALUE', empty($row['rightValueMoney']) ? '' : $this->plugin->toString($row['rightValueMoney'], $this->object->getPrecision(), $this->object->getThousandsDelim()));
                if ($a_show_points) {
                    $tpl->setVariable('LEFT_POINTS', (string) ($row['leftPoints'] ?? ''));
                    $tpl->setVariable('RIGHT_POINTS', (string) ($row['rightPoints'] ?? ''));
                }
                $tpl->parseCurrentBlock();
            }
        } else {
            $tpl->setCurrentBlock('booking_row');
            $tpl->setVariable('LEFT_POINTS', '');
            $tpl->setVariable('RIGHT_POINTS', '');
            $tpl->parseCurrentBlock();
        }

        // sum row of the record
        $tpl->setCurrentBlock('sum_row');
        $tpl->setVariable('TXT_SUM', $this->plugin->txt('label_sum') . ': ');
        $tpl->setVariable('SUM_VALUES_LEFT', empty($row['sumValuesLeft']) ? '' : $this->plugin->toString($record['sumValuesLeft'], $this->object->getPrecision(), $this->object->getThousandsDelim()));
        $tpl->setVariable('SUM_VALUES_RIGHT', empty($row['sumValuesRight']) ? '' : $this->plugin->toString($record['sumValuesRight'], $this->object->getPrecision(), $this->object->getThousandsDelim()));
        if ($a_show_points) {
            $tpl->setVariable('SUM_POINTS_LEFT', (string) ($record['sumPointsLeft'] ?? ''));
            $tpl->setVariable('SUM_POINTS_RIGHT', (string) ($record['sumPointsRight'] ?? ''));
        }
        $tpl->parseCurrentBlock();

        // special scores for a record
        if ($a_show_points) {
            foreach (array('bonusOrderLeft', 'bonusOrderRight', 'malusCountLeft', 'malusCountRight', 'malusSumsDiffer') as $score) {
                if (!empty($record[$score])) {
                    $tpl->setCurrentBlock('record_scoring_row');
                    $tpl->setVariable('TXT_RECORD_SCORING', $this->plugin->txt($score) . ': ');
                    $tpl->setVariable('VALUE_RECORD_SCORING', $record[$score] . ' '
                        . (abs($record[$score]) == 1 ? $this->plugin->txt('point') : $this->plugin->txt('points')));
                    $tpl->parseCurrentBlock();
                }
            }
        }
        $tpl->setCurrentBlock('booking_record');
        $tpl->parseCurrentBlock();

        return $tpl->get();
    }


    /**
     * Returns the answer specific feedback for the question
     *
     * @param array $userSolution ($userSolution[<value1>] = <value2>)
     * @return string HTML Code with the answer specific feedback
     * @access public
     */
    public function getSpecificFeedbackOutput($userSolution): string
    {
        global $DIC;
        $ilAccess = $DIC->access();

        $show_points = false;
        if (is_object($this->getPreviewSession())) {
            $show_points = true;
        } elseif ($ilAccess->checkAccess('write', '', ($this->plugin->request()->getInt('ref_id')))) {
            $show_points = true;
            $feedback_note = $this->plugin->txt('grading_details_note');
        }

        // there is nospecific feedback except points
        if (!$show_points) {
            return '';
        }

        // get the user input
        $solutionParts = $this->object->getSolutionParts($userSolution);
        $this->object->initVariablesFromUserSolution($userSolution);

        // get the output template
        $template = $this->plugin->getTemplate("tpl.il_as_qpl_accqst_output_solution.html");

        foreach ($this->object->getParts() as $part_obj) {
            $part_id = $part_obj->getPartId();
            $part_obj->setWorkingXML(($solutionParts[$part_id] ?? ''));
            $part_obj->calculateReachedPoints();
            $student_data = $part_obj->getWorkingData();

            $template->setCurrentBlock('part');
            $template->setVariable('SOLUTION', $this->getSolutionTable($student_data, $show_points));
            $template->setVariable('TXT_SCORE', $this->plugin->txt('reached_score') . ': ');
            $template->setVariable('SCORE', ($student_data["sumPoints"] ?? ''));
            $template->parseCurrentBlock();
        }
        // show like the question
        $template->setVariable('CLASS', 'ilc_question_Standard');
        if (!empty($feedback_note)) {
            $template->setVariable('GRADING_DETAILS_NOTE', $feedback_note);
        }
        return $template->get();
    }
}
