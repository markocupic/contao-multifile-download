/*
 * This file is part of Contao Multi File Download.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-multifile-download
 */

'use strict';

(function ($) {
  $().ready(function () {
    let ceDownloadsLang = {};

    if ($('.multifile-downloads-link-container').length) {
      // Download language data from xhr
      let ceElId = $('.multifile-downloads-link-container').first().attr('data-ceid');
      if(Object.keys(ceDownloadsLang).length === 0) {
        $.ajax({
          url: window.location.href,
          type: 'get',
          dataType: 'json',
          data: {
            'load_language_data': 'true',
            'ce_downloads': 'true',
            'ce_id': ceElId
          }
        }).done(function (resp) {
          if (resp.done == 'true') {
            $.each(resp, function (index, value) {
              ceDownloadsLang[index] = value;
            });
          }
        }).fail(function () {
          //
        }).always(function () {
          //
        });
      }
    }

    // Init file download
    $('.multifile-downloads-button-container button').click(function (e) {
      e.preventDefault();
      let button = $(this);
      let list = button.closest('.ce_downloads').find('ul').eq(0);
      let files = [];
      $(list).find('input').each(function () {
        let input = $(this);
        if ($(this).is(':checked')) {
          files.push(parseInt(input.prop('value')));
        }
      });
      // Get content element id
      let ceId = $(this).closest('.ce_downloads').find('.multifile-downloads-link-container').attr('data-ceid');

      if (files.length > 0) {
        let path = window.location.href + '?multifile_download=true&files=' + btoa(files.join().trim()) + '&el_id=' + ceId;
        window.location.href = path;
      } else {
        alert(ceDownloadsLang.pleaseSelectOneFile)
      }

    });

    // Toggle checkboxes, select-all link and download button
    $('.multifile-downloads-link-container a').click(function (e) {
      e.preventDefault();
      $(this).closest('.ce_downloads').find('ul').toggleClass('show-checkbox');
      $(this).closest('.ce_downloads').find('.multifile-downloads-button-container').toggle();
      $(this).closest('.ce_downloads').find('.multifile-downloads-select-all-container').toggle();
    });

    // Disable links
    $('.ce_downloads ul li a').click(function (e) {
      let link = $(this);
      if ($(this).closest('ul').hasClass('show-checkbox')) {
        e.preventDefault();
      }
    });

    // Select all checkboxes
    $('.multifile-downloads-select-all-container a').click(function (e) {
      e.preventDefault();
      $(this).toggleClass('selected');
      if ($(this).hasClass('selected')) {
        $(this).closest('.ce_downloads').find('ul li input[type="checkbox"]').prop('checked', true);
      } else {
        $(this).closest('.ce_downloads').find('ul li input[type="checkbox"]').prop('checked', false);
      }
    });
  });

})(jQuery);