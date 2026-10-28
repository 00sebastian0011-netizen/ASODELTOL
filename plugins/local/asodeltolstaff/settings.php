<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_asodeltolstaff', get_string('pluginname', 'local_asodeltolstaff'));
    $ADMIN->add('localplugins', $settings);
    $recipients = [0 => get_string('choosedots')];
    foreach (get_admins() as $admin) {
        $recipients[$admin->id] = fullname($admin);
    }
    $settings->add(new admin_setting_configselect('local_asodeltolstaff/supportuserid',
        get_string('supportrecipient', 'local_asodeltolstaff'),
        get_string('supportmeaning', 'local_asodeltolstaff'), 0, $recipients));
    $settings->add(new admin_setting_configtext('local_asodeltolstaff/supportemail',
        get_string('supportemail', 'local_asodeltolstaff'),
        get_string('supportemail_desc', 'local_asodeltolstaff'), '', PARAM_EMAIL));
    $ADMIN->add('localplugins', new admin_externalpage('asodeltolmetadata',
        get_string('metadatafields', 'local_asodeltolstaff'),
        new moodle_url('/local/asodeltolstaff/metadata.php')));
    $ADMIN->add('localplugins', new admin_externalpage('asodeltolcredentials',
        get_string('studentcredentials', 'local_asodeltolstaff'),
        new moodle_url('/local/asodeltolstaff/credentials.php')));
}
