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


/**
 * Internal helpers for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A quiz identifier is only ever a bare file name under Rahoot's /solo/ route.
 *
 * Anchoring the whole string and allowing nothing but these characters is what
 * keeps a crafted value from walking out of that route.
 */
define('RAHOOT_QUIZID_REGEX', '/^[A-Za-z0-9._-]+\.json$/');

// Quanto tempo um catalogo lido com sucesso continua valendo. Curto de
// proposito: e o atraso maximo entre criar um quiz no Rahoot e ve-lo aqui.
define('RAHOOT_CATALOGUE_FRESH', 30);

/**
 * Returns the configured Rahoot base URL without a trailing slash.
 *
 * @return string Empty string when the site has not been configured yet.
 */
function rahoot_base_url() {
    $base = trim((string)get_config('mod_rahoot', 'baseurl'));

    return rtrim($base, '/');
}

/**
 * Reduces whatever the teacher supplied to a canonical quiz identifier.
 *
 * Accepts a full URL copied from the browser, a bare path, or just the file
 * name, with or without the .json suffix, because all four are things people
 * actually paste.
 *
 * @param string $raw
 * @return string Canonical identifier, or an empty string when it is not valid.
 */
function rahoot_normalise_quizid($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }

    // Drop any query string or fragment before looking at the path.
    $raw = preg_replace('~[?#].*$~', '', $raw);

    // Taking the last path segment would quietly turn ../../etc/passwd into
    // "passwd", a name that looks legitimate and silently saves a broken
    // activity. Refuse it instead, so the teacher sees the validation error.
    if (preg_match('~(^|/)\.\.(/|$)~', $raw)) {
        return '';
    }

    if (preg_match('~/solo/([^/]+)/?$~', $raw, $matches)) {
        $raw = $matches[1];
    } else if (strpos($raw, '/') !== false) {
        $parts = explode('/', rtrim($raw, '/'));
        $raw = (string)end($parts);
    }

    $raw = rawurldecode($raw);

    if ($raw !== '' && substr($raw, -5) !== '.json') {
        $raw .= '.json';
    }

    if (!preg_match(RAHOOT_QUIZID_REGEX, $raw)) {
        return '';
    }

    return $raw;
}

/**
 * Builds the URL that gets embedded for a given quiz.
 *
 * @param string $quizid Canonical quiz identifier.
 * @return moodle_url|null Null when the site or the identifier is not usable.
 */
function rahoot_quiz_url($quizid) {
    $base = rahoot_base_url();
    if ($base === '' || !preg_match(RAHOOT_QUIZID_REGEX, (string)$quizid)) {
        return null;
    }

    return new moodle_url($base . '/solo/' . $quizid);
}

/**
 * Fetches the quiz catalogue from Rahoot.
 *
 * Never throws and never blocks the form for long: if Rahoot is unreachable the
 * caller falls back to the free text field, which is the whole reason that
 * field exists.
 *
 * @return array|null List of objects with id, subject, category and questions,
 *                    or null when the catalogue could not be read.
 */
