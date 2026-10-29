<?php
/**
 * Standalone Moodle-compatible decision tests. Run with:
 *   php tests/local_asodeltolstaff/profile-permissions.test.php
 *
 * This mocks Moodle's enrolment, role and group APIs without creating users
 * or modifying any production data. HTTP acceptance is still required.
 */
namespace core {
    final class user {
        public const VIEWPROFILE_PREVENT = -1;
        public const VIEWPROFILE_DO_NOT_PREVENT = 0;
        public const VIEWPROFILE_FORCE_ALLOW = 1;
    }
}
namespace {
    define('MOODLE_INTERNAL', true);
    define('PARAM_INT', 1);
    class context {}
    class context_course extends context {
        public function __construct(public int $id) {}
        public static function instance(int $id): self { return new self($id); }
    }
    class moodle_url {
        public function __construct(public string $path) {}
    }
    class RedirectSignal extends \Exception {}

    $courses = [
        101 => [21], 102 => [21, 22], 103 => [21], 104 => [21], 105 => [],
        106 => [21], 107 => [4], 108 => [21, 4], 109 => [21],
        110 => [21], 111 => [4], 112 => [21], 113 => [21], 201 => [21], 202 => [21],
        203 => [4], 204 => [22], 300 => [21], 401 => [21, 4],
    ];
    $courseRoles = [
        21 => [101 => ['student'], 102 => ['asodeltol_capacitador'],
            103 => ['asodeltol_auditor'], 104 => ['sena'],
            106 => ['asodeltol_capacitador_m07'], 108 => ['asodeltol_capacitador'],
            109 => ['editingteacher'], 110 => ['asodeltol_capacitador'],
            112 => ['capacitador'], 113 => ['asodeltol_trainer'],
            201 => ['student'], 202 => ['student'], 300 => ['editingteacher'],
            401 => ['student']],
        22 => [102 => ['asodeltol_capacitador'], 204 => ['student']],
        4 => [107 => ['student'], 108 => ['asodeltol_capacitador'],
            111 => ['asodeltol_auditor'], 203 => ['student'], 401 => ['student']],
    ];
    $groups = [
        21 => [102 => [1], 106 => [1], 108 => [2], 110 => [2], 112 => [1], 113 => [1],
            201 => [1], 202 => [2], 300 => [1], 401 => [1]],
        22 => [102 => [7], 204 => [7]],
        4 => [108 => [5], 203 => [6], 401 => [5]],
    ];
    $USER = (object)['id' => 101];
    $SCRIPT = '/user/profile.php';

    function isloggedin(): bool { return true; }
    function isguestuser(): bool { return false; }
    function is_siteadmin(): bool { return (int) $GLOBALS['USER']->id === 105; }
    function enrol_get_users_courses(int $id, bool $onlyactive = true, string $fields = 'id'): array {
        $result = [];
        foreach ($GLOBALS['courses'][$id] ?? [] as $courseid) {
            $result[$courseid] = (object)['id' => $courseid];
        }
        return $result;
    }
    function is_enrolled(context_course $context, $userid = null, string $capability = '', bool $onlyactive = false): bool {
        return in_array($context->id, $GLOBALS['courses'][(int)$userid] ?? [], true);
    }
    function get_user_roles(context_course $context, int $userid, bool $checkparentcontexts = false): array {
        return array_map(static fn($name) => (object)['shortname' => $name],
            $GLOBALS['courseRoles'][$context->id][$userid] ?? []);
    }
    function has_capability(string $capability, context_course $context, int $userid): bool {
        return $capability === 'local/asodeltolstaff:viewminimalprofile'
            && in_array('student', $GLOBALS['courseRoles'][$context->id][$userid] ?? [], true);
    }
    function groups_get_all_groups(int $courseid, int $userid): array {
        $result = [];
        foreach ($GLOBALS['groups'][$courseid][$userid] ?? [] as $groupid) {
            $result[$groupid] = (object)['id' => $groupid];
        }
        return $result;
    }
    function optional_param(string $name, $default, int $type) {
        return $GLOBALS['requestedid'] ?? $default;
    }
    function redirect(moodle_url $url): void { throw new RedirectSignal($url->path); }

