<?php
// Local-only native progression pilot for Moodle 5.3. Mounted read-only into the container.

declare(strict_types=1);

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->dirroot . '/mod/assign/externallib.php');

const M10_COURSE_IDNUMBER = 'ASODELTOL-AF8';
const M10_CHECKPOINT_PREFIX = 'ASODELTOL-AF8-M10-UT';
const M10_SYNTHETIC_PASS_GRADE = 60.0;

const M10_OFFICIAL_UNITS = [
    'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.',
    'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.',
    'Marketing estratégico y gestión competitiva de destinos turísticos.',
    'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.',
    'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.',
];

const M10_ACTORS = [
    'g01' => 'af8.synthetic.g01',
    'g02' => 'af8.synthetic.g02',
];

function m10_fail(string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function m10_set_user(stdClass $user): void {
    \core\session\manager::set_user($user);
}

function m10_admin(): stdClass {
    $admin = get_admin();
    if (!$admin || empty($admin->id)) {
        m10_fail('No se encontró el administrador del staging local.');
    }
    m10_set_user($admin);
    return $admin;
}

function m10_course(): stdClass {
    global $DB;
    return $DB->get_record('course', ['idnumber' => M10_COURSE_IDNUMBER], '*', MUST_EXIST);
}

function m10_user(string $actor): stdClass {
    global $CFG, $DB;

    if (!array_key_exists($actor, M10_ACTORS)) {
        m10_fail("Actor sintético no permitido: {$actor}.");
    }
    $user = $DB->get_record('user', [
        'username' => M10_ACTORS[$actor],
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ], '*', MUST_EXIST);
    if (!str_ends_with((string) $user->email, '@example.invalid')) {
        m10_fail("La cuenta {$user->username} no pertenece al dominio sintético aprobado.");
    }
    return $user;
}

function m10_group(stdClass $course, string $code): stdClass {
    global $DB;

    $group = $DB->get_record('groups', [
        'courseid' => $course->id,
        'idnumber' => $code,
    ]);
    if (!$group) {
        $group = $DB->get_record('groups', [
            'courseid' => $course->id,
            'name' => $code,
        ]);
    }
    if (!$group) {
        m10_fail("No existe el grupo sintético esperado {$code}.");
    }
    return $group;
}

/**
 * @return list<string>
 */
function m10_user_groups(stdClass $course, stdClass $user): array {
    $groups = groups_get_all_groups((int) $course->id, (int) $user->id, 0, 'g.id,g.idnumber,g.name');
    $codes = array_map(
        static fn(stdClass $group): string => $group->idnumber !== ''
            ? (string) $group->idnumber
            : (string) $group->name,
        $groups
    );
    sort($codes);
    return array_values($codes);
}

function m10_checkpoint_idnumber(int $unit): string {
    return M10_CHECKPOINT_PREFIX . $unit;
}

function m10_marker(int $unit): string {
    return "M10-UT{$unit}-ACCESS-MARKER";
}

/**
 * @return array{0: stdClass, 1: stdClass, 2: cm_info}
 */
function m10_checkpoint(stdClass $course, int $unit): array {
    global $DB;

    $cmrecord = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => m10_checkpoint_idnumber($unit),
    ], '*', MUST_EXIST);
    $assign = $DB->get_record('assign', ['id' => $cmrecord->instance], '*', MUST_EXIST);
    $cm = get_fast_modinfo($course)->get_cm((int) $cmrecord->id);
    return [$cmrecord, $assign, $cm];
}

function m10_grade_item(stdClass $course, stdClass $assign): grade_item {
    $item = grade_item::fetch([
        'courseid' => $course->id,
        'itemtype' => 'mod',
        'itemmodule' => 'assign',
        'iteminstance' => $assign->id,
        'itemnumber' => 0,
    ]);
    if (!$item) {
        m10_fail("No existe el ítem de calificación para la actividad {$assign->id}.");
    }
    return $item;
}