function rahoot_fetch_catalogue() {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $base = rahoot_base_url();
    if ($base === '') {
        return null;
    }

    $cache = cache::make('mod_rahoot', 'quizzes');
    $cached = $cache->get('catalogue');
    // Duas validades para o mesmo cache.
    //
    // O cache existe para que um Rahoot fora do ar nao acrescente tres segundos
    // a cada carga de formulario — isso continua valendo 300 s, pelo TTL da
    // definicao. Mas o mesmo prazo aplicado ao SUCESSO fazia um quiz recem
    // criado demorar ate cinco minutos para aparecer na lista, e quem criava o
    // quiz e vinha direto ao Moodle concluia que ele so aparece depois de
    // jogado. O sucesso agora vale RAHOOT_CATALOGUE_FRESH segundos.
    if (is_array($cached) && array_key_exists('at', $cached)) {
        $vazio = empty($cached['list']);
        if ($vazio || (time() - (int)$cached['at']) < RAHOOT_CATALOGUE_FRESH) {
            return $vazio ? null : $cached['list'];
        }
    }

    // The Rahoot host is chosen by a site administrator and normally resolves
    // to an internal address, which Moodle's cURL security helper blocks.
    // This flag must never be extended to a URL that comes from user input.
    $curl = new \curl(['ignoresecurity' => true]);
    $body = $curl->get($base . '/api/quizzes', [], [
        'CURLOPT_TIMEOUT'        => 5,
        'CURLOPT_CONNECTTIMEOUT' => 3,
        'CURLOPT_FOLLOWLOCATION' => 0,
    ]);

    $httpcode = isset($curl->info['http_code']) ? (int)$curl->info['http_code'] : 0;
    if ($curl->get_errno() || $httpcode !== 200) {
        // Cache the failure briefly too, so a down Rahoot does not add three
        // seconds to every form load.
        $cache->set('catalogue', ['at' => time(), 'list' => []]);
        return null;
    }

    $decoded = json_decode($body);
    if (!is_array($decoded)) {
        $cache->set('catalogue', ['at' => time(), 'list' => []]);
        return null;
    }

    $list = [];
    foreach ($decoded as $item) {
        if (empty($item->id)) {
            continue;
        }
        $quizid = rahoot_normalise_quizid($item->id);
        if ($quizid === '') {
            continue;
        }
        $entry = new stdClass();
        $entry->id = $quizid;
        $entry->subject = isset($item->subject) ? (string)$item->subject : $quizid;
        $entry->category = isset($item->category) ? (string)$item->category : '';
        $entry->region = isset($item->region) ? (string)$item->region : '';
        $entry->questions = isset($item->questions) ? (int)$item->questions : 0;
        $entry->maxattempts = isset($item->maxAttempts) ? (int)$item->maxAttempts : 0;
        $list[] = $entry;
    }

    core_collator::asort_objects_by_property($list, 'subject', core_collator::SORT_NATURAL);

    $cache->set('catalogue', ['at' => time(), 'list' => $list]);

    return $list ?: null;
}

/**
 * Looks up the human readable subject for a quiz, for display later.
 *
 * @param string $quizid
 * @return string Empty string when the catalogue is unavailable.
 */
function rahoot_lookup_subject($quizid) {
    if ($quizid === '') {
        return '';
    }

    $catalogue = rahoot_fetch_catalogue();
    if ($catalogue === null) {
        return '';
    }

    foreach ($catalogue as $entry) {
        if ($entry->id === $quizid) {
            return $entry->subject;
        }
    }

    return '';
}

/**
 * Resolves the height to apply to one activity.
 *
 * @param stdClass $rahoot Activity record.
 * @return int Height in pixels, or 0 to let the stylesheet size it.
 */
function rahoot_effective_height($rahoot) {
    if (!empty($rahoot->height) && $rahoot->height > 0) {
        return (int)$rahoot->height;
    }

    return (int)get_config('mod_rahoot', 'defaultheight');
}

/**
 * Builds the options for the quiz selector.
 *
 * @param array $catalogue As returned by rahoot_fetch_catalogue().
 * @return array Identifier => label.
 */
function rahoot_catalogue_options(array $catalogue) {
    $options = [];

    foreach ($catalogue as $entry) {
        $label = $entry->subject;
        $suffix = [];
        if ($entry->category !== '') {
            $suffix[] = $entry->category;
        }
        if ($entry->questions > 0) {
            $suffix[] = get_string('nquestions', 'mod_rahoot', $entry->questions);
        }
        // Surface the attempt limit here, at the moment of choosing. It is set
        // per quiz in Rahoot, and a teacher who does not know about it only
        // finds out when a student runs out.
        if ($entry->maxattempts > 0) {
            $suffix[] = get_string('nattempts', 'mod_rahoot', $entry->maxattempts);
        }
        if ($suffix) {
            $label .= ' (' . implode(', ', $suffix) . ')';
        }
        $options[$entry->id] = $label;
    }

    return $options;
}