    require __DIR__ . '/../../plugins/local/asodeltolstaff/classes/profile_privacy.php';

    use local_asodeltolstaff\profile_privacy as privacy;
    $cases = [
        // Viewer, subject, course (null means no course parameter), expected decision.
        ['beneficiary self', 101, 101, 21, 0],
        ['beneficiary same group', 101, 201, 21, -1],
        ['beneficiary other group', 101, 202, 21, -1],
        ['beneficiary no shared course', 107, 201, null, -1],
        ['beneficiary tampered course', 101, 203, 4, -1],
        ['beneficiary no course parameter', 101, 201, null, -1],
        ['trainer authorised group', 102, 201, 21, 1],
        ['trainer other group', 102, 202, 21, -1],
        ['trainer no course parameter', 102, 201, null, 1],
        ['trainer not a beneficiary', 102, 300, 21, -1],
        ['trainer spoofed different course', 102, 203, 4, -1],
        ['trainer target not enrolled in requested course', 102, 201, 22, -1],
        ['trainer role m07 valid group', 106, 201, 21, 1],
        ['trainer role m07 other group', 106, 202, 21, -1],
        ['trainer role capacitador alias', 112, 201, 21, 1],
        ['trainer role asodeltol_trainer alias', 113, 202, 21, -1],
        ['trainer alternate shared course without parameter', 108, 401, null, 1],
        ['trainer only denied scoped course', 108, 401, 21, -1],
        ['trainer authorised second course', 108, 401, 4, 1],
        ['auditor authorised', 103, 201, 21, 1],
        ['auditor target teacher same enrolled course', 103, 300, 21, 1],
        ['auditor target not enrolled', 103, 203, 21, -1],
        ['auditor different course tamper', 103, 203, 4, -1],
        ['auditor no shared course', 103, 203, null, -1],
        ['auditor shared course no parameter', 103, 201, null, 1],
        ['auditor assigned second course', 111, 203, 4, 1],
        ['sena authorised', 104, 202, 21, 1],
        ['sena target not enrolled', 104, 203, 21, -1],
        ['sena no shared course', 104, 203, null, -1],
        ['sena same course without parameter', 104, 201, null, 1],
        ['administrator unchanged', 105, 203, null, 0],
        ['non-institutional core rules unchanged', 109, 201, 21, 0],
        ['deleted target denied', 103, 201, 21, -1, true],
    ];

    $passed = 0;
    foreach ($cases as [$label, $viewerid, $subjectid, $courseid, $expected]) {
        $USER = (object)['id' => $viewerid];
        $target = (object)['id' => $subjectid];
        if (($cases[$passed][5] ?? false) === true) {
            $target->deleted = 1;
        }
        $course = $courseid === null ? null : (object)['id' => $courseid];
        $actual = privacy::control_view_profile($target, $course);
        if ($actual !== $expected) {
            fwrite(STDERR, "FAIL: {$label} (expected {$expected}, got {$actual})\n");
            exit(1);
        }
        $passed++;
    }

    $USER = (object)['id' => 101];
    $requestedid = 101;
    try {
        privacy::enforce_own_profile();
        fwrite(STDERR, "FAIL: Beneficiario own profile was not redirected\n");
        exit(1);
    } catch (RedirectSignal $redirect) {
        if ($redirect->getMessage() !== '/local/asodeltolstaff/profile.php') {
            fwrite(STDERR, "FAIL: Unexpected beneficiary redirect\n");
            exit(1);
        }
        $passed++;
    }

    $requestedid = 201;
    privacy::enforce_own_profile();
    $passed++;

    echo "PASS: {$passed} PHP profile-permission decisions including own-profile redirects.\n";
}
