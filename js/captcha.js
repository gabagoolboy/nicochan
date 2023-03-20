var tout;

function redo_events(provider) {
  $('.captcha .captcha_text, textarea[id="body"]').off("focus").one("focus", function() { actually_load_captcha(provider); });
}

function actually_load_captcha(provider) {
  $('.captcha .captcha_text, textarea[id="body"]').off("focus");

  if (tout !== undefined) {
    clearTimeout(tout);
  }

  $.getJSON(provider, {mode: 'get'}, function(json) {
    $(".captcha_cookie").val(json.cookie);
    $(".captcha .captcha_html").html(json.html);

    setTimeout(function() {
      redo_events(provider);
    }, json.expires_in * 1000);
  });
}

function load_captcha(provider) {
  $(function() {
    $(".captcha>td").html("<input class='captcha_text' type='text' name='captcha_text' size='25' maxlength='6' autocomplete='off'>"+
                          "<input class='captcha_cookie' name='captcha_cookie' type='hidden'>"+
                          "<div class='captcha_html'><img id='captcha_click' src='/static/captcha.png'></div>");

    $("#quick-reply .captcha .captcha_text").prop("placeholder", _("Verification"));

    $(".captcha .captcha_html").on("click", function() { actually_load_captcha(provider); });
    $(document).on("ajax_after_post",       function() { actually_load_captcha(provider); });
    redo_events(provider);

    $(window).on("quick-reply", function() {
      redo_events(provider);
      $("#quick-reply .captcha .captcha_html").html($("form:not(#quick-reply) .captcha .captcha_html").html());
      $("#quick-reply .captcha_cookie").html($("form:not(#quick-reply) .captcha .captcha_cookie").html());
      $("#quick-reply .captcha .captcha_html").on("click", function() { actually_load_captcha(provider); });
    });
  });
}
