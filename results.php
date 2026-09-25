<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shows all locally synchronised Solo results for one Rahoot activity.
 *
 * @package    mod_rahoot
 * @copyright 2026 Angel Aligner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$rahootid = required_param('rahootid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$refresh = optional_param('refresh', 0, PARAM_BOOL);
$download = optional_param('download', 0, PARAM_BOOL);

$rahoot = $DB->get_record('rahoot', ['id' => $rahootid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $rahoot->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('rahoot', $rahoot->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

$coursecontext = context_course::instance($course->id);
require_capability('mod/rahoot:viewallresults', $coursecontext);

$params = ['rahootid' => $rahoot->id];
$where = 'a.rahootid = :rahootid';
if ($userid > 0) {
    $where .= ' AND a.userid = :userid';
    $params['userid'] = $userid;
}

if ($refresh) {
    require_sesskey();
    $sync = rahoot_sync_results($rahoot);
    if ($sync === null) {
        $refreshmessage = $OUTPUT->notification(
            get_string('resultsrefreshfailed', 'mod_rahoot'),
            \core\output\notification::NOTIFY_ERROR
        );
    } else {
        $refreshmessage = $OUTPUT->notification(
            get_string('resultsrefreshed', 'mod_rahoot', $sync['synced']),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
} else {
    $refreshmessage = '';
}

$records = $DB->get_records_sql(
    "SELECT a.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
            u.middlename, u.alternatename, u.email
       FROM {rahoot_attempts} a
       JOIN {user} u ON u.id = a.userid
      WHERE $where AND u.deleted = 0
   ORDER BY u.lastname, u.firstname, u.id",
    $params
);

$users = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic,
            u.lastnamephonetic, u.middlename, u.alternatename
       FROM {rahoot_attempts} a
       JOIN {user} u ON u.id = a.userid
      WHERE a.rahootid = :rahootid AND u.deleted = 0
   ORDER BY u.lastname, u.firstname, u.id",
    ['rahootid' => $rahoot->id]
);

if ($download) {
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, [
        get_string('user'),
        get_string('account', 'mod_rahoot'),
        get_string('attempts', 'mod_rahoot'),
        get_string('bestresult', 'mod_rahoot'),
        get_string('besttime', 'mod_rahoot'),
        get_string('lastresult', 'mod_rahoot'),
        get_string('lasttime', 'mod_rahoot'),
    ]);
    foreach ($records as $record) {
        fputcsv($handle, [
            fullname($record),
            $record->account,
            (int)$record->attempts,
            sprintf('%d/%d (%s%%)', $record->bestcorrect, $record->besttotal,
                format_float((float)$record->bestpercent, 1, true, true)),
            $record->besttime ? userdate($record->besttime) : '',
            sprintf('%d/%d (%s%%)', $record->lastcorrect, $record->lasttotal,
                format_float((float)$record->lastpercent, 1, true, true)),
            $record->lasttime ? userdate($record->lasttime) : '',
        ]);
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    \core\session\manager::write_close();
    // Uma linha de resumo no fim: o CSV e o que vai para a planilha, e quem
    // abre lá nao tem a tela do Moodle do lado para ver a media.
    $resumo = rahoot_results_summary($rahoot->id, $userid, $rahoot->grademethod);
    if ($resumo !== null) {
        fputcsv($handle, []);
        fputcsv($handle, [
            get_string('csvsummary', 'mod_rahoot'),
            '',
            $resumo->tries,
            sprintf('%d/%d (%s%%)', $resumo->correct, $resumo->total,
                ($resumo->poolpercent === null)
                    ? '-' : format_float($resumo->poolpercent, 1, true, true)),
            '',
            sprintf('%s%%', format_float($resumo->meanpercent, 1, true, true)),
            '',
        ]);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rahoot-results-' . $rahoot->id . '.csv"');
    echo "\xEF\xBB\xBF" . $csv;
    exit;
}

$PAGE->set_url('/mod/rahoot/results.php', [
    'rahootid' => $rahoot->id,
    'userid' => $userid,
]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('resultsreport', 'mod_rahoot'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('resultsreport', 'mod_rahoot'));
echo $OUTPUT->heading(format_string($rahoot->name), 3);

if ($refreshmessage !== '') {
    echo $refreshmessage;
}

echo html_writer::tag('p', get_string('resultssyncednotice', 'mod_rahoot'), ['class' => 'alert alert-info']);

$filterurl = new moodle_url('/mod/rahoot/results.php', ['rahootid' => $rahoot->id]);
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $filterurl->out(false),
    'class' => 'mod-rahoot-results-filter mb-3',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'rahootid',
    'value' => $rahoot->id,
]);
echo html_writer::tag('label', get_string('userfilter', 'mod_rahoot'), [
    'for' => 'rahoot-user-filter',
    'class' => 'mr-2',
]);
echo html_writer::start_tag('select', [
    'id' => 'rahoot-user-filter',
    'name' => 'userid',
    'class' => 'custom-select mr-2',
]);
$allattributes = ['value' => '0'];
if ($userid === 0) {
    $allattributes['selected'] = 'selected';
}
echo html_writer::tag('option', get_string('allusers', 'mod_rahoot'), $allattributes);
foreach ($users as $user) {
    $attributes = ['value' => $user->id];
    if ((int)$user->id === $userid) {
        $attributes['selected'] = 'selected';
    }
    echo html_writer::tag('option', fullname($user), $attributes);
}
echo html_writer::end_tag('select');
echo html_writer::tag('button', get_string('filter', 'mod_rahoot'), [
    'type' => 'submit',
    'class' => 'btn btn-secondary mr-2',
]);
echo html_writer::end_tag('form');

$refreshurl = new moodle_url('/mod/rahoot/results.php', ['rahootid' => $rahoot->id]);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $refreshurl->out(false),
    'class' => 'mb-3',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'rahootid',
    'value' => $rahoot->id,
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'refresh',
    'value' => '1',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);
echo html_writer::tag('button', get_string('refreshresults', 'mod_rahoot'), [
    'type' => 'submit',
    'class' => 'btn btn-primary mr-2',
]);
$downloadurl = new moodle_url('/mod/rahoot/results.php', [
    'rahootid' => $rahoot->id,
    'userid' => $userid,
    'download' => 1,
]);
echo html_writer::link($downloadurl, get_string('downloadcsv', 'mod_rahoot'), [
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

if (!$records) {
    echo $OUTPUT->notification(get_string('noresults', 'mod_rahoot'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable mod-rahoot-results';
    $table->head = [
        get_string('user'),
        get_string('account', 'mod_rahoot'),
        get_string('attempts', 'mod_rahoot'),
        get_string('bestresult', 'mod_rahoot'),
        get_string('besttime', 'mod_rahoot'),
        get_string('lastresult', 'mod_rahoot'),
        get_string('lasttime', 'mod_rahoot'),
    ];

    foreach ($records as $record) {
        $table->data[] = [
            fullname($record),
            s($record->account),
            (int)$record->attempts,
            sprintf('%d/%d (%s%%)', $record->bestcorrect, $record->besttotal,
                format_float((float)$record->bestpercent, 1, true, true)),
            $record->besttime ? userdate($record->besttime) : '-',
            sprintf('%d/%d (%s%%)', $record->lastcorrect, $record->lasttotal,
                format_float((float)$record->lastpercent, 1, true, true)),
            $record->lasttime ? userdate($record->lasttime) : '-',
        ];
    }

    // A media da turma, que era o que faltava para o relatorio responder
    // "como foi" sem somar as linhas a mao. Fica ABAIXO do aviso de
    // sincronizacao de proposito: quem comparar com o Rahoot e vir diferenca
    // precisa ler primeiro que o numero vem da copia no Moodle.
    //
    // Respeita o filtro de pessoa: com uma pessoa escolhida, a media e dela.
    $resumo = rahoot_results_summary($rahoot->id, $userid, $rahoot->grademethod);
    if ($resumo !== null) {
        $a = (object)[
            'people' => $resumo->people,
            'tries'  => $resumo->tries,
            'mean'   => format_float($resumo->meanpercent, 1, true, true),
            'pool'   => ($resumo->poolpercent === null)
                ? '-' : format_float($resumo->poolpercent, 1, true, true),
            'correct' => $resumo->correct,
            'total'   => $resumo->total,
        ];
        $chave = ($resumo->method === 'last') ? 'summarylast' : 'summarybest';
        $corpo = html_writer::tag('strong', get_string($chave, 'mod_rahoot', $a));
        // A soma de todas as respostas só aparece quando dá um número
        // DIFERENTE da média por pessoa. Iguais, seria a mesma informação duas
        // vezes com uma ressalva que não se aplica.
        if ($resumo->divergent) {
            $corpo .= html_writer::empty_tag('br')
                . html_writer::tag('small', get_string('summarypool', 'mod_rahoot', $a));
        }
        echo html_writer::div($corpo, 'alert alert-secondary mod-rahoot-summary');
    }

    echo html_writer::tag('p', get_string('resultsfound', 'mod_rahoot', count($records)));
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/mod/rahoot/view.php', ['id' => $cm->id]),
    get_string('backtoactivity', 'mod_rahoot'),
    ['class' => 'btn btn-link']
);

echo $OUTPUT->footer();
