<?php
define('TEXT_SCORE', 1);
define('TEXT_COUNT', 2);
define('TEXT_PERCENT', 3);
define('TEXT_TITLE', 4);
define('TEXT_GCOUNTVAL', 5);
define('TEXT_GMEANVAL', 6);
define('TEXT_GCOUNT', 7);
define('TEXT_GMEAN', 8);

$fontPath = ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/media/font/verdana.ttf';
$barWidth = 220;
$barHeight = 12 + 1;
$barPadding = 1;
$iconImageMask = imagecreatefrompng(ChibiConfig::getInstance()->chibi->runtime->rootFolder . '/media/img/export-mask.png');

if (!function_exists('readColor')) {
	function readColor($color) {
		$b = $color & 0xff; $color >>= 8;
		$g = $color &0xff; $color >>= 8;
		$r = $color &0xff; $color >>= 8;
		$a = $color &0xff;
		return compact('a', 'r', 'g', 'b');
	}

	function makeColor($c) {
		$color = max(0, min(127, $c['a'] >> 1)); $color <<= 8;
		$color |= max(0, min(255, $c['r'])); $color <<= 8;
		$color |= max(0, min(255, $c['g'])); $color <<= 8;
		$color |= max(0, min(255, $c['b']));
		return $color;
	}

	function mixColors($c1, $c2, $r) {
		$a1 = $c1['a']; $r1 = $c1['r']; $g1 = $c1['g']; $b1 = $c1['b'];
		$a2 = $c2['a']; $r2 = $c2['r']; $g2 = $c2['g']; $b2 = $c2['b'];
		$r3 = $r1 + ($r2 - $r1) * $r;
		$g3 = $g1 + ($g2 - $g1) * $r;
		$b3 = $b1 + ($b2 - $b1) * $r;
		$a3 = $a1 + ($a2 - $a1) * $r;
		$c3 = array('a' => $a3, 'r' => $r3, 'g' => $g3, 'b' => $b3);
		return $c3;
	}

	function renderText($img, $x, $y, $t) {
		imagealphablending($img, true);
		imagettftext($img, $t['size'], 0, $x + $t['shiftX'], $y + $t['shiftY'], makeColor($t['color']), $t['font'], $t['text']);
		imagealphablending($img, false);
	}

	function gradient($img, $x1, $y1, $x2, $y2, $c1, $c2) {
		if ($x2 < $x1) {
			$t = $x1;
			$x1 = $x2;
			$x2 = $t;
			$t = $c1;
			$c1 = $c2;
			$c2 = $t;
		}
		for ($x3 = $x1; $x3 <= $x2; $x3 ++) {
			$r = ($x3 - $x1) / max(1, $x2 - $x1);
			$c3 = mixColors($c1, $c2, $r);
			imageline($img, $x3, $y1, $x3, $y2, makeColor($c3));
		}
	}
}

$iconImage = imagecreatetruecolor(imagesx($iconImageMask), imagesy($iconImageMask));
imagesavealpha($iconImage, true);
imagealphablending($iconImage, false);
imagefilledrectangle($iconImage, 0, 0, imagesx($iconImage), imagesy($iconImage), makeColor($this->colors[COLOR_BACKGROUND]));
imagealphablending($iconImage, true);
for ($y = 0; $y < imagesy($iconImageMask); $y ++) {
	for ($x = 0; $x < imagesx($iconImageMask); $x ++) {
		$c = $this->colors[COLOR_LOGO];
		$r = imagecolorat($iconImageMask, $x, $y);
		$r &= 0xff;
		$r /= 255.0;
		$c['a'] = 255 - (255 - $c['a']) * $r;
		imagesetpixel($iconImage, $x, $y, makeColor($c));
	}
}

