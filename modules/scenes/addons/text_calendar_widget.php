<?php


$this->widget_types['chart'] = array(
    'TITLE' => 'Calendar',
    'DESCRIPTION' => 'Add calendar widget',
    'PROPERTIES' => array(
        'calendar_id'=> array('DESCRIPTION'=>'Календарь',
            '_CONFIG_TYPE'=>'select',
            '_CONFIG_OPTIONS'=>function () {
                $options = SQLSelect("SELECT ID as VALUE, TITLE FROM t_calendars ORDER BY TITLE");
                $options[] = array('VALUE'=>0,'TITLE'=>'Все');
                return $options;
            }),
        'type'=>array('DESCRIPTION'=>'Тип виджета',
            '_CONFIG_TYPE'=>'select',
            '_CONFIG_OPTIONS' => array(
                array('VALUE' => 'summary', 'TITLE'=>'Суммарный (7 дней)'),
                array('VALUE' => 'summary3', 'TITLE'=>'Суммарный (3 дня)'),
                array('VALUE' => 'week', 'TITLE'=>'Эта неделя'),
                array('VALUE' => 'today', 'TITLE'=>'Сегодня'),
                array('VALUE' => 'tomorrow', 'TITLE'=>'Завтра'),
             ),
            'DEFAULT_VALUE'=>'summary'),
        'use_background'=>array('DESCRIPTION'=>'Использовать фон',
            '_CONFIG_TYPE'=>'yesno',
            'DEFAULT_VALUE'=>0)
    ),
    'RESIZABLE' => true,
    'DEFAULT_WIDTH' => 300,
    'DEFAULT_HEIGHT' => 200,
    'TEMPLATE' => 'file:text_calendar_block.html'
);