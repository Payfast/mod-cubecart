{*Copyright (c) 2008 PayFast (Pty) Ltd
You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.*}
<link href="modules/gateway/PayFast/skin/admin/style.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700&display=swap" rel="stylesheet">
<div class="payfast_plugin">
	<div class="payfast_image">
		<img class="payfast__logo" src="modules/gateway/PayFast/admin/logo.gif" alt="PayFast" border="0">
	</div>
	<form class="payfast_form" {$VAL_SELF}" method="post" enctype="multipart/form-data">
	<legend class="payfast_legend">{$LANG.payfast.module_settings}</legend>
		<div id="PayFast" class="tab_content">
			<p class="payfast_description">{$LANG.payfast.module_description}</p>
			<fieldset>
				<div class="payfast_section">
					<label class="payfast_label" for="status">{$LANG.payfast.status}</label>
					<span>
						<input type="hidden" name="module[status]" id="status" class="toggle" value="{$MODULE.status}" />
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="default">{$LANG.payfast.default}</label>
					<span>
						<input type="hidden" name="module[default]" id="default" class="toggle" value="{$MODULE.default}" />
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="description">{$LANG.payfast.desc}</label>
					<span>
						<input class="payfast_input" name="module[desc]" id="desc" class="textbox" type="text" value="{$MODULE.desc}" placeholder="Reference to represent this gateway"/>
					</span>
				</div>

				<div class="payfast_section">
					<label class="payfast_label" class="payfast_label" for="merchant_id">{$LANG.payfast.merchant_id}</label>
					<span>
						<input class="payfast_input" name="module[merchant_id]" id="merchant_id" class="textbox" type="text" value="{$MODULE.merchant_id}" placeholder="merchant-id"/>
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="merchant_key">{$LANG.payfast.merchant_key}</label>
					<span>
						<input class="payfast_input" name="module[merchant_key]" id="merchant_key" class="textbox" type="text" value="{$MODULE.merchant_key}" placeholder="merchant-key"/>
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="passphrase">{$LANG.payfast.passphrase}</label>
					<span>
						<input class="payfast_input" name="module[passphrase]" id="passphrase" class="textbox" type="text" value="{$MODULE.passphrase}" placeholder="Passphrase"/>
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="mode">{$LANG.payfast.mode}</label>
					<span>
						<input name="module[testMode]" id="mode" class="toggle" type="hidden" class="toggle" value="{$MODULE.testMode}"/>
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="status">{$LANG.payfast.debug_log}</label>
					<span>
						<input type="hidden" name="module[debug_log]" id="debug_log" class="toggle" value="{$MODULE.debug_log}" />
					</span>
				</div>
				<div class="payfast_section">
					<label class="payfast_label" for="email">{$LANG.payfast.debug_email}</label>
					<span>
						<input class="payfast_input"  placeholder="Debug Email" name="module[debug_email]" id="email" class="textbox" type="text" value="{$MODULE.debug_email}" />
					</span>
				</div>
			</div>
			{$MODULE_ZONES}
			<div class="form_control">
				<input class="payfast_submit" type="submit" name="save" value="{$LANG.common.save}" />
			</div>
		</fieldset>
		<input type="hidden" name="token" value="{$SESSION_TOKEN}" />
	</form>
</div>
