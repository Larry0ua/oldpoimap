L.Control.MinZoomIdenticator = L.Control.extend({
	options: {
		position: 'bottomleft',
    },

    /**
     * map: layerId -> zoomlevel
     */
    _layers: {},

    /** TODO check if nessesary
     */
	initialize: function (options) {
		L.Util.setOptions(this, options);
        this._layers = new Object();
	},

    /**
     * adds a layer with minzoom information to this._layers
     */
    _addLayer: function(layer) {
        var minzoom = 15;
        if (layer.options.minzoom) {
            minzoom = layer.options.minzoom;
        }
        this._layers[layer._leaflet_id] = minzoom;
        this._updateBox(null);
    },
    
    /**
     * removes a layer from this._layers
     */
    _removeLayer: function(layer) {
        this._layers[layer._leaflet_id] = null;
        this._updateBox(null);
    },

    _getMinZoomLevel: function() {
        var minZoomlevel=-1;
        for(var key in this._layers) {
            if ((this._layers[key] != null)&&(this._layers[key] > minZoomlevel)) {
                minZoomlevel = this._layers[key];
            }
        }
        return minZoomlevel;
    },

    onAdd: function (map) {
        this._map = map;
        map.zoomIndecator = this;

        var className = this.className;
        container = this._container = L.DomUtil.create('div', className);
        container.style.fontSize = "2em";
        container.style.background = "#ffffff";
        container.style.backgroundColor = "rgba(255,255,255,0.7)";
        container.style.borderRadius = "10px";
        container.style.padding = "1px 15px";
        container.style.oppacity = "0.5";
        map.on('moveend', this._updateBox, this);
        this._updateBox(null);

        //        L.DomEvent.disableClickPropagation(container);
        return container;
    },

    onRemove: function(map) {
        L.Control.prototype.onRemove.call(this, map);
        map.off({
            'moveend': this._updateBox
        }, this);

        this._map = null;
    },

    _updateBox: function (event) {
        //console.log("map moved -> update Container...");
		if (event != null) {
            L.DomEvent.preventDefault(event);
        }
        var minzoomlevel = this._getMinZoomLevel();
        if (minzoomlevel == -1) {
            this._container.innerHTML = "no layer assigned";
        }else{
            this._container.innerHTML = "current Zoom-Level: "+this._map.getZoom()+" all data at Level: "+minzoomlevel;
        }

        if (this._map.getZoom() >= minzoomlevel) {
            this._container.style.display = 'none';
        }else{
            this._container.style.display = 'block';
        }
    },

  className : 'leaflet-control-minZoomIndecator'
});

L.LatLngBounds.prototype.toOverpassBBoxString = function (){
  var a = this._southWest,
      b = this._northEast;
  return [a.lat, a.lng, b.lat, b.lng].join(",");
}

