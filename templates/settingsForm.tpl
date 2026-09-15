{**
 * plugins/generic/customMetadata/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings form: the custom field definitions.
 *}
<script>
	$(function() {ldelim}
		$('#customMetadataSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="customMetadataSettingsForm"
	method="POST"
	action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}

	{fbvFormArea id="customMetadataFormArea"}
		{fbvFormSection
			label="plugins.generic.customMetadata.fields.label"
			description="plugins.generic.customMetadata.fields.description"
		}
			{fbvElement
				type="textarea"
				id="customFieldsDefinition"
				value=$customFieldsDefinition
				rows=12
				class="mceNoEditor"
			}
			<p class="description">{"plugins.generic.customMetadata.fields.ignored"|translate|escape}</p>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save" hideCancel=true}
</form>
