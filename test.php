<?php

require_once __DIR__ . '/vendor/autoload.php';

use Swpider\Queue;
use Swpider\Database;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\DomCrawler\Crawler;

$html = <<<'HTML'
<!DOCTYPE html>
<html>
    <body>
        <p class="message">Hello World!</p>
        <p>Hello Crawler!</p>
    </body>
</html>
HTML;

$crawler = new Crawler($html);



$css = new CssSelectorConverter();
$xpath = $css->toXPath("p.message+p");


$re = $crawler->filter('p.message+p');
$re = $crawler->filterXPath("//p[@class='message']//@class");

var_dump($re->text());