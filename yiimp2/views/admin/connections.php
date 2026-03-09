<?php

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

echo <<<end

<div id='main_results'></div>

<script>

var main_delay = 30000;

$(function()
{
	main_refresh();
});

function main_ready(data)
{
	$('#main_results').html(data);
	setTimeout(main_refresh, main_delay);
}

function main_error()
{
	setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = "/admin/connections_results";
	$.get(url, '', main_ready).error(main_error);
}

</script>

end;