L.OverPassExtendedLayer = L.FeatureGroup.extend({
  options: {
    minzoom: 15,
    query: "http://overpass-api.de/api/interpreter?data=[out:json];(QUERY);out meta;",
    updateApi: "api/poi/",
    tags : ["amenity=restaurant"],
    callbackOverpass: function(data) {
        if (this.instance._map == null) {
            console.error("_map == null");
        }
        for(i=0;i<data.elements.length;i++) {
          e = data.elements[i];
          if(e.type!="node") continue;

          if (e.id in this.instance._ids) return;
          this.instance._ids[e.id] = true;
          this.instance.osmPois[e.id] = e;
          
          var circle = this.instance.createCircleMarker(e, this.instance.updateInfo[e.id] || e.timestamp);
          this.instance.addLayer(circle);
        }
    },
    callbackUpdateApi: function(data) {
      var instance = this;
      if(this.instance) instance = this.instance;
      // returns [{id:123, updated:'date'},]
      for(var i=0;i<data.length;i++) {
        var id = data[i].id;
        if(instance.updateInfo[id]!=data[i].updated) {
          instance.updateInfo[id]=data[i].updated;
          if(instance.osmPois[id]) {
            instance.redrawCircleMarker(id, data[i].updated);
          }
        }
      }
    }
  },

  initialize: function (options) {
    L.Util.setOptions(this, options);
    this._layers = {};
    // save position of the layer or any options from the constructor
    this._ids = {};
    this._requested = {};
    this.osmPois = {};
    this.pois = {};
    this.updateInfo = {};
  },

  poiInfo: function(obj, days) {
    var r = $('<table>');
    for (key in obj.tags)
      r.append($('<tr>').append($('<th>').text(key)).append($('<td>').text(obj.tags[key])));
    r.append($('<tr>').append($('<th>').text('Age')).append($('<td>').css('background-color', this.colorByDays(obj.id, days)).text(days)));
    var b = $('<input>', {type:'button', value:'Update', onclick:'javascript:update_in_db('+obj.id+')'});
    var del = $('<input>', {type:'button', value:'Remove', onclick:'javascript:remove_from_db('+obj.id+')'});
    return $('<div>')
      .append(r)
      .append(b)
      .append(del)
      .html();
  },
  redrawCircleMarker: function(id, modifiedTime) {
    this._map.removeLayer(this.pois[id]);
    var newCircle = this.createCircleMarker(this.osmPois[id], modifiedTime);
    this._map.addLayer(newCircle);
  },
  
  createCircleMarker: function(e, modifiedTime) {
    var pos = new L.LatLng(e.lat, e.lon);
    var poiDate = Date.parse(modifiedTime);
    var daysOld = Math.round((new Date() - poiDate) / (60*60*24*1000));
    var popup = this.poiInfo(e, daysOld);
    var color = this.colorByDays(e.id, daysOld);
    var circle = L.circleMarker(pos, {
      color: color,
      fillColor: color,
      fillOpacity: 0.5
    }).bindPopup(popup);
    circle._osm_age = daysOld;
    this.pois[e.id] = circle;
    return circle;
  },
  
  colorByDays: function(id, daysOld) {
    if(removedList.indexOf(id)>=0) {
      return 'black';
    }
    var color = 'red';
    if (daysOld < 90) color = 'green'; else
    if (daysOld < 180) color = 'yellow'; else
    if (daysOld < 365) color = '#FFA62F';
    return color;
  },
  
  updatePoi: function(id) {
    $.ajax({
      url: this.options.updateApi,
      context: { instance: this },
      dataType: "json",
      data: {"id": id, "lat": this.osmPois[id].lat, "lng": this.osmPois[id].lon},
      method: "post",
      error: function() {
        alert("POI "+id+" was not saved");
      }
      //success: this.options.callbackUpdateApi
    });
    var data = [{"id":id, updated:new Date()}];
    this.options.callbackUpdateApi.call(this, data);
  },

  /**
   * splits the current view in uniform bboxes to allow caching
   */
  long2tile: function (lon,zoom) { return (Math.floor((lon+180)/360*Math.pow(2,zoom))); },
  lat2tile: function (lat,zoom)  {
      return (Math.floor((1-Math.log(Math.tan(lat*Math.PI/180) + 1/Math.cos(lat*Math.PI/180))/Math.PI)/2 *Math.pow(2,zoom))); 
  },
  tile2long: function (x,z) {
      return (x/Math.pow(2,z)*360-180);
  },
  tile2lat: function (y,z) {
      var n=Math.PI-2*Math.PI*y/Math.pow(2,z);
      return (180/Math.PI*Math.atan(0.5*(Math.exp(n)-Math.exp(-n))));
  },
  _view2BBoxes: function(l,b,r,t) {
      //console.log(l+"\t"+b+"\t"+r+"\t"+t);
      //this.addBBox(l,b,r,t);
      //console.log("calc bboxes");
      var requestZoomLevel= 14;
      //get left tile index
      var lidx = this.long2tile(l,requestZoomLevel);
      var ridx = this.long2tile(r,requestZoomLevel);
      var tidx = this.lat2tile(t,requestZoomLevel);
      var bidx = this.lat2tile(b,requestZoomLevel);

      //var result;
      var result = new Array();
      for (var x=lidx; x<=ridx; x++) {
          for (var y=tidx; y<=bidx; y++) {//in tiles tidx<=bidx
              var left = Math.round(this.tile2long(x,requestZoomLevel)*1000000)/1000000;
              var right = Math.round(this.tile2long(x+1,requestZoomLevel)*1000000)/1000000;
              var top = Math.round(this.tile2lat(y,requestZoomLevel)*1000000)/1000000;
              var bottom = Math.round(this.tile2lat(y+1,requestZoomLevel)*1000000)/1000000;
              //console.log(left+"\t"+bottom+"\t"+right+"\t"+top);
              //this.addBBox(left,bottom,right,top);
              //console.log("http://osm.org?bbox="+left+","+bottom+","+right+","+top);
              result.push( new L.LatLngBounds(new L.LatLng(bottom, left),new L.LatLng(top, right)));
          }
      }
      //console.log(result);
      return result;
  },

  addBBox: function (l,b,r,t) {
      var polygon = L.polygon([
              [t, l],
              [b, l],
              [b, r],
              [t, r]
              ]).addTo(this._map);
  },

  onMoveEnd: function () {
    //console.log("load Pois");
    //console.log(this._map.getBounds());
    if (this._map.getZoom() >= this.options.minzoom) {
      //var bboxList = new Array(this._map.getBounds());
      var bboxList = this._view2BBoxes(
              this._map.getBounds()._southWest.lng,
              this._map.getBounds()._southWest.lat,
              this._map.getBounds()._northEast.lng,
              this._map.getBounds()._northEast.lat);

      for (var i=0; i<bboxList.length; i++) {
          var bbox = bboxList[i];
          var x = bbox._southWest.lng;
          var y = bbox._northEast.lat;
          if ((x in this._requested) && (y in this._requested[x]) && (this._requested[x][y] == true)) {
              continue;
          }
          if (!(x in this._requested)) {
              this._requested[x] = {};
          }
          this._requested[x][y] = true;
          //this.addBBox(x,bbox._southWest.lat,bbox._northEast.lng,y);
    
        var query = "";
        for(var i=0;i<this.options.tags.length;i++) {
        query += "node(BBOX)["+this.options.tags[i]+"];";
        //query += "way(BBOX)["+this.options.tags[i]+"];>;";
        }
        // add "node(BBOX)[(TAG)];" into QUERY

        $.ajax({
          url: this.options.query.replace(/QUERY/g, query).replace(/(BBOX)/g, bbox.toOverpassBBoxString()),
          context: { instance: this },
          crossDomain: true,
          dataType: "json",
          data: {},
          success: this.options.callbackOverpass
        });
        $.ajax({
          url: this.options.updateApi + "?" + bbox.toOverpassBBoxString(),
          context: { instance: this },
          dataType: "json",
          data: {},
          method: "get",
          success: this.options.callbackUpdateApi
        });
      }
    }
  },

  onAdd: function (map) {
      this._map = map;
      if (map.zoomIndecator) {
          this._zoomControl = map.zoomIndecator;
          this._zoomControl._addLayer(this);
      }else{
          this._zoomControl = new L.Control.MinZoomIdenticator();
          map.addControl(this._zoomControl);
          this._zoomControl._addLayer(this);
      }

      this.onMoveEnd();
      map.on('moveend', this.onMoveEnd, this);
      //console.log("add layer");
  },

  onRemove: function (map) {
      console.log("remove layer");
      L.LayerGroup.prototype.onRemove.call(this, map);
      this._ids = {};
      this._requested = {};
      this._zoomControl._removeLayer(this);

      map.off({
          'moveend': this.onMoveEnd
      }, this);

      this._map = null;
  },

  getData: function () {
    //console.log(this._data);
    return this._data;
  }

});

