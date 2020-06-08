(function($) {

    $(function() {
        var $actions = $('#fields-action').children().remove(),
            $submit = $('p.submit'),
            $container = $('#custobar-settings'),
            $fields = $container.find('textarea');

        $submit.append($actions);

        $('.submit .button-lock, .submit .button-restore').tipTip({
            'attribute': 'data-tip',
            'fadeIn': 50,
            'fadeOut': 50,
            'delay': 200,
            'defaultPosition': 'top'
        });

        $submit.on('click', '.button-lock', function(event) {
            event.preventDefault();
            
            var $icon = $(this).find('.dashicons');
            
            if ($icon.hasClass('dashicons-lock')) {
                $fields.attr('readonly', false);
                $icon.removeClass('dashicons-lock').addClass('dashicons-unlock');
            } else {
                $fields.attr('readonly', true);
                $icon.removeClass('dashicons-unlock').addClass('dashicons-lock');
            }
        });

        $submit.on('click', '.button-restore', function(event) {
            event.preventDefault();

            for (var fieldKey in Custobar.fieldsMap) {
                $('#'+fieldKey).val(Custobar.fieldsMap[fieldKey]);
            }

            $submit.find('.woocommerce-save-button').click();
        });
    });

  // Export run
  $('button.custobar-export').on('click', function( e ) {

    e.preventDefault()

    var recordType = $(this).data('record-type');
    var previousCount = 0;

    var responseCell = $('#custobar-export-wrap table tr.response td');
    var message = 'Starting to export ' + recordType + 's...';

    if (!responseCell.length) {
      $('#custobar-export-wrap table').append('<tr class="response"><td colspan="7">' + message + '</td></tr>');
    }
    else {
      $responseCell.html( message );
    }

    var _post = function () {

      var resetCheck = $('input[name="reset-' + recordType + '"]');

      data = {
        action: 'custobar_export',
        recordType: recordType
      }

      // Reset offset
      if (resetCheck.is(":checked")) {
        data['reset'] = 1;
        resetCheck.prop('checked', false);
      }

      $.post( ajaxurl, data, function( response ) {

        response = JSON.parse( response )

        var message = '';
        if( response.code == 200) {
          message += response.stats.synced + " " + recordType + "s exported.";

          // update row
          var reportRow = $('tr.sync-report-' + response.recordType);
          reportRow.find('td').eq(2).html( response.stats.synced );
          reportRow.find('td').eq(3).html( response.stats.synced_percent );
          reportRow.find('td').eq(4).html( response.stats.updated );
        }
        if( response.code == 220) {
          message += "No more records were found. Total of " + response.stats.synced + " " + recordType + "s exported.";
        }
        if( response.code == 420 ) {
          message += "Either WooCommerce is uninstalled or other configuration conditions were not met. Check that you have a valid API key set for Custobar. Response code " + response.code + ", no records were exported.";
        }
        if( response.code == 440 ) {
          message += "No more records available to export. Response code " + response.code + ", no records were exported.";
        }

        $( '#custobar-export-wrap table tr.response td' ).html( message );

        // Post again
        if (response.count && response.stats.synced < response.stats.total) {
          _post();
        }
      });
    }
    _post();
  })

  // API connection test
  $('#custobar-api-connection-test').on('click', function( e ) {

    e.preventDefault()

    data = {
       action: 'custobar_api_test'
     }
     $.post( ajaxurl, data, function( response ) {
       response = JSON.parse( response )
       $('#custobar-api-connection-test-wrap').append('<p>' + response.message + '</p>')
     });


    console.log('testing api...')
  })

})( jQuery );
