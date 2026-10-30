<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace contenttype_asodeltolfile;

use html_writer;
use moodle_url;

/**
 * Content bank type for regular files: PDF, video, audio, images and office documents.
 *
 * Moodle core only ships the H5P content type, so the native "Subir" button rejects
 * PDF or MP4 files. This type uses the native content bank API and storage
 * (component "contentbank", filearea "public") and renders an in-page preview.
 *
 * Only passive media types are ever served inline; anything else (HTML, SVG,
 * scripts, office documents, archives) is always forced to download.
 *
 * @package    contenttype_asodeltolfile
 * @copyright  2026 ASODELTOL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contenttype extends \core_contentbank\contenttype {

    /** @var string[] Extensions rendered inside a PDF viewer. */
    public const PDF_EXTENSIONS = ['pdf'];

    /** @var string[] Extensions rendered with the HTML5 video player. */
    public const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'webm', 'ogv', 'mov'];

    /** @var string[] Extensions rendered with the HTML5 audio player. */
    public const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'wav', 'ogg', 'oga'];

    /** @var string[] Extensions rendered as images. SVG is excluded on purpose (may contain scripts). */
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** @var string[] Extensions accepted but only offered as download. */
    public const DOWNLOAD_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'txt', 'csv', 'zip'];

    /**
     * Return the lowercase extension of a filename.
     *
     * @param string $filename
     * @return string
     */
    public static function extension_of(string $filename): string {
        return \core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Whether a file may be served inline (passive media only).
     *
     * @param string $filename
     * @return bool
     */
    public static function is_inline_safe(string $filename): bool {
        $ext = self::extension_of($filename);
        return in_array($ext, array_merge(self::PDF_EXTENSIONS, self::VIDEO_EXTENSIONS,
            self::AUDIO_EXTENSIONS, self::IMAGE_EXTENSIONS), true);
    }

    /**
     * Return an array of implemented features by this plugin.
     *
     * @return array
     */
    protected function get_implemented_features(): array {
        return [self::CAN_UPLOAD, self::CAN_DOWNLOAD];
    }

    /**
     * Return an array of extensions this contenttype could manage.
     *
     * @return array
     */
    public function get_manageable_extensions(): array {
        $all = array_merge(self::PDF_EXTENSIONS, self::VIDEO_EXTENSIONS, self::AUDIO_EXTENSIONS,
            self::IMAGE_EXTENSIONS, self::DOWNLOAD_EXTENSIONS);
        return array_map(static fn(string $ext): string => '.' . $ext, $all);
    }

    /**
     * Returns the list of different types of the given content type (none: upload only).
     *
     * @return array
     */
    public function get_contenttype_types(): array {
        return [];
    }

    /**
     * Returns the HTML content to add to view.php visualizer.
     *
     * @param \core_contentbank\content $content The content to be displayed.
     * @return string HTML code to include in view.php.
     */
    public function get_view_content(\core_contentbank\content $content): string {
        $html = parent::get_view_content($content);
        $file = $content->get_file();
        if (!$file) {
            return $html . html_writer::div(get_string('nofile', 'contenttype_asodeltolfile'), 'alert alert-warning');
        }

        $url = $content->get_file_url();
        $downloadurl = $this->get_download_url($content);
        $ext = self::extension_of($file->get_filename());
        $name = s($file->get_filename());

        if (in_array($ext, self::PDF_EXTENSIONS, true)) {
            $preview = html_writer::tag('iframe', '', [
                'src' => $url,
                'title' => $name,
                'style' => 'width:100%;height:80vh;border:1px solid #cbd5e1;border-radius:8px;',
            ]);
        } else if (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
            $preview = html_writer::tag('video', html_writer::tag('source', '', ['src' => $url]), [
                'controls' => 'controls',
                'preload' => 'metadata',
                'style' => 'width:100%;max-height:80vh;background:#000;border-radius:8px;',
            ]);
        } else if (in_array($ext, self::AUDIO_EXTENSIONS, true)) {
            $preview = html_writer::tag('audio', html_writer::tag('source', '', ['src' => $url]), [
                'controls' => 'controls',
                'preload' => 'metadata',
                'style' => 'width:100%;',
            ]);
        } else if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            $preview = html_writer::empty_tag('img', [
                'src' => $url,
                'alt' => $name,
                'style' => 'max-width:100%;height:auto;border-radius:8px;',
            ]);
        } else {
            $preview = html_writer::div(get_string('notpreviewable', 'contenttype_asodeltolfile'), 'alert alert-info');
        }

        $button = html_writer::link($downloadurl, get_string('downloadfile', 'contenttype_asodeltolfile', $name),
            ['class' => 'btn btn-secondary mt-3']);

        return $html . html_writer::div($preview . $button, 'contenttype-asodeltolfile-viewer my-3');
    }

    /**
     * Returns the URL that forces the download of the content.
     *
     * @param \core_contentbank\content $content
     * @return string
     */
    public function get_download_url(\core_contentbank\content $content): string {
        $file = $content->get_file();
        if (!$file) {
            return '';
        }
        return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true)->out(false);
    }

    /**
     * Returns the icon matching the uploaded file type.
     *
     * @param \core_contentbank\content $content
     * @return string
     */
    public function get_icon(\core_contentbank\content $content): string {
        global $OUTPUT;
        $file = $content->get_file();
        if ($file) {
            return $OUTPUT->image_url(file_file_icon($file))->out(false);
        }
        return $OUTPUT->image_url('f/unknown')->out(false);
    }

    /**
     * Serve content bank files of this type. Called by core file_pluginfile() for component "contentbank".
     *
     * Core already required login to the course/category context before calling this.
     * Passive media is served inline so it can be previewed; everything else is forced to download.
     *
     * @param \stdClass|null $course
     * @param \stdClass|null $cm
     * @param \context $context
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload
     * @param array $options
     * @return bool false if not handled
     */
    public static function pluginfile($course, $cm, \context $context, string $filearea, array $args,
            bool $forcedownload, array $options = []): bool {
        if ($filearea !== 'public') {
            return false;
        }
        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'contentbank', 'public', $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        $inline = !$forcedownload && self::is_inline_safe($file->get_filename());
        send_stored_file($file, 0, 0, !$inline, $options);
        return true;
    }
}
