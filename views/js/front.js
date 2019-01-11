(function($) {
    payfullModule = {
        BIN: null,
        ajaxUrl: null,
        baseUrl: null,
        orderTotal: 100.0,
        installment: 1,
        merchantPayFees: false,
        
        ajax: function(command, options) {
            options = $.extend(true, {url: this.ajaxUrl, type: 'POST', dataType: 'json', data: {command: command}}, options);
            
            
            return $.ajax(options);
        },
        
        onCardChanged: function(element) {
            var bin = $(element).val().trim().replace(/\s/g, '').substr(0, 6);
            if (bin === this.BIN ) { return; }
            else if (bin.length < 6) {this.BIN = null; return;}
            this.BIN = bin;
            
            $('#payment_card_number_loading').show();
            this.ajax('bin', {
                data: {bin: bin}
            }).done(function( data ) {
                payfullModule.updateInstallmen(data);
            }).fail(function( data ) {
                this.BIN = null;
                _showInstallment(false);
                payfullModule.updateCardImages(false, false, false);
            }).always(function(data){
                $('#payment_card_number_loading').hide();
            });
        },
        
        updateInstallmen: function(data) {
            var type = data ? (data.type || false) : false;
            var bank = data ? (data.bank || false) : false;
            var brand = data ? (data.brand || false) : false;
            var gateway = data ? (data.gateway || false) : false;
            
            var installments = data ? (data.installments || []) : [];
            this.updateCardImages(brand, bank, type);
            
            if(!installments.length) {
                _showInstallment(false);
                return;
            }
            $('#payment_bank_id').val(bank);
            var $table = $('#installment_body');
            $table.empty();
            for(var i in installments) {
                var count = installments[i].count;
                var commission = installments[i].commission;
                var option = _getInstallmentOption(count, this.orderTotal, commission, '', true,gateway);
                $table.append(option);
            }
            _showInstallment(true);
        },
        
        updateCardImages: function(brand, bank, type) {
            if(bank && brand) {
                var t = (bank+'-'+type).toLowerCase();
                _showImage('#payment_card_img_bank', this.baseUrl+'views/img/banks/'+t+'.png');
            } else {
                _showImage('#payment_card_img_bank', false);
            }
            if(brand) {
                _showImage('#payment_card_img_brand', this.baseUrl+'views/img/brands/'+brand.toLowerCase()+'.png');
            } else {
                _showImage('#payment_card_img_brand', false);
            }
        },
        
        run: function(config) {
            config = config || {};
            for(var k in config) {
                this[k] = config[k];
            }
            
            $('#payment_card_number').on('keyup', function(event){
                payfullModule.onCardChanged(this);
            });
            
            $('#payment_card_number').on('keydown', function(event){
                return _isValidNumber(event, 18);
            });
            
            $('#payment_card_cvc').on('keydown', function(event){
                return _isValidNumber(event, 4);
            });
            
            $('#payfull_payment_module').on('change', 'input[name="paymentInstallmentRadio"]', function(event) {
                
                $('#payment_installment').val(event.target.value);
                $('#payment_gateway').val($(event.target).data('gateway'));
            });
            
            payfullModule.onCardChanged($('#payment_card_number'));
        }
    };
    
    function _getInstallmentOption(count, amount, commission, currency, has3d,gateway) {
        commission = parseFloat(commission) / 100;
        var fee = amount * commission;
        var total = amount * (1 + commission);
        var pmon = total / count;
        var checked = count === payfullModule.installment ? 'checked' : '';
        
        return ''
            + '<div class="installment_row">'
            + '<div class="install_body_label installment_radio">'
            + '<input type="radio" class="installment custom_field_installment_radio" rel="' + count + '"'
            + ' data-commission="' + commission + '" data-fee="' + _round(fee) + '" data-gateway="'+gateway+'" data-total="' + _round(total) + '" data-has3d="' + has3d + '"'
            + checked + ' value="' + count + '"'
            + ' name="paymentInstallmentRadio"/>'
            + '</div>'
            + '<div class="install_body_label installment_lable_code">' + count + '</div>'
            + '<div class="install_body_label">' + currency + ' ' + _price(pmon) + '</div>'
            + '<div class="install_body_label final_commi_price">' + currency + ' ' + _price(total) + '</div>'
            + '</div>'
        ;
    }
    
    function _showImage(id, src) {
        console.log(id);
        if(id=="#payment_card_img_brand")
            return false;
        if(src) {
            $(id).show().attr('src', src);
        } else {
            $(id).hide();
        }
    }
        
    function _showInstallment(show) {
        show ? $('#installment_table_id').show() : $('#installment_table_id').hide();
    }
        
    function _round(val) {
        //return (Math.floor(parseFloat(val) * 100) / 100).toFixed(2);
        return (Math.round(parseFloat(val) * 100) / 100).toFixed(2);
    }

    function _price(value) {
        return _round(value).toLocaleString();
    }
    
    function _isValidNumber(e, length) {
        var charCode = (e.which) ? e.which : e.keyCode;
        
        if ($.inArray(charCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
            (e.ctrlKey === true || e.metaKey === true) ||
            (e.keyCode >= 35 && e.keyCode <= 40)) {
                 return true;
        }
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (charCode < 48 || charCode > 57)) && (charCode < 96 || charCode > 105)) {
            return false;
        }
        if(e.target.value.length>=length) {
            return false;
        }
        return true;
    }
})(jQuery);