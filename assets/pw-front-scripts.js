jQuery(document).ready(function ($) {

  function typeAndFade(selector, newText, typingSpeed = 50, fadeDuration = 5000) {
    const $el = $(selector);
    $el.empty(); // Clear existing content

    let i = 0;
    const interval = setInterval(() => {
      $el.append(newText.charAt(i));
      i++;
      if (i >= newText.length) {
        clearInterval(interval);
        // Wait a moment, then fade out
        setTimeout(() => {
          $el.fadeOut(fadeDuration);
        }, 800); // small delay before fade
      }
    }, typingSpeed);
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

    var data = {
      'action': 'savePolicyAgreement',
      'userId': $userId,
      'policyId': $policyId,
      'security': PW.pw_ajax_nonce
    }

    $.post(PW.ajaxurl, data, function (response) {

      // fadeout spinner
      $($spinner).fadeOut(200);

      if (true === response.data.success) {
        typeAndFade($userFeedback, response.data.message);
      } // yup

      if (false === response.data.success) {
        typeAndFade($userFeedback, response.data.message);
      }

    }); // end ajax post

  });

  // accordion
  $('.pw-accordion-content').hide();

  $('.pw-accordion-title').on('click', function () {
    var wrapper = $(this).parent('.pw-embedded-content-accordion');

    $(wrapper).find('.pw-accordion-content').slideToggle();
  })

});

