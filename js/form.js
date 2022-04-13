(function (document, window) {

  'use strict';

  // Change buttons state
  function form_buttons(disabled) {
    document.querySelectorAll('form.vspam-protect input[type=submit]').forEach(function(button) {
      button.disabled = disabled;
    });
  }

  // Google reCAPTCHA 'onload' callback
  window.vspamOnload = function() {
    form_buttons(false);
  };

  // Disable buttons if Google reCAPTCHA is not ready
  form_buttons(!('grecaptcha' in window));

  // Find all protected forms
  document.querySelectorAll('form.vspam-protect').forEach(function(form) {
    form.querySelector('input[name="vspam_token"]').value = '';

    // Add event
    form.addEventListener('submit', function(e) {
      var input = form.querySelector('input[name="vspam_token"]');

      if (input && input.value.length < 10) {
        e.preventDefault();
        form_buttons(true);
        grecaptcha.execute(e.target.dataset.vspamKey, {action: e.target.dataset.vspamAction}).then(function(token) {
          input.value = token;
          e.target.submit();
        });
      }
    });
  });

})(document, window);
