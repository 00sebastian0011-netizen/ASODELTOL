<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English strings for local_asodeltolstaff.
 *
 * @package    local_asodeltolstaff
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Action';
$string['trainingactioncode'] = 'Training action code';
$string['trainingaction'] = 'Training action';
$string['attendance'] = 'Attendance';
$string['attendance_saved'] = 'The provisional attendance record was saved.';
$string['attendance_status'] = 'Provisional attendance status';
$string['cannotmanagegroup'] = 'You cannot manage records for this group or trainer.';
$string['createdby'] = 'Recorded by';
$string['evidence'] = 'Evidence reference';
$string['evidence_help'] = 'Optional HTTPS reference to evidence held by an approved system. Do not enter credentials or personal data.';
$string['export'] = 'Export report';
$string['group'] = 'Group';
$string['groupchangeforbidden'] = 'A session group cannot be changed after the session has been created.';
$string['history'] = 'Change history';
$string['invalidattendanceuser'] = 'The selected user is not an eligible student in this session group.';
$string['invalidevidence'] = 'The evidence reference must be an HTTPS URL.';
$string['invalidreportedperiod'] = 'Reported start and end must both be empty or form a consistent interval.';
$string['invalidscheduledperiod'] = 'The scheduled end must be later than the scheduled start.';
$string['invalidvalidatedminutes'] = 'Validated minutes must be positive and cannot exceed the reported duration.';
$string['asodeltolstaff:beattended'] = 'Be included in provisional attendance records';
$string['asodeltolstaff:export'] = 'Export session and attendance reports';
$string['asodeltolstaff:manageown'] = 'Manage sessions and attendance for assigned groups';
$string['asodeltolstaff:validate'] = 'Administratively validate reported session time';
$string['asodeltolstaff:viewall'] = 'View all groups in session reports';
$string['asodeltolstaff:viewevidence'] = 'View session evidence references';
$string['asodeltolstaff:viewsessions'] = 'View academic session records';
$string['newsession'] = 'New synthetic session';
$string['pluginname'] = 'ASODELTOL attendance and trainer tracking';
$string['privacy:metadata:attendance'] = 'Stores provisional student attendance declarations for an academic session.';
$string['privacy:metadata:attendance:reportedby'] = 'User who recorded or corrected attendance.';
$string['privacy:metadata:attendance:status'] = 'Provisional technical attendance state.';
$string['privacy:metadata:attendance:studentid'] = 'Student associated with the declaration.';
$string['privacy:metadata:history'] = 'Stores append-only history for session and attendance changes.';
$string['privacy:metadata:history:actorid'] = 'User who performed the change.';
$string['privacy:metadata:history:subjectuserid'] = 'User whose session or attendance record the change concerns.';
$string['privacy:metadata:history:payload'] = 'Structured before-and-after values for the change.';
$string['privacy:metadata:session'] = 'Stores scheduled, reported and administratively validated session data.';
$string['privacy:metadata:session:createdby'] = 'User who created the session record.';
$string['privacy:metadata:session:evidenceref'] = 'Optional reference to externally held evidence.';
$string['privacy:metadata:session:trainerid'] = 'Trainer responsible for the session.';
$string['privacy:metadata:session:validatedby'] = 'User who performed administrative validation.';
$string['reason'] = 'Reason for change';
$string['reasonrequired'] = 'A reason is required for declarations and corrections.';
$string['reportedend'] = 'Reported end';
$string['reportedminutes'] = 'Reported minutes';
$string['reportedstart'] = 'Reported start';
$string['saveattendance'] = 'Save provisional attendance';
$string['savesession'] = 'Save session record';
$string['scheduledend'] = 'Scheduled end';
$string['scheduledminutes'] = 'Scheduled minutes';
$string['scheduledstart'] = 'Scheduled start';
$string['section'] = 'Academic unit';
$string['sessioncode'] = 'Session identifier';
$string['sessioncreated'] = 'The session record was created.';
$string['sessionupdated'] = 'The session record was updated and any previous validation was reset.';
$string['stafftracking'] = 'Attendance and trainer tracking';
$string['status_pending'] = 'Pending administrative validation';
$string['status_rejected'] = 'Rejected technical scenario';
$string['status_reported_absent'] = 'Reported absent (provisional)';
$string['status_reported_excused'] = 'Reported excused (provisional)';
$string['status_reported_late'] = 'Reported late (provisional)';
$string['status_reported_present'] = 'Reported present (provisional)';
$string['status_validated'] = 'Validated technical scenario';
$string['student'] = 'Student';
$string['trainer'] = 'Trainer';
$string['validate'] = 'Administrative validation';
$string['validatedby'] = 'Validated by';
$string['validatedminutes'] = 'Validated minutes';
$string['validation_saved'] = 'The administrative validation was recorded.';
$string['validationstatus'] = 'Validation state';

$string['additionalreports'] = 'Enrollment and completion reports';
$string['reportsdownloads'] = 'Reports and downloads';
$string['reportcourseselect'] = 'Select an authorised course to view its reports and downloads.';
$string['reportsessions'] = 'Sessions and attendance';
$string['reportmeaning'] = 'Enrollment is counted once per learner in the consolidated report. Completion reflects the current Moodle course configuration; it does not prove attendance.';
$string['reportdataset'] = 'Report';
$string['reportparticipants'] = 'Enrolled learners by group';
$string['reportconsolidated'] = 'Course consolidated totals';
$string['report_trainingactioncode'] = 'Training action code';
$string['report_trainingaction'] = 'Training action';
$string['report_group'] = 'Group';
$string['report_username'] = 'Username';
$string['report_student'] = 'Learner';
$string['report_enrolmentstate'] = 'Current enrollment state';
$string['report_moodlecompleted'] = 'Completed in Moodle';
$string['report_completionutc'] = 'Completion (UTC)';
$string['report_groupcount'] = 'Groups in scope';
$string['report_registeredcount'] = 'Distinct enrolled learners';
$string['report_activecount'] = 'Active learners';
$string['report_moodlecompletedcount'] = 'Completed in Moodle';
$string['report_completionenabled'] = 'Course completion enabled';

