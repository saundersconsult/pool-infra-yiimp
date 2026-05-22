<?php

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */

$algo = Yii::$app->session->get('yaamp-algo');
$algo_unit = 'Mh';
$algo_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
if ($algo_factor == 0.001) $algo_unit = 'Kh';
if ($algo_factor == 1000) $algo_unit = 'Gh';
if ($algo_factor == 1000000) $algo_unit = 'Th';
if ($algo_factor == 1000000000) $algo_unit = 'Ph';

$hour = 60 * 60;
$days = 24 * $hour;

$dbMax = Yii::$app->cache->get("stats_maxt-$algo");
if (!$dbMax) {
	$dbMax = (new \yii\db\Query())
			->select(['(MAX(time)-30*60)'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',(time()-2*$hour)])
			->scalar();
	Yii::$app->cache->set("stats_maxt-$algo", $dbMax);
}

$dtMax = max(time()-$hour, $dbMax);

$t1 = $dtMax - 2*$days;
$t2 = $dtMax - 7*$days;
$t3 = $dtMax - 30*$days;

$row1 = Yii::$app->cache->get("stats_col1-$algo");
if (!$row1) {
	$row1 = (new \yii\db\Query())
			->select(['AVG(hashrate) as a','SUM(earnings) as b'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',$t1])
			->one();
	Yii::$app->cache->set("stats_col1-$algo", $row1);
}
$row2 = Yii::$app->cache->get("stats_col2-$algo");
if (!$row2) {
	$row2 = (new \yii\db\Query())
			->select(['AVG(hashrate) as a','SUM(earnings) as b'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',$t2])
			->one();
	Yii::$app->cache->set("stats_col2-$algo", $row2);
}
$row3 = Yii::$app->cache->get("stats_col3-$algo");
if (!$row3) {
	$row3 = (new \yii\db\Query())
			->select(['AVG(hashrate) as a','SUM(earnings) as b'])
			->from('hashstats')
			->where(['algo' => $algo])
			->andWhere(['>','time',$t3])
			->one();
	Yii::$app->cache->set("stats_col3-$algo", $row3);
}

if($row1['a']>0 && $row2['a']>0 && $row3['a']>0)
{
	$a1 = max(1., (double) $row1['a']);
	$a2 = max(1., (double) $row2['a']);
	$a3 = max(1., (double) $row3['a']);

	$btcmhday1 = Yii::$app->ConversionUtils->bitcoinvaluetoa(($row1['b'] / 2)  * $algo_factor * (1000000 / $a1));
	$btcmhday2 = Yii::$app->ConversionUtils->bitcoinvaluetoa(($row2['b'] / 7)  * $algo_factor * (1000000 / $a2));
	$btcmhday3 = Yii::$app->ConversionUtils->bitcoinvaluetoa(($row3['b'] / 30) * $algo_factor * (1000000 / $a3));
}
else
{
	$btcmhday1 = 0;
	$btcmhday2 = 0;
	$btcmhday3 = 0;
}

$hashrate1 = Yii::$app->ConversionUtils->Itoa2($row1['a']);
$hashrate2 = Yii::$app->ConversionUtils->Itoa2($row2['a']);
$hashrate3 = Yii::$app->ConversionUtils->Itoa2($row3['a']);

$total1 = Yii::$app->ConversionUtils->bitcoinvaluetoa($row1['b']);
$total2 = Yii::$app->ConversionUtils->bitcoinvaluetoa($row2['b']);
$total3 = Yii::$app->ConversionUtils->bitcoinvaluetoa($row3['b']);

$height = '240px';

//$algos = YIIMP_get_algos();
$algos = array();
$enabled = (new \yii\db\Query())
			->select(['algo','count(id) as count'])
			->from('coins')
			->where(['enable' => 1,'visible' => 1])
			->groupBy('algo')
			->orderBy('algo')
			->all();
foreach ($enabled as $row) {
	$algos[$row['algo']] = $row['count'];
}

$string = '';
foreach($algos as $a => $count)
{
	if($a == $algo)
		$string .= "<option value='$a' selected>$a</option>";
	else
		$string .= "<option value='$a'>$a</option>";
}

// to fill the graphs on right edges (big tick interval of 4 days)
$dtMin1 = $t1 + $hour;
$dtMax1 = $dtMax;

$dtMin2 = $t2 - 2*$hour;
$dtMax2 = $dtMin2 + 7 * $days;

$dtMin3 = $dtMax1 - (8*4+1)*$days;
$dtMax3 = $dtMin3 + (8*4) * $days;

echo <<<end

<div id='resume_update_button' style='color: #444; background-color: #ffd; border: 1px solid #eea;
	padding: 10px; margin-left: 20px; margin-right: 20px; margin-top: 15px; cursor: pointer; display: none;'
	onclick='auto_page_resume();' align=center>
	<b>Auto refresh is paused - Click to resume</b></div>

<div align=right>
Select Algo: <select id='algo_select'>$string</select>&nbsp;
</div>

<script>

$('#algo_select').change(function(event)
{
	var algo = $('#algo_select').val();
	window.location.href = '/site/algo?algo='+algo+'&r=/stats';
});

</script>

<table width=100%><tr><td valign=top width=33%>

<div class="main-left-box">
<div class="main-left-title">Last 48 Hours</div>
<div class="main-left-inner">

<ul>
<li>Average Hashrate: <b>{$hashrate1}h/s</b></li>
<li>BTC Value: <b>$total1</b></li>
<li>BTC/{$algo_unit}/d: <b>$btcmhday1</b></li>
</ul>

<br>
<div id='graph_results_1' style='height: $height;'></div><br><br>
<div id='graph_results_2' style='height: $height;'></div><br><br>
<div id='graph_results_3' style='height: $height;'></div><br><br>

</div></div><br>

</td>
<td></td>
<td valign=top width=33%>

<div class="main-left-box">
<div class="main-left-title">Last 7 Days</div>
<div class="main-left-inner">

<ul>
<li>Average Hashrate: <b>{$hashrate2}h/s</b></li>
<li>BTC Value: <b>$total2</b></li>
<li>BTC/{$algo_unit}/d: <b>$btcmhday2</b></li>
</ul>

<br>
<div id='graph_results_4' style='height: $height;'></div><br><br>
<div id='graph_results_5' style='height: $height;'></div><br><br>
<div id='graph_results_6' style='height: $height;'></div><br><br>

</div></div><br>

</td>
<td></td>
<td valign=top width=33%>

<div class="main-left-box">
<div class="main-left-title">Last 30 Days</div>
<div class="main-left-inner">

<ul>
<li>Average Hashrate: <b>{$hashrate3}h/s</b></li>
<li>BTC Value: <b>$total3</b></li>
<li>BTC/{$algo_unit}/d: <b>$btcmhday3</b></li>
</ul>

<br>
<div id='graph_results_7' style='height: $height;'></div><br><br>
<div id='graph_results_8' style='height: $height;'></div><br><br>
<div id='graph_results_9' style='height: $height;'></div><br><br>

</div></div><br>

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script type="text/javascript">

var dtMin1 = new Date(1000*{$dtMin1});
var dtMax1 = new Date(1000*{$dtMax1});

var dtMin2 = new Date(1000*{$dtMin2});
var dtMax2 = new Date(1000*{$dtMax2});

var dtMin3 = new Date(1000*{$dtMin3});
var dtMax3 = new Date(1000*{$dtMax3});

function page_refresh()
{
	main_refresh_1();
	main_refresh_2();
	main_refresh_3();
	main_refresh_4();
	main_refresh_5();
	main_refresh_6();
	main_refresh_7();
	main_refresh_8();
	main_refresh_9();
}

end;

for($i = 1; $i < 10; $i++)
{
	echo <<<end
	///////////////////////////////////////////////////////////////////////

	function main_ready_$i(data)
	{
		graph_init_$i(data);
	}

	function main_refresh_$i()
	{
		var url = "/stats/graph_results_$i";
		$.get(url, '', main_ready_$i);
	}
end;
}

echo <<<end

function graph_init_1(data)
{
	$('#graph_results_1').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_1', [t],
	{
		title: '<b>Hashrate ({$algo_unit}/s)</b>',
		axes: {
			xaxis: {
				min: dtMin1,
				max: dtMax1,
				tickInterval: 14400,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%#Hh</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.3f</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'}
			}
		},

		seriesDefaults: {
			markerOptions: { style: 'none' },
			rendererOptions: { smooth: true }
		},

		seriesColors: [ "rgba(78, 180, 180, 0.8)" ],
		series: [ { fill: true } ],

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

function graph_init_2(data)
{
	$('#graph_results_2').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_2', [t],
	{
		title: '<b>BTC/Day</b>',
		axes: {
			xaxis: {
				min: dtMin1,
				max: dtMax1,
				tickInterval: 14400,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%#Hh</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults: {
			renderer: $.jqplot.BarRenderer,
			rendererOptions: {barWidth: 3}
		},

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

function graph_init_3(data)
{
	$('#graph_results_3').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_3', [t],
	{
		title: '<b>BTC/{$algo_unit}/d</b>',
		axes: {
			xaxis: {
				min: dtMin1,
				max: dtMax1,
				tickInterval: 14400,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%#Hh</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults: {
			renderer: $.jqplot.BarRenderer,
			rendererOptions: { barWidth: 3 }
		},

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

//////////////////////////////////////////////////////////////////////////////////////////////

function graph_init_4(data)
{
	$('#graph_results_4').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_4', [t],
	{
		title: '<b>Hashrate ({$algo_unit}/s)</b>',
		axes: {
			xaxis: {
				min: dtMin2,
				max: dtMax2,
				tickInterval: 86400,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%d</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.3f</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'}
			}
		},

		seriesDefaults: {
			markerOptions: { style: 'none' },
			rendererOptions: { smooth: true }
		},

		seriesColors: [ "rgba(78, 180, 180, 0.8)" ],
		series: [ { fill: true } ],

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

function graph_init_5(data)
{
	$('#graph_results_5').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_5', [t],
	{
		title: '<b>BTC/Day</b>',
		axes: {
			xaxis: {
				min: dtMin2,
				max: dtMax2,
				tickInterval: 86400,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%d</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults: {
			renderer: $.jqplot.BarRenderer,
			rendererOptions: { barWidth: 3 }
		},

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

function graph_init_6(data)
{
	$('#graph_results_6').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_6', [t],
	{
		title: '<b>BTC/{$algo_unit}/d</b>',
		axes: {
			xaxis: {
				min: dtMin2,
				max: dtMax2,
				tickInterval: 86400,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%d</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults: {
			renderer: $.jqplot.BarRenderer,
			rendererOptions: { barWidth: 3 }
		},

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

//////////////////////////////////////////////////////////////////////////////////////////////

function graph_init_7(data)
{
	$('#graph_results_7').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_7', [t],
	{
		title: '<b>Hashrate ({$algo_unit}/s)</b>',
		axes: {
			xaxis: {
				min: dtMin3,
				max: dtMax3,
				tickInterval: 4 * 24*60*60,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%m/%d</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.3f</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'}
			}
		},

		seriesDefaults: {
			markerOptions: { style: 'none' },
			rendererOptions: { smooth: true }
		},

		seriesColors: [ "rgba(78, 180, 180, 0.8)" ],
		series: [ { fill: true } ],

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

function graph_init_8(data)
{
	$('#graph_results_8').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_8', [t],
	{
		title: '<b>BTC/Day</b>',
		axes: {
			xaxis: {
				min: dtMin3,
				max: dtMax3,
				tickInterval: 4 * 24*60*60,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%m/%d</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults: {
			markerOptions: { style: 'none' },
			rendererOptions: { smooth: true }
		},

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}

function graph_init_9(data)
{
	$('#graph_results_9').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_9', [t],
	{
		title: '<b>BTC/{$algo_unit}/d</b>',
		axes: {
			xaxis: {
				min: dtMin3,
				max: dtMax3,
				tickInterval: 4 * 24*60*60,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%m/%d</font>'}
			},
			yaxis: {
				min: 0.0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults: {
			markerOptions: { style: 'none' },
			rendererOptions: { smooth: true }
		},

		grid: {
			borderWidth: 1,
			shadowWidth: 2,
			shadowDepth: 2
		},

	});
}


</script>
end;


