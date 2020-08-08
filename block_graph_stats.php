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

/*
 * Main block class for graph_stats block
 *
 * @copyright 2011 Éric Bugnet with help of Jean Fruitet
 * @copyright 2014 Wesley Ellis, Code Improvements.
 * @copyright 2014 Vadim Dvorovenko
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/blocks/graph_stats/locallib.php');

/**
 * Main block class for graph_stats block
 *
 * @package    block_graph_stats
 * @copyright 2011 Éric Bugnet with help of Jean Fruitet
 * @copyright 2014 Wesley Ellis, Code Improvements.
 * @copyright 2014 Vadim Dvorovenko
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_graph_stats extends block_base {

    /*
    * Standard block API function for initializing block instance
    * @return void
    */
    public function init() {
        $this->title = get_string('blockname', 'block_graph_stats');
    }

    /**
     * Subclasses should override this and return true if the
     * subclass block has a settings.php file.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Is each block of this type going to have instance-specific configuration?
     * Normally, this setting is controlled by {@link instance_allow_multiple()}: if multiple
     * instances are allowed, then each will surely need its own configuration. However, in some
     * cases it may be necessary to provide instance configuration to blocks that do not want to
     * allow multiple instances. In that case, make this function return true.
     * I stress again that this makes a difference ONLY if {@link instance_allow_multiple()} returns false.
     * @return boolean
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Are you going to allow multiple instances of each block?
     * If yes, then it is assumed that the block WILL USE per-instance configuration
     * @return boolean
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Which page types this block may appear on.
     *
     * The information returned here is processed by the
     * {@link blocks_name_allowed_in_format()} function. Look there if you need
     * to know exactly how this works.
     *
     * Default case: everything except mod and tag.
     *
     * @return array page-type prefix => true/false.
     */
    public function applicable_formats() {
        return array(
            'site' => true,
            'my' => true,
            'course-view' => true);
    }

    /**
     * Allows the block to load any JS it requires into the page.
     *
     * By default this function simply permits the user to dock the block if it is dockable.
     */
    public function get_required_javascript() {
        parent::get_required_javascript();

        $this->page->requires->jquery();
    }

    /**
     * Parent class version of this function simply returns NULL
     * This should be implemented by the derived class to return
     * the content object.
     *
     * @return stdObject
     */
    public function get_content() {
        global $COURSE, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $cfg = get_config('block_graph_stats');
        // Number of seconds for cache today data.
        if (isset($cfg->todaycache)) {
            $todaycache = $cfg->todaycache;
        } else {
            $todaycache = 300;
        }
        $cache = cache::make('block_graph_stats', 'visits');

        /*
         * number of day for the graph
         * @var int
         */
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        $this->content->text .= block_graph_stats_graph_google($COURSE->id);

        // Add a link to course report for today.
        if (has_capability('report/log:view', context_course::instance($COURSE->id))) {
            $this->content->text .= html_writer::start_tag('div' , array('class' => 'moredetails'));
            $this->content->text .= html_writer::link(
                    new moodle_url('/blocks/graph_stats/details.php', array('course_id' => $COURSE->id)),
                    get_string('moredetails', 'block_graph_stats'),
                    array('title' => get_string('moredetails', 'block_graph_stats')));
            $this->content->text .= html_writer::end_tag('div');
        }

        // Add some details in the footer.
        if ($COURSE->id > 1) {
            $needtoupdate = false;
            $connections = $cache->get('visitsmain_' . $courseid . '_today');
            $todayupdatedtime = $cache->get('visitsmain_' . $courseid . '_todayupdatedtime');
            if ($todayupdatedtime === false || ($todayupdatedtime + $todaycache) < time()) {
                $needtoupdate = true;
                $cache->set('visitsmain_' . $courseid . '_todayupdatedtime', time());
            }
            if ($connections === false || $needtoupdate) {
                // In a course.
                $sql = "SELECT COUNT (*) as countid FROM (SELECT userid FROM {logstore_standard_log}
                        WHERE timecreated >= :time AND eventname = :eventname  AND courseid = :course GROUP BY userid) AS users";
                $params = array(
                        'time' => usergetmidnight(time()),
                        'eventname' => '\core\event\course_viewed',
                        'course' => $COURSE->id);
                $connections = $DB->get_record_sql($sql , $params);
                $cache->set('visitsmain_' . $courseid . '_today', $connections);
            }
            $this->content->footer .= get_string('connectedtodaya', 'block_graph_stats', $connections->countid);
        } else {
            $needtoupdate = false;
            $connections = $cache->get('visitsmain_' . $courseid . '_today');
            $todayupdatedtime = $cache->get('visitsmain_' . $courseid . '_todayupdatedtime');
            if ($todayupdatedtime === false || ($todayupdatedtime + $todaycache) < time()) {
                $needtoupdate = true;
                $cache->set('visitsmain_' . $courseid . '_todayupdatedtime', time());
            }
            if ($connections === false || $needtoupdate) {
                // In the front page.
                $sql = "SELECT COUNT (*) as countid FROM (SELECT userid FROM {logstore_standard_log}
                        WHERE timecreated >= :time AND eventname = :eventname GROUP BY userid) AS users";
                $params = array(
                        'time' => usergetmidnight(time()),
                        'eventname' => '\core\event\user_loggedin');
                $connections = $DB->get_record_sql($sql, $params);
                $cache->set('visitsmain_' . $courseid . '_today', $connections);
            }
            $this->content->footer .= get_string('connectedtodaya', 'block_graph_stats', $connections->countid);
            $users = $DB->count_records('user', array('deleted' => 0, 'confirmed' => 1));
            $courses = $DB->count_records('course', array('visible' => 1));
            $this->content->footer .= '<br />'.get_string('membersnba', 'block_graph_stats', $users);
            $this->content->footer .= '<br />'.get_string('coursesnba', 'block_graph_stats', $courses);
        }
        return $this->content;
    }
}
