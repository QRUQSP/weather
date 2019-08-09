<?php
//
// Description
// -----------
// This hook returns content for a panel to be added to the dashboard.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_panels_orbitDials(&$ciniki, $tnid, $args, $num_dials) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    if( !isset($args['panel']['panel_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.54', 'msg'=>'No dashboard panel specified'));
    }

    if( !isset($args['panel']['content']) ) {
        $args['panel']['content'] = '';
    }

    if( !isset($args['panel']['settings']) ) {
        $args['panel']['content'] = '';
    }

    $panel = $args['panel'];

    //
    // Get the current temp/humidity for the sensors
    //
    $sensor_ids = array();
    foreach($panel['settings'] as $k => $v) {
        for($i = 1; $i <= $num_dials; $i++ ) {
            if( in_array($k, ["o{$i}t", "o{$i}h", "o{$i}ws", "o{$i}wd"]) && !in_array($v, $sensor_ids) ) {
                $sensor_ids[] = $v;
            }
        }
    }
    if( count($sensor_ids) > 0 ) {
        $strsql = "SELECT sensors.id, "
            . "sensors.name, "
            . "sensors.flags, "
            . "sensors.fields, "
/*            . "IFNULL(data.sample_date, '') AS sample_date, "
            . "IFNULL(data.sample_date, '') AS sample_time, "
            . "IFNULL(data.sample_date, '') AS sample_dt, " */
            . "IFNULL(data.celsius, '') AS temperature, "
            . "IFNULL(data.humidity, '') AS humidity, "
            . "IFNULL(data.millibars, '') AS millibars, "
            . "IFNULL(data.wind_kph, '') AS windspeed, "
            . "IFNULL(data.wind_deg, '') AS wind_deg, "
            . "IFNULL(data.rain_mm, '') AS rain_mm "
            . "FROM qruqsp_weather_sensors AS sensors "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
                . "sensors.id = data.sensor_id "
                . "AND sensors.last_sample_date = data.sample_date "
                . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $sensor_ids) . ") "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY sensors.id ";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'flags', 'fields', // 'sample_date', 'sample_time', 'sample_dt',
                    'temperature', 'humidity', 'millibars', 'windspeed', 'wind_deg', 'rain_mm'),
