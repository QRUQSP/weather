<?php
//
// Description
// -----------
// This widget displays the temperature and humidity orbit dials
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_widgets_baro2(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    if( !isset($args['widget']['widget_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.69', 'msg'=>'No dashboard widget specified'));
    }

    if( !isset($args['widget']['content']) ) {
        $args['widget']['content'] = '';
    }

    if( !isset($args['widget']['settings']) ) {
        $args['widget']['settings'] = array();
    }

    $widget = $args['widget'];
//    $widget['settings']['scale'] = '2';

    //
    // Setup the scale
    //
    if( isset($widget['settings']['units']) && $widget['settings']['units'] == 'mmhg' ) {
        if( isset($widget['settings']['scale']) && $widget['settings']['scale'] == '2' ) {
            $start_tick = 740;
            $end_tick = 780;
            $multiplier = 7.50;
            $tick_space = 1;
            $label_space = 10;
        } else {
            $start_tick = 720;
            $end_tick = 800;
            $multiplier = 3.75;
            $tick_space = 2;
            $label_space = 20;
        }
    } else {
        if( isset($widget['settings']['scale']) && $widget['settings']['scale'] == '2' ) {
            $start_tick = 987;
            $end_tick = 1043;
            $multiplier = 5.50;
            $tick_space = 1;
            $label_space = 10;
        } else {
            $start_tick = 960;
            $end_tick = 1070;
            $multiplier = 2.75;
            $tick_space = 2;
            $label_space = 20;
        }
    }

    $label_font_size = 12;
    $tick_font_size = 10;
    $pressure_font_size = 50;
    if( isset($_SERVER['HTTP_USER_AGENT']) && stristr($_SERVER['HTTP_USER_AGENT'], ' Gecko/20') !== false ) {
        $label_font_size = 11;
        $tick_font_size = 9;
        $pressure_font_size = 44;
    }

    //
    // Make sure the sample date is within the last 5 minutes
    //
    $age_dt = new DateTime('now', new DateTimezone('UTC'));
    $end_ages = array();
    $start_ages = array();
    for($i = 1; $i < 6; $i++) {
        $end_ages[$i] = clone $age_dt;
        $end_ages[$i]->sub(new DateInterval('PT' . $i . 'H'));
        $start_ages[$i] = clone $end_ages[$i];
        $start_ages[$i]->sub(new DateInterval('PT6M'));
    }
    $age_dt->sub(new DateInterval('PT6M'));

    $sensor_ids = array();
    if( isset($widget['settings']['pid']) ) {
        $sensor_ids[] = $widget['settings']['pid'];
    }

    if( count($sensor_ids) > 0 ) {
        $strsql = "SELECT sensors.id, "
            . "sensors.name, "
            . "sensors.flags, "
            . "sensors.fields, "
            . "IFNULL(data.millibars, '') AS millibars, "
            . "IFNULL(data1.millibars, '') AS millibars1, "
            . "IFNULL(data2.millibars, '') AS millibars2, "
            . "IFNULL(data3.millibars, '') AS millibars3, "
            . "IFNULL(data4.millibars, '') AS millibars4, "
            . "IFNULL(data5.millibars, '') AS millibars5 "
            . "FROM qruqsp_weather_sensors AS sensors "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
                . "sensors.id = data.sensor_id "
                . "AND sensors.last_sample_date = data.sample_date "
                . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") ";
        for($i = 1; $i < 6; $i++) {
            $strsql .= "LEFT JOIN qruqsp_weather_sensor_data AS data{$i} ON ("
                . "sensors.id = data{$i}.sensor_id "
                . "AND data{$i}.sample_date > '" . ciniki_core_dbQuote($ciniki, $start_ages[$i]->format('Y-m-d H:i:s')) . "' "
                . "AND data{$i}.sample_date < '" . ciniki_core_dbQuote($ciniki, $end_ages[$i]->format('Y-m-d H:i:s')) . "' "
                . "AND data{$i}.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") ";
        }
        $strsql .= "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $sensor_ids) . ") "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND sensors.last_sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt->format('Y-m-d H:i:s')) . "' "
            . "ORDER BY sensors.id "
            . "LIMIT 1 ";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'flags', 'fields', 'millibars', 'millibars1', 'millibars2',
                    'millibars3', 'millibars4', 'millibars5'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.37', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
        }
        $sensors = isset($rc['sensors']) ? $rc['sensors'] : array();
    } else {
        $sensors = array();
    }

    //
    // Check if this is a data update
    //
    $widget['data'] = array();
    $scale = 2;

    if( isset($widget['settings']['pid']) && isset($sensors[$widget['settings']['pid']]['millibars']) ) {
        if( isset($widget['settings']['units']) && $widget['settings']['units'] == 'mmhg' ) {
            $widget['data']['angle'] = ((($sensors[$widget['settings']['pid']]['millibars'] * 0.75006) - $start_tick) * $multiplier) + 90 + 28.75;
            $widget['data']['pid'] = round($sensors[$widget['settings']['pid']]['millibars'] * 0.75006);
            for($i = 1; $i < 6; $i++) {
                $widget['data']['angle' . $i] = ((($sensors[$widget['settings']['pid']]['millibars' . $i] * 0.75006) - $start_tick) * $multiplier) + 90 + 28.75;
            }
        } else {
            $widget['data']['angle'] = (($sensors[$widget['settings']['pid']]['millibars'] - $start_tick) * $multiplier) + 90 + 28.75;
            $widget['data']['pid'] = round($sensors[$widget['settings']['pid']]['millibars']);
            for($i = 1; $i < 6; $i++) {
                $widget['data']['angle' . $i] = (($sensors[$widget['settings']['pid']]['millibars' . $i] - $start_tick) * $multiplier) + 90 + 28.75;
            }
        }
    } else {
        $widget['data']['pid'] = '?';
        $widget['data']['angle'] = 0;
        for($i = 1; $i < 6; $i++) {
            $widget['data']['angle' . $i] = 0;
        }
    }

    if( isset($args['action']) && $args['action'] == 'update' ) {
        return array('stat'=>'ok', 'widget'=>$widget);
    }

    //
    // Setup the HTML for the dial
    //
    $x1 = 100 + (80 * cos(65 * 0.0174532925));
    $y1 = 100 + (80 * sin(65 * 0.0174532925));
    $x2 = 100 + (80 * cos(115 * 0.0174532925));
    $y2 = 100 + (80 * sin(115 * 0.0174532925));
    // Major ticks every 27.5 degrees
    $widget['content'] .= '<svg viewBox="0 0 200 200">';
    for($y = $start_tick; $y <= $end_tick; $y++) {
        $tick_angle = (($y - $start_tick) * $multiplier) + 90 + 28.75;
        if( ($y%10) == 0 ) {
            $x1 = 100 + (74 * cos($tick_angle * 0.0174532925));
            $y1 = 100 + (74 * sin($tick_angle * 0.0174532925));
            $x2 = 100 + (86 * cos($tick_angle * 0.0174532925));
            $y2 = 100 + (86 * sin($tick_angle * 0.0174532925));
            $widget['content'] .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#fff' stroke-width='1' />";
        } elseif( ($y%$tick_space) == 0 ) {
            $x1 = 100 + (78 * cos($tick_angle * 0.0174532925));
            $y1 = 100 + (78 * sin($tick_angle * 0.0174532925));
            $x2 = 100 + (82 * cos($tick_angle * 0.0174532925));
            $y2 = 100 + (82 * sin($tick_angle * 0.0174532925));
            $widget['content'] .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#aaa' stroke-width='1' />";
        }
        if( ($y%$label_space) == 0 ) {
            $x1 = 100 + (62 * cos($tick_angle * 0.0174532925));
            $y1 = 100 + (62 * sin($tick_angle * 0.0174532925));
            if( $y == 1040 ) {
                $x1 -= 3;
                $y1 -= 5;
            }
            $widget['content'] .= "<text x='{$x1}' y='{$y1}' width='10' height='10' font-size='{$tick_font_size}' fill='#888'><tspan dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>{$y}</tspan></text>";
        }
    }
    // Add history dots
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot5' cx='150' cy='100' r='1' "
        . "fill='rgba(0,105,255,0.85)' stroke='white' stroke-width='0.25' "
        . "transform='rotate({$widget['data']['angle5']},100,100)' />";
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot4' cx='152.5' cy='100' r='2' "
        . "fill='rgba(0,105,255,0.85)' stroke='white' stroke-width='0.25' "
        . "transform='rotate({$widget['data']['angle4']},100,100)' />";
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot3' cx='157' cy='100' r='3.5' "
        . "fill='rgba(0,105,255,0.85)' stroke='white' stroke-width='0.25' "
        . "transform='rotate({$widget['data']['angle3']},100,100)' />";
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot2' cx='163' cy='100' r='5' "
        . "fill='rgba(0,105,255,0.85)' stroke='white' stroke-width='0.25' "
        . "transform='rotate({$widget['data']['angle2']},100,100)' />";
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot1' cx='170' cy='100' r='6.5' "
        . "fill='rgba(0,105,255,0.85)' stroke='white' stroke-width='0.25' "
        . "transform='rotate({$widget['data']['angle1']},100,100)' />"; 
    // Add the needle line
    if( isset($widget['settings']['line']) && $widget['settings']['line'] == 'yes' ) {
        $widget['content'] .= "<line id='widget-{$widget['id']}-line' x1='100' y1='100' x2='170' y2='100' "
            . "fill='rgba(0,105,255,0.85)' stroke='#bbb' stroke-width='0.5' "
            . "transform='rotate({$widget['data']['angle']},100,100)' />";
        $widget['content'] .= "<line id='widget-{$widget['id']}-stub' x1='190' y1='100' x2='193' y2='100' "
            . "fill='rgba(0,105,255,0.85)' stroke='#bbb' stroke-width='0.5' "
            . "transform='rotate({$widget['data']['angle']},100,100)' />";
    }
    // Add barometer dot
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot' cx='180' cy='100' r='10' "
        . "fill='rgba(0,105,255,0.85)' stroke='white' stroke-width='0.25' "
        . "transform='rotate({$widget['data']['angle']},100,100)' />";

    // Add label
    $widget['content'] .= "<text x='100' y='70' width='100' height='12' font-size='{$label_font_size}' fill='#bbb'>"
        . "<tspan text-anchor='middle'>"
        . (isset($panel['settings']['name']) ? $widget['settings']['name'] : '')
        . "</tspan></text>"
        // Add center text
        . "<text x='100' y='105' width='100' height='100' font-size='{$pressure_font_size}' fill='white'>"
            . "<tspan id='widget-{$widget['id']}-pid' dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>"
            . (isset($widget['data']['pid']) ? $widget['data']['pid'] : '?')
            . "</tspan></text>"
        // Add units text
        . "<text x='100' y='135' width='100' height='12' font-size='{$tick_font_size}' fill='#888'>"
            . "<tspan text-anchor='middle'>"
            . (isset($widget['settings']['units']) ? $widget['settings']['units'] : '')
            . "</tspan></text>"
        . "</svg>";

    //
    // Prepare update JS
    //
    $widget['js'] = array(
        'update_args' => "function() {};",
        'update' => "function(data) {"
            . "if( data.pid != null ) {"
                . "db_setInnerHtml(this,'pid', data.pid);"
                . "if( data.angle != null && data.angle != '?' ) {"
                    . "var dot = db_ge(this,'dot');"
                    . "dot.setAttributeNS(null,'transform', 'rotate('+data.angle+',100,100)');"
                    . "var line = db_ge(this,'line');"
                    . "if( line != null ) { line.setAttributeNS(null,'transform', 'rotate('+data.angle+',100,100)'); }"
                    . "var stub = db_ge(this,'stub');"
                    . "if( stub != null ) { stub.setAttributeNS(null,'transform', 'rotate('+data.angle+',100,100)'); }"
                . "}"
                . "if( data.angle1 != null && data.angle1 != '?' ) {"
                    . "var dot1 = db_ge(this,'dot1');"
                    . "dot1.setAttributeNS(null,'transform', 'rotate('+data.angle1+',100,100)');"
                . "}"
                . "if( data.angle2 != null && data.angle2 != '?' ) {"
                    . "var dot2 = db_ge(this,'dot2');"
                    . "dot2.setAttributeNS(null,'transform', 'rotate('+data.angle2+',100,100)');"
                . "}"
                . "if( data.angle3 != null && data.angle3 != '?' ) {"
                    . "var dot3 = db_ge(this,'dot3');"
                    . "dot3.setAttributeNS(null,'transform', 'rotate('+data.angle3+',100,100)');"
                . "}"
                . "if( data.angle4 != null && data.angle4 != '?' ) {"
                    . "var dot4 = db_ge(this,'dot4');"
                    . "dot4.setAttributeNS(null,'transform', 'rotate('+data.angle4+',100,100)');"
                . "}"
                . "if( data.angle5 != null && data.angle5 != '?' ) {"
                    . "var dot5 = db_ge(this,'dot5');"
                    . "dot5.setAttributeNS(null,'transform', 'rotate('+data.angle5+',100,100)');"
                . "}"
            . "}};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'widget'=>$widget);
}
?>
