<?php
namespace local_asodeltolstaff;

/** Add a discoverable, native navigation link for authenticated learners. */
final class hook_callbacks {
    /**
     * Enforce the scoped Staff profile before Moodle emits any page output.
     *
     * Moodle's user/profile.php does not call require_login() when neither
     * forceloginforprofiles nor forcelogin is enabled, so after_require_login
     * alone cannot protect that route.
     */
    public static function enforce_profile_privacy(
            \core\hook\output\before_standard_head_html_generation $hook): void {
        profile_privacy::enforce_own_profile();

        // If navigating to feedback print preview (e.g. from the Moodle "Vista previa" button),
        // redirect to the official interactive complete form so beneficiaries and staff can test and submit.
        global $COURSE, $PAGE;
        $courseid = (int) ($COURSE->id ?? ($PAGE->course->id ?? 0));
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/mod/feedback/print.php') !== false || ($PAGE->has_set_url() && strpos($PAGE->url->get_path(), '/mod/feedback/print.php') !== false)) {
            $cmid = optional_param('id', 0, PARAM_INT);
            if ($cmid > 0) {
                $printcourseid = optional_param('courseid', 0, PARAM_INT);
                $redirectparams = ['id' => $cmid];
                if ($printcourseid > 0) {
                    $redirectparams['courseid'] = $printcourseid;
                }
                redirect(new \moodle_url('/mod/feedback/complete.php', $redirectparams));
            }
        }

