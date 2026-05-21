<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\helpers\Html;

class ViewUtils extends Component
{
	public function showButtonHeader()
	{
		echo "<div class='buttonwrapper'>";
	}

	public function showButton($name, $link, $htmlOptions = array())
	{
		echo Html::a($name, $link, $htmlOptions);
	}

	public function showButtonPost($name, $htmlOptions)
	{
		echo Html::submitButton($name, $htmlOptions);
	}

	public function showTextTeaser($text, $more, $count = 120, $class = 'text')
	{
		if(empty($text)) return "";

		$text = strip_tags($text);
		if(strlen($text) < $count)
		{
			echo "<p class='$class'>$text</p>";
			return;
		}

		$text = substr($text, 0, $count)."...";
		echo "<p class='$class'>".$text." [".Html::a("more...", $more)."]</p>";
	}

	public function getTextTeaser($text, $count = 120)
	{
		if(empty($text)) return "";

		$text = strip_tags($text);
		if(strlen($text) < $count)
			return $text;

		$text = substr($text, 0, $count)."...";
		return $text;
	}

	public function getTextTitle($text)
	{
		$b = preg_match('/([^\.\r\n]*)/', $text, $match);
		return $match[1];
	}

	public function showTableSorter($id, $options='')
	{
		$this->JavascriptReady("
			$('#{$id}').tablesorter({$options});
			$('.tablesorter-header').not('.sorter-false').css('cursor', 'pointer');"
		);
		echo "<table id='$id' class='dataGrid2'>";
	}
	public function JavascriptFile($filename)
	{
		echo Html::jsFile($filename);
	}

	public function Javascript($javascript)
	{
		echo "<script>$javascript</script>";
	}

	public function JavascriptReady($javascript)
	{
		echo "<script>$(function(){ $javascript})</script>";
	}

	// Functions commonly used in admin pages

	public function getAdminSideBarLinks()
	{
	$links = <<<end
	<a href="/admin/exchange">Exchanges</a>&nbsp;
	<a href="/admin/botnets">Botnets</a>&nbsp;
	<a href="/admin/user">Users</a>&nbsp;
	<a href="/admin/worker">Workers</a>&nbsp;
	<a href="/admin/version">Version</a>&nbsp;
	<a href="/admin/earning">Earnings</a>&nbsp;
	<a href="/admin/payments">Payments</a>&nbsp;
	<a href="/admin/monsters">Big Miners</a>&nbsp;
	end;
		return $links;
	}

	// shared by wallet "tabs", to move in another php file...
	public function getAdminWalletLinks($coin, $info=NULL, $src='wallet')
	{
		$html = Html::a("<b>COIN PROPERTIES</b>", '/admin/coinwallet_update?id='.$coin->id);
		if($info) {
			$html .= ' || '.$coin->createExplorerLink("<b>EXPLORER</b>");
			$html .= ' || '.Html::a("<b>PEERS</b>", '/admin/coinwallet_peers?id='.$coin->id);
			if (YAAMP_ADMIN_WEBCONSOLE)
				$html .= ' || '.Html::a("<b>CONSOLE</b>", '/admin/coinwallet_console?id='.$coin->id);
			$html .= ' || '.Html::a("<b>TRIGGERS</b>", '/admin/cointriggers?id='.$coin->id);
			if ($src != 'wallet')
				$html .= ' || '.Html::a("<b>{$coin->symbol}</b>", '/admin/coinwallet?id='.$coin->id);
		}

		if(!$info && $coin->enable)
			$html .= '<br/>'.Html::a("<b>STOP COIND</b>", '/admin/stopcoin?id='.$coin->id);

		if($coin->auto_ready)
			$html .= '<br/>'.Html::a("<b>UNSET AUTO</b>", '/admin/coinwallet_unsetauto?id='.$coin->id);
		else
			$html .= '<br/>'.Html::a("<b>SET AUTO</b>", '/admin/coinwallet_setauto?id='.$coin->id);

		$html .= '<br/>';

		if(!empty($coin->link_bitcointalk))
			$html .= Html::a('forum', $coin->link_bitcointalk, array('target'=>'_blank')).' ';

		if(!empty($coin->link_github))
			$html .= Html::a('git', $coin->link_github, array('target'=>'_blank')).' ';

		if(!empty($coin->link_site))
			$html .= Html::a('site', $coin->link_site, array('target'=>'_blank')).' ';

		if(!empty($coin->link_explorer))
			$html .= Html::a('chain', $coin->link_explorer, array('target'=>'_blank','title'=>'External Blockchain Explorer')).' ';

		$html .= Html::a('google', 'http://google.com/search?q='.urlencode($coin->name.' '.$coin->symbol.' bitcointalk'), array('target'=>'_blank'));

		return $html;
	}

}