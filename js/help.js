(function ($, Drupal, once) {
  Drupal.behaviors.filterHelp = {
    attach: function attach(context) {
      function updateFilterHelp(event) {
        var $this = $(event.target);
        var value = $this.val();
        $this.closest('.filter-wrapper').find('.filter-help-item').hide().filter('.filter-help-' + value).show();
      }

      $(once('filter-help', '.filter-help', context)).closest('.filter-wrapper').find('select.text-format-filter-list').on('change.filterHelp', updateFilterHelp).trigger('change.filterHelp');
    }
  };
})(jQuery, Drupal, once);
