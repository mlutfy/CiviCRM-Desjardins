
    // Since v4.1, this is called from BillingBlock.tpl (templated must be overriden to support it)
    function civicrmdesjardins_logos() {
      // In donation form, show Desjardins' logo
      cj('#crm-container .crm-contribution-main-form-block .credit_card_info-section').prepend('<div class="civicrmdesjardins-logodj">' + Drupal.t('Payment secured by Desjardins') + '</div>');

      // add visa/mc logos
      cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .content').append('<div class="civicrmdesjardins-cclogo"><a href="#" class="civicrmdesjardins-cclogo-visa"><span>Visa</span></a> <a href="#" class="civicrmdesjardins-cclogo-mc"><span>MasterCard</span></a> <a href="#" class="civicrmdesjardins-cclogo-amex"><span>Amex</span></a></div>');

      cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-visa').click(function() {
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section #credit_card_type').val('Visa');
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section a').css('opacity', 0.4);
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-visa').css('opacity', 1);
        cj('input#cvv2').val('000');
        return false;
      });
      cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-mc').click(function() {
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section #credit_card_type').val('MasterCard');
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section a').css('opacity', 0.4);
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-mc').css('opacity', 1);
        cj('input#cvv2').val('000');
        return false;
      });
      cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-amex').click(function() {
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section #credit_card_type').val('Amex');
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section a').css('opacity', 0.4);
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-amex').css('opacity', 1);
        cj('input#cvv2').val('0000');
        return false;
      });

      cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section select#credit_card_type').change(function() {
        var cctype = cj(this).val();
        if (cctype == 'Amex') {
          cj('input#cvv2').val('0000');
        } else {
          cj('input#cvv2').val('000');
        }
      });

      // Hide the CC type field (redundant anyway) and select according to the number entered
      cj('input#credit_card_number').change(function() {
        var ccnumtype = cj(this).val().substr(0, 4);

        // Semi-hide all images, we will un-hide the right one afterwards
        cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section a').css('opacity', 0.4);
        cj('#credit_card_type').val('');

        // https://en.wikipedia.org/wiki/Credit_card_numbers
        if (ccnumtype.substr(0, 1) == '3') {
          cj('#credit_card_type').val('Amex');
          cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-amex').css('opacity', 1);
          cj('input#cvv2').val('0000');
        }
        else if (ccnumtype.substr(0, 2) >= '51' && ccnumtype.substr(0, 2) <= '55') {
          cj('#credit_card_type').val('MasterCard');
          cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-mc').css('opacity', 1);
          cj('input#cvv2').val('000');
        }
        else if (ccnumtype.substr(0, 1) == '4') {
          cj('#credit_card_type').val('Visa');
          cj('#crm-container .crm-contribution-main-form-block .credit_card_type-section .civicrmdesjardins-cclogo-visa').css('opacity', 1);
          cj('input#cvv2').val('000');
        }
      });
    }

