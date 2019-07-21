//
// This is the main app for the weather module
//
function qruqsp_weather_main() {
    this.sensor_colors = [
        'blue', 'green', 'red', 'purple', 'orange', 'cyan',
        'blue', 'green', 'red', 'purple', 'orange', 'cyan',
        'blue', 'green', 'red', 'purple', 'orange', 'cyan',
        ];
    //
    // The panel to list the station
    //
    this.menu = new M.panel('Weather', 'qruqsp_weather_main', 'menu', 'mc', 'medium', 'sectioned', 'qruqsp.weather.main.menu');
    this.menu.data = {};
    this.menu.nplist = [];
    this.menu.sections = {
//        'search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':1,
//            'cellClasses':[''],
//            'hint':'Search station',
//            'noData':'No station found',
//            },
        'stations':{'label':'Weather Stations', 'type':'simplegrid', 'num_cols':1,
            'noData':'No station',
            'addTxt':'Add Station',
            'addFn':'M.qruqsp_weather_main.editstation.open(\'M.qruqsp_weather_main.menu.open();\',0,null);'
            },
    }
/*    this.menu.liveSearchCb = function(s, i, v) {
        if( s == 'search' && v != '' ) {
            M.api.getJSONBgCb('qruqsp.weather.stationSearch', {'tnid':M.curTenantID, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.qruqsp_weather_main.menu.liveSearchShow('search',null,M.gE(M.qruqsp_weather_main.menu.panelUID + '_' + s), rsp.stations);
                });
        }
    }
    this.menu.liveSearchResultValue = function(s, f, i, j, d) {
        return d.name;
    }
    this.menu.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.qruqsp_weather_main.station.open(\'M.qruqsp_weather_main.menu.open();\',\'' + d.id + '\');';
    } */
    this.menu.cellValue = function(s, i, j, d) {
        if( s == 'stations' ) {
            switch(j) {
                case 0: return d.name;
            }
        }
    }
    this.menu.rowFn = function(s, i, d) {
        if( s == 'stations' ) {
            return 'M.qruqsp_weather_main.station.open(\'M.qruqsp_weather_main.menu.open();\',\'' + d.id + '\',M.qruqsp_weather_main.station.nplist);';
        }
    }
    this.menu.open = function(cb) {
        M.api.getJSONCb('qruqsp.weather.stationList', {'tnid':M.curTenantID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.menu;
            p.data = rsp;
            p.nplist = (rsp.nplist != null ? rsp.nplist : null);
            p.refresh();
            p.show(cb);
        });
    }
    this.menu.addClose('Back');

    //
    // The panel to display Station
    //
    this.station = new M.panel('Station', 'qruqsp_weather_main', 'station', 'mc', 'medium fiftyfifty', 'sectioned', 'qruqsp.weather.main.station');
    this.station.data = null;
    this.station.start_ts = 0;
    this.station.end_ts = 0;
    this.station.slice_seconds = 60;
    this.station.station_id = 0;
    this.station.refreshTimer = null;
    this.station.sections = {
//        'details':{'label':'Station', 'type':'simplegrid', 'num_cols':2, 'aside':'yes',
//            'cellClasses':['label', ''],
//            },
//        '_tabs':{'label':'', 'type':'paneltabs', 'selected':'sensors', 'tabs':{
//            'sensors':{'label':'Sensors', 'fn':'M.qruqsp_weather_main.station.switchTab("sensors");'},
//            'graphs':{'label':'Graphs', 'fn':'M.qruqsp_weather_main.station.switchTab("graphs");'},
//            'historical':
//            }},
//        'wu_details':{'label':'Weather Underground', 'type':'simplegrid', 'num_cols':2, 'aside':'yes',
//            'visible':function() {return (M.qruqsp_weather_main.station.data.flags&0x04) == 0x04 ? 'yes' : 'no';},
//            },
        'sensors':{'label':'Sensors', 'type':'simplegrid', 'num_cols':7, 'aside':'full',
            'headerValues':['Name', 'Last Reading', 'Temp', 'Humidity', 'Pressure', 'Wind', 'Rain'],
            'headerClasses':['', '', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter'],
            'cellClasses':['', '', 'alignright', 'alignright', 'alignright', 'alignright', 'alignright'],
            },
        'svgstyles':{'label':'', 'visible':'hidden', 'type':'html'},
        '_tabs':{'label':'', 'type':'menutabs', 'selected':'60', 'tabs':{
            '60':{'label':'12 Hours', 'fn':'M.qruqsp_weather_main.station.switchTab("60");'},
            '120':{'label':'24 Hours', 'fn':'M.qruqsp_weather_main.station.switchTab("120");'},
            '240':{'label':'48 Hours', 'fn':'M.qruqsp_weather_main.station.switchTab("240");'},
            '480':{'label':'4 Days', 'fn':'M.qruqsp_weather_main.station.switchTab("480");'},
            '960':{'label':'8 Days', 'fn':'M.qruqsp_weather_main.station.switchTab("960");'},
//            '1920':{'label':'16 Days', 'fn':'M.qruqsp_weather_main.station.switchTab("1920");'},
//            '3840':{'label':'32 Days', 'fn':'M.qruqsp_weather_main.station.switchTab("3840");'},
            }},
        'celsius':{'label':'Temperature (C)', 'type':'svg', 'aside':'yes',
            'visible':function() { return M.qruqsp_weather_main.station.sections.celsius.sensor_ids.length > 0 ? 'yes' : 'no'},
            'dataFields':['celsius'],
            },
        'humidity':{'label':'Humidity', 'type':'svg', 'aside':'yes',
            'visible':function() { return M.qruqsp_weather_main.station.sections.humidity.sensor_ids.length > 0 ? 'yes' : 'no'},
            'dataFields':['humidity'],
            },
        'millibars':{'label':'Pressure', 'type':'svg', 'aside':'no',
            'visible':function() { return M.qruqsp_weather_main.station.sections.millibars.sensor_ids.length > 0 ? 'yes' : 'no'},
            'dataFields':['millibars'],
            },
        'wind_kph':{'label':'Wind Speed (kph)', 'type':'svg', 'aside':'no',
            'visible':function() { return M.qruqsp_weather_main.station.sections.wind_kph.sensor_ids.length > 0 ? 'yes' : 'no'},
            'dataFields':['wind_kph'],
            }, 
    }
    this.station.cellValue = function(s, i, j, d) {
        if( s == 'details' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        }
        if( s == 'sensors' ) {
            switch(j) {
                case 0: return d.name;
                case 1: return d.sample_date;
                case 2: return M.formatCelsius(d.celsius,1);
                case 3: return M.formatHumidity(d.humidity,1);
                case 4: return M.formatMillibars(d.millibars);
                case 5: return M.formatWind(d.wind_kph,d.wind_deg);
                case 6: return M.formatRain(d.rain_mm);
            }
        }
    }
    this.station.rowFn = function(s, i, d) {
        if( s == 'sensors' ) {
            return 'M.qruqsp_weather_main.sensor.open(\'M.qruqsp_weather_main.station.open();\',\'' + d.id + '\');';
        }
    }
    this.station.switchTab = function(t) {
        if( this.refreshTimer != null ) {
            clearTimeout(this.refreshTimer);
        }
        this.sections._tabs.selected = t;
        this.slice_seconds = parseInt(t);
        this.refreshSection('_tabs');
        this.end_ts = Math.floor(Date.now()/(1000*this.slice_seconds)) * this.slice_seconds;
        this.start_ts = this.end_ts - (this.slice_seconds * 720);
        for(var i in this.sections) {
            if( this.sections[i].type == 'svg' ) {
                this.loadSVG(i);
            }
        }
        this.refreshTimer = setTimeout('M.qruqsp_weather_main.station.autoUpdate();', 30000);
    }
    this.station.loadSVG = function(s) {
        M.api.getJSONBgCb('qruqsp.weather.svgGet', {'tnid':M.curTenantID, 
            'sensor_ids':this.sections[s].sensor_ids.join(','), 
            'prefix':s,
            'fields':this.sections[s].dataFields.join(','),
            'start_ts':this.start_ts,
            'end_ts':this.end_ts,
            'slice_seconds':this.slice_seconds,
            }, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.qruqsp_weather_main.station;
                var e = M.gE(p.panelUID + '_' + s + '_svg');
                e.innerHTML = rsp.svg;
                if( e.offsetHeight <= 30 ) {
                    e.style.height = ((e.offsetWidth/rsp.width) * rsp.height) + 'px';
                }
                p.sections[s].last_slice_id = rsp.last_slice_id;
                p.sections[s].yaxis_left_min = rsp.yaxis_left_min;
                p.sections[s].yaxis_left_max = rsp.yaxis_left_max;
                p.sections[s].yaxis_right_min = rsp.yaxis_right_min;
                p.sections[s].yaxis_right_max = rsp.yaxis_right_max;
            });
    }
    this.station.updateSVG = function(s) {
        var refresh_start_ts = (this.sections[s].last_slice_id*this.slice_seconds);
        //
        // Ask for the last slice again, it may have been updated since last fetched
        //
        M.api.getJSONBgCb('qruqsp.weather.svgGet', {'tnid':M.curTenantID, 
            'sensor_ids':this.sections[s].sensor_ids.join(','), 
            'prefix':s,
            'fields':this.sections[s].dataFields.join(','),
            'start_ts':refresh_start_ts,
            'yaxis_left_min':this.sections[s].yaxis_left_min,
            'yaxis_left_max':this.sections[s].yaxis_left_max,
            'yaxis_right_min':this.sections[s].yaxis_right_min,
            'yaxis_right_max':this.sections[s].yaxis_right_max,
            'end_ts':this.end_ts,
            'slice_seconds':this.slice_seconds,
            'slicesonly':'yes',
            }, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.qruqsp_weather_main.station;
                //
                // If the min or max values are outside of current size, then need to reload entire graph
                //
                if( rsp.yaxis_left_min < p.sections[s].yaxis_left_min 
                    || rsp.yaxis_left_max > p.sections[s].yaxis_left_max 
                    || rsp.yaxis_right_min < p.sections[s].yaxis_right_min 
                    || rsp.yaxis_right_max > p.sections[s].yaxis_right_max 
                    ) {
                    M.qruqsp_weather_main.station.loadSVG(s);
                    return false;
                }
                var e = M.gE(p.panelUID + '_' + s + '_svg');
                var svg = e.children[0];
                var offset = 51;
                // Check if replacement provided
                if( rsp.slices.length > 0 ) {
                    var e = M.gE(s + '-' + rsp.slices[0].id);
                    if( e != null ) {
                        e.innerHTML = rsp.slices[0].slice;
                        rsp.slices.shift();
                    }
                }
                // Add new slices
                if( rsp.slices != null && rsp.slices.length > 0 ) {
                    // Remove first slices, same number we're adding
                    var elements = svg.getElementsByTagName('g');
                    for(var i = 0; i < rsp.slices.length; i++) {
                        svg.removeChild(elements[0]);
                    }
                    // shift existing slices
                    elements = svg.getElementsByTagName('g');
                    for(var i in elements) {
                        if( typeof(elements[i]) == 'object' ) {
                            elements[i].setAttribute('transform', 'translate(' + offset + ',0)');
                            offset++;
                        }
                    }
                    // Add new slices
                    for(var i = 0; i < rsp.slices.length; i++) {
                        var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                        g.setAttribute('id', s + '-' +rsp.slices[i].id);
                        g.setAttribute('transform', 'translate(' + offset + ',0)');
                        g.innerHTML = rsp.slices[i].slice;
                        svg.appendChild(g);
                        offset++;
                    }
                }
                p.sections[s].last_slice_id = rsp.last_slice_id;
                    
                p.sections[s].yaxis_left_min = rsp.yaxis_left_min;
                p.sections[s].yaxis_left_max = rsp.yaxis_left_max;
                p.sections[s].yaxis_right_min = rsp.yaxis_right_min;
                p.sections[s].yaxis_right_max = rsp.yaxis_right_max;
            });

    }
    this.station.open = function(cb, sid, list) {
        if( sid != null ) { this.station_id = sid; }
        if( list != null ) { this.nplist = list; }
        this.end_ts = Math.floor(Date.now()/(1000*this.slice_seconds)) * this.slice_seconds;
        this.start_ts = this.end_ts - (this.slice_seconds * 720);
        M.api.getJSONCb('qruqsp.weather.stationGet', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.station;
            p.data = rsp.station;
            p.sections.celsius.sensor_ids = [];
            p.sections.humidity.sensor_ids = [];
            p.sections.millibars.sensor_ids = [];
            p.sections.wind_kph.sensor_ids = [];
            p.data.svgstyles = '<style>';

            for(var i = 0;i < rsp.station.sensors.length; i++) {
                if( (rsp.station.sensors[i].flags&0x01) == 0x01 ) {
                    continue;
                }
                if( (rsp.station.sensors[i].fields&0x01) == 0x01 ) {
                    p.sections.celsius.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x02) == 0x02 ) {
                    p.sections.humidity.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x04) == 0x04 ) {
                    p.sections.millibars.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x10) == 0x10 ) {
                    p.sections.wind_kph.sensor_ids.push(rsp.station.sensors[i].id);
                }
                p.data.svgstyles += 'svg circle.celsius-' + rsp.station.sensors[i].id + ' { fill: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg circle.humidity-' + rsp.station.sensors[i].id + ' { fill: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg circle.millibars-' + rsp.station.sensors[i].id + ' { fill: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg circle.wind_kph-' + rsp.station.sensors[i].id + ' { fill: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg line.celsius-' + rsp.station.sensors[i].id + ' { stroke: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg line.humidity-' + rsp.station.sensors[i].id + ' { stroke: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg line.millibars-' + rsp.station.sensors[i].id + ' { stroke: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
                p.data.svgstyles += 'svg line.wind_kph-' + rsp.station.sensors[i].id + ' { stroke: ' + M.qruqsp_weather_main.sensor_colors[i] + '; }';
            }
            p.data.svgstyles += '</style>';
            p.refresh();
            p.show(cb);
            p.refreshTimer = setTimeout('M.qruqsp_weather_main.station.autoUpdate();', 30000);
        });
    }
    this.station.beacon = function() {
        M.api.getJSONCb('qruqsp.weather.stationBeacon', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
        });
    }
    this.station.autoUpdate = function() {
        if( this.refreshTimer != null ) {
            clearTimeout(this.refreshTimer);
        }
        this.update();
        this.refreshTimer = setTimeout('M.qruqsp_weather_main.station.autoUpdate();', 30000);
    }
    this.station.update = function() {
        this.end_ts = Math.floor(Date.now()/(1000*this.slice_seconds)) * this.slice_seconds;
        // 720 is a good width for the graph that works well on most screens
        // 720 is the number of minutes in half a day
        // When slice_seconds is 60, then graph shows half day
        // when slice_seconds is 120, then graphs shows 1 day
        // when slice_seconds is 840, then graphs shows 1 week
        this.start_ts = this.end_ts - (this.slice_seconds * 720);
        if( this.start_ts == this.end_ts ) {
            this.end_ts = this.start_ts + this.slice_seconds;
        }
        M.api.getJSONBgCb('qruqsp.weather.stationGet', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.station;
            p.data = rsp.station;
            p.refreshSection('sensors');
        });
        for(var i in this.sections) {
            if( this.sections[i].type == 'svg' ) {
                this.updateSVG(i);
            }
        }
    }
    this.station.addButton('edit', 'Edit', 'M.qruqsp_weather_main.editstation.open(\'M.qruqsp_weather_main.station.open();\',M.qruqsp_weather_main.station.station_id);');
    this.station.addButton('beacon', 'Beacon', 'M.qruqsp_weather_main.station.beacon();');
    this.station.addClose('Back');

    //
    // The panel to edit Station
    //
    this.editstation = new M.panel('Station', 'qruqsp_weather_main', 'editstation', 'mc', 'medium', 'sectioned', 'qruqsp.weather.main.editstation');
    this.editstation.data = null;
    this.editstation.station_id = 0;
    this.editstation.nplist = [];
    this.editstation.sections = {
        'general':{'label':'', 'fields':{
            'name':{'label':'Name', 'required':'yes', 'type':'text'},
//            'flags':{'label':'Options', 'type':'text'},
            'latitude':{'label':'Latitude', 'type':'text'},
            'longitude':{'label':'Longitude', 'type':'text'},
            'altitude':{'label':'Altitude', 'type':'text'},
        }},
        'aprs_sensors':{'label':'APRS Beacon', 
            'visible':function() {return (M.modOn('qruqsp.aprs') ? 'yes' : 'no'); },
            'fields':{
                'flags2':{'label':'Beacon', 'type':'flagtoggle', 'default':'off', 'field':'flags', 'bit':0x02},
                'aprs_frequency':{'label':'Frequency (min)', 'type':'toggle', 'toggles':{'10':'10', '15':'15', '30':'30', '45':'45', '60':'60'}},
                'aprs_celsius_sensor_id':{'label':'Temperature', 'type':'select', 'options':{}},
                'aprs_humidity_sensor_id':{'label':'Humidity', 'type':'select', 'options':{}},
                'aprs_millibars_sensor_id':{'label':'Pressure', 'type':'select', 'options':{}},
                'aprs_wind_kph_sensor_id':{'label':'Wind Speed', 'type':'select', 'options':{}},
                'aprs_wind_deg_sensor_id':{'label':'Wind Direction', 'type':'select', 'options':{}},
                'aprs_rain_mm_sensor_id':{'label':'Rainfall', 'type':'select', 'options':{}},
            }},
        'wu_sensors':{'label':'Weather Underground', 'fields':{
            'flag3':{'label':'Enable', 'type':'flagtoggle', 'default':'off', 'field':'flags', 'bit':0x04},
            'wu_frequency':{'label':'Frequency (min)', 'type':'toggle', 'toggles':{'1':'1', '5':'5', '10':'10', '15':'15', '30':'30', '60':'60'}},
            'wu_id':{'label':'Station ID', 'type':'text'},
            'wu_key':{'label':'Station Key', 'type':'text'},
            'wu_celsius_sensor_id':{'label':'Temperature', 'type':'select', 'options':{}},
            'wu_humidity_sensor_id':{'label':'Humidity', 'type':'select', 'options':{}},
            'wu_millibars_sensor_id':{'label':'Pressure', 'type':'select', 'options':{}},
            'wu_wind_kph_sensor_id':{'label':'Wind Speed', 'type':'select', 'options':{}},
            'wu_wind_deg_sensor_id':{'label':'Wind Direction', 'type':'select', 'options':{}},
            'wu_rain_mm_sensor_id':{'label':'Rainfall', 'type':'select', 'options':{}},
            }},
/*        'aprs':{'label':'APRS', 'fields':{
            'aprs_celsius_sensor_id':{'label':'APRS Celsius Sensor', 'type':'select', 'options':{}},
            'aprs_humidity_sensor_id':{'label':'APRS Celsius Sensor', 'type':'select', 'options':{}},
            'aprs_millibars_sensor_id':{'label':'APRS Celsius Sensor', 'type':'select', 'options':{}},
            'aprs_wind_kph_sensor_id':{'label':'APRS Celsius Sensor', 'type':'select', 'options':{}},
            'aprs_wind_degrees_sensor_id':{'label':'APRS Celsius Sensor', 'type':'select', 'options':{}},
            'aprs_rain_mm_sensor_id':{'label':'APRS Celsius Sensor', 'type':'select', 'options':{}},
            }}, */
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.qruqsp_weather_main.editstation.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.qruqsp_weather_main.editstation.station_id > 0 ? 'yes' : 'no'; },
                'fn':'M.qruqsp_weather_main.editstation.remove();'},
            }},
        };
    this.editstation.fieldValue = function(s, i, d) { return this.data[i]; }
    this.editstation.fieldHistoryArgs = function(s, i) {
        return {'method':'qruqsp.weather.stationHistory', 'args':{'tnid':M.curTenantID, 'station_id':this.station_id, 'field':i}};
    }
    this.editstation.open = function(cb, sid, list) {
        if( sid != null ) { this.station_id = sid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('qruqsp.weather.stationGet', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.editstation;
            p.data = rsp.station;
            p.sections.aprs_sensors.fields.aprs_celsius_sensor_id.options = {'0':'None'};
            p.sections.aprs_sensors.fields.aprs_humidity_sensor_id.options = {'0':'None'};
            p.sections.aprs_sensors.fields.aprs_millibars_sensor_id.options = {'0':'None'};
            p.sections.aprs_sensors.fields.aprs_wind_kph_sensor_id.options = {'0':'None'};
            p.sections.aprs_sensors.fields.aprs_wind_deg_sensor_id.options = {'0':'None'};
            p.sections.aprs_sensors.fields.aprs_rain_mm_sensor_id.options = {'0':'None'};
            p.sections.wu_sensors.fields.wu_celsius_sensor_id.options = {'0':'None'};
            p.sections.wu_sensors.fields.wu_humidity_sensor_id.options = {'0':'None'};
            p.sections.wu_sensors.fields.wu_millibars_sensor_id.options = {'0':'None'};
            p.sections.wu_sensors.fields.wu_wind_kph_sensor_id.options = {'0':'None'};
            p.sections.wu_sensors.fields.wu_wind_deg_sensor_id.options = {'0':'None'};
            p.sections.wu_sensors.fields.wu_rain_mm_sensor_id.options = {'0':'None'};
            if( rsp.station.sensors != null ) {
                for(var i in rsp.station.sensors) {
                    if( (rsp.station.sensors[i].fields&0x01) == 0x01 ) {
                        p.sections.aprs_sensors.fields.aprs_celsius_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                        p.sections.wu_sensors.fields.wu_celsius_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                    }
                    if( (rsp.station.sensors[i].fields&0x02) == 0x02 ) {
                        p.sections.aprs_sensors.fields.aprs_humidity_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                        p.sections.wu_sensors.fields.wu_humidity_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                    }
                    if( (rsp.station.sensors[i].fields&0x04) == 0x04 ) {
                        p.sections.aprs_sensors.fields.aprs_millibars_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                        p.sections.wu_sensors.fields.wu_millibars_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                    }
                    if( (rsp.station.sensors[i].fields&0x10) == 0x10 ) {
                        p.sections.aprs_sensors.fields.aprs_wind_kph_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                        p.sections.wu_sensors.fields.wu_wind_kph_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                    }
                    if( (rsp.station.sensors[i].fields&0x20) == 0x20 ) {
                        p.sections.aprs_sensors.fields.aprs_wind_deg_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                        p.sections.wu_sensors.fields.wu_wind_deg_sensor_id.options[rsp.station.sensors[i].id] = rsp.station.sensors[i].name;
                    }
                }
            }
            p.refresh();
            p.show(cb);
        });
    }
    this.editstation.save = function(cb) {
        if( cb == null ) { cb = 'M.qruqsp_weather_main.editstation.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.station_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('qruqsp.weather.stationUpdate', {'tnid':M.curTenantID, 'station_id':this.station_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('qruqsp.weather.stationAdd', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.qruqsp_weather_main.editstation.station_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.editstation.remove = function() {
        if( confirm('Are you sure you want to remove station?') ) {
            M.api.getJSONCb('qruqsp.weather.stationDelete', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.qruqsp_weather_main.editstation.close();
            });
        }
    }
    this.editstation.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.station_id) < (this.nplist.length - 1) ) {
            return 'M.qruqsp_weather_main.editstation.save(\'M.qruqsp_weather_main.editstation.open(null,' + this.nplist[this.nplist.indexOf('' + this.station_id) + 1] + ');\');';
        }
        return null;
    }
    this.editstation.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.station_id) > 0 ) {
            return 'M.qruqsp_weather_main.editstation.save(\'M.qruqsp_weather_main.editstation.open(null,' + this.nplist[this.nplist.indexOf('' + this.station_id) - 1] + ');\');';
        }
        return null;
    }
    this.editstation.addButton('save', 'Save', 'M.qruqsp_weather_main.editstation.save();');
    this.editstation.addClose('Cancel');
    this.editstation.addButton('next', 'Next');
    this.editstation.addLeftButton('prev', 'Prev');

    //
    // The panel to edit Sensor
    //
    this.sensor = new M.panel('Sensor', 'qruqsp_weather_main', 'sensor', 'mc', 'medium', 'sectioned', 'qruqsp.weather.main.sensor');
    this.sensor.data = null;
    this.sensor.sensor_id = 0;
    this.sensor.nplist = [];
    this.sensor.sections = {
        'general':{'label':'', 'fields':{
            'station_id':{'label':'Station', 'required':'yes', 'type':'select', 'options':{}, 'complex_options':{'value':'id', 'name':'name'}},
            'name':{'label':'Name', 'required':'yes', 'type':'text'},
//            'flags':{'label':'Options', 'type':'text'},
//            'fields':{'label':'Fields', 'type':'text'},
//            'rain_mm_offset':{'label':'Rain Offset', 'type':'text'},
//            'rain_mm_last':{'label':'Rain Last Reading', 'type':'text'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.qruqsp_weather_main.sensor.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.qruqsp_weather_main.sensor.sensor_id > 0 ? 'yes' : 'no'; },
                'fn':'M.qruqsp_weather_main.sensor.remove();'},
            }},
        };
    this.sensor.fieldValue = function(s, i, d) { return this.data[i]; }
    this.sensor.fieldHistoryArgs = function(s, i) {
        return {'method':'qruqsp.weather.sensorHistory', 'args':{'tnid':M.curTenantID, 'sensor_id':this.sensor_id, 'field':i}};
    }
    this.sensor.open = function(cb, sid, list) {
        if( sid != null ) { this.sensor_id = sid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('qruqsp.weather.sensorGet', {'tnid':M.curTenantID, 'sensor_id':this.sensor_id, 'stations':'yes'}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.sensor;
            p.data = rsp.sensor;
            p.sections.general.fields.station_id.options = rsp.stations;
            p.refresh();
            p.show(cb);
        });
    }
    this.sensor.save = function(cb) {
        if( cb == null ) { cb = 'M.qruqsp_weather_main.sensor.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.sensor_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('qruqsp.weather.sensorUpdate', {'tnid':M.curTenantID, 'sensor_id':this.sensor_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('qruqsp.weather.sensorAdd', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.qruqsp_weather_main.sensor.sensor_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.sensor.remove = function() {
        if( confirm('Are you sure you want to remove sensor?') ) {
            M.api.getJSONCb('qruqsp.weather.sensorDelete', {'tnid':M.curTenantID, 'sensor_id':this.sensor_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.qruqsp_weather_main.sensor.close();
            });
        }
    }
    this.sensor.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.sensor_id) < (this.nplist.length - 1) ) {
            return 'M.qruqsp_weather_main.sensor.save(\'M.qruqsp_weather_main.sensor.open(null,' + this.nplist[this.nplist.indexOf('' + this.sensor_id) + 1] + ');\');';
        }
        return null;
    }
    this.sensor.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.sensor_id) > 0 ) {
            return 'M.qruqsp_weather_main.sensor.save(\'M.qruqsp_weather_main.sensor.open(null,' + this.nplist[this.nplist.indexOf('' + this.sensor_id) - 1] + ');\');';
        }
        return null;
    }
    this.sensor.addButton('save', 'Save', 'M.qruqsp_weather_main.sensor.save();');
    this.sensor.addClose('Cancel');
    this.sensor.addButton('next', 'Next');
    this.sensor.addLeftButton('prev', 'Prev');

    //
    // Start the app
    // cb - The callback to run when the user leaves the main panel in the app.
    // ap - The application prefix.
    // ag - The app arguments.
    //
    this.start = function(cb, ap, ag) {
        args = {};
        if( ag != null ) {
            args = eval(ag);
        }
        
        //
        // Create the app container
        //
        var ac = M.createContainer(ap, 'qruqsp_weather_main', 'yes');
        if( ac == null ) {
            alert('App Error');
            return false;
        }
        
        this.menu.open(cb);
    }
}
