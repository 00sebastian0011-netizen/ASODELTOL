<?php
namespace local_asodeltolstaff;

/** Create native resources with mandatory group restrictions and narrow authorization. */
final class group_publisher {
    /** Caller supplies reviewed content; this method never chooses academic rules. */
    public static function create(int $courseid, int $groupid, int $section, string $kind, string $title,
            string $reference, string $html = '', string $url = '', int $draftid = 0, int $duedate = 0): int {
        global $CFG, $DB, $USER;
        $context = \context_course::instance($courseid);
        require_capability('local/asodeltolstaff:publishown', $context);
        \core_user::require_active_user($USER);
        $course = get_course($courseid);
        $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid], '*', MUST_EXIST);
        $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section], 'id', MUST_EXIST);
        if (!is_siteadmin() && (!is_enrolled($context, $USER->id, '', true)
                || !groups_is_member($groupid, $USER->id))) {
            throw new \moodle_exception('cannotmanagegroup', 'local_asodeltolstaff');
        }
        if (empty($CFG->enableavailability)) {
            throw new \invalid_parameter_exception('Group availability must be enabled before publishing');
        }
        if (!in_array($kind, ['page', 'url', 'resource', 'video', 'assign'], true)) {
            throw new \invalid_parameter_exception('Unsupported module type');
        }
        $title = trim(clean_param($title, PARAM_TEXT));
        $reference = trim(clean_param($reference, PARAM_TEXT));
        if ($title === '' || \core_text::strlen($title) > 255 || $reference === '') {
            throw new \invalid_parameter_exception('Title and content review reference are required');
        }
        $cleanhtml = clean_text($html, FORMAT_HTML);
        if (in_array($kind, ['page', 'assign'], true) && html_is_blank($cleanhtml)) {
            throw new \invalid_parameter_exception('Page content required');
        }
        if ($kind === 'assign' && $duedate <= 0) {
            throw new \invalid_parameter_exception('An explicit activity due date is required');
        }
        if ($kind === 'url') {
            $parts = parse_url($url);
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                    || isset($parts['user']) || isset($parts['pass'])) {
                throw new \invalid_parameter_exception('HTTPS URL without embedded credentials required');
            }
        }
        if (in_array($kind, ['resource', 'video'], true)) {
            $files = get_file_storage()->get_area_files(\context_user::instance($USER->id)->id,
                'user', 'draft', $draftid, 'id', false);
            if ($draftid <= 0 || count($files) !== 1) {
                throw new \invalid_parameter_exception('One uploaded file from the current user is required');
            }
            $file = reset($files);
            if ($kind === 'video' && strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'mp4') {
                throw new \invalid_parameter_exception('Embedded video requires an MP4 file');
            }
            $allowed = ['pdf', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'jpeg', 'mp3', 'mp4', 'vtt'];
            if (!in_array(strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)), $allowed, true)
                    || $file->is_external_file() || $file->get_filesize() <= 0
                    || $file->get_filesize() > get_max_upload_file_size($CFG->maxbytes, $course->maxbytes)) {
                throw new \invalid_parameter_exception('File type, size or ownership is not permitted');
            }
        }
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        $requestedkind = $kind;
        $embeddedvideo = $requestedkind === 'video';
        $kind = $embeddedvideo ? 'resource' : $kind;
        $module = $DB->get_record('modules', ['name' => $kind, 'visible' => 1], '*', MUST_EXIST);
        $info = (object) ['module' => $module->id, 'modulename' => $kind, 'section' => $section,
            'name' => $title, 'visible' => 1, 'visibleoncoursepage' => 1, 'groupingid' => 0,
            'groupmode' => $course->groupmode, 'showdescription' => 0, 'completion' => COMPLETION_TRACKING_NONE,
            'cmidnumber' => 'ASODELTOL-GROUP-' . $groupid . '-' . bin2hex(random_bytes(6)),
            'intro' => s($reference), 'introformat' => FORMAT_HTML,
            'introeditor' => ['text' => s($reference), 'format' => FORMAT_HTML, 'itemid' => 0],
            'availability' => json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => $groupid]], 'showc' => [false]]),
            'display' => 0, 'printintro' => 0, 'printlastmodified' => 0,
        ];
        if ($kind === 'page') {
            $info->content = $cleanhtml;
            $info->contentformat = FORMAT_HTML;
            $info->revision = 1;
        } else if ($kind === 'url') {
            $info->externalurl = $url;
        } else if ($requestedkind === 'assign') {
            // A practice activity; grading rules must be configured separately by
            // an authorized academic editor. No pass mark or official grade is chosen.
            $info->introeditor['text'] = $cleanhtml;
            $info->intro = $cleanhtml;
            $info->grade = 0;
            $info->duedate = $duedate;
            $info->allowsubmissionsfromdate = 0;
            $info->cutoffdate = 0;
            $info->gradingduedate = 0;
            $info->submissiondrafts = 0;
            $info->requiresubmissionstatement = 0;
            $info->sendnotifications = 0;
            $info->sendstudentnotifications = 0;
            $info->assignsubmission_file_enabled = 1;
            $info->assignsubmission_file_maxfiles = 1;
            $info->assignsubmission_file_maxsizebytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes);
            $info->assignsubmission_file_filetypeslist = '*';
            $info->assignsubmission_onlinetext_enabled = 0;
            $info->teamsubmission = 0;
            $info->attemptreopenmethod = 'none';
            $info->maxattempts = 1;
            $info->markingworkflow = 0;
            $info->markingallocation = 0;
            $info->blindmarking = 0;
            $info->activityformat = 0;
            $info->timelimit = 0;
        } else {
            $info->display = $embeddedvideo ? RESOURCELIB_DISPLAY_EMBED : RESOURCELIB_DISPLAY_DOWNLOAD;
            $info->files = $draftid;
            $info->showsize = 1;
            $info->showtype = 1;
            $info->showdate = 0;
            $info->filterfiles = 0;
            $info->revision = 1;
        }
        // add_moduleinfo intentionally delegates authorization to its caller.
        // The narrow publishown capability and group checks above are the gate;
        // the current user remains the author in native events. No admin impersonation.
        $saved = add_moduleinfo($info, $course);
        return (int) $saved->coursemodule;
    }
}