/*                'utctotz'=>array(
                    'sample_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
                    'sample_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
                    'sample_dt'=>array('timezone'=>$intl_timezone, 'format'=>$datetime_format),
                    ), */
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.23', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
        }
        $sensors = isset($rc['sensors']) ? $rc['sensors'] : array();
    }

    //
    // Check if this is a data update
    //
    $panel['data'] = array();
    for($i = 1; $i <= $num_dials; $i++ ) {
        if( isset($sensors[$panel['settings']["o{$i}t"]]['temperature']) ) {
            if( isset($panel['settings']["o{$i}tu"]) && $panel['settings']["o{$i}tu"] == 'fahrenheit' ) {
                $panel['data']["o{$i}t"] = round((($sensors[$panel['settings']["o{$i}t"]]['temperature'] * 9) / 5) + 32);
            } else {
                $panel['data']["o{$i}t"] = round($sensors[$panel['settings']["o{$i}t"]]['temperature']);
            }
        } else {
            $panel['data']["o{$i}t"] = '?';
        }
        $panel['data']["o{$i}h"] = (isset($sensors[$panel['settings']["o{$i}h"]]['humidity']) ? round($sensors[$panel['settings']["o{$i}h"]]['humidity']) : '?');
        if( isset($sensors[$panel['settings']["o{$i}ws"]]['windspeed']) ) {
            if( isset($panel['settings']["o{$i}wu"]) && $panel['settings']["o{$i}wu"] == 'mph' ) {
                $panel['data']["o{$i}ws"] = round($sensors[$panel['settings']["o{$i}ws"]]['windspeed'] * 0.62137);
            } else {
                $panel['data']["o{$i}ws"] = round($sensors[$panel['settings']["o{$i}ws"]]['windspeed']);
            }
        } else {
            $panel['data']["o{$i}ws"] = '?';
        }
        $panel['data']["o{$i}wd"] = (isset($sensors[$panel['settings']["o{$i}wd"]]['wind_deg']) ? round($sensors[$panel['settings']["o{$i}wd"]]['wind_deg']) : '?');

    }
    if( isset($args['action']) && $args['action'] == 'update' ) {
        return array('stat'=>'ok', 'panel'=>$panel);
    }

    //
    // Setup the html content
    //
    for($i = 1; $i <= $num_dials; $i++) {
        $panel['content'] .= "<div class='orbit o{$i}'>";
        $cx = 100;
        $cy = 20;
        if( $panel['settings']["o{$i}type"] == 'wind1' ) {
            if( isset($panel['data']["o{$i}wd"]) && $panel['data']["o{$i}wd"] > 0 ) {
                $angle = ($panel['data']["o{$i}wd"] - 90) * 0.0174532925;
                $x1 = 100 + (60 * cos($angle));
                $y1 = 100 + (60 * sin($angle));
                $x2 = 100 + (95 * cos($angle-0.1));
                $y2 = 100 + (95 * sin($angle-0.1));
                $x3 = 100 + (95 * cos($angle+0.1));
                $y3 = 100 + (95 * sin($angle+0.1));
            }
            $panel['content'] .= '<svg viewBox="0 0 200 200">'
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35)'/>"
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#fff' stroke-width='12' stroke-dasharray='1,61.832' transform='rotate(-0.35)' />"
                . "<text x='100' y='55' width='100' height='12' font-size='12' fill='#ddd'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}name"]) ? $panel['settings']["o{$i}name"] : '')
                    . "</tspan></text>"
                . "<text x='100' y='126' width='100' height='100' font-size='80' fill='white'><tspan id='panel-{$panel['id']}-o{$i}ws' text-anchor='middle'>"
                    . (isset($panel['data']["o{$i}ws"]) ? $panel['data']["o{$i}ws"] : '?')
                    . "</tspan></text>"
                . "<path id='panel-{$panel['id']}-o{$i}wd' d='M{$x1} {$y1} L{$x2} {$y2} L{$x3} {$y3} Z' fill='#09ff00' stroke='white' stroke-width='1'/>"
                . "</svg>"
                . "</div>";

        } else {
            if( isset($panel['data']["o{$i}h"]) && $panel['data']["o{$i}h"] > 0 ) {
                $angle = ((($panel['data']["o{$i}h"]/100) * 360) - 90) * 0.0174532925;
                $cx = 100 + (80 * cos($angle));
                $cy = 100 + (80 * sin($angle));
            }
            $panel['content'] .= '<svg viewBox="0 0 200 200">'
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35)'/>"
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#fff' stroke-width='12' stroke-dasharray='1,124.664' transform='rotate(-0.35)' />"
                . "<text x='100' y='55' width='100' height='12' font-size='12' fill='#ddd'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}name"]) ? $panel['settings']["o{$i}name"] : '')
                    . "</tspan></text>"
                . "<text x='100' y='126' width='100' height='100' font-size='80' fill='white'><tspan id='panel-{$panel['id']}-o{$i}t' text-anchor='middle'>"
                    . (isset($panel['data']["o{$i}t"]) ? $panel['data']["o{$i}t"] : '?')
                    . "</tspan></text>"
                . "<circle id='panel-{$panel['id']}-o{$i}hc' cx='{$cx}' cy='{$cy}' r='16' fill='yellow' stroke='white' stroke-width='1'/>"
                . "<text x='{$cx}' y='" . ($cy+6) . "' width='20' height='20' font-size='20' fill='black'>"
                    . "<tspan id='panel-{$panel['id']}-o{$i}h' text-anchor='middle'>"
                        . (isset($panel['data']["o{$i}h"]) ? $panel['data']["o{$i}h"] : '?')
                        . "</tspan>"
                    . "</text>"
                . "</svg>"
                . "</div>";
        }
    }

    $width = 100;
    $height = 100;
    $ipad_height = 745;
    if( $num_dials == 2 ) {
        $width = 50;
        $height = 100;
    } elseif( $num_dials == 4 ) {
        $width = 50;
        $height = 50;
        $ipad_height = 365;
    } elseif( $num_dials == 6 ) {
        $width = 33;
        $height = 50;
        $ipad_height = 365;
    } elseif( $num_dials == 12 ) {
        $width = 25;
        $height = 33;
        $ipad_height = 240;
    }
    $panel['css'] = ""
        . "#panel-{$panel['id']} div.orbit {"
            . "display: inline-block; "
            . "width: {$width}%; "
            . "width: {$width}vw; "
            . "height: {$height}%; "
            . "height: {$height}vh; "
            . "vertical-align: middle; "
            . "line-height: 1em; "
            . "line-height: {$height}%; "
            . "line-height: {$height}vh; "
            . "}"
        . "#panel-{$panel['id']} svg {"
            . "width: 100%; "
            . "max-width: 100%; "
            . "max-height: {$ipad_height}px; "     // Required for vertical center on ipad 1
            . "max-height: 100%; "
            . "max-height: {$height}vh; "
            . "vertical-align: middle; "
            . "}"
        . "";
    if( $ciniki['remote_device'] == 'ipad1' ) {
        $panel['css'] .= ""
            . "#panel-{$panel['id']} svg {"
                . "max-height: {$ipad_height}px; "
            . "} "
            . "";
    }

    //
    // Prepare update JS
    //
    $update_js = '';
    for($i = 1; $i <= $num_dials; $i++) {
        if( $panel['settings']["o{$i}type"] == 'temphum1' ) {
            $update_js .= "if( data.o{$i}t != null ) {"
                . "db_setInnerHtml(this,'o{$i}t', data.o{$i}t);"
                . "};";
            $update_js .= "if( data.o{$i}h != null ) {"
                . "this.setHOrbit('o{$i}h', data.o{$i}h);"
                . "};";
        } elseif( $panel['settings']["o{$i}type"] == 'wind1' ) {
            $update_js .= "if( data.o{$i}ws != null ) {"
                . "db_setInnerHtml(this,'o{$i}ws', data.o{$i}ws);"
                . "};";
            $update_js .= "if( data.o{$i}wd != null ) {"
                . "this.setWOrbit('o{$i}wd', data.o{$i}wd);"
                . "};";
        }
    }
    
    $panel['js'] = array(
        'setHOrbit' => "function(i,v) {"
            . "var txt = db_ge(this,i);"
            . "var circle = db_ge(this,i + 'c');"
            . "var a = (((v/100)*360)-90)*0.0174532925;"
            . "circle.setAttributeNS(null,'cx',100 + (80 * Math.cos(a)));"
            . "circle.setAttributeNS(null,'cy',100 + (80 * Math.sin(a)));"
            . "txt.parentNode.setAttributeNS(null,'x',100 + (80 * Math.cos(a)));"
            . "txt.parentNode.setAttributeNS(null,'y',106 + (80 * Math.sin(a)));"
            . "txt.textContent = v;"
            . "};",
        'setWOrbit' => "function(i,v) {"
            . "var txt = db_ge(this,i);"
            . "var arrow = db_ge(this,i);"
            . "var a = (v-90)*0.0174532925;"
            . "arrow.setAttributeNS(null,'d', "
                . "'M' + (100 + (60 * Math.cos(a))) + ' ' + (100 + (60 * Math.sin(a)))"
                . " + ' L' + (100 + (95 * Math.cos(a-0.1))) + ' ' + (100 + (95 * Math.sin(a-0.1)))"
                . " + ' L' + (100 + (95 * Math.cos(a+0.1))) + ' ' + (100 + (95 * Math.sin(a+0.1)))"
                . " + ' Z');"
            . "};",
        'update_args' => "function() {};",
        'update' => "function(data) {"
            . $update_js
            . "};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'panel'=>$panel);
}
?>

