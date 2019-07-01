//
// This is the main app for the weather module
//
function qruqsp_weather_main() {
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
    this.station = new M.panel('Station', 'qruqsp_weather_main', 'station', 'mc', 'medium mediumaside', 'sectioned', 'qruqsp.weather.main.station');
    this.station.data = null;
    this.station.station_id = 0;
    this.station.refreshTimer = null;
    this.station.graphData = function(s) {
        M.api.getJSONBgCb('qruqsp.weather.graphData', {'tnid':M.curTenantID, 'sensor_ids':M.qruqsp_weather_main.station.sections[s].sensor_ids.join(','), 'graph':s}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.station;
            p.data[s] = [];
            p.sections[s].dataLoaded = 'yes';
            p.sections[s].max_y = rsp.max;
            p.sections[s].min_y = rsp.min;
            for(var i in rsp.sensors) {
                p.sections[s].legend[i] = rsp.sensors[i].name;
                p.data[s][i] = rsp.sensors[i].data;
            }
            p.createMetricsGraphicsContent(s);
        });
    }
    this.station.sections = {
        'details':{'label':'Station', 'type':'simplegrid', 'num_cols':2, 'aside':'yes',
            'cellClasses':['label', ''],
            },
//        '_tabs':{'label':'', 'type':'paneltabs', 'selected':'sensors', 'tabs':{
//            'sensors':{'label':'Sensors', 'fn':'M.qruqsp_weather_main.station.switchTab("sensors");'},
//            'graphs':{'label':'Graphs', 'fn':'M.qruqsp_weather_main.station.switchTab("graphs");'},
//            'historical':
//            }},
        'wu_details':{'label':'Weather Underground', 'type':'simplegrid', 'num_cols':2, 'aside':'yes',
            'visible':function() {return (M.qruqsp_weather_main.station.data.flags&0x04) == 0x04 ? 'yes' : 'no';},
            },
        'sensors':{'label':'Sensors', 'type':'simplegrid', 'num_cols':7,
            'headerValues':['Name', 'Last Reading', 'Temp', 'Humidity', 'Pressure', 'Wind', 'Rain'],
            'headerClasses':['', '', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter'],
            'cellClasses':['', '', 'alignright', 'alignright', 'alignright', 'alignright', 'alignright'],
            },
        'temperature':{'label':'Temperature (C)', 'type':'metricsgraphics', 'aside':'yes',
            'visible':function() { return M.qruqsp_weather_main.station.sections.temperature.sensor_ids.length > 0 ? 'yes' : 'no'},
            'graphtype':'multiline',
            'linked':false,
            'missing_is_hidden': true,
            'legend':[],
            'loadData':'yes',
            'dataFn':this.station.graphData,
            },
        'humidity':{'label':'Humdity (%)', 'type':'metricsgraphics', 
            'visible':function() { return M.qruqsp_weather_main.station.sections.humidity.sensor_ids.length > 0 ? 'yes' : 'no'},
            'graphtype':'multiline',
            'area':false,
            'linked':false,
            'missing_is_hidden': true,
            'min_y_from_data':true,
            'legend':[],
            'loadData':'yes',
            'dataFn':this.station.graphData,
            },
        'pressure':{'label':'Barometric Pressure (millibars)', 'type':'metricsgraphics', 'aside':'yes',
            'visible':function() { return M.qruqsp_weather_main.station.sections.pressure.sensor_ids.length > 0 ? 'yes' : 'no'},
            'graphtype':'multiline',
            'linked':false,
            'missing_is_hidden': true,
            'min_y_from_data': true,
            'legend':[],
            'loadData':'yes',
            'dataFn':this.station.graphData,
            },
        'windspeed':{'label':'Wind Speed (kph)', 'type':'metricsgraphics', 
            'visible':function() { return M.qruqsp_weather_main.station.sections.windspeed.sensor_ids.length > 0 ? 'yes' : 'no'},
            'graphtype':'multiline',
            'linked':false,
            'missing_is_hidden': true,
            'legend':[],
            'loadData':'yes',
            'dataFn':this.station.graphData,
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
                case 2: return M.formatCelsius(d.celsius);
                case 3: return M.formatHumidity(d.humidity);
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
    this.station.open = function(cb, sid, list) {
        if( sid != null ) { this.station_id = sid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('qruqsp.weather.stationGet', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.station;
            p.data = rsp.station;
            p.sections.temperature.legend = [];
            p.sections.temperature.sensor_ids = [];
            p.sections.temperature.dataLoaded = 'no';
            p.sections.humidity.legend = [];
            p.sections.humidity.sensor_ids = [];
            p.sections.humidity.dataLoaded = 'no';
            p.sections.pressure.legend = [];
            p.sections.pressure.sensor_ids = [];
            p.sections.pressure.dataLoaded = 'no';
            p.sections.windspeed.legend = [];
            p.sections.windspeed.sensor_ids = [];
            p.sections.windspeed.dataLoaded = 'no';
            for(var i in rsp.station.sensors) {
                if( (rsp.station.sensors[i].flags&0x01) == 0x01 ) {
                    continue;
                }
                if( (rsp.station.sensors[i].fields&0x01) == 0x01 ) {
                    p.sections.temperature.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x02) == 0x02 ) {
                    p.sections.humidity.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x04) == 0x04 ) {
                    p.sections.pressure.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x10) == 0x10 ) {
                    p.sections.windspeed.sensor_ids.push(rsp.station.sensors[i].id);
                }
            }
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
        this.update();
        this.refreshTimer = setTimeout('M.qruqsp_weather_main.station.autoUpdate();', 30000);
    }
    this.station.update = function() {
        M.api.getJSONBgCb('qruqsp.weather.stationGet', {'tnid':M.curTenantID, 'station_id':this.station_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.station;
            p.data = rsp.station;
            p.sections.temperature.legend = [];
            p.sections.temperature.sensor_ids = [];
            p.sections.temperature.dataLoaded = 'no';
            p.sections.humidity.legend = [];
            p.sections.humidity.sensor_ids = [];
            p.sections.humidity.dataLoaded = 'no';
            p.sections.pressure.legend = [];
            p.sections.pressure.sensor_ids = [];
            p.sections.pressure.dataLoaded = 'no';
            for(var i in rsp.station.sensors) {
                if( (rsp.station.sensors[i].flags&0x01) == 0x01 ) {
                    continue;
                }
                if( (rsp.station.sensors[i].fields&0x01) == 0x01 ) {
                    p.sections.temperature.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x02) == 0x02 ) {
                    p.sections.humidity.sensor_ids.push(rsp.station.sensors[i].id);
                }
                if( (rsp.station.sensors[i].fields&0x04) == 0x04 ) {
                    p.sections.pressure.sensor_ids.push(rsp.station.sensors[i].id);
                }
            }
            p.refreshSection('sensors');
        });
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
                'aprs_frequency':{'label':'Frequency (min)', 'type':'toggle', 'toggles':{'1':'1', '5':'5', '10':'10', '15':'15', '30':'30', '45':'45', '60':'60'}},
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
