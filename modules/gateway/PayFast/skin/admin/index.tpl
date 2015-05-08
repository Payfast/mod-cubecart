<form action="{$VAL_SELF}" method="post" enctype="multipart/form-data">
	<div id="PayFast" class="tab_content">
  		<h3>{$TITLE}</h3>
  		<p>{$LANG.payfast.module_description}</p>
  		<fieldset><legend>{$LANG.module.cubecart_settings}</legend>
			<div><label for="status">{$LANG.common.status}</label><span><input type="hidden" name="module[status]" id="status" class="toggle" value="{$MODULE.status}" /></span></div>
			<div><label for="default">{$LANG.common.default}</label><span><input type="hidden" name="module[default]" id="default" class="toggle" value="{$MODULE.default}" /></span></div>
			<div><label for="description">{$LANG.common.description}</label><span><input name="module[desc]" id="desc" class="textbox" type="text" value="{$MODULE.desc}" /></span></div>
			<br />
            <div><label for="email">{$LANG.payfast.merchant_id}</label><span><input name="module[merchant_id]" id="email" class="textbox" type="text" value="{$MODULE.merchant_id}" /></span></div>
			<div><label for="email">{$LANG.payfast.merchant_key}</label><span><input name="module[merchant_key]" id="email" class="textbox" type="text" value="{$MODULE.merchant_key}" /></span></div>
            <div><label for="email">{$LANG.payfast.passphrase}</label><span><input name="module[passphrase]" id="email" class="textbox" type="text" value="{$MODULE.passphrase}" /></span></div>
			<br />
            <div>
				<label for="email">{$LANG.payfast.mode}</label>
				<span>
					<select name="module[testMode]">
    					<option value="1" {$SELECT_testMode_1}>{$LANG.payfast.mode_test}</option>
    					<option value="0" {$SELECT_testMode_0}>{$LANG.payfast.mode_live}</option>
					</select>
				</span>
   			</div>
            <br />
   			<div><label for="status">{$LANG.payfast.debug_log}</label><span><input type="hidden" name="module[debug_log]" id="debug_log" class="toggle" value="{$MODULE.debug_log}" /></span></div>
			<div><label for="email">{$LANG.payfast.debug_email}</label><span><input name="module[debug_email]" id="email" class="textbox" type="text" value="{$MODULE.debug_email}" /></span></div>
	    </div>
  		{$MODULE_ZONES}
  		<div class="form_control">
			<input type="submit" name="save" value="{$LANG.common.save}" />
  		</div>
  	</fieldset>
  	<input type="hidden" name="token" value="{$SESSION_TOKEN}" />
</form>