function m10_ensure_checkpoint(stdClass $course, int $unit): cm_info {
    global $DB;

    $idnumber = m10_checkpoint_idnumber($unit);
    $cmrecord = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
    ]);
    if (!$cmrecord) {
        [, , , , $data] = prepare_new_moduleinfo_data($course, 'assign', $unit);
        $data->name = "Control sintético de progresión M10 — UT{$unit}";
        $data->cmidnumber = $idnumber;
        $data->introeditor = [
            'text' => m10_marker($unit)
                . '. Actividad técnica sintética; no sustituye contenido ni evaluación institucional.',
            'format' => FORMAT_PLAIN,
            'itemid' => 0,
        ];
        $data->showdescription = 0;
        $data->alwaysshowdescription = 1;
        $data->allowsubmissionsfromdate = 0;
        $data->duedate = 0;
        $data->cutoffdate = 0;
        $data->gradingduedate = 0;
        $data->timelimit = 0;
        $data->grade = 100;
        $data->submissiondrafts = 0;
        $data->requiresubmissionstatement = 0;
        $data->sendnotifications = 0;
        $data->sendlatenotifications = 0;
        $data->sendstudentnotifications = 0;
        $data->teamsubmission = 0;
        $data->requireallteammemberssubmit = 0;
        $data->blindmarking = 0;
        $data->hidegrader = 0;
        $data->markingworkflow = 0;
        $data->markingallocation = 0;
        $data->attemptreopenmethod = 'none';
        $data->maxattempts = -1;
        $data->preventsubmissionnotingroup = 1;
        $data->assignsubmission_file_enabled = 0;
        $data->assignsubmission_onlinetext_enabled = 0;
        $data->assignfeedback_comments_enabled = 1;
        $data->assignfeedback_editpdf_enabled = 0;
        $data->assignfeedback_file_enabled = 0;
        $data->visible = 1;
        $data->groupmode = SEPARATEGROUPS;
        $data->completion = COMPLETION_TRACKING_AUTOMATIC;
        $data->completionusegrade = 1;
        $data->completionpassgrade = 1;
        $moduleinfo = add_moduleinfo($data, $course);
        $cmrecord = $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
    }

    rebuild_course_cache((int) $course->id, true);
    [$cmrecord, $assign, $cm] = m10_checkpoint($course, $unit);
    if ((int) $cm->sectionnum !== $unit) {
        m10_fail("El control {$idnumber} no está ubicado en UT{$unit}.");
    }
    if ((int) $cmrecord->completion !== COMPLETION_TRACKING_AUTOMATIC
            || (int) $cmrecord->completiongradeitemnumber !== 0
            || !(int) $cmrecord->completionpassgrade) {
        m10_fail("El control {$idnumber} no conserva finalización automática por nota aprobatoria.");
    }

    $gradeitem = m10_grade_item($course, $assign);
    if ((float) $gradeitem->gradepass !== M10_SYNTHETIC_PASS_GRADE || (int) $gradeitem->hidden !== 0) {
        $gradeitem->gradepass = M10_SYNTHETIC_PASS_GRADE;
        $gradeitem->hidden = 0;
        if (!$gradeitem->update('asodeltol_m10_synthetic_setup')) {
            m10_fail("No fue posible fijar el umbral sintético en {$idnumber}.");
        }
    }
    return $cm;
}

function m10_availability_json(int $previouscmid): string {
    $json = json_encode([
        'op' => '&',
        'c' => [[
            'type' => 'completion',
            'cm' => $previouscmid,
            'e' => COMPLETION_COMPLETE_PASS,
        ]],
        'showc' => [false],
    ], JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        m10_fail('No fue posible serializar la restricción nativa de finalización.');
    }
    return $json;
}

/**
 * @param array<int, cm_info> $checkpoints
 */
function m10_ensure_section_chain(stdClass $course, array $checkpoints): void {
    global $DB;

    for ($unit = 1; $unit <= 5; $unit++) {
        $section = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $unit,
        ], '*', MUST_EXIST);
        if ((string) $section->name !== M10_OFFICIAL_UNITS[$unit - 1]) {
            m10_fail("UT{$unit} no conserva su denominación oficial.");
        }
        if ($unit === 1) {
            if (!empty($section->availability)) {
                m10_fail('UT1 ya tiene una restricción ajena al piloto; no se sobrescribirá.');
            }
            continue;
        }

        $expected = m10_availability_json((int) $checkpoints[$unit - 1]->id);
        if (!empty($section->availability) && $section->availability !== $expected) {
            m10_fail("UT{$unit} ya tiene una restricción ajena al piloto; no se sobrescribirá.");
        }
        if ($section->availability !== $expected) {
            course_update_section($course, $section, ['availability' => $expected]);
        }
    }
    rebuild_course_cache((int) $course->id, true);
}

function m10_reset_grade(stdClass $course, stdClass $user, int $unit): void {
    [, $assign, $cm] = m10_checkpoint($course, $unit);
    mod_assign_external::save_grade(
        (int) $assign->id,
        (int) $user->id,
        -1.0,
        -1,
        false,
        '',
        false,
        ['assignfeedbackcomments_editor' => [
            'text' => 'Reinicio sintético M10; sin contenido académico.',
            'format' => FORMAT_PLAIN,
        ]]
    );
    $completion = new completion_info($course);
    $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $user->id);
}

