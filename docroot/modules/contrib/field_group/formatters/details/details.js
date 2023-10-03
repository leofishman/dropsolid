/**
 * @file
 * Provides the processing logic for details element.
 */

(function ($) {

  'use strict';

  Drupal.FieldGroup = Drupal.FieldGroup || {};
  Drupal.FieldGroup.Effects = Drupal.FieldGroup.Effects || {};

  /**
   * This script adds the required and error classes to the details wrapper.
   */
  Drupal.behaviors.fieldGroupDetails = {
    attach: function (context) {
      $(once('field-group-details', '.field-group-details', context)).each(function () {
        var $this = $(this);

        if ($this.is('.required-fields') && ($this.find('[required]').length > 0 || $this.find('.form-required').length > 0)) {
          $('summary', $this).first().addClass('form-required');
        }
      });
    }
  };

  // Adaptation of https://www.drupal.org/project/field_group/issues/2969051 for detail groups.
  Drupal.behaviors.openDetailGroupWithError = {
    attach: function (context, settings) {
      // Check if browser supports HTML5 validation.
      if (typeof $('<input>')[0].checkValidity == 'function') {
        // Can't use .submit() because HTML validation prevents it from running.
        $('.form-submit:not([formnovalidate])').once('openDetailGroupWithError').on('click', function() {
          var $this = $(this);
          // Get form of the submit button.
          var $form = $this.closest('form');

          // Add Gin or Claro theme Support.
          if(!$form.length) {
            let $form_id = $this.attr('form');
            $form = $("#" + $form_id);
          }

          // Do not process if the form is not found.
          if ($form.length === 0) {
            return;
          }

          $($form[0].elements).each(function () {
            // First check for details element.
            if (this.checkValidity && !this.checkValidity()) {
              // Get wrapper's id.
              var id = $(this).closest('.field-group-details').attr('id');
              // Click menu item with id.
              $('#' + id ).find('summary').click();
              // Break loop after first error.
              return false;
            }
          });
        });
      }
    }
  };

})(jQuery);