$string['officehours'] = 'Schedule consultation hour';
$string['joinmeeting'] = 'Join meeting';
$string['meetingurl'] = 'Approved meeting URL (HTTPS, optional)';
$string['officehoursminimum'] = 'Enter the agreed date, trainer and group. The consultation slot must last at least 60 minutes.';
$string['reportaccess'] = 'Trainer access and consultation times';
$string['report_trainer'] = 'Trainer';
$string['report_rowtype'] = 'Source event type';
$string['report_loginutc'] = 'Observed site login (UTC)';
$string['report_logoututc'] = 'Observed site logout (UTC)';
$string['report_coursestartutc'] = 'Course configured start (UTC)';
$string['report_courseendutc'] = 'Course configured end (UTC)';
$string['report_officehoursstartutc'] = 'Consultation start (UTC)';
$string['report_officehoursendutc'] = 'Consultation end (UTC)';
$string['report_sourcerecord'] = 'Native source record';
$string['privacy:metadata:calendar'] = 'Consultation group, trainer and schedule are stored by the Moodle Calendar subsystem.';

$string['accessreportmeaning'] = 'Login/logout rows are independent observed site events. A missing logout stays empty; these events do not prove attendance, work duration or continuous connection.';

$string['platformhelp'] = 'Platform help';
$string['guide_access_title'] = 'Access the course';
$string['guide_access'] = 'Open My courses and select your course. If a unit is unavailable, check the conditions displayed in the course and contact your trainer.';
$string['guide_submission_title'] = 'Submit activities';
$string['guide_submission'] = 'For assignments that accept files, open the activity, select Add submission, attach your file and save. Check the submission status; if a final submit button appears, follow the instructions for that activity.';
$string['guide_grades_title'] = 'Grades and progress';
$string['guide_grades'] = 'Open Grades to review your results. Completion marks and visible unit conditions show which activities remain; the rules depend on the course configuration.';
$string['guide_communication_title'] = 'Messages and calendar';
$string['guide_communication'] = 'Use Messages to contact authorized people. Check the Calendar for published activities and appointments. Trainers or administrators schedule consultation slots for each group.';
$string['guide_account_title'] = 'Account and security';
$string['guide_account'] = 'Use your own account, keep your password private and log out on shared devices. Begin account recovery through the forgotten password link on the login page.';

$string['asodeltolstaff:publishown'] = 'Publish reviewed resources for assigned groups';
$string['publishresource'] = 'Publish resource for group';
$string['resourcetype'] = 'Resource type';
$string['contentreference'] = 'Content review reference';
$string['publishmeaning'] = 'Choose your group and an existing unit. The new resource is restricted to that group. This form creates resources; it does not edit other materials or choose grading rules.';
$string['publishinvalidurl'] = 'Use an HTTPS URL without embedded credentials.';

$string['academicprogress'] = 'Grades and progress';
$string['progressmeaning'] = 'Progress reflects activities completed according to Moodle. Completion is not the same as passing, attendance or time worked. An em dash means progress is not configured or there are no tracked activities.';
$string['overallprogress'] = 'Progress';
$string['completedactivities'] = 'Completed / tracked activities';
$string['embeddedvideo'] = 'Embedded MP4 video';
$string['practiceactivity'] = 'Ungraded practice activity';
$string['supportchannel'] = 'Platform support';
$string['supportrecipient'] = 'Internal support recipient';
$string['supportmeaning'] = 'This form sends an internal notification to the configured site administrator. Do not send passwords or sensitive documents. Response times are defined by the institution.';
$string['supportunconfigured'] = 'The institution has not configured the support recipient yet.';
$string['supportsent'] = 'Request registered. Native notification reference: {$a}.';
$string['supportwait'] = 'Please wait at least one minute before submitting another request.';
$string['supportfailed'] = 'The native notification could not be delivered.';
$string['metadatafields'] = 'ASODELTOL administrative fields';
$string['adminroleid'] = 'Administrator role identifier';
$string['auditorroleid'] = 'Oversight role identifier';
$string['groupavailability'] = 'Group administrative availability';
$string['metadatameaning'] = 'Adds optional fields to the native course and group forms. Role IDs are descriptive metadata and do not grant permissions. Group availability is administrative metadata; it does not suspend enrolments or replace access restrictions. No existing values are overwritten.';
$string['groupannouncement'] = 'Group announcement';
$string['groupschedule'] = 'Group timetable event';
$string['groupcommunication'] = 'Group announcements and timetable';
$string['opengroupsection'] = 'Open a hidden unit for this group';
$string['communicationmeaning'] = 'Publish only reviewed information. Timetable dates must be supplied explicitly. Opening a hidden unit requires native section-visibility permission and preserves existing date/completion restrictions.';
$string['invalidinterval'] = 'End must be later than start.';
$string['messageprovider:supportrequest'] = 'Internal support requests';
$string['messageprovider:groupannouncement'] = 'Group announcements';
$string['privacy:metadata:messages'] = 'Support requests and group notices use native Moodle notifications.';
$string['privacy:metadata:customfields'] = 'Administrative course and group metadata use native custom fields.';
$string['privacy:metadata:calendar'] = 'Consultation group, trainer and schedule are stored by the Moodle Calendar subsystem.';
