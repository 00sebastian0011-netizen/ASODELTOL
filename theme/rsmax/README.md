# RSMAX theme for Moodle

A theme for Moodle 5.3 and later, built as a child of Boost. It gives the site a landing home
page made of blocks, a course page that shows the learner where they are and what comes next,
activity pages with their place in the section, an enrolment page that presents the course, and
a settings panel to adapt colours, typography, header, footer and course pages without code.

## Requirements

- Moodle 5.3 or later.
- The Boost theme, which is part of Moodle.

## Installation

1. Unpack the theme into `public/theme/rsmax`.
2. Visit *Site administration > Notifications* to complete the installation.
3. Choose the theme in *Site administration > Appearance > Themes*.

The theme needs no other plugin.

## Settings

*Site administration > Appearance > Themes > RSMAX*. Four colours (brand, accent, dark and
background) set the line of the whole site; every other colour follows them unless it is given
a value of its own. The remaining pages cover shapes and typography, header, footer, login,
dashboard, courses and the AI assistant.

## The site home

The home page opens with the name of the site, a search box for its courses and a button to the
catalogue, followed by whatever the site shows on its home page (*Site administration > General >
Site home settings*). The title and the line under it are settings of the theme, and the opening
can be turned off.

## Optional integration

When the *AI assistant* block (`block_openaiagent`) is installed, the theme places it: one in
each new course and one for the site on the home page, the course catalogue and the enrolment
pages. A setting adds a floating button that opens it. The theme works the same without it.

## Privacy

The theme stores no personal data. It keeps, for each course, the banners uploaded by its
teachers and where they are shown.

Nothing is requested from other sites when a page loads. The font (Inter) is bundled with the
theme. Two things reach an external service, and only after the visitor presses them:

- A video block with a YouTube or Vimeo address loads the player from
  `youtube-nocookie.com` or `player.vimeo.com`.
- The map of the contact block (`block_pluginia_contact`) loads Google Maps.

## RSMAX Premium

This theme is free and complete on its own. The premium package adds, for the same theme:

- 17 Pluginia blocks to build the home page and the course pages: hero, featured courses,
  categories, figures, features, testimonials, pricing, events, news, gallery, people, course
  information and rating, call to action, accordion, slider and contact form.
- The Pluginia AI plugins: assistant, course recommender, forum assistant, assignment tutor,
  analytics, and the oral defence and roleplay activities.

More at <https://pluginia.es/>. To get the package or ask about it, write to
<julio@rsmax.es>.

## Third party code

- Inter typeface, SIL Open Font License 1.1 (`fonts/`, declared in `thirdpartylibs.xml`).

## License

GNU GPL v3 or later. See <http://www.gnu.org/copyleft/gpl.html>.

Copyright 2026 Pluginia.
