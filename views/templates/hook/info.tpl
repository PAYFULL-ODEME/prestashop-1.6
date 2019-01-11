{*<div class="row">
	<div class="col-xs-12">
        <p class="payment_module">
			<a href="{$link->getModuleLink('payfull', 'payment')|escape:'html'}" class="payfull">
                {l s='Pay with Payfull Gateway.' mod='payfull'}
            </a>
        </p>
    </div>
</div>
*}

<div class="alert alert-info">
    <img src="../modules/payfull/logo.png" style="float:left; margin-right:15px;">
    <p><strong>{l s="Payfull Payment Gateway" mod='payfull'}</strong></p>
    <p>{l s="Pay securelly with any bank supported in TURKEY.'" mod='payfull'}</p>
</div>
