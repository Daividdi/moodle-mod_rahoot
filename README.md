# moodle-mod_rahoot

A Moodle activity that embeds a self paced [Rahoot](https://github.com/Daividdi/Rahoot)
quiz inside a course, sized to the content area.

## Why this exists

The obvious way to do this is a URL resource set to *Embed*. That breaks on any
theme that does not use core's expected element ids.

Core sizes the embedded iframe with `M.util.init_maximised_embed`, which reads
the width of `#maincontent` and the heights of `#page-header` and
`#page-footer`. When a theme names those regions differently the lookups return
`0`, the width calculation fails its own `> 500` test and the iframe is pinned
to a hardcoded **500px**, while the height is computed from the full document
height — which already includes the iframe — and overshoots, so the page grows
a second scrollbar. The URL activity offers no size field to correct this:
`popupwidth` and `popupheight` are hidden unless the display mode is *pop-up*.

This module renders the iframe itself, with its own stylesheet, and never calls
that function.

## What a teacher sees

Add the activity, pick a quiz from the list, save. The list is read from the
Rahoot server; if it cannot be reached the form falls back to a field that
accepts the quiz address, so nobody is ever blocked by a catalogue that is
temporarily unavailable.

The address field is deliberately forgiving. All of these are accepted and
resolve to the same quiz:

```
https://rahoot.example.org/solo/quiz-example-1770000000000.json
https://rahoot.example.org/solo/quiz-example-1770000000000.json?utm=x#top
/solo/quiz-example-1770000000000.json
quiz-example-1770000000000.json
quiz-example-1770000000000
```

Only the identifier is kept. The activity URL is always rebuilt from the Rahoot
server configured for the site, so moving Rahoot to another domain is a
one-setting change rather than an edit of every activity.

## Settings

| Setting | Meaning |
| --- | --- |
| `baseurl` | Base address of the Rahoot installation, no trailing slash. |
| `defaultheight` | Height in pixels for activities that do not set their own. `0` sizes the quiz to the browser window, which suits most screens. |

## Requirements

Moodle 4.5 or later. Verified on 4.5 and 5.2.

The quiz list needs `GET /api/quizzes` on the Rahoot server, returning objects
with `id`, `subject`, `category`, `region`, `group` and `questions`. Without it
the module still works through the address field.

Note that Rahoot limits solo attempts **per person, per quiz** — three by
default. A student who exhausts them sees Rahoot's own "no attempts left"
screen inside the activity. Raise `solo.maxAttempts` in the quiz JSON if a
quiz is meant to be repeatable practice.

## Licence

GPL v3 or later, same as Moodle.
