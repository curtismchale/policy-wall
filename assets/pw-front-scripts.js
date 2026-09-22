jQuery(document).ready(function ($) {

  /**
   * Types text into an element one character at a time and leaves it there.
   *
   * The message used to fade out after a few seconds, which meant users lost
   * the confirmation — and the "proceed" link that follows it — before they
   * had a chance to read either one.
   */
  function typeMessage($el, newText, onComplete, typingSpeed) {
    typingSpeed = typingSpeed || 50;

    $el.stop(true, true).empty().show();

    var i = 0;
    var interval = setInterval(function () {
      $el.append(newText.charAt(i));
      i++;
      if (i >= newText.length) {
        clearInterval(interval);
        if (typeof onComplete === 'function') {
          onComplete();
        }
      }
    }, typingSpeed);
  }

  /**
   * Renders the "you may now proceed" link under the feedback message and,
   * unless the delay is 0, sends the user there after it.
   */
  function offerProceedLink($after, payload) {
    if (!payload.redirect) {
      return;
    }

    var $link = $('<a></a>')
      .attr('href', payload.redirect)
      .text(payload.redirectLabel || payload.redirect);

    $after.after($('<p class="pw-proceed"></p>').append($link));

    if (payload.redirectDelay > 0) {
      setTimeout(function () {
        window.location.href = payload.redirect;
      }, payload.redirectDelay);
    }
  }

  $buttonWrapper = $('.pw-button-wrapper');
  $button = $($buttonWrapper).find('#pw_policy_save');
  $spinner = $($buttonWrapper).find('.pw-spinner');
  $userFeedback = $($buttonWrapper).find('#pw_user_feedback');
  $userId = $($button).data('userid');
  $policyId = $($button).data('policyid');

  $($button).on('click touchstart', function (e) {
    e.preventDefault();

    $($spinner)
      .css('display', 'inline-block')
      .fadeIn(200);

    // drop any link left over from a previous click
    $($buttonWrapper).find('.pw-proceed').remove();

    var data = {
      'action': 'savePolicyAgreement',
      'userId': $userId,
      'policyId': $policyId,
      'security': PW.pw_ajax_nonce
    }

    $.post(PW.ajaxurl, data, function (response) {

      // fadeout spinner
      $($spinner).fadeOut(200);

      if (!response || !response.data) {
        return;
      }

      var payload = response.data;

      // wp_send_json_error() responses carry a message but no success key,
      // so treat anything that isn't an explicit success as a failure.
      var succeeded = false !== response.success && true === payload.success;

      typeMessage($userFeedback, payload.message, function () {
        if (!succeeded) {
          return;
        }

        offerProceedLink($userFeedback, payload);
      });

    }); // end ajax post

  });

  // accordion
  $('.pw-accordion-content').hide();

  $('.pw-accordion-title').on('click', function () {
    var wrapper = $(this).parent('.pw-embedded-content-accordion');

    $(wrapper).find('.pw-accordion-content').slideToggle();
  })

});
