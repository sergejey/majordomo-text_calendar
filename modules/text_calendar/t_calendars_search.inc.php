<?php
/*
* @version 0.1 (wizard)
*/

$week_days = array('Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб');
$days = array();
for ($i = 0; $i < 8; $i++) {
    $tm = time() + (($i - 1) * 24 * 60 * 60);
    $day = array('TITLE' => date('d/m', $tm), 'TM' => $tm);
    $day['TITLE'].=' - '.$week_days[date('w',$tm)];

    if (date('d.m.Y')==date('d.m.Y',$tm)) {
        $day['TITLE'].=' (сегодня)';
        $day['TODAY']=1;
    }
    if (date('d.m.Y',time()-24*60*60)==date('d.m.Y',$tm)) {
        $day['TITLE'].=' (вчера)';
    }
    if (date('d.m.Y',time()+24*60*60)==date('d.m.Y',$tm)) {
        $day['TITLE'].=' (завтра)';
    }
    $days[]=$day;
}
$out['DAYS'] = $days;

if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$qry = "1";
// search filters
// QUERY READY
$qry = "1";
$sortby_t_calendars = "TITLE";
$out['SORTBY'] = $sortby_t_calendars;
// SEARCH RESULTS
$res = SQLSelect("SELECT * FROM t_calendars WHERE $qry ORDER BY " . $sortby_t_calendars);
if (isset($res[0])) {
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        // some action for every record if required
        $item_days = $days;
        $total_days = count($item_days);
        for($id=0;$id<$total_days;$id++) {
            $events = $this->getEventsForDay($res[$i]['CALENDAR'],$item_days[$id]['TM']);
            if (count($events)>0) {
                $item_days[$id]['BODY']=implode('<br/>',$events);
            }
        }
        $res[$i]['DAYS'] = $item_days;
    }
    $out['RESULT'] = $res;
}
