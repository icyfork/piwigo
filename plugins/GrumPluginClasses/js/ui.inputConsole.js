/**
 * -----------------------------------------------------------------------------
 * file: ui.inputConsole.js
 * file version: 1.0.0
 * date: 2010-11-05
 *
 * A jQuery plugin provided by the piwigo's plugin "GrumPluginClasses"
 *
 * -----------------------------------------------------------------------------
 * Author     : Grum
 *   email    : grum@piwigo.com
 *   website  : http://photos.grum.fr
 *   PWG user : http://forum.phpwebgallery.net/profile.php?id=3706
 *
 *   << May the Little SpaceFrog be with you ! >>
 * -----------------------------------------------------------------------------
 *
 *
 *
 *
 * :: HISTORY ::
 *
 * | release | date       |
 * | 1.0.0   | 2010/10/10 | first release
 * |         |            |
 * |         |            |
 * |         |            |
 * |         |            |
 * |         |            |
 * |         |            |
 *
 */



(
  function($)
  {
    /*
     * plugin 'public' functions
     */
    var publicMethods =
    {
      init : function (opt)
        {
          return this.each(function()
            {
              // default values for the plugin
              var $this=$(this),
                  data = $this.data('options'),
                  objects = $this.data('objects'),
                  properties = $this.data('properties'),
                  options =
                    {
                      disabled:false,
                      prompt:'>',
                      historySize:8,
                      historyHeight:60,
                      change:null,
                      submit:null
                    };

              // if options given, merge it
              // if(opt) $.extend(options, opt); ==> options are set by setters

              $this.data('options', options);


              if(!properties)
              {
                $this.data('properties',
                  {
                    initialized:false,
                    value:'',
                    isValid:true,
                    mouseIsOver:false,
                    historyIsVisible:false,
                    inputMargins:0
                  }
                );
                properties=$this.data('properties');
              }

              if(!objects)
              {
                objects =
                  {
                    container:$('<div/>',
                        {
                          'class':'ui-inputConsole',
                          css:{
                            width:'100%'
                          }
                        }
                    ).bind('click.inputConsole',
                        function ()
                        {
                          objects.input.focus();
                        }
                      )
                    .bind('mouseenter',
                        function ()
                        {
                          properties.mouseIsOver=true;
                        }
                      )
                    .bind('mouseleave',
                        function ()
                        {
                          properties.mouseIsOver=false;
                        }
                      ),
                    inputContainer:$('<div/>',
                      {
                        'class':'ui-inputConsole-input'
                      }
                    ),
                    input:$('<input>',
                      {
                        type:"text",
                        value:''
                      }
                    ).bind('focusout.inputConsole',
                        function ()
                        {
                          privateMethods.lostFocus($this);
                        }
                      )
                      .bind('focus.inputConsole',
                          function ()
                          {
                            privateMethods.getFocus($this);
                          }
                        )
                      .bind('keyup.inputConsole',
                          function (event)
                          {
                            privateMethods.keyUp($this, event);
                          }
                        ),
                    prompt:$('<div/>',
                      {
                        html:options.prompt,
                        'class':'ui-inputConsole-prompt'
                      }
                    ),
                    historyContainer:$('<div/>',
                      {
                        'class':'ui-inputConsole-history',
                        css:{
                          display:'none'
                        }
                      }
                    ),
                    historyList:$('<ul/>')

                  };

                $this
                  .html('')
                  .append(
                    objects.container
                    .append(
                      objects.historyContainer.append(objects.historyList)
                    )
                    .append(
                      objects.inputContainer.append(objects.prompt).append(objects.input)
                    )
                  ).bind('resize.inputConsole',
                        function ()
                        {
                          privateMethods.setObjectsWidth($this);
                        }
                      );

                properties.inputMargins=objects.input.outerWidth(true)-objects.input.width();

                $this.data('objects', objects);
              }

              privateMethods.setOptions($this, opt);
            }
          );
        }, // init
      destroy : function ()
        {
          return this.each(
            function()
            {
              // default values for the plugin
              var $this=$(this),
                  objects = $this.data('objects');
              objects.input.unbind().remove();
              objects.container.unbind().remove();
              $this
                .unbind('.inputConsole')
                .css(
                  {
                    width:'',
                    height:''
                  }
                );
            }
          );
        }, // destroy

      options: function (value)
        {
          return this.each(function()
            {
              privateMethods.setOptions($(this), value);
            }
          );
        }, // options

      disabled: function (value)
        {
          if(value!=null)
          {
            return this.each(function()
              {
                privateMethods.setDisabled($(this), value);
              }
            );
          }
          else
          {
            var options = this.data('options');

            if(options)
            {
              return(options.disabled);
            }
            else
            {
              return('');
            }
          }
        }, // disabled


      prompt: function (value)
        {
          if(value!=null)
          {
            return this.each(function()
              {
                privateMethods.setPrompt($(this), value);
              }
            );
          }
          else
          {
            var options = this.data('options');

            if(options)
            {
              return(options.prompt);
            }
            else
            {
              return('');
            }
          }
        }, // prompt

      historySize: function (value)
        {
          if(value!=null)
          {
            return this.each(function()
              {
                privateMethods.setHistorySize($(this), value);
              }
            );
          }
          else
          {
            var options = this.data('options');

            if(options)
            {
              return(options.historySize);
            }
            else
            {
              return('');
            }
          }
        }, // historySize

      historyHeight: function (value)
        {
          if(value!=null)
          {
            return this.each(function()
              {
                privateMethods.setHistoryHeight($(this), value);
              }
            );
          }
          else
          {
            var options = this.data('options');

            if(options)
            {
              return(options.historyHeight);
            }
            else
            {
              return('');
            }
          }
        }, // historyHeight

      value: function (value)
        {
          if(value!=null)
          {
            // set selected value
            return this.each(function()
              {
                privateMethods.setValue($(this), value, true);
              }
            );
          }
          else
          {
            // return the selected tags
            var properties=this.data('properties');
            return(properties.value);
          }
        }, // value

      history: function (value)
        {
          var objects=this.data('objects');

          if(value!=null)
          {
            // set selected value
            return this.each(function()
              {
                if(value=='clear')
                {
                  objects.historyList.html('');
                }
              }
            );
          }
          else
          {
            // return the selected tags
            var returned=[];
            objects.historyList.children().each(
              function (index, item)
              {
                returned.push($(item).text());
              }
            );
            return(returned);
          }
        }, // value

      isValid: function (value)
        {
          if(value!=null)
          {
            // set selected value
            return this.each(function()
              {
                privateMethods.setIsValid($(this), value);
              }
            );
          }
          else
          {
            // return the selected tags
            var properties=this.data('properties');
            return(properties.isValid);
          }
        }, // isValid

      change: function (value)
        {
          if(value!=null && $.isFunction(value))
          {
            // set selected value
            return this.each(function()
              {
                privateMethods.setEventChange($(this), value);
              }
            );
          }
          else
          {
            // return the selected value
            var options=this.data('options');

            if(options)
            {
              return(options.change);
            }
            else
            {
              return(null);
            }
          }
        }, // change


      submit: function (value)
        {
          if(value!=null && $.isFunction(value))
          {
            // set selected value
            return this.each(function()
              {
                privateMethods.setEventSubmit($(this), value);
              }
            );
          }
          else
          {
            // return the selected value
            var options=this.data('options');

            if(options)
            {
              return(options.submit);
            }
            else
            {
              return(null);
            }
          }
        } // submit


    }; // methods


    /*
     * plugin 'private' methods
     */
    var privateMethods =
    {
      setOptions : function (object, value)
        {
          var properties=object.data('properties'),
              options=object.data('options');

          if(!$.isPlainObject(value)) return(false);

          properties.initialized=false;

          privateMethods.setHistoryHeight(object, (value.historyHeight!=null)?value.historyHeight:options.historyHeight);
          privateMethods.setHistorySize(object, (value.historySize!=null)?value.historySize:options.historySize);
          privateMethods.setPrompt(object, (value.prompt!=null)?value.prompt:options.prompt);
          privateMethods.setValue(object, (value.value!=null)?value.value:options.value, true);
          privateMethods.setDisabled(object, (value.disabled!=null)?value.disabled:options.disabled);

          privateMethods.setEventChange(object, (value.change!=null)?value.change:options.change);
          privateMethods.setEventSubmit(object, (value.submit!=null)?value.submit:options.submit);

          properties.initialized=true;
        },

      setPrompt: function (object, value)
        {
          var objects=object.data('objects'),
              options=object.data('options'),
              properties=object.data('properties');

          if(!properties.initialized || options.prompt!=value)
          {
            options.prompt=value;
            objects.prompt.html(options.prompt);
            privateMethods.setObjectsWidth(object);
          }
          return(options.prompt);
        },

      setHistorySize: function (object, value)
        {
          var options=object.data('options'),
              properties=object.data('properties');

          if(!properties.initialized || options.historySize!=value)
          {
            options.historySize=value;
            privateMethods.updateHistory(object, null);
          }
          return(options.historySize);
        },

      setHistoryHeight: function (object, value)
        {
          var objects=object.data('objects'),
              options=object.data('options'),
              properties=object.data('properties');

          if(!properties.initialized || options.historyHeight!=value)
          {
            options.historyHeight=value;
            objects.historyContainer.css(
              {
                height:options.historyHeight+'px',
                'margin-top':(-options.historyHeight)+'px'
              }
            );
          }
          return(options.historyHeight);
        },

      setIsValid : function (object, value)
        {
          var objects=object.data('objects'),
              properties=object.data('properties');

          if(properties.isValid!=value)
          {
            properties.isValid=value;
            if(properties.isValid)
            {
              objects.container.removeClass('ui-error');
              objects.input.removeClass('ui-error');
            }
            else
            {
              objects.container.addClass('ui-error');
              objects.input.addClass('ui-error');
            }
          }
          return(properties.isValid);
        },

      setDisabled : function (object, value)
        {
          var options=object.data('options'),
              properties=object.data('properties');

          if((!properties.initialized || options.disabled!=value) && (value==true || value==false))
          {
            options.disabled=value;

          }
          return(options.disabled);
        },

      setValue : function (object, value, apply)
        {
          var options=object.data('options'),
              properties=object.data('properties'),
              objects=object.data('objects');

          properties.value=value;

          if(apply) objects.input.val(properties.value);

          if(options.change) object.trigger('inputConsoleChange', properties.value);

          return(true);
        }, //setValue


      getFocus : function (object)
        {
          var objects=object.data('objects');

          objects.historyContainer.css('display', 'block');
          privateMethods.setObjectsWidth(object);
        },

      lostFocus : function (object)
        {
          var objects=object.data('objects');

          objects.historyContainer.css('display', 'none');
        },

      setEventChange : function (object, value)
        {
          var options=object.data('options');

          options.change=value;
          object.unbind('inputConsoleChange');
          if(value) object.bind('inputConsoleChange', options.change);
          return(options.change);
        },

      setEventSubmit : function (object, value)
        {
          var options=object.data('options');

          options.submit=value;
          object.unbind('inputConsoleSubmit');
          if(value) object.bind('inputConsoleSubmit', options.submit);
          return(options.submit);
        },

      keyUp : function (object, event)
        {
          var properties=object.data('properties'),
              options=object.data('options'),
              objects=object.data('objects');

          if(event.keyCode==13 && // DOM_VK_ENTER
             properties.isValid)
          {
            if(options.submit) object.trigger('inputConsoleSubmit', properties.value);
            privateMethods.updateHistory(object, properties.value);
            privateMethods.setValue(object, '', true);
          }
          else
          {
            privateMethods.setValue(object, objects.input.val(), false);
          }
        },

      updateHistory : function (object, item)
        {
          var options=object.data('options'),
              objects=object.data('objects');

          if(item!='' && item!=null) objects.historyList.append($('<li/>', { html: item }));

          while(objects.historyList.children().length>options.historySize)
          {
            objects.historyList.children(':first').remove();
          }
          objects.historyContainer.scrollTop(objects.historyList.height());
        },

      setObjectsWidth : function (object)
        {
          var objects=object.data('objects')
              properties=object.data('properties');

          if(objects.inputContainer.width()>0)
          {
            objects.input.css('width',
              (objects.inputContainer.innerWidth()-objects.prompt.outerWidth(true)-properties.inputMargins)+'px'
            );
            objects.historyContainer.css(
              {
                width:objects.inputContainer.innerWidth()+'px',
                'margin-left':((objects.historyContainer.width()-objects.historyContainer.outerWidth())/2)+'px'
              }
            );
          }
        }

    };


    $.fn.inputConsole = function(method)
    {
      if(publicMethods[method])
      {
        return publicMethods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
      }
      else if(typeof method === 'object' || ! method)
      {
        return publicMethods.init.apply(this, arguments);
      }
      else
      {
        $.error( 'Method ' +  method + ' does not exist on jQuery.inputConsole' );
      }
    } // $.fn.inputConsole

  }
)(jQuery);


