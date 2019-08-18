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
    // Make sure the sample date is within the last 5 minutes
    //
    $age_dt = new DateTime('now', new DateTimezone('UTC'));
    $age_dt->sub(new DateInterval('PT6M'));

    //
    // Get the current temp/humidity for the sensors
    //
    $sensor_ids = array();
    foreach($panel['settings'] as $k => $v) {
        for($i = 1; $i <= $num_dials; $i++ ) {
            if( in_array($k, ["o{$i}t", "o{$i}h", "o{$i}ws", "o{$i}wd", "o{$i}p"]) && !in_array($v, $sensor_ids) ) {
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
            . "AND sensors.last_sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt->format('Y-m-d H:i:s')) . "' "
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
        if( isset($panel['settings']["o{$i}p"]) && isset($sensors[$panel['settings']["o{$i}p"]]['millibars']) ) {
            if( isset($panel['settings']["o{$i}pu"]) && $panel['settings']["o{$i}pu"] == 'mmhg' ) {
                $panel['data']["o{$i}p"] = round($sensors[$panel['settings']["o{$i}p"]]['millibars'] * 0.75006);
                $panel['data']["o{$i}pa"] = (($panel['data']["o{$i}p"] - 720) * 3.75) + 90 + 28.75;
            } else {
                $panel['data']["o{$i}pa"] = (($sensors[$panel['settings']["o{$i}p"]]['millibars'] - 960) * 2.75) + 90 + 28.75;
                $panel['data']["o{$i}p"] = round($sensors[$panel['settings']["o{$i}p"]]['millibars']);
            }
        } else {
            $panel['data']["o{$i}p"] = '?';
            $panel['data']["o{$i}pa"] = 0;
        }
        if( isset($sensors[$panel['settings']["o{$i}ws"]]['windspeed']) ) {
            if( isset($panel['settings']["o{$i}wu"]) && $panel['settings']["o{$i}wu"] == 'mph' ) {
                $panel['data']["o{$i}ws"] = round($sensors[$panel['settings']["o{$i}ws"]]['windspeed'] * 0.62137);
            } else {
                $panel['data']["o{$i}ws"] = round($sensors[$panel['settings']["o{$i}ws"]]['windspeed']);
            }
        } else {
            $panel['data']["o{$i}ws"] = '?';
        }
        $panel['data']["o{$i}wd"] = (isset($sensors[$panel['settings']["o{$i}wd"]]['wind_deg']) ? $sensors[$panel['settings']["o{$i}wd"]]['wind_deg'] : '?');

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
            $angle = 0;
            if( isset($panel['data']["o{$i}wd"]) && $panel['data']["o{$i}wd"] != '' ) {
                $angle = $panel['data']["o{$i}wd"];
            }
            $panel['content'] .= '<svg viewBox="0 0 200 200">'
                // Add tick marks
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35 100 100)'/>"
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#fff' stroke-width='12' stroke-dasharray='1,61.832' transform='rotate(-0.35 100 100)' />"
                // Add tick labels
                . "<text x='165' y='101' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>E</tspan></text>"
                . "<text x='100' y='167' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>S</tspan></text>"
                . "<text x='35' y='101' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>W</tspan></text>"
                . "<text x='100' y='35' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>N</tspan></text>"
                // Add label at top
                . "<text x='100' y='60' width='100' height='12' font-size='12' fill='#bbb'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}name"]) ? $panel['settings']["o{$i}name"] : '')
                    . "</tspan></text>"
                // Add middle text
                . "<text x='100' y='108' width='100' height='100' font-size='80' fill='white'><tspan id='panel-{$panel['id']}-o{$i}ws' alignment-baseline='middle' text-anchor='middle'>"
                    . (isset($panel['data']["o{$i}ws"]) ? $panel['data']["o{$i}ws"] : '?')
                    . "</tspan></text>"
                // Add units text
                . "<text x='100' y='145' width='100' height='12' font-size='10' fill='#888'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}wu"]) ? $panel['settings']["o{$i}wu"] : '')
                    . "</tspan></text>"
                // Add arrow
