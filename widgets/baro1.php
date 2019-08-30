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
function qruqsp_weather_widgets_baro1(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    if( !isset($args['widget']['widget_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.54', 'msg'=>'No dashboard widget specified'));
    }

    if( !isset($args['widget']['content']) ) {
        $args['widget']['content'] = '';
    }

    if( !isset($args['widget']['settings']) ) {
        $args['widget']['settings'] = array();
    }

    $widget = $args['widget'];

    //
    // Make sure the sample date is within the last 5 minutes
    //
    $age_dt = new DateTime('now', new DateTimezone('UTC'));
    $age_dt_1a = clone $age_dt;
    $age_dt_1a->sub(new DateInterval('PT1H'));
    $age_dt_1b = clone $age_dt_1a;
    $age_dt_1b->sub(new DateInterval('PT6M'));

    $age_dt_6a = clone $age_dt;
    $age_dt_6a->sub(new DateInterval('PT6H'));
    $age_dt_6b = clone $age_dt_6a;
    $age_dt_6b->sub(new DateInterval('PT6M'));

    $age_dt_12a = clone $age_dt;
    $age_dt_12a->sub(new DateInterval('PT12H'));
    $age_dt_12b = clone $age_dt_12a;
    $age_dt_12b->sub(new DateInterval('PT6M'));
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
            . "IFNULL(data6.millibars, '') AS millibars6, "
            . "IFNULL(data12.millibars, '') AS millibars12 "
            . "FROM qruqsp_weather_sensors AS sensors "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
                . "sensors.id = data.sensor_id "
                . "AND sensors.last_sample_date = data.sample_date "
                . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data1 ON ("
                . "sensors.id = data1.sensor_id "
                . "AND data1.sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt_1b->format('Y-m-d H:i:s')) . "' "
                . "AND data1.sample_date < '" . ciniki_core_dbQuote($ciniki, $age_dt_1a->format('Y-m-d H:i:s')) . "' "
                . "AND data1.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data6 ON ("
                . "sensors.id = data6.sensor_id "
                . "AND data6.sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt_6b->format('Y-m-d H:i:s')) . "' "
                . "AND data6.sample_date < '" . ciniki_core_dbQuote($ciniki, $age_dt_6a->format('Y-m-d H:i:s')) . "' "
                . "AND data6.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data12 ON ("
                . "sensors.id = data12.sensor_id "
                . "AND data12.sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt_12b->format('Y-m-d H:i:s')) . "' "
                . "AND data12.sample_date < '" . ciniki_core_dbQuote($ciniki, $age_dt_12a->format('Y-m-d H:i:s')) . "' "
                . "AND data12.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $sensor_ids) . ") "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND sensors.last_sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt->format('Y-m-d H:i:s')) . "' "
            . "ORDER BY sensors.id "
            . "LIMIT 1 ";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'flags', 'fields', 'millibars', 'millibars1', 'millibars6', 'millibars12'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.23', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
        }
        $sensors = isset($rc['sensors']) ? $rc['sensors'] : array();
    } else {
        $sensors = array();
    }

    //
    // Check if this is a data update
    //
    $widget['data'] = array();

    if( isset($widget['settings']['pid']) && isset($sensors[$widget['settings']['pid']]['millibars']) ) {
        if( isset($widget['settings']['units']) && $widget['settings']['units'] == 'mmhg' ) {
            $widget['data']['angle'] = ((($sensors[$widget['settings']['pid']]['millibars'] * 0.75006) - 720) * 3.75) + 90 + 28.75;
            $widget['data']['pid'] = round($sensors[$widget['settings']['pid']]['millibars'] * 0.75006);
            $widget['data']['angle1'] = ((($sensors[$widget['settings']['pid']]['millibars1'] * 0.75006) - 720) * 3.75) + 90 + 28.75;
            $widget['data']['pid1'] = round($sensors[$widget['settings']['pid']]['millibars1'] * 0.75006);
            $widget['data']['angle6'] = ((($sensors[$widget['settings']['pid']]['millibars6'] * 0.75006) - 720) * 3.75) + 90 + 28.75;
            $widget['data']['pid6'] = round($sensors[$widget['settings']['pid']]['millibars6'] * 0.75006);
            $widget['data']['angle12'] = ((($sensors[$widget['settings']['pid']]['millibars12'] * 0.75006) - 720) * 3.75) + 90 + 28.75;
            $widget['data']['pid12'] = round($sensors[$widget['settings']['pid']]['millibars12'] * 0.75006);
        } else {
            $widget['data']['angle'] = (($sensors[$widget['settings']['pid']]['millibars'] - 960) * 2.75) + 90 + 28.75;
            $widget['data']['pid'] = round($sensors[$widget['settings']['pid']]['millibars']);
            $widget['data']['angle1'] = (($sensors[$widget['settings']['pid']]['millibars1'] - 960) * 2.75) + 90 + 28.75;
            $widget['data']['pid1'] = round($sensors[$widget['settings']['pid']]['millibars1']);
            $widget['data']['angle6'] = (($sensors[$widget['settings']['pid']]['millibars6'] - 960) * 2.75) + 90 + 28.75;
            $widget['data']['pid6'] = round($sensors[$widget['settings']['pid']]['millibars6']);
            $widget['data']['angle12'] = (($sensors[$widget['settings']['pid']]['millibars12'] - 960) * 2.75) + 90 + 28.75;
            $widget['data']['pid12'] = round($sensors[$widget['settings']['pid']]['millibars12']);
        }
    } else {
        $widget['data']['pid'] = '?';
        $widget['data']['angle'] = 0;
        $widget['data']['pid1'] = '?';
        $widget['data']['angle1'] = 0;
        $widget['data']['pid6'] = '?';
        $widget['data']['angle6'] = 0;
        $widget['data']['pid12'] = '?';
        $widget['data']['angle12'] = 0;
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
    if( isset($widget['settings']['units']) && $widget['settings']['units'] == 'mmhg' ) {
        $start_tick = 720;
        $end_tick = 800;
        $multiplier = 3.75;
    } else {
        $start_tick = 960;
        $end_tick = 1070;
        $multiplier = 2.75;
    }
    for($y = $start_tick; $y <= $end_tick; $y++) {
        $tick_angle = (($y - $start_tick) * $multiplier) + 90 + 28.75;
        if( ($y%10) == 0 ) {
            $x1 = 100 + (74 * cos($tick_angle * 0.0174532925));
            $y1 = 100 + (74 * sin($tick_angle * 0.0174532925));
            $x2 = 100 + (86 * cos($tick_angle * 0.0174532925));
            $y2 = 100 + (86 * sin($tick_angle * 0.0174532925));
            $widget['content'] .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#fff' stroke-width='1' />";
        } elseif( ($y%2) == 0 ) {
            $x1 = 100 + (78 * cos($tick_angle * 0.0174532925));
            $y1 = 100 + (78 * sin($tick_angle * 0.0174532925));
            $x2 = 100 + (82 * cos($tick_angle * 0.0174532925));
            $y2 = 100 + (82 * sin($tick_angle * 0.0174532925));
            $widget['content'] .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#aaa' stroke-width='1' />";
        }
        if( ($y%20) == 0 ) {
            $x1 = 100 + (62 * cos($tick_angle * 0.0174532925));
            $y1 = 100 + (62 * sin($tick_angle * 0.0174532925));
            if( $y == 1040 ) {
                $x1 -= 3;
                $y1 -= 5;
            }
            $widget['content'] .= "<text x='{$x1}' y='{$y1}' width='10' height='10' font-size='10' fill='#888'><tspan alignment-baseline='middle' text-anchor='middle'>{$y}</tspan></text>";
        }
    }
    // Add history dots
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot1' cx='180' cy='100' r='10' "
        . "fill='rgba(255,255,255,0.35)' stroke='grey' stroke-width='0.5' "
        . "transform='rotate({$widget['data']['angle1']},100,100)' />";
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot6' cx='180' cy='100' r='7' "
        . "fill='rgba(255,255,255,0.35)' stroke='grey' stroke-width='0.5' "
        . "transform='rotate({$widget['data']['angle6']},100,100)' />";
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot12' cx='180' cy='100' r='3' "
        . "fill='rgba(255,255,255,0.35)' stroke='grey' stroke-width='0.5' "
        . "transform='rotate({$widget['data']['angle12']},100,100)' />";
    // Add barometer dot
    $widget['content'] .= "<circle id='widget-{$widget['id']}-dot' cx='180' cy='100' r='12' "
        . "fill='rgba(0,105,255,0.65)' stroke='white' stroke-width='0.5' "
        . "transform='rotate({$widget['data']['angle']},100,100)' />";


    // Add label
    $widget['content'] .= "<text x='100' y='70' width='100' height='12' font-size='12' fill='#bbb'><tspan text-anchor='middle'>"
        . (isset($panel['settings']['name']) ? $widget['settings']['name'] : '')
        . "</tspan></text>"
        // Add center text
        . "<text x='100' y='105' width='100' height='100' font-size='50' fill='white'><tspan id='widget-{$widget['id']}-pid' alignment-baseline='middle' text-anchor='middle'>"
            . (isset($widget['data']['pid']) ? $widget['data']['pid'] : '?')
            . "</tspan></text>"
        // Add units text
        . "<text x='100' y='135' width='100' height='12' font-size='10' fill='#888'><tspan text-anchor='middle'>"
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
                . "}"
                . "if( data.angle1 != null && data.angle1 != '?' ) {"
                    . "var dot1 = db_ge(this,'dot1');"
                    . "dot1.setAttributeNS(null,'transform', 'rotate('+data.angle1+',100,100)');"
                . "}"
                . "if( data.angle6 != null && data.angle6 != '?' ) {"
                    . "var dot6 = db_ge(this,'dot6');"
                    . "dot6.setAttributeNS(null,'transform', 'rotate('+data.angle6+',100,100)');"
                . "}"
                . "if( data.angle12 != null && data.angle12 != '?' ) {"
                    . "var dot12 = db_ge(this,'dot12');"
                    . "dot12.setAttributeNS(null,'transform', 'rotate('+data.angle12+',100,100)');"
                . "}"
            . "}};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'widget'=>$widget);
}
?>
