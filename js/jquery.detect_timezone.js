/**
 * jQuery Detect Timezone plugin
 *
 * Copyright (c) 2011 Scott Watermasysk (scottwater@gmail.com)
 * Provided under the Do Whatever You Want With This Code License. (same as detect_timezone).
 *
 */

(function( $ ){

  $.fn.set_timezone = function(options) {
    
      this.val(this.get_timezone(options));      
      return this;
  };
  
  $.fn.get_timezone = function(options) {
    
    var settings = {
      'debug' : false,
      'default' : 'America/New_York'
    };
    
    if(options) {
      $.extend( settings, options );
    }
    
    var tz_info = jstz.determine_timezone();
    var timezone = tz_info.timezone;
    if (timezone != 'undefined') {
      timezone.ambiguity_check();
      return timezone.olson_tz;
    } else {
      if(settings['debug']) {
        alert('no timezone to be found. using default.')
      }
      return settings['default']
    }
  };
  
})( jQuery );
