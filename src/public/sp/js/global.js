jQuery.fn.preventDoubleSubmission = function() {
  $(this).on('submit',function(e) {
    var $form = $(this);

    if ($form.hasClass('submitted')) {
      // Previously submitted - don't submit again
      e.preventDefault();
    } else {
      // Mark it so that the next submit can be ignored
      $form.addClass('submitted');
    }
  });

  // Keep chainability
  return this;
};

$(document).ready(function() {
	$('form').preventDoubleSubmission();
});