function m10_setup(): void {
    global $CFG, $DB;

    m10_admin();
    if (empty($CFG->enablecompletion) || empty($CFG->enableavailability)) {
        m10_fail('Finalización o disponibilidad condicional no están habilitadas en el staging.');
    }

    $course = m10_course();
    $category = \core_course_category::get((int) $course->category);
    $originalcoursevisible = (bool) $course->visible;
    $originalcategoryvisible = (bool) $category->visible;
    $criteria = $DB->count_records('course_completion_criteria', ['course' => $course->id]);

    if (!$category->visible) {
        $category->show();
    }
    if (!(int) $course->visible) {
        course_change_visibility((int) $course->id, true);
    }
    if (!(int) $course->enablecompletion) {
        update_course((object) ['id' => $course->id, 'enablecompletion' => 1]);
    }
    if ((int) $course->groupmode !== SEPARATEGROUPS || !(int) $course->groupmodeforce) {
        update_course((object) [
            'id' => $course->id,
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
        ]);
    }
    rebuild_course_cache((int) $course->id, true);
    $course = m10_course();

    $coursecontext = \context_course::instance((int) $course->id);
    foreach (M10_ACTORS as $actor => $username) {
        $user = m10_user($actor);
        $expectedgroup = strtoupper($actor);
        $expectedgroup = 'AF8-' . $expectedgroup;
        m10_group($course, $expectedgroup);
        if (!is_enrolled($coursecontext, $user, '', true)) {
            m10_fail("{$username} no está matriculado activamente en AF8.");
        }
        if (m10_user_groups($course, $user) !== [$expectedgroup]) {
            m10_fail("{$username} no pertenece exclusivamente a {$expectedgroup}.");
        }
    }

    $checkpoints = [];
    for ($unit = 1; $unit <= 5; $unit++) {
        $checkpoints[$unit] = m10_ensure_checkpoint($course, $unit);
    }
    m10_ensure_section_chain($course, $checkpoints);

    foreach (array_keys(M10_ACTORS) as $actor) {
        $user = m10_user($actor);
        for ($unit = 1; $unit <= 5; $unit++) {
            m10_reset_grade($course, $user, $unit);
        }
    }
    rebuild_course_cache((int) $course->id, true);

    echo json_encode([
        'status' => 'ready',
        'course_id' => (int) $course->id,
        'original_course_visible' => $originalcoursevisible,
        'original_category_visible' => $originalcategoryvisible,
        'synthetic_pass_grade' => M10_SYNTHETIC_PASS_GRADE,
        'threshold_is_institutional_policy' => false,
        'course_completion_criteria_unchanged' => $criteria,
        'synthetic_only' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function m10_apply_grade(string $actor, int $unit, float $grade): void {
    if ($unit < 1 || $unit > 5) {
        m10_fail('La unidad debe estar entre 1 y 5.');
    }
    if ($grade < 0 || $grade > 100) {
        m10_fail('La nota sintética debe estar entre 0 y 100.');
    }

    m10_admin();
    $course = m10_course();
    $user = m10_user($actor);
    [, $assign, $cm] = m10_checkpoint($course, $unit);
    mod_assign_external::save_grade(
        (int) $assign->id,
        (int) $user->id,
        $grade,
        -1,
        false,
        '',
        false,
        ['assignfeedbackcomments_editor' => [
            'text' => "Nota técnica sintética M10 UT{$unit}; no constituye regla académica.",
            'format' => FORMAT_PLAIN,
        ]]
    );
    $completion = new completion_info($course);
    $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $user->id);
    rebuild_course_cache((int) $course->id, true);

    $data = $completion->get_data($cm, false, (int) $user->id);
    echo json_encode([
        'status' => 'graded',
        'actor' => $actor,
        'unit' => $unit,
        'grade' => $grade,
        'completion_state' => (int) $data->completionstate,
        'passed' => (int) $data->completionstate === COMPLETION_COMPLETE_PASS,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function m10_grade_value(grade_item $item, stdClass $user): ?float {
    $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $user->id]);
    if (!$grade || $grade->finalgrade === null) {
        return null;
    }
    return (float) $grade->finalgrade;
}

/**
 * @param array<int, array{cmrecord: stdClass, assign: stdClass, cm: cm_info, gradeitem: grade_item}> $checkpoints
 */
function m10_actor_snapshot(stdClass $course, stdClass $user, array $checkpoints): array {
    $modinfo = get_fast_modinfo($course, (int) $user->id);
    $completion = new completion_info($course);
    $units = [];
    foreach ($checkpoints as $unit => $checkpoint) {
        $cm = $modinfo->get_cm((int) $checkpoint['cm']->id);
        $section = $modinfo->get_section_info($unit, MUST_EXIST);
        $completiondata = $completion->get_data($cm, false, (int) $user->id);
        $state = (int) $completiondata->completionstate;
        $units[(string) $unit] = [
            'grade' => m10_grade_value($checkpoint['gradeitem'], $user),
            'completion_state' => $state,
            'activity_completed' => $state !== COMPLETION_INCOMPLETE,
            'activity_passed' => $state === COMPLETION_COMPLETE_PASS,
            'section_user_visible' => (bool) $section->uservisible,
            'activity_user_visible' => (bool) $cm->uservisible,
            'cm_id' => (int) $cm->id,
            'marker' => m10_marker($unit),
        ];
    }

    return [
        'user_id' => (int) $user->id,
        'username' => (string) $user->username,
        'groups' => m10_user_groups($course, $user),
        'course_complete' => $completion->is_course_complete((int) $user->id),
        'units' => $units,
    ];
}

function m10_snapshot(): void {
    global $CFG, $DB;

    m10_admin();
    $course = m10_course();
    $category = \core_course_category::get((int) $course->category);
    $checkpoints = [];
    $sections = [];
    for ($unit = 1; $unit <= 5; $unit++) {
        [$cmrecord, $assign, $cm] = m10_checkpoint($course, $unit);
        $section = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $unit,
        ], '*', MUST_EXIST);
        $gradeitem = m10_grade_item($course, $assign);
        $checkpoints[$unit] = compact('cmrecord', 'assign', 'cm', 'gradeitem');
        $sections[(string) $unit] = [
            'name' => (string) $section->name,
            'official_name_match' => (string) $section->name === M10_OFFICIAL_UNITS[$unit - 1],
            'availability' => $section->availability,
            'expected_previous_cm_id' => $unit === 1 ? null : (int) $checkpoints[$unit - 1]['cm']->id,
        ];
    }

    $actors = [];
    foreach (array_keys(M10_ACTORS) as $actor) {
        $actors[$actor] = m10_actor_snapshot($course, m10_user($actor), $checkpoints);
    }

    echo json_encode([
        'runtime' => [
            'release' => $CFG->release,
            'wwwroot' => $CFG->wwwroot,
            'course_visible' => (bool) $course->visible,
            'category_visible' => (bool) $category->visible,
            'completion_enabled' => (bool) $course->enablecompletion,
            'availability_enabled' => (bool) $CFG->enableavailability,
            'separate_groups' => (int) $course->groupmode === SEPARATEGROUPS,
            'group_mode_forced' => (bool) $course->groupmodeforce,
        ],
        'course' => [
            'course_id' => (int) $course->id,
            'idnumber' => (string) $course->idnumber,
            'completion_criteria_count' => $DB->count_records('course_completion_criteria', [
                'course' => $course->id,
            ]),
            'sections' => $sections,
        ],
        'scenario' => [
            'synthetic_pass_grade' => M10_SYNTHETIC_PASS_GRADE,
            'threshold_is_institutional_policy' => false,
            'required_completion_state' => COMPLETION_COMPLETE_PASS,
        ],
        'actors' => $actors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function m10_lockdown(): void {
    m10_admin();
    $course = m10_course();
    if ((int) $course->visible) {
        course_change_visibility((int) $course->id, false);
    }
    $category = \core_course_category::get((int) $course->category);
    if ($category->visible) {
        $category->hide();
    }
    rebuild_course_cache((int) $course->id, true);
    echo json_encode([
        'status' => 'locked_down',
        'course_visible' => false,
        'category_visible' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$options = getopt('', ['phase:', 'actor:', 'unit:', 'grade:']);
$phase = $options['phase'] ?? '';

switch ($phase) {
    case 'setup':
        m10_setup();
        break;
    case 'grade':
        if (!isset($options['actor'], $options['unit'], $options['grade'])) {
            m10_fail('Use --phase=grade --actor=g01|g02 --unit=1..5 --grade=0..100.');
        }
        m10_apply_grade((string) $options['actor'], (int) $options['unit'], (float) $options['grade']);
        break;
    case 'snapshot':
        m10_snapshot();
        break;
    case 'lockdown':
        m10_lockdown();
        break;
    default:
        m10_fail('Use --phase=setup, --phase=grade, --phase=snapshot o --phase=lockdown.');
}
