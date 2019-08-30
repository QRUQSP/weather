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
function qruqsp_weather_widgets_temp3(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    if( !isset($args['widget']['widget_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.71', 'msg'=>'No dashboard widget specified'));
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
                . ") ";
        $strsql .= "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $sensor_ids) . ") "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND sensors.last_sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt->format('Y-m-d H:i:s')) . "' "
            . "ORDER BY sensors.id "
            . "LIMIT 1 ";
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

        //
        // Get humidity history
        //
        if( isset($widget['settings']['hid']) && $widget['settings']['hid'] != '' ) {
            for($i = 1; $i < 6; $i++) {
                $strsql = "SELECT humidity "
                    . "FROM qruqsp_weather_sensor_data AS data "
                    . "WHERE sensor_id = '" . ciniki_core_dbQuote($ciniki, $widget['settings']['hid']) . "' "
                    . "AND sample_date > '" . ciniki_core_dbQuote($ciniki, $start_ages[$i]->format('Y-m-d H:i:s')) . "' "
                    . "AND sample_date < '" . ciniki_core_dbQuote($ciniki, $end_ages[$i]->format('Y-m-d H:i:s')) . "' "
                    . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . "ORDER BY sample_date "
                    . "LIMIT 1 "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'item');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.75', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
                }
                if( isset($rc['item']['humidity']) ) {
                    $sensors[$widget['settings']['hid']]['humidity' . $i] = $rc['item']['humidity'];
                }
            }
        }
                
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
    if( isset($widget['settings']['hid']) && isset($sensors[$widget['settings']['hid']]['humidity']) ) {
        $widget['data']['hid'] = (isset($sensors[$widget['settings']['hid']]['humidity']) ? round($sensors[$widget['settings']['hid']]['humidity']) : '?');
        $widget['data']['angle'] = ((round($sensors[$widget['settings']['hid']]['humidity'])/100)*360) - 90;
        for($i = 1; $i < 6; $i++) {
            if( isset($sensors[$widget['settings']['hid']]['humidity' . $i]) ) {
                $widget['data']['angle' . $i] = (($sensors[$widget['settings']['hid']]['humidity' . $i]/100)*360) - 90;
                $widget['data']['angle' . $i] = (($sensors[$widget['settings']['hid']]['humidity' . $i]/100)*360) - 90;
            } else {
                $widget['data']['angle' . $i] = -90;
            }
        }
    } else {
        $widget['data']['hid'] = '?';
        $widget['data']['angle'] = -90;
        for($i = 1; $i < 6; $i++) {
            $widget['data']['angle' . $i] = -90;
        }
    }

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
        // Add humidity history
        $widget['content'] .= "<circle id='widget-{$widget['id']}-dot5' cx='150' cy='100' r='1' "
            . "fill='rgba(255,200,0,0.75)' stroke='white' stroke-width='0.25' "
            . "transform='rotate({$widget['data']['angle5']},100,100)' />";
        $widget['content'] .= "<circle id='widget-{$widget['id']}-dot4' cx='152.5' cy='100' r='2' "
            . "fill='rgba(255,200,0,0.75)' stroke='white' stroke-width='0.25' "
            . "transform='rotate({$widget['data']['angle4']},100,100)' />";
        $widget['content'] .= "<circle id='widget-{$widget['id']}-dot3' cx='157' cy='100' r='3.5' "
            . "fill='rgba(255,200,0,0.75)' stroke='white' stroke-width='0.25' "
            . "transform='rotate({$widget['data']['angle3']},100,100)' />";
        $widget['content'] .= "<circle id='widget-{$widget['id']}-dot2' cx='163' cy='100' r='5' "
            . "fill='rgba(255,200,0,0.75)' stroke='white' stroke-width='0.25' "
            . "transform='rotate({$widget['data']['angle2']},100,100)' />";
        $widget['content'] .= "<circle id='widget-{$widget['id']}-dot1' cx='170' cy='100' r='6.5' "
            . "fill='rgba(255,200,0,0.75)' stroke='white' stroke-width='0.25' "
            . "transform='rotate({$widget['data']['angle1']},100,100)' />"; 
        // Add humidity circle & value
        // Add the needle line
        if( isset($widget['settings']['line']) && $widget['settings']['line'] == 'yes' ) {
            $widget['content'] .= "<line id='widget-{$widget['id']}-line' x1='100' y1='100' x2='170' y2='100' "
                . "fill='rgba(0,105,255,0.85)' stroke='#bbb' stroke-width='0.5' "
                . "transform='rotate({$widget['data']['angle']},100,100)' />";
            $widget['content'] .= "<line id='widget-{$widget['id']}-stub' x1='190' y1='100' x2='193' y2='100' "
                . "fill='rgba(0,105,255,0.85)' stroke='#bbb' stroke-width='0.5' "
                . "transform='rotate({$widget['data']['angle']},100,100)' />";
        }
        $widget['content'] .= "<circle id='widget-{$widget['id']}-hc' cx='{$cx}' cy='{$cy}' r='10' fill='rgba(255,200,0,0.75)' stroke='#fff' stroke-width='0.5'/>";
        $widget['content'] .= "<text x='{$cx}' y='" . ($cy+1) . "' width='20' height='20' font-size='14' fill='black'>"
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
                . "var circle = db_ge(this,'hc');\n"
                . "txt.textContent = data.hid;\n"
                . "if(data.hid!='?'){"
                    . "var a = (((data.hid/100)*360)-90)*0.0174532925;"
                    . "circle.setAttributeNS(null,'cx',100 + (80 * Math.cos(a)));"
                    . "circle.setAttributeNS(null,'cy',100 + (80 * Math.sin(a)));"
                    . "txt.parentNode.setAttributeNS(null,'x',100 + (80 * Math.cos(a)));"
                    . "txt.parentNode.setAttributeNS(null,'y',101 + (80 * Math.sin(a)));"
                    . "var line = db_ge(this,'line');"
                    . "if( line != null ) { line.setAttributeNS(null,'transform', 'rotate('+data.angle+',100,100)'); }"
                    . "var stub = db_ge(this,'stub');"
                    . "if( stub != null ) { stub.setAttributeNS(null,'transform', 'rotate('+data.angle+',100,100)'); }"
                . "}}"
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
            . "};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'widget'=>$widget);
}
?>
