<?php
/**
 * Text Calendar
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 11:09:31 [Sep 30, 2023])
 */

require_once DIR_MODULES . 'text_calendar/ICal/ICal.php';
require_once DIR_MODULES . 'text_calendar/ICal/Event.php';

use ICal\ICal;


//
//
class text_calendar extends module
{
    /**
     * text_calendar
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "text_calendar";
        $this->title = "Text Calendar";
        $this->module_category = "<#LANG_SECTION_APPLICATIONS#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (isset($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (isset($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 't_calendars' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_t_calendars') {
                $this->search_t_calendars($out);
            }
            if ($this->view_mode == 'edit_t_calendars') {
                $this->edit_t_calendars($out, $this->id);
            }
            if ($this->view_mode == 'delete_t_calendars') {
                $this->delete_t_calendars($this->id);
                $this->redirect("?");
            }
            if ($this->view_mode == 'widgets') {
                $this->widgets($out);
            }
        }
    }

    function widgets(&$out)
    {
        $calendar_id = gr('calendar_id', 'int');
        $type = gr('type');
        if (!$type) $type = 'summary';

        if ($type == 'summary') {
            $code = '[#module name="' . $this->name . '" type="summary" calendar_id="' . $calendar_id . '"#]';
        }

        if ($code != '') {
            $out['CODE'] = $code;
        }

        $out['CALENDARS'] = SQLSelect("SELECT ID, TITLE FROM t_calendars ORDER BY TITLE");
        $out['CALENDAR_ID'] = $calendar_id;
        $out['TYPE'] = $type;
    }

    function getEvents($calendar_id = 0, $tm = 0)
    {
        if (!$tm) $tm = time();
        $events = array();
        if ($calendar_id) {
            $calendars = SQLSelect("SELECT * FROM t_calendars WHERE ID=" . (int)$this->calendar_id);
        } else {
            $calendars = SQLSelect("SELECT * FROM t_calendars ORDER BY TITLE");
        }
        $total = count($calendars);
        for ($i = 0; $i < $total; $i++) {
            $calendar_events = $this->getEventsForDay($calendars[$i]['CALENDAR'], $tm);
            foreach ($calendar_events as $event) {
                $events[] = $event;
            }
        }
        return $events;

    }

    function getEventsForDay($data, $tm = 0)
    {

        if (!$tm) $tm = time();

        $week_days = array('Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб');
        $week_days_en = array(0 => "Sun", 1 => "Mon", 2 => "Tue", 3 => "Wed", 4 => "Thu", 5 => "Fri", 6 => "Sat");

        $lines = explode("\n", $data);
        $matched = array();
        foreach ($lines as $line) {
            if (preg_match('/^\#/', $line) || trim($line) == '') {
                continue;
            }

            $line = trim($line);

            if (preg_match('/^http/', $line)) {
                list($url, $prefix) = explode(';', $line);
                $external_events = $this->parseExternalCalendar($url, $tm);
                foreach ($external_events as $item) {
                    if ($prefix!='') {
                        $matched[] = trim($prefix).' '.$item;
                    } else {
                        $matched[] = $item;
                    }

                }
                continue;
            }


            list($dt, $event) = explode(';', $line);
            $event = trim($event);
            if (!$event) continue;
            $match = false;

            if (preg_match('/^(\d+)-(\d+)(.*)$/', $dt, $m)) {
                //days period
                $day_from = $m[1];
                $day_to = $m[2];
                $month_year = $m[3];
                $month = '';
                $year = '';
                if (preg_match('/(\d+)\/(\d+)/', $month_year, $m)) {
                    $month = (int)$m[1];
                    $year = (int)$m[2];
                } elseif (preg_match('/(\d+)/', $month_year, $m)) {
                    $month = (int)$m[1];
                }
                if (date('d', $tm) >= $day_from && date('d', $tm) <= $day_to &&
                    ($year == '' || date('Y', $tm) == $year) &&
                    ($month == '' || date('m', $tm) == $month)
                ) {
                    $match = true;
                }
            } elseif (preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $dt, $m)) {
                // exact date
                $day = (int)$m[1];
                $month = (int)$m[2];
                $year = (int)$m[3];
                if ($year < 2000) $year += 2000;
                if (date('Y', $tm) == $year && date('m', $tm) == $month && date('d', $tm) == $day) {
                    $match = true;
                }
            } elseif (preg_match('/^(\d+)\/(\d+)$/', $dt, $m)) {
                // day/month every year
                $day = (int)$m[1];
                $month = (int)$m[2];
                if (date('m', $tm) == $month && date('d', $tm) == $day) {
                    $match = true;
                }
            } elseif (preg_match('/^(\d+)$/', $dt, $m)) {
                //exact day of month
                $day = (int)$m[1];
                if (date('d', $tm) == $day) {
                    $match = true;
                }
            }

            if (!$match) {
                for ($i = 0; $i < 7; $i++) {
                    $week_day = $week_days[$i];
                    if (preg_match('/^' . $week_day . '(.*)/', $dt, $m) && date('w', $tm) == $i) {
                        $num_part = $m[1];
                        $wk = 0;
                        $month = 0;
                        if (preg_match('/(\d+)\/(\d+)/', $num_part, $m)) {
                            $wk = (int)$m[1];
                            $month = (int)$m[2];
                        } elseif (preg_match('/(\d+)/', $num_part, $m)) {
                            $wk = (int)$m[1];
                        }
                        if ($wk) {
                            if ($wk >= 5) {
                                $text = 'last ' . $week_days_en[$i];
                                $test_tm = strtotime($text, strtotime(date('m', strtotime('+1 month')) . '/01/' . date('Y'), $tm));
                            } else {
                                $text = 'first ' . $week_days_en[$i] . ' + ' . ($wk - 1) . ' weeks';
                                $test_tm = strtotime($text, strtotime(date('Y-m-01', $tm)));
                            }
                            if (date('Y-m-d', $tm) == date('Y-m-d', $test_tm) &&
                                ($month == 0 || date('m', $tm) == $month)) {
                                $match = true;
                            }
                        } else {
                            $match = true;
                        }

                        break;
                    }

                }
            }

            if ($match) {
                $matched[] = $event;
            }

        }

        return $matched;
    }

    function parseExternalCalendar($url, $tm = 0)
    {
        global $ical_cached;

        if (!$tm) $tm = time();

        $filename = ROOT . 'cms/cached/calendar_' . md5($url) . '.ics';
        if (file_exists($filename)) {
            $mtime = filemtime($filename);
        } else {
            $mtime = 0;
        }

        if ((time() - $mtime) > 15 * 60) {
            $data = getURL($url);
            if ($data != '') {
                SaveFile($filename, $data);
            }
        }

        if (!file_exists($filename)) return array();

        $events = array();
        try {
            if (!isset($ical_cached[$url])) {
                $ical_cached[$url] = new ICal($filename, array(
                    'defaultSpan' => 2,     // Default value
                    'defaultTimeZone' => 'UTC',
                    'defaultWeekStart' => 'MO',  // Default value
                    'disableCharacterReplacement' => true, // Default value
                    'filterDaysAfter' => 180,  // Default value
                    'filterDaysBefore' => 180,  // Default value
                    'httpUserAgent' => null,  // Default value
                    'skipRecurrence' => false, // Default value
                ));
            }
            $result = $ical_cached[$url]->eventsFromRange(date('Y-m-d 00:00:00', $tm), date('Y-m-d 23:59:59', $tm));
            if (isset($result[0])) {
                foreach ($result as $item) {
                    $events[] = $item->summary;
                }
            }
        } catch (\Exception $e) {
            return array();
        }

        return $events;

    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {

        $week_days = array('Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб');

        if (!$this->type) $this->type = 'summary';

        if (!$this->days) {
            $this->days = 7;
        }

        $out['TYPE'] = $this->type;

        if ($this->type == 'code') {
            $now = time();
            $events = $this->getEvents($this->calendar_id, $now);
            $out['OUTPUT'] = '<pre>' . implode("\n", $events) . '</pre>';
        }

        if ($this->type == 'week') {
            if (isset($this->next)) {
                $start = strtotime('Next Monday');
            } else {
                $start = strtotime('Previous Monday');
            }
            $days = array();
            for ($i = 0; $i < 7; $i++) {
                $tm = $start + ($i * 24 * 60 * 60);
                $day = array('TITLE' => date('d/m', $tm), 'TM' => $tm);
                $day['TITLE'] .= ' - ' . $week_days[date('w', $tm)];
                if (date('d.m.Y') == date('d.m.Y', $tm)) {
                    $day['TODAY'] = 1;
                }
                $days[] = $day;
            }
            $out['DAYS'] = $days;

            if ($this->calendar_id) {
                $calendars = SQLSelect("SELECT * FROM t_calendars WHERE ID=" . (int)$this->calendar_id);
                $out['SINGLE_CALENDAR'] = 1;
            } else {
                $calendars = SQLSelect("SELECT * FROM t_calendars ORDER BY TITLE");
            }

            $total = count($calendars);
            for ($i = 0; $i < $total; $i++) {
                // some action for every record if required
                $item_days = $days;
                $total_days = count($item_days);
                for ($id = 0; $id < $total_days; $id++) {
                    $events = $this->getEventsForDay($calendars[$i]['CALENDAR'], $item_days[$id]['TM']);
                    if (count($events) > 0) {
                        $item_days[$id]['BODY'] = implode('<br/>', $events);
                    }
                }
                $calendars[$i]['DAYS'] = $item_days;
            }
            $out['CALENDARS'] = $calendars;

        }

        if ($this->type == 'today' || $this->type == 'tomorrow') {
            $tm = $this->type == 'today' ? time() : (time() + 24 * 60 * 60);
            $out['BODY'] = '<div><big><b>' . date('d.m.Y', $tm) . ' (' . $week_days[date('w', $tm)] . ')</b></big></div>';
            if ($this->calendar_id) {
                $calendar = SQLSelectOne("SELECT * FROM t_calendars WHERE ID=" . (int)$this->calendar_id);
                $events = $this->getEventsForDay($calendar['CALENDAR'], $tm);
                if (count($events) > 0) {
                    $out['BODY'] .= '<div>' . implode("<br/>", $events) . '</div>';
                }
            } else {
                $calendars = SQLSelect("SELECT * FROM t_calendars ORDER BY TITLE");
                $total_c = count($calendars);
                for ($ic = 0; $ic < $total_c; $ic++) {
                    $events = $this->getEventsForDay($calendars[$ic]['CALENDAR'], $tm);
                    if (count($events) > 0) {
                        $out['BODY'] .= '<div><b>' . $calendars[$ic]['TITLE'] . '</b><br/>' . implode("<br/>", $events) . '</div>';
                    }
                }
            }
        }

        if ($this->type == 'summary') {
            $days = array();
            for ($i = 0; $i < $this->days; $i++) {
                $tm = time() + (($i) * 24 * 60 * 60);
                $day = array('TITLE' => date('d/m', $tm), 'TM' => $tm);
                $day['TITLE'] .= ' - ' . $week_days[date('w', $tm)];

                if (date('d.m.Y') == date('d.m.Y', $tm)) {
                    $day['TITLE'] .= ' (сегодня)';
                    $day['TODAY'] = 1;
                }
                if (date('d.m.Y', time() - 24 * 60 * 60) == date('d.m.Y', $tm)) {
                    $day['TITLE'] .= ' (вчера)';
                }
                if (date('d.m.Y', time() + 24 * 60 * 60) == date('d.m.Y', $tm)) {
                    $day['TITLE'] .= ' (завтра)';
                }

                if ($this->calendar_id) {
                    $calendar = SQLSelectOne("SELECT * FROM t_calendars WHERE ID=" . (int)$this->calendar_id);
                    $events = $this->getEventsForDay($calendar['CALENDAR'], $day['TM']);
                    if (count($events) > 0) {
                        $day['BODY'] = implode("<br/>", $events);
                    }
                } else {
                    $calendars = SQLSelect("SELECT * FROM t_calendars ORDER BY TITLE");
                    $total_c = count($calendars);
                    for ($ic = 0; $ic < $total_c; $ic++) {
                        $events = $this->getEventsForDay($calendars[$ic]['CALENDAR'], $day['TM']);
                        if (count($events) > 0) {
                            $day['BODY'] .= '<div><b>' . $calendars[$ic]['TITLE'] . '</b><br/>' . implode("<br/>", $events) . '</div>';
                        }
                    }
                }
                if ($day['BODY']) {
                    $days[] = $day;
                }
            }
            $out['DAYS'] = $days;
        }

    }

    /**
     * t_calendars search
     *
     * @access public
     */
    function search_t_calendars(&$out)
    {
        require(dirname(__FILE__) . '/t_calendars_search.inc.php');
    }

    /**
     * t_calendars edit/add
     *
     * @access public
     */
    function edit_t_calendars(&$out, $id)
    {
        require(dirname(__FILE__) . '/t_calendars_edit.inc.php');
    }

    /**
     * t_calendars delete record
     *
     * @access public
     */
    function delete_t_calendars($id)
    {
        $rec = SQLSelectOne("SELECT * FROM t_calendars WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM t_calendars WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS t_calendars');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        t_calendars -
        */
        $data = <<<EOD
 t_calendars: ID int(10) unsigned NOT NULL auto_increment
 t_calendars: TITLE varchar(100) NOT NULL DEFAULT ''
 t_calendars: CALENDAR text
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgU2VwIDMwLCAyMDIzIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
