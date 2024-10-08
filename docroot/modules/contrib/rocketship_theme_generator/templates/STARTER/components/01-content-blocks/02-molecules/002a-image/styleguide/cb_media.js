/**
 * Rocketship UI JS
 *
 * contains: triggers for functions
 * Functions themselves are split off and grouped below each behavior
 *
 * Drupal behaviors:
 *
 * Means the JS is loaded when page is first loaded
 * \+ during AJAX requests (for newly added content)
 * use "once" to avoid processing the same element multiple times
 * use the "context" param to limit scope, by default this will return document
 * use the "settings" param to get stuff set via the theme hooks and such.
 *
 *
 * Avoid multiple triggers by using Once
 *
 * EXAMPLE 1:
 *
 * once('js-once-my-behavior', '.some-link', context).forEach(function(element) {
 *   $(element).click(function () {
 *     // Code here will only be applied once
 *   });
 * });
 *
 * EXAMPLE 2:
 *
 * once('js-once-my-behavior', '.some-element', context).forEach(function (element) {
 *   // The following click-binding will only be applied once
 * });
 */

(function ($, Drupal, window, document) {

  "use strict";

  // set namespace for frontend UI javascript
  if (typeof window.rocketshipUI == 'undefined') { window.rocketshipUI = {}; }

  var self = window.rocketshipUI;

  ///////////////////////////////////////////////////////////////////////
  // Cache variables available across the namespace
  ///////////////////////////////////////////////////////////////////////

  self.screen = self.screen || '';

  ///////////////////////////////////////////////////////////////////////
  // Behavior for P008: triggers
  ///////////////////////////////////////////////////////////////////////

  Drupal.behaviors.rocketshipUI_cbMedia = {
    attach: function (context, settings) {
      var multicolSections = $('.layout--2-col, .layout--3-col, .layout--4-col', context)
      if (multicolSections.length) self.sizeStretchedMedia(multicolSections);

    }
  };

  ///////////////////////////////////////////////////////////////////////
  // Behavior for P008: functions
  ///////////////////////////////////////////////////////////////////////

  /**
   * Stretched image/video blocks are positioned absolutely and rely on
   * other blocks to decide the height of the section.
   * So if there are no other blocks in a section,
   * we need to somehow force a height
   * @param {*} sections
   */
  self.sizeStretchedMedia = function(sections) {

    // for multicol layouts
    sections.each(function() {

      var section = $(this);
      var stretchedMedia = section.find('.cb--layout-mode--stretched');
      var matches = true;

      // if has stretched media blocks
      if (stretchedMedia.length) {

        // check if regions with blocks, have stretched blocks
        var regions = section.find('.layout__region');
        regions.each(function() {
          var region = $(this);
          var blocks = region.find('.content-block, .storybook__content-section');
          if (blocks.length) {
            blocks.each(function() {
              var block = $(this);

              // for styleguide: check for placeholder blocks (they take up space too)
              if (block.hasClass('storybook__content-section')) {
                matches = false;
              }

              if (!block.hasClass('cb--layout-mode--stretched')) {
                // if not, there's no need to do stuff, that block will dictate the region height
                matches = false;
              }
            });
          }
        });

        // if all conditions apply, we need to manipulate the height of the block's regions
        if (matches) {

          // manipulate sizes of blocks and their elements that need it
          self.resizeMedia(section, stretchedMedia);

          // same but on resize
          window.rocketshipUI.optimizedResize().add(function() {
            self.resizeMedia(section, stretchedMedia);
          });

        }

      }

    });

  };

  self.resizeMedia = function(section, stretchedMedia) {

    // if not on small screen, manipulate heights
    // otherwise, reset those css props

    if (self.screen === 'xs') {

      section.attr('style', '');

      stretchedMedia.each(function() {

        var block = $(this);

        block.attr('style', '');

        var img = block.find('img');
        if (img.length) {
          img.attr('style', '');
          block.attr('style', '');
          block.parent().attr('style', '');
        }

        var iFrame = block.find('iframe');
        if (iFrame.length) {
          block.attr('style', '');
          block.parent().attr('style', '');
        }

      });

    } else {

      // reset spacings
      section.css('padding', '0');

      // for each of those blocks
      stretchedMedia.each(function() {

        var block = $(this);

        // set a min-height on its parent, which is the media block ratio * current width
        // also happens on resize

        // need to reset some styling in order to be able to properly know the image size
        block.css({'height': 'auto'});

        // if image
        var img = block.find('img');
        if (img.length) {
          var imgNaturalWidth = img.attr('width');
          var imgNaturalHeight = img.attr('height');
          var ratio;

          img.css({'width': '100%', 'height': 'auto'});

          // if img has size attributes, use them
          if (typeof imgNaturalWidth !== 'undefined' && typeof imgNaturalHeight !== 'undefined') {
            ratio = (imgNaturalHeight / imgNaturalWidth);
            block.parent().css({'min-height': (ratio * img.width()) + 'px'});
            block.css({'min-height': (ratio * img.width()) + 'px'});
          // if not, need to wait for it to load so we can find the size
          // NOTE: it's a problem if other JS manipulates the size after load
          } else {
            self.imgLoaded(img, function() {
              // var imgNaturalWidth = img.width();
              var imgNaturalHeight = img.height();
              block.parent().css({'min-height': imgNaturalHeight + 'px'});
              block.css({'min-height': imgNaturalHeight + 'px'});
            });
          }

        }

        // if iframe
        var iFrame = block.find('iframe');
        if (iFrame.length) {
          var imgNaturalWidth = iFrame.attr('width');
          var imgNaturalHeight = iFrame.attr('height');
          var ratio = (imgNaturalHeight / imgNaturalWidth);

          block.parent().css({'min-height': (ratio * imgNaturalWidth) + 'px'});
          block.css({'height': 'auto', 'min-height': (ratio * imgNaturalWidth) + 'px'});
        }

      });

    }

  }

  /**
   * Detect if all the images withing your object are loaded
   *
   * No longer needs imagesLoaded plugin to work
   */
  self.imgLoaded = function (el, callback)
  {
    var img = el.find('img'),
      iLength = img.length,
      iCount = 0;

    if (iLength) {

      img.each(function() {

        var img = $(this);

        // fires after images are loaded (if not cached)
        img.on('load', function(){

          iCount = iCount + 1;

          if (iCount == iLength) {
            // all images loaded so proceed
            callback();
          }

        }).each(function() {
          // in case images are cached
          // re-enter the load function in order to get to the callback
          if (this.complete) {

            var url = img.attr('src');

            $(this).load(url);

            iCount = iCount + 1;

            if (iCount == iLength) {
              // all images loaded so proceed
              callback();
            }

          }
        });

      });

    } else {
      // no images, so we can proceed
      return callback();
    }
  };

  /**
   *
   * Find out if we're on a small device (phone)
   *
   **/
  self.checkScreenSize = function () {

    var currentBreakpoint = self.getBreakpoint();

    if (currentBreakpoint == 'bp-xs') {
      self.screen = 'xs';
    }

    if (currentBreakpoint == 'bp-sm') {
      self.screen = 'sm';
    }

    if (currentBreakpoint == 'bp-md') {
      self.screen = 'md';
    }

    if (currentBreakpoint == 'bp-lg') {
      self.screen = 'lg';
    }
  };

  /*
   * Get the current breakpoint
   * Refers to the content of the body::after pseudo element (set in set-breakpoints.scss)
   * call with window.rocketshipUI.helpers().getBreakpoint().
   */
  self.getBreakpoint = function () {
    var tag = window.getComputedStyle(document.body, '::after').getPropertyValue('content');
    // Firefox bugfix
    tag = tag.replace(/"/g,'');

    return tag.replace(/'/g,'');
  };

})(jQuery, Drupal, window, document);
