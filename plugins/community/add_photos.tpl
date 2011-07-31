{combine_script id='jquery.jgrowl' load='footer' require='jquery' path='themes/default/js/plugins/jquery.jgrowl_minimized.js' }

{if $upload_mode eq 'multiple'}
{combine_script id='swfobject' load='footer' path='admin/include/uploadify/swfobject.js'}
{combine_script id='jquery.uploadify' load='footer' require='jquery' path='admin/include/uploadify/jquery.uploadify.v2.1.0.min.js' }
{/if}

{combine_css path="admin/include/uploadify/uploadify.css"}
{combine_css path="admin/themes/default/uploadify.jGrowl.css"}


{footer_script}{literal}
jQuery(document).ready(function(){
  function checkUploadStart() {
    var nbErrors = 0;
    jQuery("#formErrors").hide();
    jQuery("#formErrors li").hide();

    if (jQuery("input[name=category_type]:checked").val() == "new" && jQuery("input[name=category_name]").val() == "") {
      jQuery("#formErrors #emptyCategoryName").show();
      nbErrors++;
    }

    var nbFiles = 0;
    if (jQuery("#uploadBoxes").size() == 1) {
      jQuery("input[name^=image_upload]").each(function() {
        if (jQuery(this).val() != "") {
          nbFiles++;
        }
      });
    }
    else {
      nbFiles = jQuery(".uploadifyQueueItem").size();
    }

    if (nbFiles == 0) {
      jQuery("#formErrors #noPhoto").show();
      nbErrors++;
    }

    if (nbErrors != 0) {
      jQuery("#formErrors").show();
      return false;
    }
    else {
      return true;
    }

  }

  function humanReadableFileSize(bytes) {
    var byteSize = Math.round(bytes / 1024 * 100) * .01;
    var suffix = 'KB';

    if (byteSize > 1000) {
      byteSize = Math.round(byteSize *.001 * 100) * .01;
      suffix = 'MB';
    }

    var sizeParts = byteSize.toString().split('.');
    if (sizeParts.length > 1) {
      byteSize = sizeParts[0] + '.' + sizeParts[1].substr(0,2);
    }
    else {
      byteSize = sizeParts[0];
    }

    return byteSize+suffix;
  }

  if (jQuery("select[name=category] option").length == 0) {
    jQuery('input[name=category_type][value=existing]').attr('disabled', true);
    jQuery('input[name=category_type]').attr('checked', false);
    jQuery('input[name=category_type][value=new]').attr('checked', true);
  }

  jQuery("input[name=category_type]").click(function () {
    jQuery("[id^=category_type_]").hide();
    jQuery("#category_type_"+jQuery(this).attr("value")).show();
  });

  jQuery("#hideErrors").click(function() {
    jQuery("#formErrors").hide();
    return false;
  });

  jQuery("a.externalLink").click(function() {
    window.open(jQuery(this).attr("href"));
    return false;
  });

{/literal}
{if $upload_mode eq 'html'}
{literal}
  function addUploadBox() {
    var uploadBox = '<p class="file"><input type="file" size="60" name="image_upload[]"></p>';
    jQuery(uploadBox).appendTo("#uploadBoxes");
  }

  addUploadBox();

  jQuery("#addUploadBox A").click(function () {
    addUploadBox();
  });

  jQuery("#uploadForm").submit(function() {
    return checkUploadStart();
  });
{/literal}
{elseif $upload_mode eq 'multiple'}

var uploadify_path = '{$uploadify_path}';
var upload_id = '{$upload_id}';
var session_id = '{$session_id}';
var pwg_token = '{$pwg_token}';
var buttonText = 'Browse';
var sizeLimit = {$upload_max_filesize};

{literal}
  jQuery("#uploadify").uploadify({
    'uploader'       : uploadify_path + '/uploadify.swf',
    'script'         : uploadify_path + '/uploadify.php',
    'scriptData'     : {
      'upload_id' : upload_id,
      'session_id' : session_id,
      'pwg_token' : pwg_token,
    },
    'cancelImg'      : uploadify_path + '/cancel.png',
    'queueID'        : 'fileQueue',
    'auto'           : false,
    'displayData'    : 'speed',
    'buttonText'     : buttonText,
    'multi'          : true,
    'fileDesc'       : 'Photo files (*.jpg,*.jpeg,*.png)',
    'fileExt'        : '*.jpg;*.JPG;*.jpeg;*.JPEG;*.png;*.PNG',
    'sizeLimit'      : sizeLimit,
    'onAllComplete'  : function(event, data) {
      if (data.errors) {
        return false;
      }
      else {
        jQuery("input[name=submit_upload]").click();
      }
    },
    onError: function (event, queueID ,fileObj, errorObj) {
      var msg;

      if (errorObj.type === "HTTP") {
        if (errorObj.info === 404) {
          alert('Could not find upload script.');
          msg = 'Could not find upload script.';
        }
        else {
          msg = errorObj.type+": "+errorObj.info;
        }
      }
      else if (errorObj.type ==="File Size") {
        msg = "File too big";
        msg = msg + '<br>'+fileObj.name+': '+humanReadableFileSize(fileObj.size);
        msg = msg + '<br>Limit: '+humanReadableFileSize(sizeLimit);
      }
      else {
        msg = errorObj.type+": "+errorObj.info;
      }

      jQuery.jGrowl(
        '<p></p>'+msg,
        {
          theme:  'error',
          header: 'ERROR',
          sticky: true
        }
      );

      jQuery("#fileUploadgrowl" + queueID).fadeOut(
        250,
        function() {
          jQuery("#fileUploadgrowl" + queueID).remove()
        }
      );
      return false;
    },
    onCancel: function (a, b, c, d) {
      var msg = "Cancelled uploading: "+c.name;
      jQuery.jGrowl(
        '<p></p>'+msg,
        {
          theme:  'warning',
          header: 'Cancelled Upload',
          life:   4000,
          sticky: false
        }
      );
    },
    onClearQueue: function (a, b) {
      var msg = "Cleared "+b.fileCount+" files from queue";
      jQuery.jGrowl(
        '<p></p>'+msg,
        {
          theme:  'warning',
          header: 'Cleared Queue',
          life:   4000,
          sticky: false
        }
      );
    },
    onComplete: function (a, b ,c, d, e) {
      var size = Math.round(c.size/1024);
      jQuery.jGrowl(
        '<p></p>'+c.name+' - '+size+'KB',
        {
          theme:  'success',
          header: 'Upload Complete',
          life:   4000,
          sticky: false
        }
      );
    }
  });

  jQuery("input[type=button]").click(function() {
    if (!checkUploadStart()) {
      return false;
    }

    jQuery("#uploadify").uploadifyUpload();
  });

{/literal}
{/if}
});
{/footer_script}