//                . "<path id='panel-{$panel['id']}-o{$i}wd' d='M100 40 L90 5 L110 5 Z' fill='rgba(9,255,0,0.65)' stroke='white' stroke-width='0.5' transform='rotate($angle 100 100)' />"
                . "<path id='panel-{$panel['id']}-o{$i}wd' d='M100 38 L90 7 L110 7 Z' fill='rgba(9,255,0,0.65)' stroke='white' stroke-width='0.5' transform='rotate($angle 100 100)' />"
                . "</svg>"
                . "</div>";

        } elseif( $panel['settings']["o{$i}type"] == 'baro1' ) {
            $x1 = 100 + (80 * cos(65 * 0.0174532925));
            $y1 = 100 + (80 * sin(65 * 0.0174532925));
            $x2 = 100 + (80 * cos(115 * 0.0174532925));
            $y2 = 100 + (80 * sin(115 * 0.0174532925));
            // Major ticks every 27.5 degrees
            $panel['content'] .= '<svg viewBox="0 0 200 200">';
            if( isset($panel['settings']["o{$i}pu"]) && $panel['settings']["o{$i}pu"] == 'mmhg' ) {
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
                    $panel['content'] .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#fff' stroke-width='1' />";
                } elseif( ($y%2) == 0 ) {
                    $x1 = 100 + (78 * cos($tick_angle * 0.0174532925));
                    $y1 = 100 + (78 * sin($tick_angle * 0.0174532925));
                    $x2 = 100 + (82 * cos($tick_angle * 0.0174532925));
                    $y2 = 100 + (82 * sin($tick_angle * 0.0174532925));
                    $panel['content'] .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='#aaa' stroke-width='1' />";
                }
                if( ($y%20) == 0 ) {
                    $x1 = 100 + (62 * cos($tick_angle * 0.0174532925));
                    $y1 = 100 + (62 * sin($tick_angle * 0.0174532925));
                    if( $y == 1040 ) {
                        $x1 -= 3;
                        $y1 -= 5;
                    }
                    $panel['content'] .= "<text x='{$x1}' y='{$y1}' width='10' height='10' font-size='10' fill='#888'><tspan alignment-baseline='middle' text-anchor='middle'>{$y}</tspan></text>";
                }
            }
            // Add barometer dot
            $panel['content'] .= "<circle id='panel-{$panel['id']}-o{$i}pd' cx='180' cy='100' r='12' "
                    . "fill='rgba(0,105,255,0.65)' stroke='white' stroke-width='0.5' "
                    . "transform='rotate({$panel['data']["o{$i}pa"]},100,100)' />"
                // Add label
                . "<text x='100' y='70' width='100' height='12' font-size='12' fill='#bbb'><tspan text-anchor='middle'>"
                . (isset($panel['settings']["o{$i}name"]) ? $panel['settings']["o{$i}name"] : '')
                . "</tspan></text>"
                // Add center text
                . "<text x='100' y='105' width='100' height='100' font-size='50' fill='white'><tspan id='panel-{$panel['id']}-o{$i}p' alignment-baseline='middle' text-anchor='middle'>"
                    . (isset($panel['data']["o{$i}p"]) ? $panel['data']["o{$i}p"] : '?')
                    . "</tspan></text>"
                // Add units text
                . "<text x='100' y='135' width='100' height='12' font-size='10' fill='#888'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}pu"]) ? $panel['settings']["o{$i}pu"] : '')
                    . "</tspan></text>"
                . "</svg>"
                . "</div>";

        } else {
            if( isset($panel['data']["o{$i}h"]) && $panel['data']["o{$i}h"] > 0 ) {
                $angle = ((($panel['data']["o{$i}h"]/100) * 360) - 90) * 0.0174532925;
                $cx = 100 + (80 * cos($angle));
                $cy = 100 + (80 * sin($angle));
            }
            $panel['content'] .= '<svg viewBox="0 0 200 200">'
                // Add tick marks
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35 100 100)'/>"
                . "<circle cx='100' cy='100' r='80' fill='none' stroke='#fff' stroke-width='12' stroke-dasharray='1,124.664' transform='rotate(-0.35 100 100)' />"
                // Add tick labels
                . "<text x='160' y='101' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>25%</tspan></text>"
                . "<text x='100' y='167' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>50%</tspan></text>"
                . "<text x='40' y='101' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>75%</tspan></text>"
                . "<text x='100' y='35' width='10' height='10' font-size='10' fill='#888'>"
                    . "<tspan alignment-baseline='middle' text-anchor='middle'>100%</tspan></text>"
                // Add label text
                . "<text x='100' y='60' width='100' height='12' font-size='12' fill='#bbb'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}name"]) ? $panel['settings']["o{$i}name"] : '')
                    . "</tspan></text>"
                // Add temperature
                . "<text x='100' y='108' width='100' height='100' font-size='80' fill='white'><tspan id='panel-{$panel['id']}-o{$i}t' alignment-baseline='middle' text-anchor='middle'>"
                    . (isset($panel['data']["o{$i}t"]) ? $panel['data']["o{$i}t"] : '?')
                    . "</tspan></text>"
                // Add humidity circle & value
                . "<circle id='panel-{$panel['id']}-o{$i}hc' cx='{$cx}' cy='{$cy}' r='12' fill='rgba(255,200,0,0.75)' stroke='#fff' stroke-width='0.5'/>"
                . "<text x='{$cx}' y='" . ($cy+1) . "' width='20' height='20' font-size='15' fill='black'>"
                    . "<tspan id='panel-{$panel['id']}-o{$i}h' alignment-baseline='middle' text-anchor='middle'>"
                        . (isset($panel['data']["o{$i}h"]) ? $panel['data']["o{$i}h"] : '?')
                        . "</tspan>"
                    . "</text>"
                // Add units text
                . "<text x='100' y='145' width='100' height='12' font-size='10' fill='#888'><tspan text-anchor='middle'>"
                    . (isset($panel['settings']["o{$i}tu"]) ? strtoupper($panel['settings']["o{$i}tu"][0]) : '')
                    . "</tspan></text>"
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
        } elseif( $panel['settings']["o{$i}type"] == 'baro1' ) {
            $update_js .= "if( data.o{$i}p != null ) {"
                . "db_setInnerHtml(this,'o{$i}p', data.o{$i}p);"
                . "this.setPOrbit('o{$i}pd', data.o{$i}pa);"
                . "};";
        }
    }
    
    $panel['js'] = array(
        'setHOrbit' => "function(i,v) {"
            . "var txt = db_ge(this,i);"
            . "var circle = db_ge(this,i + 'c');"
            . "txt.textContent = v;"
            . "if(v!='?'){"
                . "var a = (((v/100)*360)-90)*0.0174532925;"
                . "circle.setAttributeNS(null,'cx',100 + (80 * Math.cos(a)));"
                . "circle.setAttributeNS(null,'cy',100 + (80 * Math.sin(a)));"
                . "txt.parentNode.setAttributeNS(null,'x',100 + (80 * Math.cos(a)));"
                . "txt.parentNode.setAttributeNS(null,'y',101 + (80 * Math.sin(a)));"
            . "}};",
        'setWOrbit' => "function(i,v) {"
            . "var arrow = db_ge(this,i);"
            . "arrow.setAttributeNS(null,'transform', 'rotate('+v+',100,100)');"
            . "};",
        'setPOrbit' => "function(i,v) {"
            . "var dot = db_ge(this,i);"
            . "dot.setAttributeNS(null,'transform', 'rotate('+v+',100,100)');"
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

