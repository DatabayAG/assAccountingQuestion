<?php

/**
 * Copyright (c) 2013 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv2, see LICENSE
 */

/**
 * Class for accounting question import
 *
 * @author     Fred Neumann <fred.neumann@fim.unierlangen.de>
 * @ingroup    ModulesTestQuestionPool
 */
class assAccountingQuestionImport extends assQuestionImport
{
    /** @var assAccountingQuestion */
    public $object;

    /**
     * Creates a question from a QTI file
     *
     * @ineritdoc
     * todo: use parameters $importdirectory and $user_id
     */
    public function fromXML(
        string $importdirectory,
        int $user_id,
        ilQTIItem $item,
        int $questionpool_id,
        ?int $tst_id,
        ?ilObject &$tst_object,
        int &$question_counter,
        array $import_mapping
    ): array {
        global $DIC;

        $ilUser = $DIC->user();
        $ilLog = $DIC->logger()->root();

        // empty session variable for imported xhtml mobs
        ilSession::clear('import_mob_xhtml');

        $presentation = $item->getPresentation();
        $now = getdate();
        $created = sprintf(
            "%04d%02d%02d%02d%02d%02d",
            $now['year'],
            $now['mon'],
            $now['mday'],
            $now['hours'],
            $now['minutes'],
            $now['seconds']
        );

        // get the generic feedbach
        $feedbacksgeneric = array();
        if (isset($item->itemfeedback)) {
            foreach ($item->itemfeedback as $ifb) {
                if (strcmp($ifb->getIdent(), "response_allcorrect") == 0) {
                    // found a feedback for the identifier
                    if (count($ifb->material)) {
                        foreach ($ifb->material as $material) {
                            $feedbacksgeneric[1] = $material;
                        }
                    }
                    if ((count($ifb->flow_mat) > 0)) {
                        foreach ($ifb->flow_mat as $fmat) {
                            if (count($fmat->material)) {
                                foreach ($fmat->material as $material) {
                                    $feedbacksgeneric[1] = $material;
                                }
                            }
                        }
                    }
                } else {
                    if (strcmp($ifb->getIdent(), "response_onenotcorrect") == 0) {
                        // found a feedback for the identifier
                        if (count($ifb->material)) {
                            foreach ($ifb->material as $material) {
                                $feedbacksgeneric[0] = $material;
                            }
                        }
                        if ((count($ifb->flow_mat) > 0)) {
                            foreach ($ifb->flow_mat as $fmat) {
                                if (count($fmat->material)) {
                                    foreach ($fmat->material as $material) {
                                        $feedbacksgeneric[0] = $material;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // set question properties
        $this->addGeneralMetadata($item);
        $this->object->setTitle($item->getTitle());
        $this->object->setNrOfTries($item->getMaxattempts());
        $this->object->setComment($item->getComment());
        $this->object->setAuthor($item->getAuthor());
        $this->object->setOwner($ilUser->getId());
        $this->object->setQuestion($this->QTIMaterialToString($item->getQuestiontext()));
        $this->object->setObjId($questionpool_id);
        $this->object->setAccountsXML(base64_decode($item->getMetadataEntry('accounts_content')));
        $this->object->setVariablesXML(base64_decode($item->getMetadataEntry('variables_content')));
        $this->object->setPoints($item->getMetadataEntry("points"));
        $this->object->setPrecision((int) $item->getMetadataEntry('precision'));
        $this->object->setThousandsDelimType($item->getMetadataEntry('thousands_delim_type'));

        // additional content editing mode information
        $this->object->setAdditionalContentEditingMode(
            $this->fetchAdditionalContentEditingModeInformation($item)
        );

        // first save the question  without its parts (to get a new question id)
        $this->object->saveToDb(null, false);

        // then create the parts
        $parts = unserialize($item->getMetadataEntry('booking_parts'));
        if (is_array($parts)) {
            for ($i = 0; $i < count($parts); $i++) {
                $part = $parts[$i];

                // since plugin version 1.1.7 the text of the questions parts is added as material to the presentation
                // this enables images in the parts text
                // the material index 0 is used for the question text
                if (isset($item->presentation->material[$i + 1])) {
                    $part['text'] = $this->QTIMaterialToString($item->presentation->material[$i + 1]);
                }

                // create and add a new part
                /** @var assAccountingQuestionPart $part_obj */
                $part_obj = $this->object->getPart();
                $part_obj->setPosition($part['position'] ?? 0);
                $part_obj->setText($part['text'] ?? '');
                $part_obj->setMaxPoints($part['max_points'] ?? 0);
                $part_obj->setMaxLines($part['max_lines'] ?? 1);
                $part_obj->setBookingXML(base64_decode($part['booking_def'] ?? ''));
                $part_obj->write();
            }
        }

        // convert the generic feedback
        foreach ($feedbacksgeneric as $correctness => $material) {
            $m = $this->QTIMaterialToString($material);
            $feedbacksgeneric[$correctness] = $m;
        }

        // handle the import of media objects in XHTML code
        $questiontext = $this->object->getQuestion();
        if (is_array(ilSession::get("import_mob_xhtml"))) {
            foreach (ilSession::get("import_mob_xhtml") as $mob) {
                if ($tst_id > 0) {
                    $importfile = $this->getTstImportArchivDirectory() . '/' . $mob["uri"];
                } else {
                    $importfile = $this->getQplImportArchivDirectory() . '/' . $mob["uri"];
                }
                $ilLog->write($importfile);

                $media_object = ilObjMediaObject::_saveTempFileAsMediaObject(basename($importfile), $importfile, false);
                ilObjMediaObject::_saveUsage($media_object->getId(), "qpl:html", $this->object->getId());

                // images in question text
                $questiontext = str_replace(
                    "src=\"" . $mob["mob"] . "\"",
                    "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"",
                    $questiontext
                );

                // images in question parts
                foreach ($this->object->getParts() as $part_obj) {
                    $part_obj->setText(str_replace(
                        "src=\"" . $mob["mob"] . "\"",
                        "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"",
                        $part_obj->getText()
                    ));
                }

                // images in feedback
                foreach ($feedbacksgeneric as $correctness => $material) {
                    $feedbacksgeneric[$correctness] = str_replace(
                        "src=\"" . $mob["mob"] . "\"",
                        "src=\"" . "il_" . IL_INST_ID . "_mob_" . $media_object->getId() . "\"",
                        $material
                    );
                }
            }
        }

        $this->object->setQuestion(ilRTE::_replaceMediaObjectImageSrc($questiontext, 1));
        foreach ($this->object->getParts() as $part_obj) {
            $part_obj->setText(ilRTE::_replaceMediaObjectImageSrc($part_obj->getText(), 1));
            $part_obj->write();
        }
        foreach ($feedbacksgeneric as $correctness => $material) {
            $this->object->feedbackOBJ->importGenericFeedback(
                $this->object->getId(),
                $correctness,
                ilRTE::_replaceMediaObjectImageSrc($material, 1)
            );
        }
        if (count($item->suggested_solutions)) {
            foreach ($item->suggested_solutions as $suggested_solution) {
                $this->object->setSuggestedSolution(
                    $suggested_solution["solution"]->getContent(),
                    $suggested_solution["gap_index"],
                    true
                );
            }
        }

        // Now save the question again
        // (this also recalculates the maximum points)
        $this->object->saveToDb(null, true);

        // import mapping for tests
        if ($tst_id > 0) {
            $q_1_id = $this->object->getId();
            $question_id = $this->object->duplicate(true, '', '', -1, $tst_id);
            $tst_object->questions[$question_counter++] = $question_id;
            $import_mapping[$item->getIdent()] = array("pool" => $q_1_id, "test" => $question_id);
        } else {
            $import_mapping[$item->getIdent()] = array("pool" => $this->object->getId(), "test" => 0);
        }

        return $import_mapping;
    }
}
