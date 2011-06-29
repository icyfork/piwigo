/* -----------------------------------------------------------------------------
  file: google_translate.js
  file version: 2.1.0
  date: 2011-01-09
  ------------------------------------------------------------------------------
  author: grum at piwigo.org
  << May the Little SpaceFrog be with you >>
  ------------------------------------------------------------------------------
   this classes provides base functions to use Google Translate AJAX API
    >>  http://code.google.com/apis/ajaxlanguage/
  ------------------------------------------------------------------------------
  HISTORY VERSION
  v2.0.0  +
  v2.1.0  + add googleTranslate function with callback parameter
  --------------------------------------------------------------------------- */

  google.load("language", "1");

  var global_google_translate_plugin_objdest;
  var global_google_translate_plugin_callback;
  var global_google_translate_plugin_objproperty;

  function google_translate(text, pfrom, pto, objdest, objproperty)
  {
    global_google_translate_plugin_objdest = objdest;
    global_google_translate_plugin_objproperty = objproperty;
    google.language.translate(text, pfrom, pto, google_translate_do);
  }

  function google_translate_do(result)
  {
    if (!result.error)
    {
      if(global_google_translate_plugin_objproperty=='value')
      {
        global_google_translate_plugin_objdest.value = result.translation;
      }
      else if(global_google_translate_plugin_objproperty=='innerHTML')
      {
        global_google_translate_plugin_objdest.innerHTML = result.translation;
      }
    }
  }

  function googleTranslate(text, pfrom, pto, callback)
  {
    global_google_translate_plugin_callback=callback;
    google.language.translate(text, pfrom, pto, googleTranslateDo);
  }

  function googleTranslateDo(result)
  {
    if (!result.error)
    {
      global_google_translate_plugin_callback(result.translation);
    }
  }
