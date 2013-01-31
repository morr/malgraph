<?php
class MediaHelper extends ChibiHelper {
	const JSCROLLPANE = 0;
	const CORE = 1;
	const HIGHCHARTS = 2;
	const INFOBOX = 3;
	const POPUPS = 4;
	const FARBTASTIC = 5;
	const TABLESORTER = 6;

	public static function addMedia($whats) {
		foreach ($whats as $what) {
			$paths = self::getPaths($what);
			foreach ($paths as $path) {
				if (strpos($path, '.css') !== false) {
					HeadHelper::addStylesheet($path);
				} elseif (strpos($path, '.js') !== false) {
					HeadHelper::addScript($path);
				}
			}
		}
	}

	public static function getPaths($what) {
		switch ($what) {
			case self::JSCROLLPANE: return [
				UrlHelper::url('media/css/jquery.jscrollpane.css'),
				UrlHelper::url('media/js/jquery.mousewheel.min.js'),
				UrlHelper::url('media/js/jquery.jscrollpane.min.js')];
			case self::CORE: return [
				'http://fonts.googleapis.com/css?family=Open+Sans|Ubuntu',
				UrlHelper::url('media/css/bootstrap.min.css'),
				UrlHelper::url('media/css/core.css'),
				UrlHelper::url('media/js/jquery.min.js'),
				UrlHelper::url('media/js/jquery.ui.position.js'),
				UrlHelper::url('media/js/core.js'),
				UrlHelper::url('media/js/glider.js')];
			case self::INFOBOX: return [
				UrlHelper::url('media/css/infobox.css')];
			case self::HIGHCHARTS: return [
				UrlHelper::url('media/js/highcharts/highcharts.js'),
				UrlHelper::url('media/js/highcharts/themes/mg.js')];
			case self::POPUPS: return [
				UrlHelper::url('media/css/popups.css'),
				UrlHelper::url('media/js/popups.js')];
			case self::FARBTASTIC: return [
				UrlHelper::url('media/js/jquery.farbtastic.js'),
				UrlHelper::url('media/css/jquery.farbtastic.css')];
			case self::TABLESORTER: return [
				UrlHelper::url('media/js/jquery.tablesorter.min.js')];
		}
	}
}
