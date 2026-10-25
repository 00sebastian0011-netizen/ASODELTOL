<?php
namespace local_asodeltolstaff\local\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** A resource can only be assigned to one of the actor's permitted groups. */
final class publish_form extends \moodleform {
    protected function definition(): void {
        $f = $this->_form;
        $f->addElement('hidden', 'courseid');
        $f->setType('courseid', PARAM_INT);
        $f->addElement('select', 'groupid', get_string('group', 'local_asodeltolstaff'), $this->_customdata['groups']);
        $f->setType('groupid', PARAM_INT);
        $f->addElement('select', 'section', get_string('section', 'local_asodeltolstaff'), $this->_customdata['sections']);
        $f->setType('section', PARAM_INT);
        $f->addElement('select', 'kind', get_string('resourcetype', 'local_asodeltolstaff'),
            ['page' => get_string('modulename', 'page'), 'url' => get_string('modulename', 'url'),
             'resource' => get_string('modulename', 'resource'), 'video' => get_string('embeddedvideo', 'local_asodeltolstaff'),
             'assign' => get_string('practiceactivity', 'local_asodeltolstaff')]);
        $f->setType('kind', PARAM_ALPHA);
        $f->addElement('text', 'title', get_string('name'), ['maxlength' => 255]);
        $f->setType('title', PARAM_TEXT);
        $f->addRule('title', null, 'required');
        $f->addElement('text', 'reference', get_string('contentreference', 'local_asodeltolstaff'));
        $f->setType('reference', PARAM_TEXT);
        $f->addRule('reference', null, 'required');
        $f->addElement('editor', 'body', get_string('content'), null, ['maxfiles' => 0]);
        $f->setType('body', PARAM_RAW);
        foreach (['url', 'resource', 'video'] as $kind) {
            $f->hideIf('body', 'kind', 'eq', $kind);
        }
        $f->addElement('date_time_selector', 'duedate', get_string('duedate', 'assign'), ['optional' => true]);
        $f->hideIf('duedate', 'kind', 'neq', 'assign');
        $f->addElement('url', 'externalurl', get_string('externalurl', 'url'));
        $f->setType('externalurl', PARAM_URL);
        $f->hideIf('externalurl', 'kind', 'neq', 'url');
        $f->addElement('filepicker', 'asset', get_string('file'), null, [
            'maxbytes' => $this->_customdata['maxbytes'],
            'accepted_types' => ['.pdf', '.docx', '.xlsx', '.pptx', '.png', '.jpg', '.jpeg', '.mp3', '.mp4', '.vtt'],
        ]);
        $f->setType('asset', PARAM_INT);
        foreach (['page', 'url', 'assign'] as $kind) {
            $f->hideIf('asset', 'kind', 'eq', $kind);
        }
        $this->add_action_buttons(true, get_string('publishresource', 'local_asodeltolstaff'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (in_array($data['kind'], ['page', 'assign'], true) && html_is_blank($data['body']['text'] ?? '')) {
            $errors['body'] = get_string('required');
        }
        if ($data['kind'] === 'url') {
            $url = parse_url($data['externalurl'] ?? '');
            if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
                $errors['externalurl'] = get_string('publishinvalidurl', 'local_asodeltolstaff');
            }
        }
        if (in_array($data['kind'], ['resource', 'video'], true) && empty($data['asset'])) {
            $errors['asset'] = get_string('required');
        }
        if ($data['kind'] === 'assign' && empty($data['duedate'])) {
            $errors['duedate'] = get_string('required');
        }
        return $errors;
    }
}