        // When switched to a non-editing role (e.g. Student) in the course, ensure the simulated
        // preview hides calendar event management actions (edit and delete) to faithfully match real student permissions.
        if ($courseid > 0 && is_role_switched($courseid)) {
            $hook->add_html('<style>
.modal [data-action="edit"],
.modal [data-action="delete"],
.event [data-action="edit"],
.event [data-action="delete"] {
    display: none !important;
}
</style>');
        }

        // Inject course schedule and caducidad toast for AF8 (course 4).
        if ($courseid === 4 && isloggedin() && !isguestuser()) {
            $toastscript = <<<'JS'
(function() {
    if (sessionStorage.getItem('asodeltol_caducidad_toast_shown')) {
        return;
    }
    document.addEventListener('DOMContentLoaded', function() {
        var toast = document.createElement('div');
        toast.className = 'asodeltol-floating-toast';
        toast.innerHTML = '<span class="asodeltol-toast-icon">📅</span>' +
            '<span class="asodeltol-toast-text"><strong>Cronograma Oficial AF8:</strong> Inicio <strong>03 Nov</strong> &middot; Cierre / Caducidad: <strong>28 Nov 2026</strong></span>' +
            '<a href="/calendar/view.php?view=month&course=4" class="asodeltol-toast-link">Ver Calendario &rarr;</a>' +
            '<button type="button" class="asodeltol-toast-close" aria-label="Cerrar">&times;</button>';
        document.body.appendChild(toast);
        setTimeout(function() { toast.classList.add('show'); }, 150);
        function dismiss() {
            toast.classList.remove('show');
            setTimeout(function() { toast.remove(); }, 400);
        }
        var closeBtn = toast.querySelector('.asodeltol-toast-close');
        if (closeBtn) { closeBtn.addEventListener('click', dismiss); }
        var timer = setTimeout(dismiss, 5000);
        toast.addEventListener('mouseenter', function() { clearTimeout(timer); });
        toast.addEventListener('mouseleave', function() { timer = setTimeout(dismiss, 2500); });
        sessionStorage.setItem('asodeltol_caducidad_toast_shown', '1');
    });
})();
JS;
            $PAGE->requires->js_amd_inline($toastscript);
        }

        // Inject inline PDF preview on folder views (mod_folder) in course 4 so learners
        // can view training materials and CVs directly in the platform without forced downloading.
        if ($courseid === 4 && (strpos($uri, '/mod/folder/view.php') !== false || ($PAGE->has_set_url() && strpos($PAGE->url->get_path(), '/mod/folder/view.php') !== false))) {
            $pdfscript = <<<'JS'
(function() {
    function initPdfPreview() {
        var fileList = document.querySelector('.filemanager');
        if (!fileList || document.querySelector('.asodeltol-pdf-viewer-card')) {
            return;
        }
        var pdfLinks = fileList.querySelectorAll('a[href*=".pdf"]');
        if (!pdfLinks || pdfLinks.length === 0) {
            return;
        }
        var firstPdf = pdfLinks[0];
        var pdfUrl = firstPdf.getAttribute('href');
        if (!pdfUrl) {
            return;
        }
        pdfUrl = pdfUrl.replace(/[\?&]forcedownload=1/, '');

        var previewCard = document.createElement('div');
        previewCard.className = 'asodeltol-pdf-viewer-card my-4 p-3 bg-white border rounded shadow-sm';
        previewCard.innerHTML = 
            '<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<span style="font-size: 1.5rem;">📄</span>' +
                    '<div>' +
                        '<strong class="d-block text-dark asodeltol-preview-title">Previsualización de Material: ' + (firstPdf.textContent || 'Documento PDF') + '</strong>' +
                        '<small class="text-muted">Lectura interactiva en plataforma sin necesidad de descarga previa</small>' +
                    '</div>' +
                '</div>' +
                '<div class="d-flex gap-2">' +
                    '<a href="' + pdfUrl + '" target="_blank" class="btn btn-sm btn-outline-primary asodeltol-preview-open-link">' +
                        '🔍 Abrir en pantalla completa' +
                    '</a>' +
                '</div>' +
            '</div>' +
            '<div class="ratio ratio-16x9" style="min-height: 820px;">' +
                '<iframe src="' + pdfUrl + '#toolbar=1&navpanes=1&scrollbar=1" width="100%" height="820" style="border: 1px solid #ced4da; border-radius: 6px;" title="Previsualización de Documento PDF"></iframe>' +
            '</div>';

        fileList.parentNode.insertBefore(previewCard, fileList.nextSibling);

        pdfLinks.forEach(function(link) {
            var href = link.getAttribute('href');
            if (href) {
                var cleanHref = href.replace(/[\?&]forcedownload=1/, '');
                link.setAttribute('href', cleanHref);
            }
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var targetUrl = link.getAttribute('href');
                var iframe = previewCard.querySelector('iframe');
                if (iframe) {
                    iframe.src = targetUrl + '#toolbar=1&navpanes=1&scrollbar=1';
                }
                var title = previewCard.querySelector('.asodeltol-preview-title');
                if (title) {
                    title.textContent = 'Previsualización de Material: ' + (link.textContent || 'Documento PDF');
                }
                var openLink = previewCard.querySelector('.asodeltol-preview-open-link');
                if (openLink) {
                    openLink.setAttribute('href', targetUrl);
                }
                previewCard.scrollIntoView({ behavior: 'smooth' });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPdfPreview);
    } else {
        initPdfPreview();
    }
})();
JS;
            $PAGE->requires->js_amd_inline($pdfscript);
        }
    }

    public static function extend_primary(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }
        // One discoverable native dropdown: the guide explains Moodle; CAU records cases.
        // The reports link stays separate, and unrelated IA tools are not touched.
        $guideurl = new \moodle_url('/local/asodeltolstaff/help.php');
        $help = $hook->get_primaryview()->add(get_string('helpandsupport', 'local_asodeltolstaff'),
            $guideurl, \navigation_node::TYPE_CUSTOM, null, 'asodeltol_help_support');
        $help->add(get_string('platformhelp', 'local_asodeltolstaff'),
            $guideurl, \navigation_node::TYPE_CUSTOM, null, 'asodeltol_platform_help');
        if (has_capability('moodle/site:sendmessage', \context_system::instance())
                && support::recipient() !== null) {
            $help->add(get_string('supportchannel', 'local_asodeltolstaff'),
                new \moodle_url('/local/asodeltolstaff/support.php'), \navigation_node::TYPE_CUSTOM,
                null, 'asodeltol_support');
        }
        if (course_access::exportable_courses()) {
            $hook->get_primaryview()->add(get_string('reportsdownloads', 'local_asodeltolstaff'),
                new \moodle_url('/local/asodeltolstaff/reports.php'), \navigation_node::TYPE_CUSTOM,
                null, 'asodeltol_reports_downloads');
        }
    }
}
