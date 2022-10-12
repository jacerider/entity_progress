/**
 * @file
 * Provides the core logic for entity progress.
 */

 (function ($) {

  'use strict';

  /**
   * Behaviors.
   */
  Drupal.behaviors.entityProgress = {
    attach: function (context, settings) {
      $('.entity-progress-required [required], .entity-progress-required[required]', context).once('entity-progress-required').each(function () {
        $(this).removeAttr('required');
      });
    }
  };

})(jQuery);