$textSettings = array();
foreach (array(AMModel::TYPE_ANIME, AMModel::TYPE_MANGA) as $am) {
	$percentOrig = array();
	$percentShow = array();
	for ($score = 1; $score <= 10; $score ++) {
		$percentOrig[$score] = $this->scoreDistribution[$am]->getGroupSize($score) * 100.0 / max(1, $this->scoreDistribution[$am]->getRatedCount());
		$percentShow[$score] = floor($percentOrig[$score]);
	}
	uasort($percentOrig, function($a, $b) { return ($b-floor($b)) > ($a-floor($a)); });
	$tmp = array_keys($percentOrig);
	while (array_sum($percentShow) < 100 and !empty($tmp)) {
		$percentShow[array_shift($tmp)] ++;
	}

	$tc = &$textSettings[$am];
	for ($i = 0; $i < 10; $i ++)
	{
		$score = 10 - $i;
		$tc[TEXT_SCORE][$i] = array
		(
			'font' => $fontPath,
			'text' => $score,
			'color' => $this->colors[COLOR_FONT_DARK],
			'shiftX' => ($score == 1 or $score == 10) ? -2 : -1,
			'shiftY' => 10,
			'padding' => 8,
			'size' => 9,
			'width' => 0,
		);
		$tc[TEXT_COUNT][$i] = array
		(
			'font' => $fontPath,
			'text' => $this->scoreDistribution[$am]->getGroupSize($score),
			'color' => $this->colors[COLOR_FONT_DARK],
			'shiftX' => 0,
			'shiftY' => 10,
			'padding' => 8,
			'size' => 9,
			'width' => 0,
		);
		$tc[TEXT_PERCENT][$i] = array
		(
			'font' => $fontPath,
			'text' => '(' . $percentShow[$score] . '%)',
			'color' => $this->colors[COLOR_FONT_LIGHT],
			'shiftX' => 0,
			'shiftY' => 9,
			'padding' => 7,
			'size' => 7,
			'width' => 0
		);
	}
	$tc[TEXT_TITLE] = array
	(
		'font' => $fontPath,
		'text' => ucfirst($this->mgHelper->amText($am)),
		'color' => $this->colors[COLOR_TITLE],
		'size' => 10.5,
		'shiftX' => 0,
		'shiftY' => -3
	);
	$tc[TEXT_GCOUNT] = array
	(
		'font' => $fontPath,
		'text' => 'rated: ',
		'color' => $this->colors[COLOR_FONT_LIGHT],
		'size' => 9,
		'shiftX' => 0,
		'shiftY' => -4,
	);
	$tc[TEXT_GCOUNTVAL] = array
	(
		'font' => $fontPath,
		'text' => $this->scoreDistribution[$am]->getRatedCount(),
		'color' => $this->colors[COLOR_FONT_DARK],
		'size' => 10.5,
		'shiftX' => 0,
		'shiftY' => -4,
	);
	$tc[TEXT_GMEAN] = array
	(
		'font' => $fontPath,
		'text' => 'mean: ',
		'color' => $this->colors[COLOR_FONT_LIGHT],
		'size' => 9,
		'shiftX' => 0,
		'shiftY' => -4,
	);
	$tc[TEXT_GMEANVAL] = array
	(
		'font' => $fontPath,
		'text' => sprintf('%.02f', $this->scoreDistribution[$am]->getMeanScore()),
		'color' => $this->colors[COLOR_FONT_DARK],
		'size' => 10.5,
		'shiftX' => 0,
		'shiftY' => -4,
	);
	foreach (array(TEXT_SCORE, TEXT_COUNT, TEXT_PERCENT) as $textType)
	{
		for ($i = 0; $i < 10; $i ++)
		{
			$tg = &$tc[$textType];
			$t = &$tg[$i];
			$bbox = imagettfbbox($t['size'], 0, $t['font'], $t['text']);
			$t['width'] = $bbox[4] - $bbox[0];
			$t['height'] = $bbox[5] - $bbox[1];
			if(!isset($tg['width']) or $t['width'] > $tg['width'])
				$tg['width'] = $t['width'];
			if(!isset($tg['padding']) or $t['padding'] > $tg['padding'])
				$tg['padding'] = $t['padding'];
		}
	}
	$tc[TEXT_SCORE]['width'] = max($tc[TEXT_SCORE]['width'], imagesx($iconImage));
	foreach (array(TEXT_TITLE, TEXT_GCOUNT, TEXT_GMEAN, TEXT_GCOUNTVAL, TEXT_GMEANVAL) as $textType) {
		$t = &$tc[$textType];
		$bbox = imagettfbbox($t['size'], 0, $t['font'], $t['text']);
		$t['width'] = $bbox[2];
		$t['height'] = $bbox[3];
	}
}


