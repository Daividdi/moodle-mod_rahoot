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

/**
 * Reads the solo results of one quiz from Rahoot.
 *
 * @param string $quizid Canonical quiz identifier.
 * @param string $account Optional sAMAccountName, to ask about one person only.
 * @param int $timeout Seconds; short on purpose when a page is waiting.
 * @return array|null Decoded `results` list, or null when Rahoot cannot be read.
 */
function rahoot_fetch_results($quizid, $account = '', $timeout = 10) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $base = rahoot_base_url();
    if ($base === '' || !preg_match(RAHOOT_QUIZID_REGEX, (string)$quizid)) {
        return null;
    }

    $params = ['quiz' => $quizid];
    if ($account !== '') {
        $params['account'] = $account;
    }

    $header = [];
    $token = trim((string)get_config('mod_rahoot', 'resultstoken'));
    if ($token !== '') {
        $header[] = 'Authorization: Bearer ' . $token;
    }

    // Same reasoning as the catalogue fetch: the host is chosen by an
    // administrator and normally resolves to an internal address, which the
    // cURL security helper blocks by default.
    $curl = new \curl(['ignoresecurity' => true]);
    if ($header) {
        $curl->setHeader($header);
    }
    $body = $curl->get($base . '/api/solo-results', $params, [
        'CURLOPT_TIMEOUT'        => $timeout,
        'CURLOPT_CONNECTTIMEOUT' => 3,
        'CURLOPT_FOLLOWLOCATION' => 0,
    ]);

    $httpcode = isset($curl->info['http_code']) ? (int)$curl->info['http_code'] : 0;
    if ($curl->get_errno() || $httpcode !== 200) {
        return null;
    }

    $decoded = json_decode($body);
    if (!is_object($decoded) || !isset($decoded->results) || !is_array($decoded->results)) {
        return null;
    }

    return $decoded->results;
}

/**
 * Copies Rahoot's results for one activity into this site, and grades them.
 *
 * The two systems authenticate against the same directory, so the account
 * Rahoot recorded and `user.username` here are the same string. That is the
 * whole match: no name comparison, no fuzzy matching, nothing to get wrong.
 *
 * @param stdClass $rahoot Activity instance record.
 * @param string $account Limit to one person, for the refresh done on view.
 * @param int $timeout Seconds to wait on Rahoot.
 * @return array{synced:int,unknown:int,notenrolled:int}|null Null when Rahoot could not be read.
 */
function rahoot_sync_results($rahoot, $account = '', $timeout = 10) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/rahoot/lib.php');

    $results = rahoot_fetch_results($rahoot->quizid, $account, $timeout);
    if ($results === null) {
        return null;
    }

    $cm = get_coursemodule_from_instance('rahoot', $rahoot->id, $rahoot->course, false, IGNORE_MISSING);
    if (!$cm) {
        return null;
    }
    $context = context_module::instance($cm->id);

    $contas = [];
    foreach ($results as $r) {
        if (!empty($r->account)) {
            $contas[] = core_text::strtolower($r->account);
        }
    }
    if (!$contas) {
        return ['synced' => 0, 'unknown' => 0, 'notenrolled' => 0];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($contas, SQL_PARAMS_NAMED, 'u');
    $usuarios = $DB->get_records_select_menu(
        'user',
        "LOWER(username) $insql AND deleted = 0",
        $inparams,
        '',
        'username, id'
    );
    $porconta = [];
    foreach ($usuarios as $username => $id) {
        $porconta[core_text::strtolower($username)] = $id;
    }

    $agora = time();
    $sincronizados = 0;
    $desconhecidos = 0;
    $naomatriculados = 0;

    foreach ($results as $r) {
        $conta = core_text::strtolower((string)($r->account ?? ''));
        if ($conta === '' || empty($r->best) || empty($r->last)) {
            continue;
        }
        if (!isset($porconta[$conta])) {
            // Played in Rahoot, has no account on this site. Counted rather than
            // logged per person: it is a roster gap, not an error.
            $desconhecidos++;
            continue;
        }
        $userid = $porconta[$conta];

        // A grade for someone who cannot open the activity would sit in the
        // gradebook with no way to explain it.
        if (!is_enrolled($context, $userid)) {
            $naomatriculados++;
            continue;
        }

        $registro = (object)[
            'rahootid'     => $rahoot->id,
            'userid'       => $userid,
            'account'      => $conta,
            'attempts'     => (int)$r->attempts,
            'bestpercent'  => (float)$r->best->percent,
            'bestcorrect'  => (int)$r->best->correct,
            'besttotal'    => (int)$r->best->total,
            'bestpoints'   => (int)$r->best->points,
            'bestattempt'  => (int)$r->best->attempt,
            'besttime'     => strtotime($r->best->endedAt) ?: $agora,
            'lastpercent'  => (float)$r->last->percent,
            'lastcorrect'  => (int)$r->last->correct,
            'lasttotal'    => (int)$r->last->total,
            'lastpoints'   => (int)$r->last->points,
            'lastattempt'  => (int)$r->last->attempt,
            'lasttime'     => strtotime($r->last->endedAt) ?: $agora,
            'timemodified' => $agora,
        ];

        $existente = $DB->get_record('rahoot_attempts', ['rahootid' => $rahoot->id, 'userid' => $userid]);
        if ($existente) {
            $registro->id = $existente->id;
            $DB->update_record('rahoot_attempts', $registro);
        } else {
            $DB->insert_record('rahoot_attempts', $registro);
        }

        rahoot_update_grades($rahoot, $userid);
        $sincronizados++;
    }

    return [
        'synced'      => $sincronizados,
        'unknown'     => $desconhecidos,
        'notenrolled' => $naomatriculados,
    ];
}

