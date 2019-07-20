<?php
//
// Description
// ===========
// This method returns the SVG for a weather station of one of the graphs.
//
// The graph is divided into slices, with each slice composed of a number of seconds (args['slice_seconds']). 
// The minimum slice seconds is 60.
// The timestamps are divided by slice_seconds to give an ID for each slice.
//
// The slicesonly option allows for most recent data to be pulled to update graph without needing to 
// pull the data for the entire graph each time.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the station is attached to.
// station_id:          The ID of the station to get the details for.
//
// Returns
// -------
//
function qruqsp_weather_svgLoad($ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_tz = new DateTimezone($rc['settings']['intl-default-timezone']);

    //
    // Check if only asking for a refresh
    //
    if( isset($args['slicesonly']) && $args['slicesonly'] == 'yes' ) {
        //
        // Need an extra one to have previous values
        //
        $args['start_ts'] = $args['start_ts'] - $args['slice_seconds'];
    }

    //
    // Setup the graph defaults
    //
    $graph_left = 50;
    $graph_bottom = 210;
    $graph_width = 720;
    $graph_height = 200;

    $yaxis_left = 'yes';
    $yaxis_left_min = null;
    $yaxis_left_max = null;
    $yaxis_right = 'no';
    $yaxis_right_min = null;
    $yaxis_right_max = null;
    $yaxis_left_tick_step = 10;
    $yaxis_left_label_step = 10;

    
    $xaxis_tick_step = 10;
    $xaxis_label_step = 60;
    if( $args['slice_seconds'] > 3000 ) {
        $xaxis_tick_step = 45;
        $xaxis_label_step = 90;
    }
    elseif( $args['slice_seconds'] > 800 ) {
        $xaxis_tick_step = 45;
        $xaxis_label_step = 90;
    }

    $svg_width = 800;
    $svg_height = 260;

    $xaxis_font_size = '16';
    $yaxis_font_size = $xaxis_font_size;
    $xaxis_label_color = '#999';
    $yaxis_label_color = $xaxis_label_color;
    $xaxis_line_width = '1';
    $yaxis_line_width = $xaxis_line_width;
    $xaxis_line_color = $xaxis_label_color;
    $yaxis_line_color = $xaxis_label_color;

    //
    // Get the list of sensors
    //
    $strsql = "SELECT id, name, fields "
        . "FROM qruqsp_weather_sensors "
        . "WHERE id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['sensor_ids']) . ") "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'qruqsp.weather', array(
        array('container'=>'sensors', 'fname'=>'id', 'fields'=>array('id', 'name', 'fields')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.53', 'msg'=>'Unable to load ', 'err'=>$rc['err']));
    }
    $sensors = isset($rc['sensors']) ? $rc['sensors'] : array();

    //
    // Setup sensors 
    //
    foreach($sensors as $sid => $sensor) {
        foreach($args['fields'] as $fid => $field) {
            if( in_array($field, ['celsius', 'humidity', 'millibars', 'wind_kph']) ) {
                $sensors[$sid]['min_' . $field] = null;
                $sensors[$sid]['max_' . $field] = null;
                if( count($args['fields']) > 1 && $field == 'millibars' ) {
                    $yaxis_right = 'yes';
                }
            }
        }
    }

    //
    // Setup the data array of slices based on input args
    //
    $prev_slice_id = 0;
    $start_slice_id = 0;
    $end_slice_id = 0;
    $data = array();
    for($i = $args['start_ts']; $i <= $args['end_ts']; $i += $args['slice_seconds']) {
        $slice_id = floor($i/$args['slice_seconds']);
        if( $start_slice_id == 0 ) {
            $start_slice_id = $slice_id;
        }
        $data[$slice_id] = array(
            'sensors' => array(),
            'prev' => $prev_slice_id,
            'next' => 0,
            );
        if( $prev_slice_id > 0 ) {    
            $data[$prev_slice_id]['next'] = $slice_id;
        }
        $prev_slice_id = $slice_id;
    }
    $end_slice_id = $slice_id;

    //
    // Get the sensor data for that the requested time period
    //
    $strsql = "SELECT sensor_id, "
        . "FLOOR(UNIX_TIMESTAMP(sample_date)/'" . ciniki_core_dbQuote($ciniki, $args['slice_seconds']) . "') AS slice_id ";
    foreach($args['fields'] as $fid => $field) {
        if( in_array($field, ['celsius', 'humidity', 'millibars', 'wind_kph']) ) {
            $strsql .= ", " . $field;
        } else {
            // Remove unrecognized fields
            unset($args['fields'][$fid]);
        }
    }
    $strsql .= " FROM qruqsp_weather_sensor_data "
        . "WHERE sensor_id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['sensor_ids']) . ") "
        . "AND sample_date >= FROM_UNIXTIME(" . ciniki_core_dbQuote($ciniki, $args['start_ts']) . ") "
        . "AND sample_date <= FROM_UNIXTIME(" . ciniki_core_dbQuote($ciniki, $args['end_ts']) . ") "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY sample_date ASC, sensor_id "
        . "";
    //
    // Use raw mode for speed and go straight into building slices array with min-avg-values
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.51', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $dh = $rc['handle'];
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');
    $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    $prev_slice_id = 0;
    while( isset($result['row']) ) {
        $row = $result['row'];
        if( !isset($data[$row['slice_id']]) ) {
            error_log('out of bounds data');
            $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
            continue;
        }
        if( !isset($data[$row['slice_id']]['sensors'][$row['sensor_id']]) ) {
            $data[$row['slice_id']]['sensors'][$row['sensor_id']] = array(
                'num_samples' => 1,
                );
        } else {
            $data[$row['slice_id']]['sensors'][$row['sensor_id']]['num_samples'] += 1;
        }

        foreach($args['fields'] as $field) {
            if( isset($row[$field]) && $row[$field] != null ) {
                //
                // Setup max-min-avg values
                //
                if( !isset($data[$row['slice_id']]['sensors'][$row['sensor_id']]['max_' . $field]) 
                    || $row[$field] > $data[$row['slice_id']]['sensors'][$row['sensor_id']]['max_' . $field]
                    ) {
                    $data[$row['slice_id']]['sensors'][$row['sensor_id']]['max_' . $field] = $row[$field];
                }
                if( !isset($data[$row['slice_id']]['sensors'][$row['sensor_id']]['min_' . $field]) 
                    || $row[$field] < $data[$row['slice_id']]['sensors'][$row['sensor_id']]['min_' . $field]
                    ) {
                    $data[$row['slice_id']]['sensors'][$row['sensor_id']]['min_' . $field] = $row[$field];
                }
                if( !isset($data[$row['slice_id']]['sensors'][$row['sensor_id']]['total_' . $field]) ) {
                    $data[$row['slice_id']]['sensors'][$row['sensor_id']]['total_' . $field] = $row[$field];
                    $data[$row['slice_id']]['sensors'][$row['sensor_id']]['avg_' . $field] = $row[$field];
                } else {
                    $data[$row['slice_id']]['sensors'][$row['sensor_id']]['total_' . $field] += $row[$field];
                    $data[$row['slice_id']]['sensors'][$row['sensor_id']]['avg_' . $field] = ($data[$row['slice_id']]['sensors'][$row['sensor_id']]['total_' . $field] / $data[$row['slice_id']]['sensors'][$row['sensor_id']]['num_samples']);
                }

                //
                // Setup min-max for each sensor
                //
                if( !isset($sensors[$row['sensor_id']]['min_' . $field])
                    || $row[$field] < $sensors[$row['sensor_id']]['min_' . $field]
                    ) {
                    $sensors[$row['sensor_id']]['min_' . $field] = $row[$field];
                }
                if( !isset($sensors[$row['sensor_id']]['max_' . $field])
                    || $row[$field] > $sensors[$row['sensor_id']]['max_' . $field]
                    ) {
                    $sensors[$row['sensor_id']]['max_' . $field] = $row[$field];
                }
            }
        }
        $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    }

    //
    // Setup the yaxis(s) min/max
    //
    foreach($sensors as $sid => $sensor) {
        foreach($args['fields'] as $fid => $field) {
            if( $field == 'millibars' && $yaxis_right == 'yes' ) {
                if( $yaxis_right_min == null || $sensor['min_' . $field] < $yaxis_right_min ) {
                    $yaxis_right_min = $sensor['min_' . $field];
                }
                if( $yaxis_right_max == null || $sensor['max_' . $field] > $yaxis_right_max ) {
                    $yaxis_right_max = $sensor['max_' . $field];
                }
            } else {
                if( $yaxis_left_min == null || $sensor['min_' . $field] < $yaxis_left_min ) {
                    $yaxis_left_min = $sensor['min_' . $field];
                }
                if( $yaxis_left_max == null || $sensor['max_' . $field] > $yaxis_left_max ) {
                    $yaxis_left_max = $sensor['max_' . $field];
                }
            }
        }
    }

    //
    // Setup overrides for certain graphs to make them look their best
    //
    if( in_array('humidity', $args['fields']) ) {
        if( $yaxis_left_min == null || $yaxis_left_min > 0 ) {
            $yaxis_left_min = 0;
        }
        if( $yaxis_left_max == null || $yaxis_left_max < 100 ) {
            $yaxis_left_max = 100;
        }
        $yaxis_left_tick_step = 10;
        $yaxis_left_label_step = 20;
    } elseif( in_array('celsius', $args['fields']) ) {
        if( $yaxis_left_min == null || $yaxis_left_min > 0 ) {
            $yaxis_left_min = 0;
        } elseif( $yaxis_left_min < 0 ) {
            $yaxis_left_min = floor($yaxis_left_min/10) * 10;
        }
        $yaxis_left_max = ceil($yaxis_left_max/10) * 10;
        $yaxis_left_tick_step = 2;
        $yaxis_left_label_step = 10;
    } elseif( in_array('millibars', $args['fields']) ) {
        if( $yaxis_right == 'yes' ) {
            $yaxis_right_min = floor($yaxis_right_min);
            $yaxis_right_max = ceil($yaxis_right_max);
            $diff = $yaxis_right_max - $yaxis_right_min;
        } else {
            $yaxis_left_min = floor($yaxis_left_min);
            $yaxis_left_max = ceil($yaxis_left_max);
            $diff = $yaxis_left_max - $yaxis_left_min;
        }
        if( $diff > 10 ) {
            $yaxis_left_tick_step = 1;
            $yaxis_left_label_step = 2;
        } else {
            $yaxis_left_tick_step = 1;
            $yaxis_left_label_step = 1;
        }
    } elseif( in_array('wind_kph', $args['fields']) ) {
        $yaxis_left_max = ceil($yaxis_left_max/5) * 5;
        $yaxis_left_tick_step = 1;
        $yaxis_left_label_step = 5;
    }

    //
    // If the min/max passed in is larger, use them
    //
    if( isset($args['yaxis_left_min']) && $args['yaxis_left_min'] < $yaxis_left_min ) {
        $yaxis_left_min = $args['yaxis_left_min'];
    }
    if( isset($args['yaxis_left_max']) && $args['yaxis_left_max'] > $yaxis_left_max ) {
        $yaxis_left_max = $args['yaxis_left_max'];
    }
    if( isset($args['yaxis_right_min']) && $args['yaxis_right_min'] < $yaxis_right_min ) {
        $yaxis_right_min = $args['yaxis_right_min'];
    }
    if( isset($args['yaxis_right_max']) && $args['yaxis_right_max'] > $yaxis_right_max ) {
        $yaxis_right_max = $args['yaxis_right_max'];
    }

    $y_left_scale = ($graph_height/($yaxis_left_max-$yaxis_left_min));
    if( $yaxis_right == 'yes' ) {
        $y_right_scale = ($graph_height/($yaxis_right_max-$yaxis_right_min));
    }

    $slices = '';
    $slicelist = array();
    foreach($data as $slice_id => $slice) {
        //
        // Skip the first slice, it doesn't have previous slice so won't be able to draw a line
        //
        if( $slice_id == floor($args['start_ts']/$args['slice_seconds']) ) {
            continue;
        }
        $offset = $graph_left + ($slice_id - $start_slice_id);
        if( !isset($args['slicesonly']) || $args['slicesonly'] != 'yes' ) {
            $slices .= "<g id='" . $args['prefix'] . "-" . $slice_id . "' transform='translate(" . $offset . ",0)'>";
        }
        foreach($slice['sensors'] as $sid => $sensor) {
            foreach($args['fields'] as $field) {
                //
                // Skip when no data 
                //
                if( !isset($slice['sensors'][$sid]['max_' . $field]) && $slice['sensors'][$sid]['max_' . $field] != null ) {
                    continue;
                }
                // 
                // Check if previous value exists, draw line
                //
                if( isset($data[$slice['prev']]['sensors'][$sid]['max_' . $field]) ) {
                    $prev_max = $data[$slice['prev']]['sensors'][$sid]['max_' . $field];
                    $slices .= "<line "
                        . "x1='0' "
                        . "y1='" . ($graph_bottom - (($prev_max-$yaxis_left_min)*$y_left_scale)) . "' "
                        . "x2='1' "
                        . "y2='" . ($graph_bottom - (($slice['sensors'][$sid]['max_' . $field]-$yaxis_left_min)*$y_left_scale)) . "' "
                        . "stroke-width='2' stroke='blue' "
                        . "class='" . $field. "-" . $sid . "'"
                        . "></line>"; 
                
                }
                //
                // Check if nothing prev and nothing next, draw circle
                //
                elseif( !isset($data[$slice['next']]['sensors'][$sid]['max_' . $field]) ) {
                    $slices .= "<circle "
                        . "cx='1' "
                        . "cy='" . ($graph_bottom - (($slice['sensors'][$sid]['max_' . $field]-$yaxis_left_min)*$y_left_scale)) . "' "
                        . "r='1' fill='blue' "
                        . "class='" . $field . "-" . $sid . "' "
                        . "></circle>";
                }
            }
        }

        //
        // Draw xaxis ticks, labels and grid lines
        // Set the timezone to local timezone so date/times are local
        //
        $dt = new DateTime('@' . $slice_id * $args['slice_seconds']);
        $dt->setTimezone($intl_tz);

        $ltz_slice_id = ($dt->format('U') + $dt->getOffset())/$args['slice_seconds'];
        if( ($ltz_slice_id%$xaxis_label_step) == 0 ) {
            $slices .= "<line x1='1' y1='{$graph_bottom}' "
                . "x2='1' y2='" . ($graph_bottom+10) . "' "
                . "stroke-width='.5' stroke='#aaa'></line>";
            if( $args['slice_seconds'] > 800 ) {
                $slices .= "<text x='1' y='" . ($graph_bottom+10+$xaxis_font_size) . "' "
                    . "font-size='" . ($xaxis_font_size-2) . "' "
                    . "fill='{$xaxis_line_color}'>"
                    . "<tspan text-anchor='middle' class='xlabel'>" . $dt->format('M d') . "<tspan>"
                    . "</text>";
 /*                   $slices .= "<text x='1' y='" . ($graph_bottom+12+$xaxis_font_size+$xaxis_font_size) . "' "
                        . "font-size='" . ($xaxis_font_size-2) . "' "
                        . "fill='{$xaxis_line_color}'>"
                        . "<tspan text-anchor='middle' class='xlabel'>" . $dt->format('H:i') . "<tspan>"
                        . "</text>"; */
            } elseif( $args['slice_seconds'] > 60 ) {
                $slices .= "<text x='1' y='" . ($graph_bottom+10+$xaxis_font_size) . "' "
                    . "font-size='{$xaxis_font_size}' "
                    . "fill='{$xaxis_line_color}'>"
                    . "<tspan text-anchor='middle' class='xlabel'>" . $dt->format('H:i') . "<tspan>"
                    . "</text>";
                if( $dt->format('H') == 0 ) {
                    $slices .= "<text x='1' y='" . ($graph_bottom+12+$xaxis_font_size+$xaxis_font_size) . "' "
                        . "font-size='" . ($xaxis_font_size-2) . "' "
                        . "fill='{$xaxis_line_color}'>"
                        . "<tspan text-anchor='middle' class='xlabel'>" . $dt->format('M d') . "<tspan>"
                        . "</text>";
                }
            } else {
                $slices .= "<text x='1' y='" . ($graph_bottom+10+$xaxis_font_size) . "' "
                    . "font-size='{$xaxis_font_size}' "
                    . "fill='{$xaxis_line_color}'>"
                    . "<tspan text-anchor='middle' class='xlabel'>" . $dt->format('H:i') . "<tspan>"
                    . "</text>";
            }
            $slices .= "<line x1='1' y1='{$graph_bottom}' "
                . "x2='1' y2='" . ($graph_bottom - $graph_height) . "' "
                . "stroke-width='1' stroke='#ddd' stroke-dasharray='6,3'></line>";
        } 
        elseif( ($ltz_slice_id%$xaxis_tick_step) == 0 ) {
            $slices .= "<line x1='1' y1='{$graph_bottom}' "
                . "x2='1' y2='" . ($graph_bottom+5) . "' "
                . "stroke-width='.5' stroke='#aaa'></line>";
        }

        if( isset($args['slicesonly']) && $args['slicesonly'] == 'yes' ) {
            $slicelist[] = array('id'=>$slice_id, 'slice'=>$slices);
            $slices = '';
        } else {
            $slices .= '</g>';
        }
        $last_slice_id = $slice_id;
    }

    //
    // This feature is used when loading updates to graph
    //
    if( isset($args['slicesonly']) && $args['slicesonly'] == 'yes' ) {
        return array('stat'=>'ok', 'slices'=>$slicelist,
            'yaxis_left_min' => $yaxis_left_min,
            'yaxis_left_max' => $yaxis_left_max,
            'yaxis_right_min' => $yaxis_right_min,
            'yaxis_right_max' => $yaxis_right_max,
            'last_slice_id'=>$slice_id);
    }
    
    $svg = "<svg viewBox='0 0 $svg_width $svg_height'>";

    //
    // X-Axis
    //
    $svg .= "<line x1='" . ($graph_left) . "' y1='" . ($graph_bottom) . "' "
        . "x2='" . ($graph_left) . "' y2='" . ($graph_bottom-$graph_height) . "' "
        . "stroke-width='{$xaxis_line_width}' stroke='{$xaxis_line_color}'/>";
    for($tick = $yaxis_left_min; $tick <= $yaxis_left_max; $tick += $yaxis_left_tick_step) {
        $y = $graph_bottom - (($tick-$yaxis_left_min)*$y_left_scale);
        if( ($tick%$yaxis_left_label_step) == 0 ) {
            $svg .= "<text x='" . ($graph_left-15) . "' y='" . ($y+($yaxis_font_size/3)) . "' "
                . "font-size='{$yaxis_font_size}' "
                . "fill='{$yaxis_line_color}'>"
                . "<tspan text-anchor='end' class='ylabel'>{$tick}</tspan>"
                . "</text>";
            $svg .= "<line x1='" . ($graph_left-10) . "' y1='{$y}' "
                . "x2='" . ($graph_left) . "' y2='{$y}' "
                . "stroke-width='.5' stroke='#aaa' />";
            if( $tick != $yaxis_left_min ) {
                $svg .= "<line x1='" . ($graph_left) . "' y1='{$y}' "
                    . "x2='" . ($graph_left+$graph_width) . "' y2='{$y}' "
                    . "stroke-width='1' stroke='#ddd' stroke-dasharray='6,3' />";
            }
        } else {
            $svg .= "<line x1='" . ($graph_left-5) . "' y1='{$y}' "
                . "x2='" . ($graph_left) . "' y2='{$y}' "
                . "stroke-width='.5' stroke='#aaa' />";
        }
    }
    
    //
    // Y-Axis
    //
    $svg .= "<line x1='" . ($graph_left) . "' y1='" . ($graph_bottom) . "' "
        . "x2='" . ($graph_left + $graph_width + 1) . "' y2='" . ($graph_bottom) . "' stroke-width='{$yaxis_line_width}' stroke='{$yaxis_line_color}' />";


    $svg .= $slices; 
    $svg .= "</svg>";

    return array('stat'=>'ok', 'svg'=>$svg, 'width'=>$svg_width, 'height'=>$svg_height, 
        'yaxis_left_min' => $yaxis_left_min,
        'yaxis_left_max' => $yaxis_left_max,
        'yaxis_right_min' => $yaxis_right_min,
        'yaxis_right_max' => $yaxis_right_max,
        'last_slice_id'=>$slice_id);
}
?>