if ($this->imageType == IMAGE_TYPE_ANIME or $this->imageType == IMAGE_TYPE_MANGA) {
	$am = $this->imageType == IMAGE_TYPE_ANIME ? AMModel::TYPE_ANIME : AMModel::TYPE_MANGA;
	$tc = &$textSettings[$am];

	$w = 330;
	$h = $barHeight * 10 + $barPadding * 9 + 20;
	$img = imagecreatetruecolor($w, $h);
	imagealphablending($img, false);
	imagesavealpha($img, true);
	imagefilledrectangle($img, 0, 0, $w, $h, makeColor($this->colors[COLOR_BACKGROUND]));

	for ($i = 0; $i < 10; $i ++) {
		$score = 10 - $i;
		$text = $tc[TEXT_SCORE][$i]['text'];
		$x = $tc[TEXT_SCORE]['width'] - $tc[TEXT_SCORE][$i]['width'];
		$y = ($barHeight + $barPadding) * $i;
		renderText($img, $x, $y, $tc[TEXT_SCORE][$i]);

		$text = $tc[TEXT_COUNT][$i]['text'];
		$x = $tc[TEXT_SCORE]['width'] + $tc[TEXT_SCORE]['padding'] + $barWidth + $tc[TEXT_COUNT]['padding'] + $tc[TEXT_COUNT]['width'] - $tc[TEXT_COUNT][$i]['width'];
		renderText($img, $x, $y, $tc[TEXT_COUNT][$i]);

		$text = $tc[TEXT_PERCENT][$i]['text'];
		$x = $tc[TEXT_SCORE]['width'] + $tc[TEXT_SCORE]['padding'] + $barWidth + $tc[TEXT_COUNT]['padding'] + $tc[TEXT_COUNT]['width'] + $tc[TEXT_PERCENT]['padding'];
		renderText($img, $x, $y, $tc[TEXT_PERCENT][$i]);

		$x1 = $tc[TEXT_SCORE]['width'] + $tc[TEXT_SCORE]['padding'];
		$y1 = ($barHeight + $barPadding) * $i;
		$x2 = $x1 + $barWidth - 1;
		$y2 = $y1 + $barHeight - 1;
		gradient($img, $x1, $y1 - 1, $x2, $y1 - 1, $this->colors[COLOR_BAR_GUIDES_1], $this->colors[COLOR_BAR_GUIDES_2]);
		gradient($img, $x1, $y1, $x2, $y2, $this->colors[COLOR_BARS_1], $this->colors[COLOR_BARS_2]);
		$x1 = $x1 + ($barWidth - 1) * $this->scoreDistribution[$am]->getGroupSize($score) / max(1, $this->scoreDistribution[$am]->getLargestGroupSize(Distribution::IGNORE_NULL_KEY));
		imagefilledrectangle ($img, $x1, $y1, $x2, $y2, makeColor($this->colors[COLOR_BACKGROUND2]));
	}

	$x = $tc[TEXT_SCORE]['width'] + $tc[TEXT_SCORE]['padding'];
	$y = $h;
	renderText($img, $x, $y, $tc[TEXT_TITLE]);
	$x1 = $x + $tc[TEXT_TITLE]['width'];

	$x += $barWidth - $tc[TEXT_GMEAN]['width'] - $tc[TEXT_GMEANVAL]['width'];
	$x2 = $x;
	renderText($img, $x, $y, $tc[TEXT_GMEAN]);
	$x += $tc[TEXT_GMEAN]['width'];
	renderText($img, $x, $y, $tc[TEXT_GMEANVAL]);

	$x = $x1 + (($x2 - ($tc[TEXT_GCOUNT]['width'] + $tc[TEXT_GCOUNTVAL]['width']) - $x1) >> 1);
	renderText($img, $x, $y, $tc[TEXT_GCOUNT]);
	$x += $tc[TEXT_GCOUNT]['width'];
	renderText($img, $x, $y, $tc[TEXT_GCOUNTVAL]);

	$x = ($tc[TEXT_SCORE]['width'] - imagesx($iconImage)) >> 1;
	$y = $h - imagesy($iconImage) + 1;
	imagecopy($img, $iconImage, $x, $y, 0, 0, imagesx($iconImage), imagesy($iconImage));

} else {

	$w = 630;
	$h = $barHeight * 10 + $barPadding * 9 + 20;
	$img = imagecreatetruecolor($w, $h);
	imagealphablending($img, false);
	imagesavealpha($img, true);
	imagefilledrectangle($img, 0, 0, $w, $h, makeColor($this->colors[COLOR_BACKGROUND]));

	foreach (array(AMModel::TYPE_ANIME, AMModel::TYPE_MANGA) as $am) {
		$tc = &$textSettings[$am];
		$mul = $am == AMModel::TYPE_ANIME ? -1 : 1;

		for ($i = 0; $i < 10; $i ++) {
			$score = 10 - $i;
			$x = ($w >> 1) - ($tc[TEXT_SCORE][$i]['width'] >> 1);
			$y = ($barHeight + $barPadding) * $i;
			renderText($img, $x, $y, $tc[TEXT_SCORE][$i]);

			$x = ($w >> 1) + (($tc[TEXT_SCORE]['width'] >> 1) + $tc[TEXT_SCORE]['padding'] + $barWidth + $tc[TEXT_COUNT]['padding']) * $mul;
			if ($am == AMModel::TYPE_MANGA) {
				$x += $tc[TEXT_COUNT]['width'] - $tc[TEXT_COUNT][$i]['width'];
			} else {
				$x -= $tc[TEXT_COUNT][$i]['width'];
			}
			renderText($img, $x, $y, $tc[TEXT_COUNT][$i]);

			$x = ($w >> 1) + (($tc[TEXT_SCORE]['width'] >> 1) + $tc[TEXT_SCORE]['padding'] + $barWidth + $tc[TEXT_COUNT]['padding'] + $tc[TEXT_COUNT]['width'] + $tc[TEXT_PERCENT]['padding']) * $mul;
			if ($am == AMModel::TYPE_ANIME) {
				$x -= $tc[TEXT_PERCENT][$i]['width'];
			}
			renderText($img, $x, $y, $tc[TEXT_PERCENT][$i]);

			$x1 = ($w >> 1) + (($tc[TEXT_SCORE]['width'] >> 1) + $tc[TEXT_SCORE]['padding']) * $mul;
			$y1 = ($barHeight + $barPadding) * $i;
			$x2 = $x1 + ($barWidth - 1) * $mul;
			$y2 = $y1 + $barHeight - 1;
			gradient($img, $x1, $y1 - 1, $x2, $y1 - 1, $this->colors[COLOR_BAR_GUIDES_1], $this->colors[COLOR_BAR_GUIDES_2]);
			gradient($img, $x1, $y1, $x2, $y2, $this->colors[COLOR_BARS_1], $this->colors[COLOR_BARS_2]);
			$x1 += $mul * (($barWidth - 1) * $this->scoreDistribution[$am]->getGroupSize($score) / max(1, $this->scoreDistribution[$am]->getLargestGroupSize(Distribution::IGNORE_NULL_KEY)));
			imagefilledrectangle ($img, $x1, $y1, $x2, $y2, makeColor($this->colors[COLOR_BACKGROUND2]));
		}
		$x = ($w >> 1) + (($tc[TEXT_SCORE]['width'] >> 1) + $tc[TEXT_SCORE]['padding']) * $mul;
		if ($am == AMModel::TYPE_ANIME) {
			$x -= $tc[TEXT_TITLE]['width'];
		}
		$y = $h;
		renderText($img, $x, $y, $tc[TEXT_TITLE]);
		if ($am == AMModel::TYPE_ANIME) {
			$x1 = $x;
		} else {
			$x1 = $x + $tc[TEXT_TITLE]['width'];
		}

		$x = ($w >> 1) + (($tc[TEXT_SCORE]['width'] >> 1) + $tc[TEXT_SCORE]['padding'] + $barWidth) * $mul;
		if ($am == AMModel::TYPE_MANGA) {
			$x -= $tc[TEXT_GMEAN]['width'] + $tc[TEXT_GMEANVAL]['width'];
		}
		renderText($img, $x, $y, $tc[TEXT_GMEAN]);
		$x2 = $x;
		$x += $tc[TEXT_GMEAN]['width'];
		renderText($img, $x, $y, $tc[TEXT_GMEANVAL]);
		if ($am == AMModel::TYPE_ANIME) {
			$x2 += $tc[TEXT_GMEAN]['width'] + $tc[TEXT_GMEANVAL]['width'];
		}

		$x = $x1 + (($x2 - ($tc[TEXT_GCOUNT]['width'] + $tc[TEXT_GCOUNTVAL]['width']) - $x1) >> 1);
		renderText($img, $x, $y, $tc[TEXT_GCOUNT]);
		$x += $tc[TEXT_GCOUNT]['width'];
		renderText($img, $x, $y, $tc[TEXT_GCOUNTVAL]);

		$x = ($w - imagesx($iconImage)) >> 1;
		$y = $h - imagesy($iconImage) + 1;
		imagecopy($img, $iconImage, $x, $y, 0, 0, imagesx($iconImage), imagesy($iconImage));
	}
}

imagepng($img);
