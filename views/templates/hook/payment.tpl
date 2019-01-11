<div class="row">
	<div class="col-xs-12 col-md-12">
        <p class="payment_module paypal">
            <a class="payfull-link" href="{$link->getModuleLink('payfull', 'payment')|escape:'html'}" title="{l s='Pay securely with Payfull' mod='payfull'}"
            style="
                background-image: url({$base_dir_ssl|escape:'htmlall':'UTF-8'}modules/payfull/logo.png);
                
                background-repeat: no-repeat;
                background-position: 1%;
                background-size: 7%;
            "
            >
                <img src="" alt="" width="86" height="49"/>
                {l s='Pay securely with Payfull.' mod='payfull'}<br/>
                {*<span>({l s='Pay securely with any card with variety options of installment plans.' mod='payfull'})</span>*}
			</a>
		</p>
    </div>
</div>