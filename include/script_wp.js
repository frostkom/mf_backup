jQuery(function($)
{
	function run_ajax(obj)
	{
		obj.selector.html("<i class='fa fa-spinner fa-spin fa-2x'></i>");

		$.ajax(
		{
			url: script_backup.ajax_url,
			type: 'post',
			dataType: 'json',
			data: {
				action: obj.action
			},
			success: function(data)
			{
				if(obj.button.is("a"))
				{
					obj.button.addClass('hide');
				}

				else
				{
					obj.button.addClass('is_disabled');
				}

				obj.selector.html(data.html);
			}
		});

		return false;
	}

	$(document).on('click', "button[name='btnBackupPerform']", function(e)
	{
		run_ajax(
		{
			'button': $(e.currentTarget),
			'action': 'api_backup_perform',
			'selector': $(".api_backup_perform")
		});
	});
});