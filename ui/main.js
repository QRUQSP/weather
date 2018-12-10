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
        'stations':{'label':'Station', 'type':'simplegrid', 'num_cols':1,
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
    this.station = new M.panel('Station', 'qruqsp_weather_main', 'station', 'mc', 'large narrowaside', 'sectioned', 'qruqsp.weather.main.station');
    this.station.data = null;
    this.station.station_id = 0;
    this.station.graphData = function(s) {
        M.api.getJSONCb('qruqsp.weather.graphData', {'tnid':M.curTenantID, 'sensor_ids':M.qruqsp_weather_main.station.sections[s].sensor_ids.join(','), 'graph':s}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.qruqsp_weather_main.station;
            p.data[s] = [];
            p.sections[s].dataLoaded = 'yes';
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
        'sensors':{'label':'Sensors', 'type':'simplegrid', 'num_cols':7,
//            'visible':function() { return (M.qruqsp_weather_main.station.sections._tabs.selected == 'sensors' ? 'yes' : 'no'); },
            'headerValues':['Name', 'Last Reading', 'Temp', 'Humidity', 'Pressure', 'Wind', 'Rain'],
            'headerClasses':['', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter', 'aligncenter'],
            'cellClasses':['', 'alignright', 'alignright', 'alignright', 'alignright', 'alignright', 'alignright'],
            },
        'temperature':{'label':'Temperature (C)', 'type':'metricsgraphics', 
//            'visible':function() { return (M.qruqsp_weather_main.station.sections._tabs.selected == 'graphs' ? 'yes' : 'no'); },
            'graphtype':'multiline',
            'missing_is_hidden': true,
            'legend':[],
            'loadData':'yes',
            'dataFn':this.station.graphData,
            },
        'humidity':{'label':'Humdity (%)', 'type':'metricsgraphics', 
//            'visible':function() { return (M.qruqsp_weather_main.station.sections._tabs.selected == 'graphs' ? 'yes' : 'no'); },
            'graphtype':'multiline',
            'area':false,
            'missing_is_hidden': true,
            'legend':[],
            'loadData':'yes',
            'dataFn':this.station.graphData,
            },
        'pressure':{'label':'Barometric Pressure (millibars)', 'type':'metricsgraphics', 
//            'visible':function() { return (M.qruqsp_weather_main.station.sections._tabs.selected == 'graphs' ? 'yes' : 'no'); },
            'graphtype':'multiline',
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
            for(var i in rsp.station.sensors) {
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
            p.refresh();
            p.show(cb);
        });
    }
    this.station.addButton('edit', 'Edit', 'M.qruqsp_weather_main.editstation.open(\'M.qruqsp_weather_main.station.open();\',M.qruqsp_weather_main.station.station_id);');
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
