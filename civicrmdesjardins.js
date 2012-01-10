
(function ($) {
  Drupal.behaviors.cividesjardins_logos = {
    attach: function(context, settings) {
      // In donation form, show Desjardins' logo
      $('#crm-container .crm-contribution-main-form-block .credit_card_info-section').prepend('<div class="civicrmdesjardins-logodj">' + Drupal.t('Payment secured by Desjardins') + '</div>');

      // add visa/mc logos
      $('#crm-container .crm-contribution-main-form-block .credit_card_type-section .content').append('<div class="civicrmdesjardins-cclogo"><a href="#" class="civicrmdesjardins-cclogo-visa"><span>Visa</span></a> <a href="#" class="civicrmdesjardins-cclogo-mc"><span>MasterCard</span></a></div>');

      $('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-visa').click(function() {
        $('#crm-container .crm-contribution-main-form-block .credit_card_type-section #credit_card_type').val('Visa');
        return false;
      });
      $('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-mc').click(function() {
        $('#crm-container .crm-contribution-main-form-block .credit_card_type-section #credit_card_type').val('MasterCard');
        return false;
      });
    }
  }
})(jQuery);
