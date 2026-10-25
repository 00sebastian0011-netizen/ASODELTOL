<?php
namespace local_asodeltolstaff;

/** Optional native fields. Values describe roles; they never grant permissions. */
final class metadata_fields {
    public static function configure(): array {
        require_capability('moodle/site:config', \context_system::instance());
        $result = [];
        foreach (['core_course' => ['course', [
                'asodeltol_adminroleid' => 'adminroleid', 'asodeltol_auditorroleid' => 'auditorroleid']],
                'core_group' => ['group', ['asodeltol_available' => 'groupavailability']]] as $component => [$area, $fields]) {
            $handler = \core_customfield\handler::get_handler($component, $area);
            $category = null;
            foreach ($handler->get_categories_with_fields() as $candidate) {
                if ($candidate->get('name') === 'ASODELTOL') {
                    $category = $candidate;
                }
            }
            if (!$category) {
                $category = \core_customfield\category_controller::create($handler->create_category('ASODELTOL'));
            }
            foreach ($fields as $shortname => $label) {
                $type = $area === 'group' ? 'select' : 'text';
                $existing = null;
                foreach ($handler->get_fields() as $field) {
                    if ($field->get('shortname') === $shortname) {
                        $existing = $field;
                    }
                }
                if ($existing) {
                    if ($existing->get('type') !== $type || $existing->get('categoryid') != $category->get('id')) {
                        throw new \invalid_parameter_exception('Existing custom field collision: ' . $shortname);
                    }
                    $result[$shortname] = (int) $existing->get('id');
                    continue;
                }
                $config = ['required' => 0, 'uniquevalues' => 0, 'locked' => 0, 'visibility' => 0,
                    'defaultvalue' => '', 'displaysize' => 12, 'maxlength' => 20, 'ispassword' => 0,
                    'link' => '', 'linktarget' => ''];
                if ($type === 'select') {
                    $config['options'] = get_string('available') . "\n" . get_string('notavailable');
                    // Blank until a responsible administrator chooses a state.
                }
                $data = (object) ['name' => get_string($label, 'local_asodeltolstaff'), 'shortname' => $shortname,
                    'type' => $type, 'description' => get_string('metadatameaning', 'local_asodeltolstaff'),
                    'descriptionformat' => FORMAT_PLAIN, 'configdata' => $config];
                $field = \core_customfield\field_controller::create(0, (object) ['type' => $type], $category);
                $handler->save_field_configuration($field, $data);
                $result[$shortname] = (int) $field->get('id');
            }
        }
        return $result;
    }
}
