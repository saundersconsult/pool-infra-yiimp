<?php
echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>

<div id='main_results'></div>

<script>

$(function()
{
	main_refresh();
});

var main_delay=60000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = "/admin/exchange_results";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>