{literal}
<style>
#photosAddContent FIELDSET {
  width:650px;
  margin:20px auto;
}

#photosAddContent fieldset#photoProperties {padding-bottom:0}
#photosAddContent fieldset#photoProperties p {text-align:left;margin:0 0 1em 0;line-height:20px;}
#photosAddContent fieldset#photoProperties input[type="text"] {width:320px}
#photosAddContent fieldset#photoProperties textarea {width:500px; height:100px}

#photosAddContent P {
  /* margin:0; */
}

#uploadBoxes P {
  margin:0;
  margin-bottom:2px;
  padding:0;
}

#batchLink {
  text-align:center;
}

.category_selection {
  min-height:50px;
  margin-top:5px;
}

.category_selection TABLE {
  margin:0;
}
</style>
{/literal}

<div id="photosAddContent">

  {if isset($errors)}
  <div class="errors">
    <ul>
      {foreach from=$errors item=error}
      <li>{$error}</li>
      {/foreach}
    </ul>
  </div>
  {/if}

  {if isset($infos)}
  <div class="infos">
    <ul>
      {foreach from=$infos item=info}
      <li>{$info}</li>
      {/foreach}
    </ul>
  </div>
  {/if}


{if count($setup_errors) > 0}
<div class="errors">
  <ul>
  {foreach from=$setup_errors item=error}
    <li>{$error}</li>
  {/foreach}
  </ul>
</div>
{else}

  {if count($setup_warnings) > 0}
<div class="warnings">
  <ul>
    {foreach from=$setup_warnings item=warning}
    <li>{$warning}</li>
    {/foreach}
  </ul>
  <div class="hideButton" style="text-align:center"><a href="{$hide_warnings_link}">{'Hide'|@translate}</a></div>
