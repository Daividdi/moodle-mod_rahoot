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
 * Brazilian Portuguese strings for mod_rahoot.
 *
 * @package    mod_rahoot
 * @copyright  2026 Angel Aligner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Rahoot';
$string['modulename'] = 'Rahoot';
$string['modulenameplural'] = 'Quizzes Rahoot';
$string['modulename_help'] = 'A atividade Rahoot incorpora um quiz do seu servidor Rahoot dentro do curso, ajustado à área de conteúdo.

Escolha um quiz na lista, ou cole o endereço de um, e o aluno responde sem sair do Moodle.';
$string['pluginadministration'] = 'Administração do Rahoot';

$string['rahoot:addinstance'] = 'Adicionar uma atividade Rahoot';
$string['rahoot:view'] = 'Ver uma atividade Rahoot';

$string['rahootsettings'] = 'Quiz';
$string['quiz'] = 'Quiz';
$string['quiz_help'] = 'A lista vem do seu servidor Rahoot. Se um quiz foi criado agora há pouco e não aparece, use o campo de endereço abaixo.';
$string['choosequiz'] = 'Escolha um quiz...';
$string['quizidmanual'] = 'Endereço do quiz';
$string['quizidmanualplaceholder'] = 'https://seu-servidor-rahoot/solo/quiz-exemplo-1770000000000.json';
$string['quizidmanual_help'] = 'Cole o endereço inteiro, exatamente como o Rahoot te dá. Não precisa recortar nada, e tanto faz vir com parâmetros depois do ? ou sem o .json.

Só o identificador também funciona. Em qualquer caso, a atividade é sempre montada com o servidor Rahoot definido para este site, então o host do que você colar é ignorado.

Preencher aqui prevalece sobre a escolha acima.';
$string['quizrequired'] = 'Escolha um quiz na lista, ou cole o endereço dele.';
$string['quizinvalid'] = 'Isso não parece um endereço de quiz do Rahoot.';
$string['catalogueunavailable'] = 'Não foi possível ler a lista de quizzes do servidor Rahoot, por isso ela não aparece. Cole o endereço do quiz.';
$string['nobaseurl'] = 'Nenhum servidor Rahoot foi definido neste site ainda. Um administrador precisa preencher em Administração do site, Plugins, Módulos de atividades, Rahoot.';
$string['nquestions'] = '{$a} perguntas';
$string['nattempts'] = '{$a} tentativas por aluno';

$string['height'] = 'Altura fixa';
$string['height_help'] = 'Altura do quiz incorporado, em pixels. Deixe 0 para ele se ajustar à janela do navegador, que atende bem à maioria das telas.';
$string['heighttoosmall'] = 'Use 0 para altura automática, ou pelo menos 200 pixels.';

$string['fullscreen'] = 'Tela cheia';
$string['openinnewtab'] = 'Abrir em nova aba';
$string['noinstances'] = 'Não há atividades Rahoot neste curso.';

$string['baseurl'] = 'Endereço do servidor Rahoot';
$string['baseurl_desc'] = 'Endereço base da sua instalação do Rahoot, sem barra no fim, por exemplo https://rahoot.exemplo.org. É de lá que vem a lista de quizzes e é com ele que cada atividade é montada.';
$string['defaultheight'] = 'Altura fixa padrão';
$string['defaultheight_desc'] = 'Altura em pixels usada pelas atividades que não definem a própria. Deixe 0 para ajustar à janela do navegador, que costuma ser a melhor escolha.';

$string['privacy:metadata'] = 'A atividade Rahoot não armazena nenhum dado pessoal. O quiz roda no servidor Rahoot, que mantém os próprios registros.';
$string['cataloguerefresh'] = 'Recarregar a lista de quizzes ({$a} na lista)';
$string['cataloguerefreshed'] = 'Lista de quizzes recarregada do Rahoot.';
