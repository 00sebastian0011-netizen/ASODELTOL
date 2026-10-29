<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_asodeltolstaff;

defined('MOODLE_INTERNAL') || die();

/**
 * Institutional provisioning for the SENA PFCE 2026 satisfaction survey (OV-ENCUESTA).
 *
 * Implements the official instrument "Modelo_Encuesta_de_Satisfacción_PFCE_-_2026_vf"
 * as a native Moodle mod_feedback activity located in the final section of the course,
 * gated by prior activity completion so it is only available upon finishing the diplomado,
 * with both institutional logos (SENA and FCE) in the survey header and ONLY the 12
 * official survey questions inside the questionnaire form.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class satisfaction_survey {
    /** @var string Canonical course module idnumber for the PFCE 2026 satisfaction survey. */
    public const CM_IDNUMBER = 'ASODELTOL-AF8-ENCUESTA-SATISFACCION-PFCE2026';

    /** @var string Official title from Modelo_Encuesta_de_Satisfacción_PFCE_-_2026_vf.xlsx. */
    public const SURVEY_NAME = 'Encuesta de satisfacción PFCE 2026 — Versión final';

    /** @var string Relative plugin path for the SENA institutional logo (image1.png). */
    public const LOGO_SENA_RELPATH = '/local/asodeltolstaff/pix/pfce_logo_sena.png';

    /** @var string Relative plugin path for the FCE institutional logo (image2.png). */
    public const LOGO_FCE_RELPATH = '/local/asodeltolstaff/pix/pfce_logo_fce.png';

    /** @var string Official objective statement from row A7 of the PFCE 2026 survey template. */
    public const OBJECTIVE_TEXT = 'Objetivo: conocer su satisfacción con la formación recibida e identificar '
        . 'oportunidades de mejora. Responda al finalizar la formación de este grupo. '
        . 'Su opinión no modifica la calificación académica.';

    /** @var string Official closing statement from row A43 of the PFCE 2026 survey template. */
    public const CLOSING_TEXT = 'Gracias por su participación. Esta encuesta recoge satisfacción y aplicabilidad '
        . 'percibida. Los aprendizajes y resultados de aplicación se documentan con sus pruebas y soportes '
        . 'correspondientes.';

    /**
     * Return the canonical specification of the PFCE 2026 satisfaction survey.
     *
     * @return array<string, mixed>
     */
    public static function specification(): array {
        $scale = [
            '1 = Muy insatisfecho',
            '2 = Insatisfecho',
            '3 = Ni satisfecho ni insatisfecho',
            '4 = Satisfecho',
            '5 = Muy satisfecho',
            'No aplica / No puedo valorar',
        ];
        $improvementoptions = [
            'Contenidos',
            'Explicaciones del capacitador',
            'Actividades',
            'Materiales',
            'Cumplimiento del cronograma',
            'Ambiente de aprendizaje',
            'Otro',
            'Ninguno',
        ];

        return [
            'idnumber' => self::CM_IDNUMBER,
            'name' => self::SURVEY_NAME,
            'header_lines' => [
                'SERVICIO NACIONAL DE APRENDIZAJE',
                'DIRECCIÓN DEL SISTEMA NACIONAL DE FORMACIÓN PARA EL TRABAJO',
                'PROGRAMA DE FORMACIÓN CONTINUA ESPECIALIZADA - CONVOCATORIA DSNFT - 0001 - FCE - 2026',
            ],
            'objective' => self::OBJECTIVE_TEXT,
            'closing' => self::CLOSING_TEXT,
            'logos' => [
                'sena' => self::LOGO_SENA_RELPATH,
                'fce' => self::LOGO_FCE_RELPATH,
            ],
            'satisfaction_scale_instruction' => 'Seleccione una respuesta por pregunta: 1 = Muy insatisfecho; '
                . '2 = Insatisfecho; 3 = Ni satisfecho ni insatisfecho; 4 = Satisfecho; 5 = Muy satisfecho. '
                . 'Si no puede valorarlo, seleccione No aplica / No puedo valorar.',
            'satisfaction_scale' => $scale,
            'satisfaction_questions' => [
                [
                    'label' => 'p1',
                    'name' => '1. ¿Qué tan satisfecho quedó con la pertinencia de los temas para las funciones que desempeña?',
                ],
                [
                    'label' => 'p2',
                    'name' => '2. ¿Qué tan satisfecho quedó con las actividades utilizadas para desarrollar los temas?',
                ],
                [
                    'label' => 'p3',
                    'name' => '3. ¿Qué tan satisfecho quedó con el dominio del tema, el uso de metodologías innovadoras y la atención de dudas por parte del capacitador?',
                ],
                [
                    'label' => 'p4',
                    'name' => '4. ¿Qué tan satisfecho quedó con las herramientas de apoyo y materiales de formación utilizados?',
                ],
                [
                    'label' => 'p5',
                    'name' => '5. ¿Qué tan satisfecho quedó con el cumplimiento del cronograma informado?',
                ],
                [
                    'label' => 'p6',
                    'name' => '6. ¿Qué tan satisfecho quedó con las condiciones del ambiente en el que recibió la formación?',
                ],
                [
                    'label' => 'p7',
                    'name' => '7. ¿Considera que aprendió nuevos conocimientos como parte de la formación?',
                ],
                [
                    'label' => 'p8',
                    'name' => '8. ¿Considera que puede aplicar lo aprendido en el puesto de trabajo?',
                ],
                [
                    'label' => 'p9',
                    'name' => '9. ¿Considera que lo aprendido genera nuevas oportunidades de crecimiento laboral?',
                ],
                [
                    'label' => 'p10',
                    'name' => '10. En general, ¿qué tan satisfecho quedó con la formación recibida?',
                ],
            ],
            'improvement_choice' => [
                'label' => 'p11',
                'name' => '11. ¿Cuál es el principal aspecto que debería mejorar en esta formación? Seleccione una sola opción.',
                'options' => $improvementoptions,
            ],
            'improvement_open' => [
                'label' => 'p12',
                'name' => '12. ¿Qué cambio concreto propone para mejorar próximas formaciones? Respuesta opcional.',
            ],
        ];
    }

    /**
     * Build the clean institutional banner HTML with both SENA and FCE logos for the survey intro.
     *
     * @param \stdClass $course Target course record.
     * @param int|null $cmid Optional course module ID (defaults to survey cmid in course).
     * @return string
     */
    public static function build_header_html(\stdClass $course, ?int $cmid = null): string {
        global $DB;
        $spec = self::specification();
        $senaurl = (new \moodle_url(self::LOGO_SENA_RELPATH))->out(false);
        $fceurl = (new \moodle_url(self::LOGO_FCE_RELPATH))->out(false);

        if ($cmid === null) {
            $feedbackmodule = $DB->get_record('modules', ['name' => 'feedback'], 'id');
            if ($feedbackmodule) {
                $cm = $DB->get_record('course_modules', [
                    'course' => $course->id,
                    'module' => $feedbackmodule->id,
                    'idnumber' => self::CM_IDNUMBER,
                ], 'id');
                $cmid = $cm ? (int) $cm->id : 50;
            } else {
                $cmid = 50;
            }
        }
        $completeurl = (new \moodle_url('/mod/feedback/complete.php', [
            'id' => $cmid,
            'courseid' => $course->id,
        ]))->out(false);

        return '<div class="asodeltol-pfce-survey-header" style="border:1.5px solid #cbd5e1;border-radius:12px;'
            . 'background:#ffffff;padding:20px 24px;margin-bottom:12px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">'
            . '<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;'
            . 'border-bottom:1px solid #e2e8f0;padding-bottom:14px;margin-bottom:12px;">'
            . '<img src="' . $senaurl . '" alt="Logo SENA" style="height:60px;width:auto;object-fit:contain;" />'
            . '<div style="flex:1;min-width:240px;text-align:center;line-height:1.35;color:#0f291e;">'
            . '<div style="font-weight:800;font-size:0.92rem;">SERVICIO NACIONAL DE APRENDIZAJE</div>'
            . '<div style="font-weight:700;font-size:0.85rem;color:#14532d;">DIRECCIÓN DEL SISTEMA NACIONAL DE FORMACIÓN PARA EL TRABAJO</div>'
            . '<div style="font-weight:700;font-size:0.82rem;color:#166534;">PROGRAMA DE FORMACIÓN CONTINUA ESPECIALIZADA - CONVOCATORIA DSNFT - 0001 - FCE - 2026</div>'
            . '<div style="margin-top:4px;font-weight:800;font-size:1.05rem;color:#0b3b24;">'
            . s(self::SURVEY_NAME) . '</div>'
            . '</div>'
            . '<img src="' . $fceurl . '" alt="Logo FCE" style="height:60px;width:auto;object-fit:contain;" />'
            . '</div>'
            . '<p style="margin:0 0 8px 0;font-size:0.95rem;color:#14532d;line-height:1.45;">'
            . '<strong>Objetivo:</strong> conocer su satisfacción con la formación recibida e identificar '
            . 'oportunidades de mejora. Responda al finalizar la formación de este grupo. '
            . 'Su opinión no modifica la calificación académica.</p>'
            . '<p style="margin:0 0 14px 0;font-size:0.90rem;color:#334155;line-height:1.4;">'
            . s($spec['satisfaction_scale_instruction']) . '</p>'
            . '<div style="text-align:center;margin:14px 0 4px 0;">'
            . '<a href="' . $completeurl . '" class="btn btn-success btn-lg" style="background:#16a34a;border:none;'
            . 'border-radius:8px;padding:12px 28px;font-size:1.05rem;font-weight:700;color:#ffffff;display:inline-block;'
            . 'text-decoration:none;box-shadow:0 4px 6px -1px rgba(22,163,74,0.35);">'
            . '📝 Responder la Encuesta Oficial de Satisfacción</a>'
            . '</div>'
            . '</div>';
    }

    /**
     * Build the post-submission thank-you HTML with both institutional logos.
     *
     * @param \stdClass $course Target course record.
     * @return string
     */
    public static function build_after_submit_html(\stdClass $course): string {
        $senaurl = (new \moodle_url(self::LOGO_SENA_RELPATH))->out(false);
        $fceurl = (new \moodle_url(self::LOGO_FCE_RELPATH))->out(false);

        return '<div class="asodeltol-pfce-survey-thanks" style="border:1px solid #16a34a;border-radius:10px;'
            . 'background:#f0fdf4;padding:18px;margin:10px 0;text-align:center;">'
            . '<div style="display:flex;align-items:center;justify-content:center;gap:24px;margin-bottom:10px;">'
            . '<img src="' . $senaurl . '" alt="Logo SENA" style="height:52px;width:auto;object-fit:contain;" />'
            . '<img src="' . $fceurl . '" alt="Logo FCE" style="height:52px;width:auto;object-fit:contain;" />'
            . '</div>'
            . '<p style="color:#0f291e;font-size:0.95rem;margin:0 auto;max-width:680px;">'
            . s(self::CLOSING_TEXT) . '</p>'
            . '</div>';
    }

    /**
     * Provision or update the official PFCE 2026 satisfaction survey at the end of the course.
     *
     * By default ($sectionnum = null) places the survey in the final section of the course
     * (MAX(section), e.g. Unidad Temática 5 in Diplomado AF8) and restricts its availability
     * ($requirepriorcompletion = true) until the learner finishes the course's tracked activities.
     *
     * @param int $courseid Target course ID (e.g. 4 for Diplomado AF8).
     * @param int|null $sectionnum Target section number (null = final section of the course).
     * @param bool $requirepriorcompletion Whether to gate availability on prior tracked activities.
     * @return array{cmid:int,feedbackid:int,sectionnum:int,items:int,gated:bool,requiredcmids:array<int>}
     */
    public static function provision(
        int $courseid,
        ?int $sectionnum = null,
        bool $requirepriorcompletion = true
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/feedback/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        if ((int) $course->enablecompletion !== 1) {
            $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
            $course->enablecompletion = 1;
        }
        if (empty($CFG->enableavailability)) {
            set_config('enableavailability', 1);
            $CFG->enableavailability = 1;
        }

        // Always target the very last section of the course (e.g. Section 5 in AF8) when $sectionnum is null.
        if ($sectionnum === null) {
            $allsections = $DB->get_records('course_sections', ['course' => $course->id], 'section DESC', 'id,section');
            $targetsection = $allsections ? (int) reset($allsections)->section : 0;
        } else {
            $targetsection = max(0, $sectionnum);
        }

        $sectionrec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $targetsection], '*', MUST_EXIST);
        if ((int) $sectionrec->visible !== 1) {
            course_update_section($course, $sectionrec, ['visible' => 1]);
            $sectionrec->visible = 1;
        }
        $feedbackmodule = $DB->get_record('modules', ['name' => 'feedback'], '*', MUST_EXIST);

        // Locate existing survey course module by canonical idnumber or exact title.
        $existingcm = $DB->get_record('course_modules', [
            'course' => $course->id,
            'module' => $feedbackmodule->id,
            'idnumber' => self::CM_IDNUMBER,
        ]);
        if (!$existingcm) {
            $sql = "SELECT cm.*
                      FROM {course_modules} cm
                      JOIN {feedback} f ON f.id = cm.instance
                     WHERE cm.course = :courseid
                       AND cm.module = :moduleid
                       AND f.name = :name
                  ORDER BY cm.id DESC";
            $matches = $DB->get_records_sql($sql, [
                'courseid' => $course->id,
                'moduleid' => $feedbackmodule->id,
                'name' => self::SURVEY_NAME,
            ]);
            $existingcm = $matches ? reset($matches) : null;
        }

        $introhtml = self::build_header_html($course);
        $aftersubmithtml = self::build_after_submit_html($course);

        $requiredcmids = [];
        $availabilityjson = null;
        if ($requirepriorcompletion) {
            // Ensure the last active academic module in the course (if only the intro video was tracked)
            // also has view-completion enabled so completing the course unlocks the survey at the end.
            $existingid = $existingcm ? (int) $existingcm->id : 0;
            $tracked = $DB->get_records_select(
                'course_modules',
                'course = :courseid AND visible = 1 AND deletioninprogress = 0 AND completion > 0 AND id <> :selfid',
                ['courseid' => $course->id, 'selfid' => $existingid],
                'id ASC',
                'id,section,completion'
            );
            if (count($tracked) <= 1) {
                $sql = "SELECT cm.id, cm.module, m.name AS modname
                          FROM {course_modules} cm
                          JOIN {course_sections} cs ON cs.id = cm.section
                          JOIN {modules} m ON m.id = cm.module
                         WHERE cm.course = :courseid
                           AND cm.visible = 1
                           AND cm.deletioninprogress = 0
                           AND cm.id <> :selfid
                           AND cs.section > 0
                           AND m.name IN ('page', 'glossary', 'resource', 'assign', 'quiz')
                      ORDER BY cs.section DESC, cm.id DESC";
                $candidates = $DB->get_records_sql($sql, ['courseid' => $course->id, 'selfid' => $existingid], 0, 1);
                if ($candidates) {
                    $lastacademic = reset($candidates);
                    $DB->update_record('course_modules', (object) [
                        'id' => (int) $lastacademic->id,
                        'completion' => COMPLETION_TRACKING_AUTOMATIC,
                        'completionview' => 1,
                    ]);
                }
                $tracked = $DB->get_records_select(
                    'course_modules',
                    'course = :courseid AND visible = 1 AND deletioninprogress = 0 AND completion > 0 AND id <> :selfid',
                    ['courseid' => $course->id, 'selfid' => $existingid],
                    'id ASC',
                    'id,section,completion'
                );
            }

            $conditions = [];
            $showc = [];
            foreach ($tracked as $tcm) {
                $requiredcmids[] = (int) $tcm->id;
                $conditions[] = ['type' => 'completion', 'cm' => (int) $tcm->id, 'e' => 1];
                $showc[] = true;
            }
            if ($conditions) {
                $availabilityjson = json_encode(['op' => '&', 'c' => $conditions, 'showc' => $showc]);
            }
        }

        if (!$existingcm) {
            $info = (object) [
                'module' => (int) $feedbackmodule->id,
                'modulename' => 'feedback',
                'section' => $targetsection,
                'name' => self::SURVEY_NAME,
                'visible' => 1,
                'visibleoncoursepage' => 1,
                'groupingid' => 0,
                'groupmode' => (int) $course->groupmode,
                'showdescription' => 0,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 0,
                'completionsubmit' => 1,
                'cmidnumber' => self::CM_IDNUMBER,
                'intro' => $introhtml,
                'introformat' => FORMAT_HTML,
                'introeditor' => ['text' => $introhtml, 'format' => FORMAT_HTML, 'itemid' => 0],
                'anonymous' => defined('FEEDBACK_ANONYMOUS_NO') ? FEEDBACK_ANONYMOUS_NO : 2,
                'email_notification' => 0,
                'multiple_submit' => 1,
                'autonumbering' => 0,
                'site_after_submit' => '',
                'page_after_submit' => $aftersubmithtml,
                'page_after_submitformat' => FORMAT_HTML,
                'page_after_submit_editor' => ['text' => $aftersubmithtml, 'format' => FORMAT_HTML, 'itemid' => 0],
                'publish_stats' => 0,
                'timeopen' => 0,
                'timeclose' => 0,
                'availability' => $availabilityjson,
            ];
            $saved = add_moduleinfo($info, $course);
            $cmid = (int) $saved->coursemodule;
            $feedbackid = (int) $saved->instance;
        } else {
            $cmid = (int) $existingcm->id;
            $feedbackid = (int) $existingcm->instance;

            $DB->update_record('feedback', (object) [
                'id' => $feedbackid,
                'name' => self::SURVEY_NAME,
                'intro' => $introhtml,
                'introformat' => FORMAT_HTML,
                'anonymous' => defined('FEEDBACK_ANONYMOUS_NO') ? FEEDBACK_ANONYMOUS_NO : 2,
                'multiple_submit' => 1,
                'autonumbering' => 0,
                'page_after_submit' => $aftersubmithtml,
                'page_after_submitformat' => FORMAT_HTML,
                'completionsubmit' => 1,
                'timemodified' => time(),
            ]);

            if ((int) $existingcm->section !== (int) $sectionrec->id) {
                \core_courseformat\formatactions::cm($course)->move_end_section($cmid, (int) $sectionrec->id);
            }

            $DB->update_record('course_modules', (object) [
                'id' => $cmid,
                'idnumber' => self::CM_IDNUMBER,
                'visible' => 1,
                'visibleold' => 1,
                'visibleoncoursepage' => 1,
                'showdescription' => 0,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 0,
                'availability' => $availabilityjson,
            ]);
        }

        $itemcount = self::sync_items($feedbackid);
        rebuild_course_cache($course->id, true);

        return [
            'cmid' => $cmid,
            'feedbackid' => $feedbackid,
            'sectionnum' => $targetsection,
            'items' => $itemcount,
            'gated' => $availabilityjson !== null,
            'requiredcmids' => $requiredcmids,
        ];
    }

    /**
     * Synchronize ONLY the 12 official survey questions (p1..p12) in feedback_item.
     *
     * Moodle 5.3 renders feedback->intro (with both SENA and FCE logos and the objective)
     * in the native activity header, so feedback_item contains strictly the 12 questions.
     *
     * @param int $feedbackid Feedback instance ID.
     * @return int Total number of feedback_item question records (12).
     */
    private static function sync_items(int $feedbackid): int {
        global $DB;
        $spec = self::specification();

        $desired = [];

        // Questions 1 to 10 (Satisfaction scale).
        $scalepresentation = 'r>>>>>' . implode("\r|", $spec['satisfaction_scale']);
        foreach ($spec['satisfaction_questions'] as $question) {
            $desired[] = [
                'name' => $question['name'],
                'label' => $question['label'],
                'presentation' => $scalepresentation,
                'typ' => 'multichoice',
                'hasvalue' => 1,
                'required' => 1,
                'options' => '',
            ];
        }

        // Question 11 (Single-choice improvement aspect).
        $improvementpresentation = 'r>>>>>' . implode("\r|", $spec['improvement_choice']['options']);
        $desired[] = [
            'name' => $spec['improvement_choice']['name'],
            'label' => $spec['improvement_choice']['label'],
            'presentation' => $improvementpresentation,
            'typ' => 'multichoice',
            'hasvalue' => 1,
            'required' => 1,
            'options' => '',
        ];

        // Question 12 (Optional open-ended concrete proposal).
        $desired[] = [
            'name' => $spec['improvement_open']['name'],
            'label' => $spec['improvement_open']['label'],
            'presentation' => '60|5',
            'typ' => 'textarea',
            'hasvalue' => 1,
            'required' => 0,
            'options' => '',
        ];

        $hasresponses = $DB->record_exists('feedback_completed', ['feedback' => $feedbackid]);
        if (!$hasresponses) {
            $DB->delete_records('feedback_item', ['feedback' => $feedbackid]);
        }
        $existing = array_values($DB->get_records('feedback_item', ['feedback' => $feedbackid], 'position ASC, id ASC'));

        $position = 1;
        foreach ($desired as $idx => $row) {
            $record = (object) [
                'feedback' => $feedbackid,
                'template' => 0,
                'name' => $row['name'],
                'label' => $row['label'],
                'presentation' => $row['presentation'],
                'typ' => $row['typ'],
                'hasvalue' => $row['hasvalue'],
                'position' => $position,
                'required' => $row['required'],
                'dependitem' => 0,
                'dependvalue' => '',
                'options' => $row['options'],
            ];
            if (isset($existing[$idx])) {
                $record->id = (int) $existing[$idx]->id;
                $DB->update_record('feedback_item', $record);
            } else {
                $DB->insert_record('feedback_item', $record);
            }
            $position++;
        }

        return count($desired);
    }

    /**
     * Inspect current status of the PFCE 2026 satisfaction survey in a course.
     *
     * @param int $courseid Course ID.
     * @return array<string, mixed>|null
     */
    public static function status(int $courseid): ?array {
        global $DB;
        $feedbackmodule = $DB->get_record('modules', ['name' => 'feedback']);
        if (!$feedbackmodule) {
            return null;
        }
        $cm = $DB->get_record('course_modules', [
            'course' => $courseid,
            'module' => $feedbackmodule->id,
            'idnumber' => self::CM_IDNUMBER,
        ]);
        if (!$cm) {
            return null;
        }
        $feedback = $DB->get_record('feedback', ['id' => $cm->instance]);
        if (!$feedback) {
            return null;
        }
        $section = $DB->get_record('course_sections', ['id' => $cm->section], 'id,section,name');
        return [
            'cmid' => (int) $cm->id,
            'feedbackid' => (int) $feedback->id,
            'name' => (string) $feedback->name,
            'sectionnum' => $section ? (int) $section->section : 0,
            'sectionname' => $section ? (string) $section->name : '',
            'items' => (int) $DB->count_records('feedback_item', ['feedback' => $feedback->id]),
            'responses' => (int) $DB->count_records('feedback_completed', ['feedback' => $feedback->id]),
            'gated' => !empty($cm->availability),
            'availability' => $cm->availability,
            'viewurl' => (new \moodle_url('/mod/feedback/view.php', ['id' => $cm->id]))->out(false),
            'completeurl' => (new \moodle_url('/mod/feedback/complete.php', ['id' => $cm->id]))->out(false),
            'entriesurl' => (new \moodle_url('/mod/feedback/show_entries.php', ['id' => $cm->id]))->out(false),
        ];
    }
}
