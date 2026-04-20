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

  $form = $('#pw_custom_policy_page_id_form');
  $button = $($form).find('#policy_page_number_submit');
  $spinner = $($form).find('.pwa-spinner');
  $userFeedback = $($form).find('#pw_user_feedback');

  $($form).on('submit', function (e) {
    e.preventDefault();

    $policyId = $($form).find('#policy_page_number').val();

    $($spinner)
      .css('display', 'inline-block')
      .fadeIn(200);

    var data = {
      'action': 'savePolicyPageIdNumber',
      'policyId': $policyId,
      'security': PWA.pwa_ajax_nonce
    }

    $.post(PWA.ajaxurl, data, function (response) {

      console.log(response);

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

  $('#csvExport').on('click', function (e) {
    e.preventDefault();

    const postId = $(this).data('postid');
    const nonce = $(this).data('nonce');

    if (!postId) {
      alert('No policy selected.');
      return;
    }

    // Build the URL for admin-ajax.php
    const url = ajaxurl
      + '?action=exportUserCSV'
      + '&policy_id=' + encodeURIComponent(postId)
      + '&_wpnonce=' + encodeURIComponent(nonce);

    // Let the browser navigate → triggers a file download
    window.location = url;
  });


});

