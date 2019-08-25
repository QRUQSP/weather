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
function qruqsp_weather_widgets_temp2(&$ciniki, $tnid, $args) {

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
    $age_dt->sub(new DateInterval('PT6M'));

    $sensor_ids = array();
    if( isset($widget['settings']['tid']) ) {
        $sensor_ids[] = $widget['settings']['tid'];
    }
    if( isset($widget['settings']['hid']) ) {
        $sensor_ids[] = $widget['settings']['hid'];
    }

    if( count($sensor_ids) > 0 ) {
        $strsql = "SELECT sensors.id, "
            . "sensors.name, "
            . "sensors.flags, "
            . "sensors.fields, "
            . "IFNULL(data.celsius, '') AS temperature, "
            . "IFNULL(data.humidity, '') AS humidity "
            . "FROM qruqsp_weather_sensors AS sensors "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
                . "sensors.id = data.sensor_id "
                . "AND sensors.last_sample_date = data.sample_date "
                . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $sensor_ids) . ") "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND sensors.last_sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt->format('Y-m-d H:i:s')) . "' "
            . "ORDER BY sensors.id ";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'flags', 'fields', 'temperature', 'humidity'),
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

    if( isset($sensors[$widget['settings']['tid']]['temperature']) ) {
        if( isset($widget['settings']['units']) && $widget['settings']['units'] == 'fahrenheit' ) {
            $widget['data']['tid'] = round((($sensors[$widget['settings']['tid']]['temperature'] * 9) / 5) + 32);
        } else {
            $widget['data']['tid'] = round($sensors[$widget['settings']['tid']]['temperature']);
        }
    } else {
        $widget['data']['tid'] = '?';
    }
    $widget['data']['hid'] = (isset($sensors[$widget['settings']['hid']]['humidity']) ? round($sensors[$widget['settings']['hid']]['humidity']) : '?');

    if( isset($args['action']) && $args['action'] == 'update' ) {
        return array('stat'=>'ok', 'widget'=>$widget);
    }

    //
    // Setup the HTML for the dial
    //
    if( isset($widget['data']['hid']) && $widget['data']['hid'] > 0 ) {
        $angle = ((($widget['data']['hid']/100) * 360) - 90) * 0.0174532925;
        $cx = 100 + (80 * cos($angle));
        $cy = 100 + (80 * sin($angle));
    } else {
        $angle = (-90 * 0.0174532925);
        $cx = 100 + (80 * cos($angle));
        $cy = 100 + (80 * sin($angle));
    }
    $widget['content'] .= '<svg viewBox="0 0 200 200">'
        // Add tick marks
        . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35 100 100)'/>"
        . "<circle cx='100' cy='100' r='80' fill='none' stroke='#fff' stroke-width='12' stroke-dasharray='1,124.664' transform='rotate(-0.35 100 100)' />"
        // Add label text
        . "<text x='100' y='60' width='100' height='12' font-size='12' fill='#bbb'><tspan text-anchor='middle'>"
            . (isset($widget['settings']['name']) ? $widget['settings']['name'] : '')
            . "</tspan></text>"
        // Add temperature
        . "<text x='100' y='108' width='100' height='100' font-size='80' fill='white'><tspan id='widget-{$widget['id']}-tid' alignment-baseline='middle' text-anchor='middle'>"
            . (isset($widget['data']['tid']) ? $widget['data']['tid'] : '?')
            . "</tspan></text>";
    //
    // Check if there is a humidity sensor setup
    //
    if( isset($widget['settings']['hid']) && $widget['settings']['hid'] != 0 ) {
        // Add tick labels
        $widget['content'] .= "<text x='160' y='101' width='10' height='10' font-size='10' fill='#888'>"
                . "<tspan alignment-baseline='middle' text-anchor='middle'>25%</tspan></text>"
            . "<text x='100' y='167' width='10' height='10' font-size='10' fill='#888'>"
                . "<tspan alignment-baseline='middle' text-anchor='middle'>50%</tspan></text>"
            . "<text x='40' y='101' width='10' height='10' font-size='10' fill='#888'>"
                . "<tspan alignment-baseline='middle' text-anchor='middle'>75%</tspan></text>"
            . "<text x='100' y='35' width='10' height='10' font-size='10' fill='#888'>"
                . "<tspan alignment-baseline='middle' text-anchor='middle'>100%</tspan></text>";
        // Add humidity circle & value
        $widget['content'] .= "<circle id='widget-{$widget['id']}-hc' cx='{$cx}' cy='{$cy}' r='12' fill='rgba(255,200,0,0.75)' stroke='#fff' stroke-width='0.5'/>";
        $widget['content'] .= "<text x='{$cx}' y='" . ($cy+1) . "' width='20' height='20' font-size='15' fill='black'>"
            . "<tspan id='widget-{$widget['id']}-hid' alignment-baseline='middle' text-anchor='middle'>"
                . (isset($widget['data']['hid']) ? $widget['data']['hid'] : '?')
                . "</tspan>"
            . "</text>";
    }
    // Add units text
    $widget['content'] .= "<text x='100' y='145' width='100' height='12' font-size='10' fill='#888'><tspan text-anchor='middle'>"
            . (isset($widget['settings']['units']) ? strtoupper($widget['settings']['units'][0]) : '')
            . "</tspan></text>"
        . "</svg>";

    $widget['js'] = array(
        'update_args' => "function() {};",
        'update' => "function(data) {"
            . "if( data.tid != null ) {"
                . "db_setInnerHtml(this,'tid', data.tid);"
            . "}"
            . "if( data.hid != null ) {"
                . "var txt = db_ge(this,'hid');"
                . "var circle = db_ge(this,'hc');"
                . "txt.textContent = data.hid;"
                . "if(data.hid!='?'){"
                    . "var a = (((data.hid/100)*360)-90)*0.0174532925;"
                    . "circle.setAttributeNS(null,'cx',100 + (80 * Math.cos(a)));"
                    . "circle.setAttributeNS(null,'cy',100 + (80 * Math.sin(a)));"
                    . "txt.parentNode.setAttributeNS(null,'x',100 + (80 * Math.cos(a)));"
                    . "txt.parentNode.setAttributeNS(null,'y',101 + (80 * Math.sin(a)));"
                . "}}"
            . "};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'widget'=>$widget);
}
?>
