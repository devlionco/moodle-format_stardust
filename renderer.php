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
 * Defines renderer for course format flexsections
 *
 * @package    format_stardust
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Renderer for flexsections format.
 *
 * @copyright 2012 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_stardust_renderer extends plugin_renderer_base {
    /** @var core_course_renderer Stores instances of core_course_renderer */
    protected $courserenderer = null;
    /** @var array Stores an array of custom section numbers */
    public $sectionscustomenumeration = array();

    /**
     * Constructor
     *
     * @param moodle_page $page
     * @param type $target
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courserenderer = $page->get_renderer('core', 'course');
    }

    /**
     * Generate the section title (with link if section is collapsed)
     *
     * @param int|section_info $section
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course, $supresslink = false) {
        global $CFG;
        if ((float)$CFG->version >= 2016052300) {
            // For Moodle 3.1 or later use inplace editable for displaying section name.
            $section = course_get_format($course)->get_section($section);
            return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, !$supresslink));
        }
        $title = get_section_name($course, $section);
        if (!$supresslink) {
            $url = course_get_url($course, $section, array('navigation' => true));
            if ($url) {
                $title = html_writer::link($url, $title);
            }
        }
        return $title;
    }

    /**
     * Calculates section progress in percents
     *
     * @param stdClass $section The course_section entry from DB.
     * @return int Progress in percents without sign '%'
     */
    protected function sectionprogress($section) {
        global $DB, $USER, $modinfo, $course;

        // get all current user's completions on current course
        $usercourseallcmcraw = $DB->get_records_sql("
        SELECT
            cmc.*
        FROM
            {course_modules} cm
            INNER JOIN {course_modules_completion} cmc ON cmc.coursemoduleid=cm.id
        WHERE
            cm.course=? AND cmc.userid=?", array($course->id, $USER->id));
        $usercmscompletions = array();
        foreach ($usercourseallcmcraw as $record) {
            //$usercourseallcmc[$record->coursemoduleid] = (array)$record;
            if ($record->completionstate <> 0) {
                $usercmscompletions[] = $record->coursemoduleid;
            }
        }

        // get current course's completable cms
        $ccompetablecms = array();
        $coursefminfo = get_fast_modinfo($course);
        foreach ($coursefminfo->get_cms() as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
                $ccompetablecms[] = $cm->id;
            }
        }

        $completedactivitiescount = 0;
        @$scms = $modinfo->sections[$section->section];     // get current section activities
        if (!empty($scms)) {
            $allcmsinsectioncount = count($scms);           // first count all cms in section
            foreach ($scms as $arid=>$scmid) {              // for each acivity in section
                if (!in_array($scmid, $ccompetablecms)) {
                    unset($scms[$arid]);                    // unset cms that are not  completable
                } else {
                    if (in_array($scmid, $usercmscompletions)) {
                        $completedactivitiescount++;        // if cm is compledted - count it
                    }
                }
            }
            $completablecmsinsectioncount = count($scms);   // count completable activities in section
            if (!empty($completablecmsinsectioncount)) {    // if section has at least 1 completable activity
                $csectionprogress = round($completedactivitiescount/$completablecmsinsectioncount*100);
            } else {
                $csectionprogress = 0;
            }
            return $csectionprogress;
        } else {
            return $csectionprogress = 0;
        }
    }

    /**
     * Show section progrees
     *
     * @param stdClass $section The course_section entry from DB.
     * @return int Progress in percents without sign '%'
     */
    protected function getsectionprogress($section) {
        $output = '';
        $output = html_writer::start_tag('div', array('class' => 'sectionprogress'));
          // echo html_writer::tag('span', $this->sectionprogress($section).'%', array(
        $output .= html_writer::tag('span', '', array(
              'class' => 'sectionprogress-percent',
              'style' => "width: ".$this->sectionprogress($section)."%",
          ));
        $output .= html_writer::tag('div', '',
            array(
              'class' => 'sectionprogress-bar',
              'role'  => "progressbar",
              'style' => "width: ".$this->sectionprogress($section)."%",
              'aria-valuenow' => $this->sectionprogress($section),
              'aria-valuemin' => "0",
              'aria-valuemax' => "100",
            )
          );
        $output .= html_writer::end_tag('div');

        return $output;
    }



    /**
     * Generate html for a section summary text
     *
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function format_summary_text($section) {
        $context = context_course::instance($section->course);
        $summarytext = file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php',
            $context->id, 'course', 'section', $section->id);

        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        return format_text($summarytext, $section->summaryformat, $options);
    }

    /**
     * Display section and all its activities and subsections (called recursively)
     *
     * @param int|stdClass $course
     * @param int|section_info $section
     * @param int $sr section to return to (for building links)
     * @param int $level nested level on the page (in case of 0 also displays additional start/end html code)
     * @param bool  $pinned display pinned sections or not
     */
    public function display_section($course, $section, $sr, $level = 0, $pinned = false, $subsectionnumerator = null) {
        global $PAGE;

        $course = course_get_format($course)->get_course();
        $section = course_get_format($course)->get_section($section);
        $context = context_course::instance($course->id);
        $contentvisible = true;
        $sectionnum = $section->section;
        $this->sectionscustomenumeration[$sectionnum] = $subsectionnumerator;
        $hiddsecclass = '';

        // check 'displaysectionsnum' option to limit sections display
        if (!$PAGE->user_is_editing() && intval($section->customnumber) > $course->displaysectionsnum) { // skip all sections over the limit value in non-edit mode
            return;
        } else if ($PAGE->user_is_editing() && intval($section->customnumber) > $course->displaysectionsnum) {
            $hiddsecclass = ' hidden-section';  // add class for not visible sections in edit mode
        }

        if (!$section->uservisible || !course_get_format($course)->is_section_real_available($section)) {
            if ($section->visible && !$section->available && $section->availableinfo) {
                // Still display section but without content.
                $contentvisible = false;
            } else {
                return '';
            }
        }
        $movingsection = course_get_format($course)->is_moving_section();

        if ($level === 0) {
            $cancelmovingcontrols = course_get_format($course)->get_edit_controls_cancelmoving();
            foreach ($cancelmovingcontrols as $control) {
                echo $this->render($control);
            }
            // if (!$PAGE->user_is_editing()) {
                $topunit_btn = '<span class = "openall">'.get_string('openall', 'format_stardust').'</span><spam class = "closeall">'.get_string('closeall', 'format_stardust').'</span>';
                echo html_writer::start_tag('ul', array('class' => 'flexsections flexsections-level-0'));
                echo html_writer::start_tag('div', array('class' => 'topunit'));
                echo html_writer::tag('span', get_string('topunit', 'format_stardust'), array('class' => 'topunit_name display__none'));
                echo html_writer::tag('button', $topunit_btn, array('class' => 'topunit_btn', 'data-handler' => 'openall'));
                echo html_writer::end_tag('div');
            // }

            if ($section->section) {
                $this->display_insert_section_here($course, $section->parent, $section->section, $sr);
            }
        }
        echo html_writer::start_tag('li',
                array('class' => "section main".
                    ($pinned ? ' pinned ' : '').
                    ($movingsection === $sectionnum ? ' ismoving' : '').
                    (course_get_format($course)->is_section_current($section) ? ' current' : '').
                    (($section->visible && $contentvisible) ? '' : ' hidden').
                    $hiddsecclass,
                    'id' => 'section-'.$sectionnum));

        // display controls except for expanded/collapsed
        $controls = course_get_format($course)->get_section_edit_controls($section, $sr);
        $collapsedcontrol = null;
        $pincontrol = '';
        $controlsstr = '';

        foreach ($controls as $idxcontrol => $control) {
            if ($control->class === 'expanded' || $control->class === 'collapsed') {
                $collapsedcontrol = $control;
            } else if ($control->class === 'pinned' || $control->class === 'unpinned' ) {
                if ($section->parent == 0) {
                    $pincontrol .= $this->render($control);
                }
            } else {
                $controlsstr .= $this->render($control);
            }
        }
        if (!empty($pincontrol) && !empty($controlsstr)) {
            $controlsstr = $pincontrol . $controlsstr;
        }
        if (!empty($controlsstr)) {
            echo html_writer::tag('div', $controlsstr, array('class' => 'controls'));
        }

        // display section content
        echo html_writer::start_tag('div', array('class' => 'content'));
        // display section name and expanded/collapsed control

         if ($sectionnum != 0 ) {


            if ($section->collapsed == FORMAT_STARDUST_COLLAPSED) {
                echo html_writer::start_tag('div', array('class' => 'section_wrap'));
            } else {
                echo html_writer::start_tag('div', array('class' => 'section_wrap section-opened'));
            }
            if ($sectionnum && ($title = $this->section_title($sectionnum, $course, ($level == 0) || !$contentvisible))) {
            // if ($sectionnum && ($title = $this->section_title($sectionnum, $course, true))) { //($level == 0) || !$contentvisible) - as it was before - not supress link if ..
              if ($collapsedcontrol) {
                  $title = $this->render($collapsedcontrol). $title;
              }
              if ($section->pinned == FORMAT_STARDUST_UNPINNED){
                // SG - enumerate unpinned sections only in edit mode, else - use data from DB
                if ($PAGE->user_is_editing()) {
                    echo html_writer::tag('span', $subsectionnumerator, array('class' => 'sectionnumber'));
                } else {
                    echo html_writer::tag('span', $section->customnumber, array('class' => 'sectionnumber'));
                }
              } else {
                echo html_writer::tag('span', '', array('class' => 'sectionnumber'));
              }
              echo html_writer::tag('h3', $title, array('class' => 'sectionname'));
              echo html_writer::tag('span', '', array('class' => 'sectiontoggle', 'data-handler' => 'toggleSection'));
          }
        echo html_writer::end_tag('div'); //end section_wrap

        echo $this->section_availability_message($section,
            has_capability('moodle/course:viewhiddensections', $context));

        // add progress bar to section header
        echo $this->getsectionprogress($section);

        }
        // display section description (if needed)
        if ($contentvisible && ($summary = $this->format_summary_text($section))) {
            $hide0sec = ($section->visible == 3) ? ' hidden' : '';
            echo html_writer::tag('div', $summary, array('class' => 'summary' . $hide0sec));
        } else {
            echo html_writer::tag('div', '', array('class' => 'summary nosummary'));
        }

        // display section contents (activities and subsections)
        if ($contentvisible) {
            // display resources and activities
            if ($sectionnum != 0 || $pinned) {  echo $this->courserenderer->course_section_cm_list($course, $section, $sr);}  // SG - content is displayed in all sections, except second (unpinned mode) 0 sec
            if ($PAGE->user_is_editing()) {
                // a little hack to allow use drag&drop for moving activities if the section is empty
                if (empty(get_fast_modinfo($course)->sections[$sectionnum])) {
                    echo "<ul class=\"section img-text\">\n</ul>\n";
                }
                echo $this->courserenderer->course_section_add_cm_control($course, $sectionnum, $sr);
            }
            // display subsections
            $children = course_get_format($course)->get_subsections($sectionnum);

            // first, count subsections to enumerate them correctly in recursive functiion
            $childnum = count($children);
            // create array within range corresponding to child subsections - for enumeration
            $childnumiterator = range(1, $childnum);

            if (!empty($children) || $movingsection) {
              //TODO hide/show subsection
              // SG - hide temporary
                $sectionstyle = '';
                // if (course_get_format($course)->get_section($num)->collapsed == FORMAT_STARDUST_COLLAPSED && $level > 0 && !$PAGE->user_is_editing() ) {
                //   $sectionstyle = 'display:none';
                // }elseif (course_get_format($course)->get_section($num)->collapsed == FORMAT_STARDUST_COLLAPSED && $level > 0 && $PAGE->user_is_editing()) {
                //   $sectionstyle = 'display:none';
                // }

                if (course_get_format($course)->get_section($sectionnum)->collapsed == FORMAT_STARDUST_COLLAPSED && $level > 0)  $sectionstyle = 'display:none';

                echo html_writer::start_tag('ul', array('class' => 'flexsections flexsections-level-'.($level+1), 'style' => $sectionstyle ));
                foreach ($children as $num) {
                    if ($pinned) {
                        // we don't enumerate sections in pinned mode
                        if (course_get_format($course)->get_section($num)->pinned == FORMAT_STARDUST_PINNED) {
                            $this->display_insert_section_here($course, $section, $num, $sr);
                            $this->display_section($course, $num, $sr, $level+1);
                        }
                    } else {
                        if (course_get_format($course)->get_section($num)->pinned == FORMAT_STARDUST_UNPINNED) {
                            // get current child section internal enumerator
                            $internaliterator = array_shift($childnumiterator);
                            // temp enumerator in foreach - works like prefix for internal iterator
                            $subsectionnumerator1 = null;
                            // get subsectionnumerator, if it was provided for function before
                            if ($subsectionnumerator) {
                                $subsectionnumerator1 .= $subsectionnumerator.'.'.$internaliterator;
                            } else {
                                $subsectionnumerator1 = $internaliterator;
                            }
                            // show icon for 'move function'
                            $this->display_insert_section_here($course, $section, $num, $sr);
                            //display subsection and provide it with correct enumerator
                            $this->display_section($course, $num, $sr, $level+1, false, $subsectionnumerator1);
                            unset($subsectionnumerator1); //unset temp enumerator for correct foreach loop
                        }
                    }
                }
                $this->display_insert_section_here($course, $section, null, $sr);
                echo html_writer::end_tag('ul'); // .flexsections
            }
            if ($addsectioncontrol = course_get_format($course)->get_add_section_control($sectionnum)) {
                echo $this->render($addsectioncontrol);
            }
        }

        echo html_writer::end_tag('div'); // .content
        echo html_writer::end_tag('li'); // .section
        if ($level === 0) {
            if ($section->section) {
                $this->display_insert_section_here($course, $section->parent, null, $sr);
            }
            echo html_writer::end_tag('ul'); // .flexsections
        }
    }

    /**
     * Displays the target div for moving section (in 'moving' mode only)
     *
     * @param int|stdClass $courseorid current course
     * @param int|section_info $parent new parent section
     * @param null|int|section_info $before number of section before which we want to insert (or null if in the end)
     */
    protected function display_insert_section_here($courseorid, $parent, $before = null, $sr = null) {
        if ($control = course_get_format($courseorid)->get_edit_control_movehere($parent, $before, $sr)) {
            echo $this->render($control);
        }
    }

    /**
     * renders HTML for format_stardust_edit_control
     *
     * @param format_stardust_edit_control $control
     * @return string
     */
    protected function render_format_stardust_edit_control(format_stardust_edit_control $control) {
        if (!$control) {
            return '';
        }
        if ($control->class === 'movehere') {
            $icon = new pix_icon('movehere', $control->text, 'moodle', array('class' => 'movetarget', 'title' => $control->text));
            $action = new action_link($control->url, $icon, null, array('class' => $control->class));
            return html_writer::tag('li', $this->render($action), array('class' => 'movehere'));
        } else if ($control->class === 'cancelmovingsection' || $control->class === 'cancelmovingactivity') {
            return html_writer::tag('div', html_writer::link($control->url, $control->text),
                    array('class' => 'cancelmoving '.$control->class));
        } else if ($control->class === 'addsection') {
            $icon = new pix_icon('t/add', '', 'moodle', array('class' => 'iconsmall'));
            $text = $this->render($icon). html_writer::tag('span', $control->text, array('class' => $control->class.'-text'));
            $action = new action_link($control->url, $text, null, array('class' => $control->class));
            return html_writer::tag('div', $this->render($action), array('class' => 'mdl-right'));
        } else if ($control->class === 'backto') {
            $icon = new pix_icon('t/up', '', 'moodle');
            $text = $this->render($icon). html_writer::tag('span', $control->text, array('class' => $control->class.'-text'));
            return html_writer::tag('div', html_writer::link($control->url, $text),
                    array('class' => 'header '.$control->class));
        } else if ($control->class === 'settings' || $control->class === 'marker' || $control->class === 'marked') {
            $icon = new pix_icon('i/'. $control->class, $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'move' || $control->class === 'expanded' || $control->class === 'collapsed' ||
                $control->class === 'hide' || $control->class === 'show' || $control->class === 'delete') {
            $icon = new pix_icon('t/'. $control->class, $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'mergeup') {
            $icon = new pix_icon('mergeup', $control->text, 'format_stardust', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'pinned') {
            $icon = new pix_icon('i/unlock', $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        } else if ($control->class === 'unpinned') {
            $icon = new pix_icon('i/lock', $control->text, 'moodle', array('class' => 'iconsmall', 'title' => $control->text));
        }
        if (isset($icon)) {
            if ($control->url) {
                // icon with a link
                $action = new action_link($control->url, $icon, null, array('class' => $control->class));
                return $this->render($action);
            } else {
                // just icon
                return html_writer::tag('span', $this->render($icon), array('class' => $control->class));
            }
        }
        // unknown control
        return ' '. html_writer::link($control->url, $control->text, array('class' => $control->class)). '';
    }

    /**
     * If section is not visible, display the message about that ('Not available
     * until...', that sort of thing). Otherwise, returns blank.
     *
     * For users with the ability to view hidden sections, it shows the
     * information even though you can view the section and also may include
     * slightly fuller information (so that teachers can tell when sections
     * are going to be unavailable etc). This logic is the same as for
     * activities.
     *
     * @param stdClass $section The course_section entry from DB
     * @param bool $canviewhidden True if user can view hidden sections
     * @return string HTML to output
     */
    protected function section_availability_message($section, $canviewhidden) {
        global $CFG;
        $o = '';
        if (!$section->uservisible) {
            // Note: We only get to this function if availableinfo is non-empty,
            // so there is definitely something to print.
            $formattedinfo = \core_availability\info::format_info(
                $section->availableinfo, $section->course);
            $o .= html_writer::div($formattedinfo, 'availabilityinfo');
        } else if ($canviewhidden && !empty($CFG->enableavailability) && $section->visible) {
            $ci = new \core_availability\info_section($section);
            $fullinfo = $ci->get_full_information();
            if ($fullinfo) {
                $formattedinfo = \core_availability\info::format_info(
                    $fullinfo, $section->course);
                $o .= html_writer::div($formattedinfo, 'availabilityinfo');
            }
        }
        return $o;
    }

    /**
     * Displays a confirmation dialogue when deleting the section (for non-JS mode)
     *
     * @param stdClass $course
     * @param int $sectionreturn
     * @param int $deletesection
     */
    public function confirm_delete_section($course, $sectionreturn, $deletesection) {
        echo $this->box_start('noticebox');
        $courseurl = course_get_url($course, $sectionreturn);
        $optionsyes = array('confirm' => 1, 'deletesection' => $deletesection, 'sesskey' => sesskey());
        $formcontinue = new single_button(new moodle_url($courseurl, $optionsyes), get_string('yes'));
        $formcancel = new single_button($courseurl, get_string('no'), 'get');
        echo $this->confirm(get_string('confirmdelete', 'format_stardust'), $formcontinue, $formcancel);
        echo $this->box_end();
    }
}
