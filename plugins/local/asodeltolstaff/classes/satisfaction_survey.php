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
 * Institutional provisioning and completion prompt for the SENA PFCE 2026 satisfaction survey (OV-ENCUESTA).
 *
 * Implements the official instrument "Modelo_Encuesta_de_Satisfacción_PFCE_-_2026_vf"
 * as a native Moodle mod_feedback activity with both institutional logos (SENA and FCE),
 * non-graded completion upon submission, and optional completion-gated availability.
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
            'action_info_fields' => [
                ['label' => 'info_convenio', 'name' => 'Número del Convenio y Sigla'],
                ['label' => 'info_accion', 'name' => 'Nombre de la Acción de formación'],
                ['label' => 'info_grupo', 'name' => 'Número del Grupo'],
                ['label' => 'info_modalidad', 'name' => 'Modalidad de la Acción de Formación'],
                ['label' => 'info_capacitador', 'name' => 'Capacitador o equipo'],
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
     * Build the institutional banner HTML with both SENA and FCE logos.
     *
     * @param \stdClass $course Target course record.
     * @param bool $includeactionguide Whether to include the section heading for training action fields.
     * @return string
     */
    public static function build_header_html(\stdClass $course, bool $includeactionguide = false): string {
        global $CFG, $DB;
        $senaurl = (new \moodle_url(self::LOGO_SENA_RELPATH))->out(false);
        $fceurl = (new \moodle_url(self::LOGO_FCE_RELPATH))->out(false);
        $coursename = s(format_string($course->fullname));

        $groups = $DB->get_records('groups', ['courseid' => $course->id], 'id ASC', 'id,name');
        $groupnames = [];
        foreach ($groups as $g) {
            $groupnames[] = s($g->name);
        }
        $groupshint = $groupnames ? implode(' · ', $groupnames) : 'Grupo asignado en la plataforma';

        $html = '<div class="asodeltol-pfce-survey-header" style="border:2px solid #14532d;border-radius:12px;'
            . 'background:#ffffff;padding:18px 20px;margin-bottom:14px;box-shadow:0 4px 14px rgba(20,83,45,0.08);">'
            . '<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;'
            . 'border-bottom:2px solid #dcfce7;padding-bottom:12px;margin-bottom:12px;">'
            . '<img src="' . $senaurl . '" alt="Logo SENA" style="height:68px;width:auto;object-fit:contain;" />'
            . '<div style="flex:1;min-width:240px;text-align:center;line-height:1.35;color:#0f291e;">'
            . '<div style="font-weight:800;font-size:0.95rem;letter-spacing:0.02em;">SERVICIO NACIONAL DE APRENDIZAJE</div>'
            . '<div style="font-weight:700;font-size:0.88rem;color:#14532d;">DIRECCIÓN DEL SISTEMA NACIONAL DE FORMACIÓN PARA EL TRABAJO</div>'
            . '<div style="font-weight:700;font-size:0.84rem;color:#166534;">PROGRAMA DE FORMACIÓN CONTINUA ESPECIALIZADA - CONVOCATORIA DSNFT - 0001 - FCE - 2026</div>'
            . '<div style="margin-top:6px;font-weight:800;font-size:1.12rem;color:#0b3b24;">'
            . s(self::SURVEY_NAME) . '</div>'
            . '</div>'
            . '<img src="' . $fceurl . '" alt="Logo FCE" style="height:68px;width:auto;object-fit:contain;" />'
            . '</div>'
            . '<div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:10px 14px;border-radius:6px;'
            . 'font-size:0.93rem;color:#14532d;">'
            . '<strong>Objetivo:</strong> conocer su satisfacción con la formación recibida e identificar '
            . 'oportunidades de mejora. Responda al finalizar la formación de este grupo. '
            . 'Su opinión no modifica la calificación académica.'
            . '</div>';

        if ($includeactionguide) {
            $html .= '<div style="margin-top:12px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:12px 14px;">'
                . '<div style="font-weight:700;color:#0f172a;margin-bottom:4px;">'
                . 'Información de la acción de formación: Incluir los siguientes aspectos</div>'
                . '<div style="font-size:0.86rem;color:#334155;line-height:1.45;">'
                . '<strong>Referencia del programa:</strong> Convenio FCE 2026 — ASODELTOL · '
                . '<strong>Acción de formación:</strong> ' . $coursename . ' · '
                . '<strong>Modalidad:</strong> Virtual · '
                . '<strong>Grupos activos:</strong> ' . $groupshint
                . '</div></div>';
        }

        $html .= '</div>';
        return $html;
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

        return '<div class="asodeltol-pfce-survey-thanks" style="border:2px solid #16a34a;border-radius:12px;'
            . 'background:#f0fdf4;padding:20px;margin:12px 0;text-align:center;">'
            . '<div style="display:flex;align-items:center;justify-content:center;gap:24px;margin-bottom:12px;">'
            . '<img src="' . $senaurl . '" alt="Logo SENA" style="height:56px;width:auto;object-fit:contain;" />'
            . '<img src="' . $fceurl . '" alt="Logo FCE" style="height:56px;width:auto;object-fit:contain;" />'
            . '</div>'
            . '<h4 style="color:#14532d;font-weight:800;margin-bottom:8px;">'
            . s(self::SURVEY_NAME) . '</h4>'
            . '<p style="color:#0f291e;font-size:0.98rem;margin:0 auto;max-width:680px;">'
            . s(self::CLOSING_TEXT) . '</p>'
            . '</div>';
    }

    /**
     * Provision or update the official PFCE 2026 satisfaction survey in the given course.
     *
     * @param int $courseid Target course ID (e.g. 4 for Diplomado AF8).
     * @param int|null $sectionnum Target section number (null = last visible section of the course).
     * @param bool $requirepriorcompletion Whether to gate availability on prior tracked activities.
     * @return array{cmid:int,feedbackid:int,sectionnum:int,items:int,gated:bool,requiredcmids:array<int>}
     */
    public static function provision(
        int $courseid,
        ?int $sectionnum = null,
        bool $requirepriorcompletion = false
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

        if ($sectionnum === null) {
            $visible = $DB->get_records('course_sections', ['course' => $course->id, 'visible' => 1], 'section DESC', 'id,section');
            $targetsection = $visible ? (int) reset($visible)->section : 0;
        } else {
            $targetsection = max(0, $sectionnum);
        }

        $sectionrec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $targetsection], '*', MUST_EXIST);
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

        $introhtml = self::build_header_html($course, false);
        $aftersubmithtml = self::build_after_submit_html($course);

        $requiredcmids = [];
        $availabilityjson = null;
        if ($requirepriorcompletion && !empty($CFG->enableavailability)) {
            $tracked = $DB->get_records_select(
                'course_modules',
                'course = :courseid AND visible = 1 AND deletioninprogress = 0 AND completion > 0 AND idnumber <> :idnum',
                ['courseid' => $course->id, 'idnum' => self::CM_IDNUMBER],
                'id ASC',
                'id'
            );
            $conditions = [];
            $showc = [];
            foreach ($tracked as $tcm) {
                if ($existingcm && (int) $tcm->id === (int) $existingcm->id) {
                    continue;
                }
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
                'showdescription' => 1,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 0,
                'completionsubmit' => 1,
                'cmidnumber' => self::CM_IDNUMBER,
                'intro' => $introhtml,
                'introformat' => FORMAT_HTML,
                'introeditor' => ['text' => $introhtml, 'format' => FORMAT_HTML, 'itemid' => 0],
                'anonymous' => defined('FEEDBACK_ANONYMOUS_NO') ? FEEDBACK_ANONYMOUS_NO : 2,
                'email_notification' => 0,
                'multiple_submit' => 0,
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
                'multiple_submit' => 0,
                'autonumbering' => 0,
                'page_after_submit' => $aftersubmithtml,
                'page_after_submitformat' => FORMAT_HTML,
                'completionsubmit' => 1,
                'timemodified' => time(),
            ]);

            $DB->update_record('course_modules', (object) [
                'id' => $cmid,
                'idnumber' => self::CM_IDNUMBER,
                'visible' => 1,
                'visibleoncoursepage' => 1,
                'showdescription' => 1,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => 0,
                'availability' => $availabilityjson,
            ]);

            if ((int) $existingcm->section !== (int) $sectionrec->id) {
                moveto_module($existingcm, $sectionrec);
            }
        }

        $itemcount = self::sync_items($feedbackid, $course);
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
     * Synchronize the exact feedback_item rows from the PFCE 2026 specification.
     *
     * If the feedback instance already has completed responses, updates items in-place by position
     * so existing feedback_value answers remain linked to their item IDs.
     *
     * @param int $feedbackid Feedback instance ID.
     * @param \stdClass $course Course record.
     * @return int Total number of feedback_item records.
     */
    private static function sync_items(int $feedbackid, \stdClass $course): int {
        global $DB;
        $spec = self::specification();

        $desired = [];

        // 1. Top institutional banner + action info guide.
        $desired[] = [
            'name' => '',
            'label' => '',
            'presentation' => self::build_header_html($course, true),
            'typ' => 'label',
            'hasvalue' => 0,
            'required' => 0,
            'options' => '',
        ];

        // 2..6. Información de la acción de formación (5 fields).
        foreach ($spec['action_info_fields'] as $field) {
            $desired[] = [
                'name' => $field['name'],
                'label' => $field['label'],
                'presentation' => '60|255',
                'typ' => 'textfield',
                'hasvalue' => 1,
                'required' => 1,
                'options' => '',
            ];
        }

        // 7. Satisfaction section heading and scale instruction.
        $desired[] = [
            'name' => '',
            'label' => '',
            'presentation' => '<div style="margin-top:10px;padding:10px 14px;background:#f0fdf4;'
                . 'border-left:4px solid #15803d;border-radius:6px;">'
                . '<div style="font-weight:800;color:#14532d;font-size:1rem;">'
                . 'Satisfacción — preguntas 1 a 10 (Modelo PFCE 2026: Satisfacción — preguntas 1 a 7 y aplicabilidad)</div>'
                . '<div style="font-size:0.9rem;color:#166534;margin-top:4px;">'
                . s($spec['satisfaction_scale_instruction']) . '</div></div>',
            'typ' => 'label',
            'hasvalue' => 0,
            'required' => 0,
            'options' => '',
        ];

        // 8..17. Questions 1 to 10 (Satisfaction scale).
        $scalepresentation = 'r>>>>>' . implode("\r|", $spec['satisfaction_scale']);
        foreach ($spec['satisfaction_questions'] as $question) {
            $desired[] = [
                'name' => $question['name'],
                'label' => $question['label'],
                'presentation' => $scalepresentation,
                'typ' => 'multichoice',
                'hasvalue' => 1,
                'required' => 1,
                'options' => 'h',
            ];
        }

        // 18. Improvement opportunities heading.
        $desired[] = [
            'name' => '',
            'label' => '',
            'presentation' => '<div style="margin-top:10px;padding:10px 14px;background:#f8fafc;'
                . 'border-left:4px solid #0f766e;border-radius:6px;">'
                . '<div style="font-weight:800;color:#0f172a;font-size:1rem;">Oportunidades de mejora</div></div>',
            'typ' => 'label',
            'hasvalue' => 0,
            'required' => 0,
            'options' => '',
        ];

        // 19. Question 11 (Single-choice improvement aspect).
        $improvementpresentation = 'r>>>>>' . implode("\r|", $spec['improvement_choice']['options']);
        $desired[] = [
            'name' => $spec['improvement_choice']['name'],
            'label' => $spec['improvement_choice']['label'],
            'presentation' => $improvementpresentation,
            'typ' => 'multichoice',
            'hasvalue' => 1,
            'required' => 1,
            'options' => 'h',
        ];

        // 20. Question 12 (Optional open-ended concrete proposal).
        $desired[] = [
            'name' => $spec['improvement_open']['name'],
            'label' => $spec['improvement_open']['label'],
            'presentation' => '60|5',
            'typ' => 'textarea',
            'hasvalue' => 1,
            'required' => 0,
            'options' => '',
        ];

        // 21. Closing statement.
        $desired[] = [
            'name' => '',
            'label' => '',
            'presentation' => '<div style="margin-top:8px;padding:10px 14px;background:#f0fdf4;'
                . 'border:1px solid #bbf7d0;border-radius:8px;color:#14532d;font-size:0.9rem;">'
                . s(self::CLOSING_TEXT) . '</div>',
            'typ' => 'label',
            'hasvalue' => 0,
            'required' => 0,
            'options' => '',
        ];

        $existing = array_values($DB->get_records('feedback_item', ['feedback' => $feedbackid], 'position ASC, id ASC'));
        $hasresponses = $DB->record_exists('feedback_completed', ['feedback' => $feedbackid]);

        if (!$hasresponses) {
            $DB->delete_records('feedback_item', ['feedback' => $feedbackid]);
            $existing = [];
        }

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
            'viewurl' => (new \moodle_url('/mod/feedback/view.php', ['id' => $cm->id]))->out(false),
            'completeurl' => (new \moodle_url('/mod/feedback/complete.php', ['id' => $cm->id]))->out(false),
            'entriesurl' => (new \moodle_url('/mod/feedback/show_entries.php', ['id' => $cm->id]))->out(false),
        ];
    }

    /**
     * Render a lightweight, non-intrusive emergent prompt on course view pages
     * so learners see and can open the PFCE 2026 satisfaction survey upon finishing the course.
     *
     * @return string HTML/JS snippet for <head> or empty string when not applicable.
     */
    public static function course_completion_prompt_head_html(): string {
        global $DB, $PAGE, $USER;
        if (!isloggedin() || isguestuser() || empty($PAGE->course->id) || (int) $PAGE->course->id === SITEID) {
            return '';
        }
        $script = $PAGE->url->get_path();
        if (!str_ends_with($script, '/course/view.php')) {
            return '';
        }
        $status = self::status((int) $PAGE->course->id);
        if (!$status) {
            return '';
        }
        $completed = $DB->record_exists('feedback_completed', [
            'feedback' => $status['feedbackid'],
            'userid' => (int) $USER->id,
        ]);

        $payload = [
            'courseid' => (int) $PAGE->course->id,
            'cmid' => $status['cmid'],
            'title' => self::SURVEY_NAME,
            'objective' => self::OBJECTIVE_TEXT,
            'viewurl' => $status['viewurl'],
            'completeurl' => $status['completeurl'],
            'senaurl' => (new \moodle_url(self::LOGO_SENA_RELPATH))->out(false),
            'fceurl' => (new \moodle_url(self::LOGO_FCE_RELPATH))->out(false),
            'completed' => $completed,
        ];
        $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    var data = {$json};
    if (!data || !data.cmid || document.getElementById('asodeltol-pfce-survey-callout')) {
        return;
    }
    var main = document.querySelector('#region-main .course-content') || document.getElementById('region-main');
    if (!main) {
        return;
    }
    var card = document.createElement('section');
    card.id = 'asodeltol-pfce-survey-callout';
    card.setAttribute('aria-label', data.title);
    card.style.cssText = 'border:2px solid #15803d;border-radius:14px;background:linear-gradient(135deg,#f0fdf4 0%,#ffffff 100%);'
        + 'padding:16px 20px;margin:18px 0;box-shadow:0 8px 22px rgba(20,83,45,0.10);display:flex;align-items:center;'
        + 'justify-content:space-between;gap:16px;flex-wrap:wrap;';
    var badge = data.completed
        ? '<span style="display:inline-block;background:#dcfce7;color:#14532d;font-weight:700;padding:6px 12px;border-radius:999px;font-size:0.86rem;">✓ Encuesta diligenciada</span>'
        : '<a href="' + data.completeurl + '" class="btn btn-success" style="font-weight:700;border-radius:999px;padding:8px 18px;background:#15803d;border-color:#15803d;">Diligenciar encuesta al finalizar</a>';
    card.innerHTML = '<div style="display:flex;align-items:center;gap:14px;flex:1;min-width:260px;">'
        + '<img src="' + data.senaurl + '" alt="Logo SENA" style="height:48px;width:auto;object-fit:contain;" />'
        + '<img src="' + data.fceurl + '" alt="Logo FCE" style="height:48px;width:auto;object-fit:contain;" />'
        + '<div style="line-height:1.35;">'
        + '<div style="font-size:0.78rem;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:0.04em;">Cierre de la acción de formación · SENA PFCE 2026</div>'
        + '<div style="font-size:1.02rem;font-weight:800;color:#0f291e;"><a href="' + data.viewurl + '" style="color:#0f291e;text-decoration:none;">' + data.title + '</a></div>'
        + '<div style="font-size:0.86rem;color:#334155;margin-top:2px;">' + data.objective + '</div>'
        + '</div></div>'
        + '<div style="display:flex;align-items:center;gap:10px;">' + badge + '</div>';
    main.appendChild(card);
});
</script>
HTML;
    }
}
