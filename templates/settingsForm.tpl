{**
 * templates/settingsForm.tpl
 *
 * Configuracao dos campos de metadados personalizados.
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
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
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
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save" hideCancel=true}
</form>
