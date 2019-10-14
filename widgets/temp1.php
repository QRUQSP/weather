<?php
//
// Description
// -----------
// This widget displays the temperature orbit dial
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_widgets_temp1(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    if( !isset($args['widget']['widget_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.70', 'msg'=>'No dashboard widget specified'));
    }

    if( !isset($args['widget']['content']) ) {
        $args['widget']['content'] = '';
    }

    if( !isset($args['widget']['settings']) ) {
        $args['widget']['settings'] = array();
    }

    $widget = $args['widget'];

    $label_font_size = 12;
    $tick_font_size = 10;
    $temperature_font_size = 80;
    $humidity_font_size = 14;
    if( isset($_SERVER['HTTP_USER_AGENT']) && stristr($_SERVER['HTTP_USER_AGENT'], ' Gecko/20') !== false ) {
        $label_font_size = 11;
        $tick_font_size = 9;
        $temperature_font_size = 72;
        $humidity_font_size = 12;
    }

    //
    // Make sure the sample date is within the last 5 minutes
    //
    $age_dt = new DateTime('now', new DateTimezone('UTC'));
    $age_dt->sub(new DateInterval('PT6M'));

    $sensor_ids = array();
    if( isset($widget['settings']['tid']) ) {
        $sensor_ids[] = $widget['settings']['tid'];
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
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.36', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
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

    if( isset($args['action']) && $args['action'] == 'update' ) {
        return array('stat'=>'ok', 'widget'=>$widget);
    }

    //
    // Setup the HTML for the dial
    //
    $widget['content'] .= '<svg viewBox="0 0 200 200">'
        // Add tick marks
        . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35 100 100)'/>"
        // Add label text
        . "<text x='100' y='60' width='100' height='12' font-size='{$label_font_size}' fill='#bbb'><tspan text-anchor='middle'>"
            . (isset($widget['settings']['name']) ? $widget['settings']['name'] : '')
            . "</tspan></text>"
        // Add temperature
        . "<text x='100' y='108' width='100' height='100' font-size='{$temperature_font_size}' fill='white'><tspan id='widget-{$widget['id']}-tid' dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>"
            . (isset($widget['data']['tid']) ? $widget['data']['tid'] : '?')
            . "</tspan></text>";
    // Add units text
    $widget['content'] .= "<text x='100' y='145' width='100' height='12' font-size='{$tick_font_size}' fill='#888'><tspan text-anchor='middle'>"
            . (isset($widget['settings']['units']) ? strtoupper($widget['settings']['units'][0]) : '')
            . "</tspan></text>"
        . "</svg>";

    //
    // Prepare update JS
    //
    $widget['js'] = array(
        'update_args' => "function() {};",
        'update' => "function(data) {"
            . "if( data.tid != null ) {"
                . "db_setInnerHtml(this,'tid', data.tid);"
                . "};"
            . "};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'widget'=>$widget);
}
?>