</div>
  {/if}


{if !empty($thumbnails)}
<fieldset>
  <legend>{'Uploaded Photos'|@translate}</legend>
  <div>
  {foreach from=$thumbnails item=thumbnail}
    <a href="{$thumbnail.link}" class="externalLink">
      <img src="{$thumbnail.src}" alt="{$thumbnail.file}" title="{$thumbnail.title}" class="thumbnail">
    </a>
  {/foreach}
  </div>
</fieldset>
<p><a href="{$another_upload_link}">{'Add another set of photos'|@translate}</a></p>
{else}

<div id="formErrors" class="errors" style="display:none">
  <ul>
    <li id="emptyCategoryName">{'The name of an album must not be empty'|@translate}</li>
    <li id="noPhoto">{'Select at least one photo'|@translate}</li>
  </ul>
  <div class="hideButton" style="text-align:center"><a href="#" id="hideErrors">{'Hide'|@translate}</a></div>
</div>

<form id="uploadForm" enctype="multipart/form-data" method="post" action="{$form_action}" class="properties">
    <fieldset>
      <legend>{'Drop into album'|@translate}</legend>
      {if $upload_mode eq 'multiple'}
      <input name="upload_id" value="{$upload_id}" type="hidden">
      {/if}

{if $create_subcategories}
      <label><input type="radio" name="category_type" value="existing"> {'existing album'|@translate}</label>
      <label><input type="radio" name="category_type" value="new" checked="checked"> {'create a new album'|@translate}</label>
{else}
      <input name="category_type" value="existing" type="hidden">
{/if}

      <div id="category_type_existing" {if $create_subcategories}style="display:none" class="category_selection"{/if}>
        <select class="categoryDropDown" name="category">
          {html_options options=$category_options selected=$category_options_selected}
        </select>
      </div>

{if $create_subcategories}
      <div id="category_type_new" class="category_selection">
        <table>
          <tr>
            <td>{'Parent album'|@translate}</td>
            <td>
              <select class="categoryDropDown" name="category_parent">
{if $create_whole_gallery}
                <option value="0">------------</option>
{/if}
                {html_options options=$category_parent_options selected=$category_parent_options_selected}
              </select>
            </td>
          </tr>
          <tr>
            <td>{'Album name'|@translate}</td>
            <td>
              <input type="text" name="category_name" value="{$F_CATEGORY_NAME}" style="width:400px">
            </td>
          </tr>
        </table>
      </div>
{/if}
    </fieldset>

{if $community_ask_for_properties}
    <fieldset id="photoProperties">
      <legend>{'Photo Properties'|@translate}</legend>

      <p>
        {'Name'|@translate}<br>
        <input type="text" class="large" name="name" value="{$NAME}">
      </p>

      <p>
        {'Author'|@translate}<br>
        <input type="text" class="large" name="author" value="{$AUTHOR}">
      </p>

      <p>
        {'Description'|@translate}<br>
        <textarea name="description" id="description" class="description" style="margin:0">{$DESCRIPTION}</textarea>
      </p>

    </fieldset>
{/if}

    <fieldset>
      <legend>{'Select files'|@translate}</legend>

{if $upload_mode eq 'html'}
    <p><a href="{$switch_url}">{'... or switch to the multiple files form'|@translate}</a></p>

      <p>{'JPEG files or ZIP archives with JPEG files inside please.'|@translate}</p>

      <div id="uploadBoxes"></div>
      <div id="addUploadBox">
        <a href="javascript:">{'+ Add an upload box'|@translate}</a>
      </div>
    
    </fieldset>

    <p>
      <input class="submit" type="submit" name="submit_upload" value="{'Upload'|@translate}">
    </p>
{elseif $upload_mode eq 'multiple'}
    <p>
      <input type="file" name="uploadify" id="uploadify">
    </p>

    <p><a href="{$switch_url}">{'... or switch to the old style form'|@translate}</a></p>

    <div id="fileQueue"></div>

    </fieldset>
    <p>
      <input class="submit" type="button" value="{'Upload'|@translate}">
      <input type="submit" name="submit_upload" style="display:none">
    </p>
{/if}
</form>
{/if} {* empty($thumbnails) *}
{/if} {* $setup_errors *}

</div> <!-- photosAddContent -->