/**
 * The class standing for one activity: how many people, how many tries, and the
 * average score.
 *
 * Lives here because two pages need the same numbers -- the report and the
 * activity page a grader lands on -- and an average that disagrees with itself
 * between two screens is worse than no average at all.
 *
 * Two averages come back, and they are NOT the same number:
 *
 *  - `meanpercent` is the mean of each person's percentage. Everybody weighs
 *    the same, whatever quiz length they answered.
 *  - `poolpercent` is the pool: every correct answer over every question asked.
 *    Someone who answered more questions pulls it harder.
 *
 * They only coincide when everyone answered the same number of questions. A
 * teacher adding up the rows by eye lands on the pool, so both are reported
 * rather than picking one and being quietly wrong on the other.
 *
 * @param int $rahootid the activity instance
 * @param int $userid   0 for everyone, or narrow to one person
 * @param string $method 'best' or 'last' -- which column the average reads
 * @return object|null null when nobody has played yet
 */
function rahoot_results_summary($rahootid, $userid = 0, $method = 'best') {
    global $DB;

    $campo = ($method === 'last') ? 'last' : 'best';
    $params = ['rahootid' => $rahootid];
    $where = 'a.rahootid = :rahootid';
    if ($userid > 0) {
        $where .= ' AND a.userid = :userid';
        $params['userid'] = $userid;
    }

    // `u.deleted = 0` matches the table above it: a deleted account must not
    // move the average of a class it is no longer part of.
    $linha = $DB->get_record_sql(
        "SELECT COUNT(a.id) AS people,
                COALESCE(SUM(a.attempts), 0) AS tries,
                COALESCE(AVG(a.{$campo}percent), 0) AS meanpercent,
                COALESCE(SUM(a.{$campo}correct), 0) AS correct,
                COALESCE(SUM(a.{$campo}total), 0) AS total
           FROM {rahoot_attempts} a
           JOIN {user} u ON u.id = a.userid
          WHERE $where AND u.deleted = 0",
        $params
    );

    if (!$linha || (int)$linha->people === 0) {
        return null;
    }

    // Ninguém respondeu pergunta nenhuma: um agregado de 0/0 não é 0 %, é "sem
    // resposta", e dividir aqui seria o NaN silencioso clássico.
    $pool = ((int)$linha->total > 0)
        ? ((float)$linha->correct * 100 / (float)$linha->total)
        : null;

    return (object)[
        'method'      => $campo,
        'people'      => (int)$linha->people,
        'tries'       => (int)$linha->tries,
        'meanpercent' => (float)$linha->meanpercent,
        'correct'     => (int)$linha->correct,
        'total'       => (int)$linha->total,
        'poolpercent' => $pool,
        // Só vale mostrar a segunda média quando ela diz algo diferente da
        // primeira. Nas turmas reais da Malásia (medido em 25/09/2026) todos
        // respondem o mesmo número de perguntas, então as duas coincidem e a
        // linha "isto difere quando..." embaixo de dois números iguais é só
        // ruído. Compara com 1 casa, que é como as duas são exibidas.
        'divergent' => ($pool !== null)
            && (round((float)$linha->meanpercent, 1) !== round($pool, 1)),
    ];
}
