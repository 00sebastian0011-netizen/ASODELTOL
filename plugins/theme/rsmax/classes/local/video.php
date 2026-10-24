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

namespace theme_rsmax\local;

/**
 * Turns the address of a video into something a page can embed.
 *
 * @package    theme_rsmax
 * @copyright  2026 Pluginia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video {
    /**
     * Returns how to embed a video.
     *
     * YouTube and Vimeo addresses become their player address (YouTube through its domain without
     * cookies); addresses that end in a video file are played by the browser itself. Anything
     * else is not embedded.
     *
     * @param string $url Address written by whoever configured the block.
     * @return array|null Null when the address cannot be embedded; otherwise isframe, isfile and src.
     */
    public static function embed(string $url): ?array {
        $url = clean_param(trim($url), PARAM_URL);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        $youtube = '#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{6,20})#i';
        if (preg_match($youtube, $url, $match)) {
            return ['isframe' => true, 'isfile' => false, 'src' => 'https://www.youtube-nocookie.com/embed/' . $match[1]];
        }
        if (preg_match('#^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)#i', $url, $match)) {
            return ['isframe' => true, 'isfile' => false, 'src' => 'https://player.vimeo.com/video/' . $match[1]];
        }
        if (preg_match('#\.(mp4|webm|ogv)(\?.*)?$#i', $url)) {
            return ['isframe' => false, 'isfile' => true, 'src' => $url];
        }
        return null;
    }
}
