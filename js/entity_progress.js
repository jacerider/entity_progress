/**
 * @file
 * Provides the core logic for entity progress.
 */

 (function ($, Drupal, once) {

  'use strict';

  /**
   * Behaviors.
   */
  Drupal.behaviors.entityProgress = {
    attach: function (context, settings) {
      once('entity-progress-required', '.entity-progress-required [required], .entity-progress-required[required]', context).forEach(function (element) {
        $(element).removeAttr('required');
      });
    }
  };

})(jQuery, Drupal, once);
