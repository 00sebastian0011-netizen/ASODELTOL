<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Banners of a course: the one of the course and one for each section.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_rsmax\local\banner;

require('../../config.php');

$courseid = required_param('id', PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:update', $context);

$modinfo = get_fast_modinfo($course);
$section = $sectionid ? $modinfo->get_section_info_by_id($sectionid, MUST_EXIST) : null;

$baseurl = new moodle_url('/theme/rsmax/banners.php', ['id' => $course->id]);
$PAGE->set_url($section ? new moodle_url($baseurl, ['sectionid' => $section->id]) : $baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('banners', 'theme_rsmax'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->add_body_class('limitedwidth');

$area = $section ? banner::AREA_SECTION : banner::AREA_COURSE;
$itemid = $section ? (int) $section->id : 0;
$sectionname = $section ? get_section_name($course, $section) : '';

$form = new \theme_rsmax\form\banner_form(null, ['sectionname' => $sectionname]);
if ($form->is_cancelled()) {
    redirect($baseurl);
}
if ($data = $form->get_data()) {
    file_save_draft_area_files($data->banner, $context->id, 'theme_rsmax', $area, $itemid, banner::file_options());
    if (!$section) {
        banner::set_scope($course->id, (int) $data->scope);
    }
    banner::purge($course->id);
    redirect($baseurl, get_string('bannersaved', 'theme_rsmax'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$draftid = file_get_submitted_draft_itemid('banner');
file_prepare_draft_area($draftid, $context->id, 'theme_rsmax', $area, $itemid, banner::file_options());
$banners = banner::get($course->id);
$form->set_data(['id' => $course->id, 'sectionid' => $itemid, 'banner' => $draftid, 'scope' => $banners['scope']]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('banners', 'theme_rsmax'));
echo html_writer::tag('p', get_string('bannersintro', 'theme_rsmax'), ['class' => 'rsmax-banners-intro']);
$form->display();

if (!$section) {
    // Below the banner of the course, its sections: each can have a banner of its own.
    $rows = [];
    foreach ($modinfo->get_section_info_all() as $info) {
        if ($info->is_delegated()) {
            continue;
        }
        $rows[] = [
            'name' => get_section_name($course, $info),
            'image' => $banners['sections'][$info->id] ?? '',
            'editurl' => (new moodle_url($baseurl, ['sectionid' => $info->id]))->out(false),
        ];
    }
    echo $OUTPUT->render_from_template('theme_rsmax/banners', ['sections' => $rows]);
}
echo $OUTPUT->footer();
