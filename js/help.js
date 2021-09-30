(function ($, Drupal) {
  Drupal.behaviors.filterHelp = {
    attach: function attach(context) {
      function updateFilterHelp(event) {
        var $this = $(event.target);
        var value = $this.val();
        $this.closest('.filter-wrapper').find('.filter-help-item').hide().filter('.filter-help-' + value).show();
      }

      $(context).find('.filter-help').once('filter-help').closest('.filter-wrapper').find('select.text-format-filter-list').on('change.filterHelp', updateFilterHelp).trigger('change.filterHelp');
    }
  };
})(jQuery, Drupal);
